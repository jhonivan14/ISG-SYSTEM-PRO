<?php
session_start();
require_once "../db.php";
require_once "../scholarship-program-options.php";
require_once "../scholarship-grants.php";

$errors = [];

function read_post_field($key) {
  return isset($_POST[$key]) ? trim($_POST[$key]) : "";
}

// normalize the contact number by removing all non-digit characters and trimming whitespace
function normalize_contact_number($value) {
  return preg_replace('/\D+/', '', trim((string)$value));
}

// normalize birth date to ISO format (Y-m-d) if possible, otherwise return empty string
function normalize_birth_date_for_storage($value) {
  $value = trim((string)$value);
  if ($value === "") {
    return "";
  }

  $isoDate = DateTime::createFromFormat('Y-m-d', $value);
  if ($isoDate instanceof DateTime && $isoDate->format('Y-m-d') === $value) {
    return $isoDate->format('Y-m-d');
  }

  $legacyDate = DateTime::createFromFormat('m/d/Y', $value);
  if ($legacyDate instanceof DateTime && $legacyDate->format('m/d/Y') === $value) {
    return $legacyDate->format('Y-m-d');
  }

  return "";
}

// format birth date for input field by converting from ISO format (Y-m-d) to the same format, or return empty string if invalid
function format_birth_date_for_input($value) {
  $value = trim((string)$value);
  if ($value === "") {
    return "";
  }

  $isoDate = DateTime::createFromFormat('Y-m-d', $value);
  if ($isoDate instanceof DateTime && $isoDate->format('Y-m-d') === $value) {
    return $value;
  }

  $legacyDate = DateTime::createFromFormat('m/d/Y', $value);
  if ($legacyDate instanceof DateTime && $legacyDate->format('m/d/Y') === $value) {
    return $legacyDate->format('Y-m-d');
  }

  return "";
}

// Validate input numbers into 11 digit only
function validate_contact_number(&$errors, $field, $label) {
  $value = read_post_field($field);
  if ($value === "") {
    return;
  }

  $normalizedValue = normalize_contact_number($value);
  $_POST[$field] = $normalizedValue;

  if (!preg_match('/^\d{11}$/', $normalizedValue)) {
    $errors[] = $label . " must contain exactly 11 digits.";
  }
}

// Validate birth date to ensure it's a valid date and can be normalized to ISO format
function validate_birth_date(&$errors, $field, $label) {
  $value = read_post_field($field);
  if ($value === "") {
    return;
  }

  $normalizedValue = normalize_birth_date_for_storage($value);
  if ($normalizedValue === "") {
    $errors[] = $label . " must be a valid date.";
    return;
  }

  $_POST[$field] = $normalizedValue;
}

// Automatic niya i-determine ang scholarship type based sa grant ID:
function infer_scholarship_type($grantId) {
  if ($grantId === 1) {
    return "Student Assistance";
  }
  if ($grantId === 2) {
    return "Academic";
  }
  if ($grantId === 4 || $grantId === 5) {
    return "Kabayani";
  }
  if ($grantId > 0) {
    return "Others";
  }

  return "";
}

// Mo-decide kung ang program/course field dapat dropdown ba para Student Assistant programs.
function uses_student_assistant_program_options($grantId, $scholarshipType) {
  return $grantId === 1 || $scholarshipType === "Student Assistance";
}

function application_form_normalize_school_year($schoolYear) {
  $value = trim((string)$schoolYear);
  if (!preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $value, $matches)) {
    return "";
  }

  $startYear = (int)$matches[1];
  $endYear = (int)$matches[2];
  if ($endYear !== $startYear + 1) {
    return "";
  }

  return $startYear . "-" . $endYear;
}

function application_form_sort_school_years(&$schoolYears) {
  $schoolYears = array_values(array_unique(array_filter($schoolYears, function ($schoolYear) {
    return trim((string)$schoolYear) !== "";
  })));

  usort($schoolYears, function ($a, $b) {
    $aYear = (int)substr((string)$a, 0, 4);
    $bYear = (int)substr((string)$b, 0, 4);
    if ($aYear === $bYear) {
      return strcmp((string)$a, (string)$b);
    }
    return $aYear <=> $bYear;
  });
}

function application_form_load_open_school_years($conn) {
  $schoolYears = [];
  if (!($conn instanceof mysqli)) {
    return $schoolYears;
  }

  $tableResult = $conn->query("SHOW TABLES LIKE 'school_years'");
  $hasSchoolYearsTable = $tableResult instanceof mysqli_result && $tableResult->num_rows > 0;
  if ($tableResult instanceof mysqli_result) {
    $tableResult->free();
  }

  if (!$hasSchoolYearsTable) {
    return $schoolYears;
  }

  $result = $conn->query("SELECT school_year FROM school_years ORDER BY school_year ASC");
  if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
      $value = application_form_normalize_school_year($row["school_year"] ?? "");
      if ($value !== "") {
        $schoolYears[] = $value;
      }
    }
    $result->free();
  }

  application_form_sort_school_years($schoolYears);
  return $schoolYears;
}

$grantNames = isg_load_scholarship_grant_names($conn);

$studentAssistantPrograms = [
  "Bachelor of Arts in English Language (AB English)",
  "Bachelor of Technical Vocational Teacher Education (BTVTED)",
  "Bachelor of Secondary Education - major in Mathematics",
  "Bachelor of Secondary Education - major in Social Studies",
  "Bachelor of Science in Computer Science (BSCS)",
  "Bachelor of Library and Information Science (BLIS)",
  "Bachelor of Science in Information Systems (BSIS)",
  "Bachelor of Public Administration (BPA)",
  "Bachelor of Science in Entrepreneurship (BSE)",
  "Bachelor of Science in Accounting Information System (BSAIS)",
  "Bachelor of Science in Business Administration (BSBA) - major in Operations Management",
  "Bachelor of Science in Business Administration (BSBA) - major in Business Economics",
  "Bachelor in Human Services (BHumserv)",
];
$studentAssistantPrograms = isg_load_program_option_names($conn, "student_assistant");

$departmentOptions = ["Elementary", "Junior High School", "Senior High School", "College"];
$elementaryProgramOptions = ["Nursery", "Kindergarten", "Grade 1", "Grade 2", "Grade 3", "Grade 4", "Grade 5", "Grade 6"];
$juniorHighSchoolProgramOptions = ["Grade 7", "Grade 8", "Grade 9", "Grade 10"];
$seniorHighSchoolProgramOptions = isg_load_program_option_names($conn, "senior_high");
$collegeProgramOptions = isg_load_program_option_names($conn, "college");
$seniorHighSchoolYearLevelOptions = ["Grade 11", "Grade 12"];
$collegeYearLevelOptions = ["1st Year", "2nd Year", "3rd Year", "4th Year"];
$semesterOptions = ["1st Semester", "2nd Semester"];

$currentYear = (int)date("Y");
$currentMonth = (int)date("n");
$currentSchoolYearStart = $currentMonth < 6 ? $currentYear - 1 : $currentYear;
$currentSchoolYear = $currentSchoolYearStart . "-" . ($currentSchoolYearStart + 1);
$schoolYearOptions = application_form_load_open_school_years($conn);
if (empty($schoolYearOptions)) {
  $schoolYearOptions = [$currentSchoolYear];
}
$currentGrantId = (int)($_POST["grant_id"] ?? $_GET["grant"] ?? 0);
$currentGrantTitle = $grantNames[$currentGrantId] ?? "";
$selectedScholarshipType = read_post_field("scholarshipType");
$inferredScholarshipType = infer_scholarship_type($currentGrantId);
$effectiveScholarshipType = $inferredScholarshipType !== "" ? $inferredScholarshipType : $selectedScholarshipType;
$selectedDepartment = read_post_field("department");
$kabayaniSpecifyValue = read_post_field("kabayaniSpecify");
$othersSpecifyValue = read_post_field("othersSpecify");
$programCourseValue = read_post_field("programCourse");
$showStudentAssistantProgramSelect = uses_student_assistant_program_options($currentGrantId, $effectiveScholarshipType);
$showProgramCourseSelect = in_array($selectedDepartment, ["Elementary", "Junior High School", "Senior High School"], true) ||
  $selectedDepartment === "College";
if ($kabayaniSpecifyValue === "" && $effectiveScholarshipType === "Kabayani" && $currentGrantTitle !== "") {
  $kabayaniSpecifyValue = $currentGrantTitle;
}
if ($othersSpecifyValue === "" && $effectiveScholarshipType === "Others" && $currentGrantTitle !== "") {
  $othersSpecifyValue = $currentGrantTitle;
}
$selectedSchoolYear = read_post_field("schoolYear");
if ($selectedSchoolYear !== "" && !in_array($selectedSchoolYear, $schoolYearOptions, true)) {
  array_unshift($schoolYearOptions, $selectedSchoolYear);
}
$selectedYearLevel = read_post_field("yearLevel");
$selectedSemester = read_post_field("semester");
$dateOfBirthInputValue = format_birth_date_for_input(read_post_field("dateOfBirth"));
 
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $grantId = $currentGrantId;
  $postedScholarshipType = infer_scholarship_type($grantId);
  if ($postedScholarshipType !== "") {
    $_POST["scholarshipType"] = $postedScholarshipType;
  }

  if ($grantId <= 0) {
    $errors[] = "Missing grant selection.";
  } elseif (!isset($grantNames[$grantId])) {
    $errors[] = "Invalid grant selection.";
  }
 
  
  $requiredFields = [
    "department" => "Department is required.",
    "scholarshipType" => "Scholarship type is required.",
    "applicantName" => "Applicant name is required.",
    "programCourse" => "Program/course is required.",
    "schoolYear" => "School year is required.",
    "permanentAddress" => "Permanent address is required.",
    "gender" => "Gender is required.",
    "age" => "Age is required.",
    "dateOfBirth" => "Date of birth is required.",
    "contactNumber" => "Contact number is required.",
    "emailAddress" => "Email address is required.",
    "estimatedIncome" => "Estimated income is required.",
    "motherName" => "Mother's name is required.",
    "fatherName" => "Father's name is required."
  ];
  

  foreach ($requiredFields as $field => $message) {
    if (read_post_field($field) === "") {
      $errors[] = $message;
    }
  }

  $department = read_post_field("department");
  $programCourse = read_post_field("programCourse");
  $yearLevel = read_post_field("yearLevel");
  $semester = read_post_field("semester");

  if ($department !== "" && !in_array($department, $departmentOptions, true)) {
    $errors[] = "Please select a valid department.";
  }

  if ($department === "Elementary" && $programCourse !== "" && !in_array($programCourse, $elementaryProgramOptions, true)) {
    $errors[] = "Please select a valid Elementary program/course.";
  }

  if ($department === "Junior High School" && $programCourse !== "" && !in_array($programCourse, $juniorHighSchoolProgramOptions, true)) {
    $errors[] = "Please select a valid Junior High School program/course.";
  }

  if ($department === "Senior High School") {
    if ($programCourse !== "" && !in_array($programCourse, $seniorHighSchoolProgramOptions, true)) {
      $errors[] = "Please select a valid Senior High School strand.";
    }
    if ($yearLevel === "") {
      $errors[] = "Year level is required.";
    } elseif (!in_array($yearLevel, $seniorHighSchoolYearLevelOptions, true)) {
      $errors[] = "Please select a valid Senior High School year level.";
    }
    if ($semester === "") {
      $errors[] = "Semester is required.";
    } elseif (!in_array($semester, $semesterOptions, true)) {
      $errors[] = "Please select a valid semester.";
    }
  } elseif ($department === "College") {
    $validCollegeProgramOptions = uses_student_assistant_program_options($grantId, read_post_field("scholarshipType"))
      ? $studentAssistantPrograms
      : $collegeProgramOptions;
    if ($programCourse !== "" && !in_array($programCourse, $validCollegeProgramOptions, true)) {
      $errors[] = "Please select a valid college program.";
    }
    if ($yearLevel === "") {
      $errors[] = "Year level is required.";
    } elseif (!in_array($yearLevel, $collegeYearLevelOptions, true)) {
      $errors[] = "Please select a valid college year level.";
    }
    if ($semester === "") {
      $errors[] = "Semester is required.";
    } elseif (!in_array($semester, $semesterOptions, true)) {
      $errors[] = "Please select a valid semester.";
    }
  }

  $emailAddress = read_post_field("emailAddress");
  if ($emailAddress !== "") {
    $isValidEmail = filter_var($emailAddress, FILTER_VALIDATE_EMAIL);
    $isGmail = (bool)preg_match('/@gmail\.com$/i', $emailAddress);
    if (!$isValidEmail || !$isGmail) {
      $errors[] = "Email address must be a valid @gmail.com address.";
    }
  }

  validate_contact_number($errors, "contactNumber", "Contact number");
  validate_contact_number($errors, "motherContact", "Mother's contact number");
  validate_contact_number($errors, "fatherContact", "Father's contact number");
  validate_birth_date($errors, "dateOfBirth", "Date of birth");

  if (empty($errors)) {
    $scholarshipType = read_post_field("scholarshipType");
    $othersSpecify = read_post_field("othersSpecify");
    if ($scholarshipType === "Others" && $othersSpecify === "" && $currentGrantTitle !== "") {
      $othersSpecify = $currentGrantTitle;
    }

    $_SESSION["application_draft"] = [
      "grant_id" => $grantId,
      "scholarship_type" => $scholarshipType,
      "kabayani_specify" => read_post_field("kabayaniSpecify"),
      "others_specify" => $othersSpecify,
      "department" => read_post_field("department"),
      "applicant_name" => read_post_field("applicantName"),
      "program_course" => read_post_field("programCourse"),
      "year_level" => read_post_field("yearLevel"),
      "school_year" => read_post_field("schoolYear"),
      "semester" => read_post_field("semester"),
      "permanent_address" => read_post_field("permanentAddress"),
      "gender" => read_post_field("gender"),
      "age" => (int)read_post_field("age"),
      "date_of_birth" => read_post_field("dateOfBirth"),
      "contact_number" => read_post_field("contactNumber"),
      "email_address" => read_post_field("emailAddress"),
      "estimated_income" => (int)read_post_field("estimatedIncome"),
      "mother_name" => read_post_field("motherName"),
      "mother_contact" => read_post_field("motherContact"),
      "mother_company_name" => read_post_field("motherCompanyName"),
      "mother_company_address" => read_post_field("motherCompanyAddress"),
      "mother_age" => (int)read_post_field("motherAge"),
      "mother_occupation" => read_post_field("motherOccupation"),
      "father_name" => read_post_field("fatherName"),
      "father_contact" => read_post_field("fatherContact"),
      "father_company_name" => read_post_field("fatherCompanyName"),
      "father_company_address" => read_post_field("fatherCompanyAddress"),
      "father_age" => (int)read_post_field("fatherAge"),
      "father_occupation" => read_post_field("fatherOccupation")
    ];

    $redirect = "upload-requirements.php?grant=" . urlencode((string)$grantId);
    header("Location: " . $redirect);
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ISG Application Form • Step 2</title>
  <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
  <link rel="stylesheet" href="../assets/css/tailwind.css">
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
  />
  <link
    href="https://fonts.googleapis.com/css2?family=Roboto+Slab&display=swap"
    rel="stylesheet"
  />
  <style>
    body {
      font-family: 'Roboto Slab', serif;
    }

    .top-brand,
    .top-brand * {
      font-family: sans-serif;
    }
  </style>
</head>

<body class="min-h-screen bg-gradient-to-b from-[#e0f2ff] via-white to-[#e0f2ff]">
  <!-- TOP BAR / BRAND -->
  <header class="top-brand sticky top-0 z-20 bg-gradient-to-r from-[#052c6a] via-[#0d8ddb] to-[#1d4ed8] shadow-md">
    <div class="w-full flex items-center gap-3 px-4 sm:px-6 lg:px-10 py-3">
      <!-- LOGO -->
      <div class="flex items-center justify-center">
        <img
          src="../img/SMCCNEWLOGO.png"
          alt="SMCC Logo"
          class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover bg-white shadow-md border border-white"
        />
      </div>

      <!-- TEXT -->
      <div class="flex-1 min-w-0">
        <p class="text-[10px] leading-4 sm:text-xs text-blue-100 uppercase tracking-[0.12em] sm:tracking-[0.18em]">
          SMCC Admission and Scholarship Office
        </p>
        <div class="mt-1 flex flex-wrap items-center gap-1.5 sm:gap-2">
          <h1 class="text-white text-sm sm:text-base font-semibold leading-tight">
            Institutional Scholarship Grants
          </h1>
          <span class="inline-flex max-w-full items-center gap-1 rounded-full bg-white/10 px-2 py-[2px] text-[10px] sm:text-[11px] text-blue-50">
            Step 2 of 3
          </span>
        </div>
        <p class="mt-1 text-[10px] leading-4 sm:text-xs text-blue-100">
          Application Form for Institutional Scholars / Grantees
        </p>
      </div>
    </div>
  </header>

  <!-- MAIN WRAPPER -->
  <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8" tabindex="-1">
    <?php if (!empty($errors)): ?>
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs sm:text-sm text-red-700">
        <?php foreach ($errors as $error): ?>
          <div><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <!-- PAGE TITLE + STEP -->
    <section class="mb-6 sm:mb-8 space-y-4">
      <div class="text-center space-y-2">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-[#052c6a] leading-tight">
          APPLICATION FORM
        </h2>
        <p class="text-xs sm:text-sm text-[#052c6a]/80">
          For Institutional Scholars / Grantees of Saint Michael College of Caraga
        </p>
      </div>

      <!-- PROGRESS BAR -->
      <div class="max-w-md mx-auto">
        <div class="flex items-center justify-between text-[11px] sm:text-xs font-semibold text-[#052c6a]/80 mb-1">
          <span>Step 1: List & Requirements</span>
          <span class="text-[#0d8ddb]">Step 2: Application Form</span>
          <span>Step 3: Upload Requirements</span>
        </div>
        <div class="h-1.5 rounded-full bg-[#dbe6ff] overflow-hidden">
          <div class="h-full w-2/3 bg-gradient-to-r from-[#0d8ddb] via-[#0d8ddb] to-[#fcdc2f]"></div>
        </div>
      </div>

      <!-- SELECTED GRANT SUMMARY -->
      <div class="max-w-xl mx-auto">
        <div class="bg-white/95 border border-[#cddfff] rounded-2xl px-4 py-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 shadow-sm">
          <div class="flex items-start gap-3">
            <div class="mt-0.5 flex h-8 w-8 items-center justify-center rounded-full bg-[#0d8ddb]/10 text-[#0d8ddb]">
              <i class="fas fa-award text-sm"></i>
            </div>
            <div>
              <p class="text-[11px] sm:text-xs font-semibold text-[#052c6a]/70 uppercase tracking-[0.16em]">
                Selected Scholarship / Grant
              </p>
              <p id="selectedGrantLabel" class="text-xs sm:text-sm font-semibold text-[#052c6a]">
                Loading selected grant...
              </p>
              <p class="text-[10px] sm:text-[11px] text-[#052c6a]/70">
                This is based on what you chose in Step 1.
              </p>
            </div>
          </div>
          <a
            href="applicationReq.php"
            class="inline-flex items-center gap-1 text-[10px] sm:text-xs text-[#0d8ddb] font-semibold hover:underline"
          >
            <i class="fas fa-chevron-left text-[9px]"></i>
            Back to Step 1
          </a>
        </div>
      </div>
    </section>

    <!-- FORM CARD -->
    <section
      class="bg-white/95 backdrop-blur rounded-2xl sm:rounded-3xl shadow-xl border border-[#cddfff] p-4 sm:p-6 lg:p-8 relative overflow-hidden"
    >
      <!-- subtle background icon -->
      <div class="pointer-events-none absolute -right-4 -top-4 opacity-5 sm:opacity-10 text-[#0d8ddb] text-6xl sm:text-8xl">
        <i class="fas fa-graduation-cap"></i>
      </div>

      <form action="isg-application-form.php" method="POST" class="space-y-8 sm:space-y-10 relative z-10">
        <!-- Hidden field to carry grant id -->
        <input type="hidden" name="grant_id" id="grantIdField" value="<?php echo $currentGrantId > 0 ? htmlspecialchars((string)$currentGrantId) : ""; ?>" />

        <!-- Type of Scholarship/Grant -->
        <fieldset class="border border-[#0d8ddb]/40 rounded-2xl p-4 sm:p-6 bg-[#f9fbff]">
          <legend class="inline-flex items-center gap-2 px-2 text-base sm:text-lg font-semibold text-[#052c6a]">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#0d8ddb]/10 text-[#0d8ddb]">
              <i class="fas fa-award text-xs"></i>
            </span>
            <span>Type of Scholarship / Grant</span>
          </legend>

          <p class="mt-2 text-[11px] sm:text-xs text-[#052c6a]/70">
            This section is automatically based on the scholarship or grant selected in Step 1.
          </p>

          <div class="mt-4">
            <div class="rounded-2xl border border-[#0d8ddb]/30 bg-white px-4 py-3 shadow-sm">
              <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#0d8ddb]">Applied Scholarship / Grant</p>
              <p id="appliedGrantTypeLabel" class="mt-2 text-sm sm:text-base font-semibold text-[#052c6a]">
                <?php echo htmlspecialchars($currentGrantTitle !== "" ? $currentGrantTitle : "No grant selected"); ?>
              </p>
            </div>
          </div>

          <div class="hidden">
            <div class="space-y-3">
              <label class="flex items-center gap-2 rounded-2xl border border-transparent bg-white px-3 py-2 shadow-sm hover:border-[#0d8ddb]/60 cursor-pointer transition">
                <input
                  class="h-4 w-4 text-[#0d8ddb] focus:ring-[#fcdc2f] border-gray-300"
                  id="academic"
                  name="scholarshipType"
                  type="radio"
                  value="Academic"
                  <?php echo $effectiveScholarshipType === "Academic" ? "checked" : ""; ?>
                />
                <span class="text-sm sm:text-base text-[#052c6a] font-medium">Academic</span>
              </label>

              <label class="flex items-center gap-2 rounded-2xl border border-transparent bg-white px-3 py-2 shadow-sm hover:border-[#0d8ddb]/60 cursor-pointer transition">
                <input
                  class="h-4 w-4 text-[#0d8ddb] focus:ring-[#fcdc2f] border-gray-300"
                  id="kabayani"
                  name="scholarshipType"
                  type="radio"
                  value="Kabayani"
                  <?php echo $effectiveScholarshipType === "Kabayani" ? "checked" : ""; ?>
                />
                <span class="text-sm sm:text-base text-[#052c6a] font-medium">Kabayani</span>
              </label>

              <div>
                <label
                  class="block text-[12px] sm:text-sm text-[#052c6a] font-medium mb-1"
                  for="kabayaniSpecify"
                >
                  Please specify (if Kabayani):
                </label>
                <input
                  class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-xs sm:text-sm
                         focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                  id="kabayaniSpecify"
                  name="kabayaniSpecify"
                  placeholder="e.g. Diocesan Scholar, School President’s Scholar, etc."
                  type="text"
                  value="<?php echo htmlspecialchars($kabayaniSpecifyValue); ?>"
                />
              </div>
            </div>

            <div class="space-y-3">
              <label class="flex items-center gap-2 rounded-2xl border border-transparent bg-white px-3 py-2 shadow-sm hover:border-[#0d8ddb]/60 cursor-pointer transition">
                <input
                  class="h-4 w-4 text-[#0d8ddb] focus:ring-[#fcdc2f] border-gray-300"
                  id="studentAssistance"
                  name="scholarshipType"
                  type="radio"
                  value="Student Assistance"
                  <?php echo $effectiveScholarshipType === "Student Assistance" ? "checked" : ""; ?>
                />
                <span class="text-sm sm:text-base text-[#052c6a] font-medium">Student Assistant</span>
              </label>

              <label class="flex items-center gap-2 rounded-2xl border border-transparent bg-white px-3 py-2 shadow-sm hover:border-[#0d8ddb]/60 cursor-pointer transition">
                <input
                  class="h-4 w-4 text-[#0d8ddb] focus:ring-[#fcdc2f] border-gray-300"
                  id="others"
                  name="scholarshipType"
                  type="radio"
                  value="Others"
                  <?php echo $effectiveScholarshipType === "Others" ? "checked" : ""; ?>
                />
                <span class="text-sm sm:text-base text-[#052c6a] font-medium">Others / Discounts</span>
              </label>

              <div>
                <label
                  class="block text-[12px] sm:text-sm text-[#052c6a] font-medium mb-1"
                  for="othersSpecify"
                >
                  Please specify (if Others / Discount):
                </label>
                <input
                  class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-xs sm:text-sm
                         focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                  id="othersSpecify"
                  name="othersSpecify"
                  placeholder="Specify other grant/discount (e.g. PWD, Alumni, DXSM-FM, etc.)"
                  type="text"
                  value="<?php echo htmlspecialchars($othersSpecifyValue); ?>"
                />
              </div>
            </div>
          </div>
        </fieldset>

        <!-- Scholar/Grantee's Profile -->
        <fieldset class="border border-[#0d8ddb]/40 rounded-2xl p-4 sm:p-6 bg-white">
          <legend class="inline-flex items-center gap-2 px-2 text-base sm:text-lg font-semibold text-[#052c6a]">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#0d8ddb]/10 text-[#0d8ddb]">
              <i class="fas fa-user text-xs"></i>
            </span>
            <span>Scholar / Grantee's Profile</span>
          </legend>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mt-4">
            <div class="md:col-span-2">
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="department">
                Department <span class="text-red-600">*</span>
              </label>
              <select
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="department"
                name="department"
                required
              >
                <option disabled <?php echo $selectedDepartment === "" ? "selected" : ""; ?> value="">Select department</option>
                <?php foreach ($departmentOptions as $option): ?>
                  <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedDepartment === $option ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($option); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="applicantName">
                Name of Applicant <span class="text-red-600">*</span>
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="applicantName"
                name="applicantName"
                placeholder="Full name"
                required
                type="text"
              />
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="programCourseSelect">
                Program / Course Enrolled <span class="text-red-600">*</span>
              </label>
              <select
                class="<?php echo $showProgramCourseSelect ? "" : "hidden "; ?>w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="programCourseSelect"
                data-selected-value="<?php echo htmlspecialchars($programCourseValue); ?>"
                <?php echo $showProgramCourseSelect ? 'name="programCourse" required' : 'disabled'; ?>
              >
                <option value="">Select program / course</option>
                <?php
                  $initialProgramOptions = [];
                  if ($selectedDepartment === "Elementary") {
                    $initialProgramOptions = $elementaryProgramOptions;
                  } elseif ($selectedDepartment === "Junior High School") {
                    $initialProgramOptions = $juniorHighSchoolProgramOptions;
                  } elseif ($selectedDepartment === "Senior High School") {
                    $initialProgramOptions = $seniorHighSchoolProgramOptions;
                  } elseif ($selectedDepartment === "College") {
                    $initialProgramOptions = $showStudentAssistantProgramSelect ? $studentAssistantPrograms : $collegeProgramOptions;
                  }
                ?>
                <?php if (empty($initialProgramOptions) && in_array($selectedDepartment, ["Senior High School", "College"], true)): ?>
                  <option value="" disabled selected>No program added yet</option>
                <?php else: ?>
                  <?php foreach ($initialProgramOptions as $programOption): ?>
                    <option value="<?php echo htmlspecialchars($programOption); ?>" <?php echo $programCourseValue === $programOption ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($programOption); ?>
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
              <input
                class="<?php echo $showProgramCourseSelect ? "hidden " : ""; ?>w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="programCourseInput"
                <?php echo $showProgramCourseSelect || $selectedDepartment === "" ? 'disabled' : 'name="programCourse" required'; ?>
                placeholder="<?php echo $selectedDepartment === "" ? "Select department first" : "e.g. BS in Computer Science"; ?>"
                type="text"
                value="<?php echo htmlspecialchars($programCourseValue); ?>"
              />
              <p
                id="programCourseHint"
                class="<?php echo $showProgramCourseSelect ? "" : "hidden "; ?>mt-1 text-[11px] sm:text-xs text-[#052c6a]/75"
              >
                
              </p>
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="yearLevel">
                Year Level <span id="yearLevelRequiredMark" class="<?php echo $selectedDepartment !== "" && !in_array($selectedDepartment, ["Elementary", "Junior High School"], true) ? "" : "hidden "; ?>text-red-600">*</span><span id="yearLevelOptionalMark" class="<?php echo in_array($selectedDepartment, ["Elementary", "Junior High School"], true) ? "" : "hidden "; ?>text-xs font-normal text-[#052c6a]/60"> (Optional)</span>
              </label>
              <select
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="yearLevel"
                name="yearLevel"
                data-selected-value="<?php echo htmlspecialchars($selectedYearLevel); ?>"
                <?php echo $selectedDepartment !== "" && !in_array($selectedDepartment, ["Elementary", "Junior High School"], true) ? "required" : ""; ?>
              >
                <option value="">Select year level</option>
                <?php
                  $initialYearLevelOptions = $selectedDepartment === "Senior High School" ? $seniorHighSchoolYearLevelOptions : $collegeYearLevelOptions;
                ?>
                <?php foreach ($initialYearLevelOptions as $option): ?>
                  <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedYearLevel === $option ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($option); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
              <div>
                <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="schoolYear">
                  School Year <span class="text-red-600">*</span>
                </label>
                <select
                  class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                         focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                  id="schoolYear"
                  name="schoolYear"
                  required
                >
                  <option disabled selected value="">Select school year</option>
                  <?php foreach ($schoolYearOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedSchoolYear === $option ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($option); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="semester">
                  Semester <span id="semesterRequiredMark" class="<?php echo $selectedDepartment !== "" && !in_array($selectedDepartment, ["Elementary", "Junior High School"], true) ? "" : "hidden "; ?>text-red-600">*</span><span id="semesterOptionalMark" class="<?php echo in_array($selectedDepartment, ["Elementary", "Junior High School"], true) ? "" : "hidden "; ?>text-xs font-normal text-[#052c6a]/60"> (Optional)</span>
                </label>
                <select
                  class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                         focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                  id="semester"
                  name="semester"
                  <?php echo $selectedDepartment !== "" && !in_array($selectedDepartment, ["Elementary", "Junior High School"], true) ? "required" : ""; ?>
                >
                  <option value="">Select semester</option>
                  <?php foreach ($semesterOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedSemester === $option ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($option); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="permanentAddress">
                Permanent Address <span class="text-red-600">*</span>
              </label>
              <textarea
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb] resize-y"
                id="permanentAddress"
                name="permanentAddress"
                placeholder="Complete home address"
                required
                rows="3"
              ></textarea>
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="gender">
                Gender <span class="text-red-600">*</span>
              </label>
              <select
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="gender"
                name="gender"
                required
              >
                <option disabled selected value="">Select gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="age">
                Age <span class="text-red-600">*</span>
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="age"
                min="0"
                name="age"
                placeholder="e.g. 20"
                required
                type="number"
              />
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="dateOfBirth">
                Date of Birth <span class="text-red-600">*</span>
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="dateOfBirth"
                name="dateOfBirth"
                required
                type="date"
                max="<?php echo htmlspecialchars(date('Y-m-d')); ?>"
                value="<?php echo htmlspecialchars($dateOfBirthInputValue); ?>"
              />
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="contactNumber">
                Contact Number <span class="text-red-600">*</span>
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="contactNumber"
                inputmode="numeric"
                maxlength="11"
                minlength="11"
                name="contactNumber"
                pattern="\d{11}"
                placeholder="09XXXXXXXXX"
                required
                title="Please enter exactly 11 digits."
                type="tel"
              />
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="emailAddress">
                Email Address <span class="text-red-600">*</span>
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="emailAddress"
                name="emailAddress"
                placeholder="name@gmail.com"
                pattern="^[A-Za-z0-9._%+-]+@gmail\\.com$"
                title="Please enter a valid @gmail.com address."
                required
                type="email"
              />
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="estimatedIncome">
                Estimated Gross Income of the Family per Month <span class="text-red-600">*</span>
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="estimatedIncome"
                min="0"
                name="estimatedIncome"
                placeholder="e.g. 25000"
                required
                type="number"
              />
            </div>
          </div>
        </fieldset>

        <!-- Mother's Information -->
        <fieldset class="border border-[#0d8ddb]/40 rounded-2xl p-4 sm:p-6 bg-[#f9fbff]">
          <legend class="inline-flex items-center gap-2 px-2 text-base sm:text-lg font-semibold text-[#052c6a]">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#0d8ddb]/10 text-[#0d8ddb]">
              <i class="fas fa-female text-xs"></i>
            </span>
            <span>Mother's Information</span>
          </legend>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mt-4">
            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="motherName">
                Mother's Name <span class="text-red-600">*</span>
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="motherName"
                name="motherName"
                placeholder="Full name"
                required
                type="text"
              />
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="motherContact">
                Contact Number
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="motherContact"
                inputmode="numeric"
                maxlength="11"
                minlength="11"
                name="motherContact"
                pattern="\d{11}"
                placeholder="09XXXXXXXXX"
                title="Please enter exactly 11 digits."
                type="tel"
              />
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="motherOccupation">
                Occupation
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="motherOccupation"
                name="motherOccupation"
                placeholder="Occupation"
                type="text"
              />
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="motherCompanyName">
                Company's Name
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="motherCompanyName"
                name="motherCompanyName"
                placeholder="Company name"
                type="text"
              />
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="motherCompanyAddress">
                Company's Address
              </label>
              <textarea
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb] resize-y"
                id="motherCompanyAddress"
                name="motherCompanyAddress"
                placeholder="Company address"
                rows="2"
              ></textarea>
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="motherAge">
                Age
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="motherAge"
                min="0"
                name="motherAge"
                placeholder="e.g. 45"
                type="number"
              />
            </div>


          </div>
        </fieldset>

        <!-- Father's Information -->
        <fieldset class="border border-[#0d8ddb]/40 rounded-2xl p-4 sm:p-6 bg-white">
          <legend class="inline-flex items-center gap-2 px-2 text-base sm:text-lg font-semibold text-[#052c6a]">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#0d8ddb]/10 text-[#0d8ddb]">
              <i class="fas fa-male text-xs"></i>
            </span>
            <span>Father's Information</span>
          </legend>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mt-4">
            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="fatherName">
                Father's Name <span class="text-red-600">*</span>
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="fatherName"
                name="fatherName"
                placeholder="Full name"
                required
                type="text"
              />
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="fatherContact">
                Contact Number
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="fatherContact"
                inputmode="numeric"
                maxlength="11"
                minlength="11"
                name="fatherContact"
                pattern="\d{11}"
                placeholder="09XXXXXXXXX"
                title="Please enter exactly 11 digits."
                type="tel"
              />
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="fatherOccupation">
                Occupation
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="fatherOccupation"
                name="fatherOccupation"
                placeholder="Occupation"
                type="text"
              />
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="fatherCompanyName">
                Company's Name
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="fatherCompanyName"
                name="fatherCompanyName"
                placeholder="Company name"
                type="text"
              />
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="fatherCompanyAddress">
                Company's Address
              </label>
              <textarea
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb] resize-y"
                id="fatherCompanyAddress"
                name="fatherCompanyAddress"
                placeholder="Company address"
                rows="2"
              ></textarea>
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="fatherAge">
                Age
              </label>
              <input
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="fatherAge"
                min="0"
                name="fatherAge"
                placeholder="e.g. 48"
                type="number"
              />
            </div>

          </div>
        </fieldset>

        <!-- CONSENT CHECKBOX -->
        <div class="mt-4 mb-2">
          <label class="inline-flex items-start gap-3 cursor-pointer select-none">
            <input
              id="consentCheckbox"
              type="checkbox"
              class="mt-0.5 w-4 h-4 flex-shrink-0 border border-black accent-[#052c6a]"
            />
            <span class="text-xs text-[#052c6a] leading-relaxed">
              I confirm that the information I have given in this form is true and accurate.
              I agree to fill-out this Application Form and hereby authorize sharing of the
              information furnished on this form with Saint Michael College of Caraga in
              accordance with the Philippine Data Privacy Act of 2012.
            </span>
          </label>
        </div>

        <!-- BUTTONS -->
        <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3">
          <p class="text-[11px] sm:text-xs text-[#052c6a]/70 text-center sm:text-left">
            After submitting this form, you will proceed to
            <span class="font-semibold text-[#052c6a]">Step 3: Upload Requirements</span>
            for the grant you selected.
          </p>

          <button
            id="submitBtn"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2
                   bg-[#0d8ddb] text-white font-semibold px-8 py-2.5 rounded-full
                   shadow-md focus:outline-none transition"
            style="opacity:0.45;cursor:not-allowed;pointer-events:none;"
            type="submit"
            disabled
          >
            <span>Submit &amp; Go to Step 3</span>
            <i class="fas fa-arrow-right text-xs"></i>
          </button>
        </div>

        <script>
          (function () {
            var checkbox = document.getElementById('consentCheckbox');
            var btn = document.getElementById('submitBtn');
            checkbox.addEventListener('change', function () {
              if (this.checked) {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
                btn.style.pointerEvents = 'auto';
              } else {
                btn.disabled = true;
                btn.style.opacity = '0.45';
                btn.style.cursor = 'not-allowed';
                btn.style.pointerEvents = 'none';
              }
            });
          })();
        </script>
      </form>
    </section>
  </main>

  <script>
    // helper: get query param
    function getQueryParam(name) {
      const params = new URLSearchParams(window.location.search);
      return params.get(name);
    }

    // map grant id to label (same order as Step 1)
    const grantNames = <?php echo json_encode($grantNames, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const elementaryProgramOptions = <?php echo json_encode($elementaryProgramOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const juniorHighSchoolProgramOptions = <?php echo json_encode($juniorHighSchoolProgramOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const seniorHighSchoolProgramOptions = <?php echo json_encode($seniorHighSchoolProgramOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const collegeProgramOptions = <?php echo json_encode($collegeProgramOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const studentAssistantProgramOptions = <?php echo json_encode($studentAssistantPrograms, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const seniorHighSchoolYearLevelOptions = <?php echo json_encode($seniorHighSchoolYearLevelOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const collegeYearLevelOptions = <?php echo json_encode($collegeYearLevelOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

    const grantIdFromUrl = getQueryParam('grant');
    const grantLabelEl = document.getElementById('selectedGrantLabel');
    const appliedGrantTypeLabel = document.getElementById('appliedGrantTypeLabel');
    const grantIdField = document.getElementById('grantIdField');
    const activeGrantId = grantIdFromUrl || grantIdField.value;
    const departmentSelect = document.getElementById('department');
    const academicRadio = document.getElementById('academic');
    const kabayaniRadio = document.getElementById('kabayani');
    const studentAssistanceRadio = document.getElementById('studentAssistance');
    const othersRadio = document.getElementById('others');
    const othersSpecifyInput = document.getElementById('othersSpecify');
    const programCourseSelect = document.getElementById('programCourseSelect');
    const programCourseInput = document.getElementById('programCourseInput');
    const programCourseHint = document.getElementById('programCourseHint');
    const yearLevelSelect = document.getElementById('yearLevel');
    const semesterSelect = document.getElementById('semester');
    const yearLevelRequiredMark = document.getElementById('yearLevelRequiredMark');
    const semesterRequiredMark = document.getElementById('semesterRequiredMark');
    const yearLevelOptionalMark = document.getElementById('yearLevelOptionalMark');
    const semesterOptionalMark = document.getElementById('semesterOptionalMark');
    const contactNumberInputs = document.querySelectorAll('#contactNumber, #motherContact, #fatherContact');

    function getAutoScholarshipType(grantId) {
      const idNum = parseInt(grantId, 10);
      if (idNum === 1) return 'Student Assistance';
      if (idNum === 2) return 'Academic';
      if (idNum === 4 || idNum === 5) return 'Kabayani';
      if (idNum > 0) return 'Others';
      return '';
    }

    function syncOthersSpecify() {
      if (!othersSpecifyInput) {
        return;
      }

      const grantTitle = grantNames[grantIdField.value] || '';
      if (othersRadio && othersRadio.checked && grantTitle) {
        othersSpecifyInput.value = grantTitle;
      }
    }

    function usesStudentAssistantPrograms() {
      const grantId = parseInt(grantIdField.value || '0', 10);
      return grantId === 1 || Boolean(studentAssistanceRadio && studentAssistanceRadio.checked);
    }

    function setSelectOptions(select, options, placeholder, selectedValue, emptyPlaceholder = '') {
      if (!select) {
        return;
      }

      select.innerHTML = '';

      const placeholderOption = document.createElement('option');
      placeholderOption.value = '';
      placeholderOption.textContent = options.length > 0 ? placeholder : (emptyPlaceholder || placeholder);
      if (options.length === 0 && emptyPlaceholder) {
        placeholderOption.disabled = true;
      }
      select.appendChild(placeholderOption);

      options.forEach((optionValue) => {
        const option = document.createElement('option');
        option.value = optionValue;
        option.textContent = optionValue;
        if (optionValue === selectedValue) {
          option.selected = true;
        }
        select.appendChild(option);
      });
    }

    function getDepartmentProgramOptions(department) {
      if (department === 'Elementary') return elementaryProgramOptions;
      if (department === 'Junior High School') return juniorHighSchoolProgramOptions;
      if (department === 'Senior High School') return seniorHighSchoolProgramOptions;
      if (department === 'College') return usesStudentAssistantPrograms() ? studentAssistantProgramOptions : collegeProgramOptions;
      return [];
    }

    function syncProgramCourseField() {
      if (!programCourseSelect || !programCourseInput) {
        return;
      }

      const department = departmentSelect ? departmentSelect.value : '';
      const selectedValue = programCourseSelect.value || programCourseSelect.dataset.selectedValue || programCourseInput.value;
      const programOptions = getDepartmentProgramOptions(department);
      const shouldUseSelect = ['Elementary', 'Junior High School', 'Senior High School', 'College'].includes(department);

      if (!department) {
        programCourseSelect.classList.add('hidden');
        programCourseSelect.disabled = true;
        programCourseSelect.required = false;
        programCourseSelect.name = '';

        programCourseInput.classList.remove('hidden');
        programCourseInput.disabled = true;
        programCourseInput.required = false;
        programCourseInput.name = '';
        programCourseInput.placeholder = 'Select department first';

        if (programCourseHint) {
          programCourseHint.classList.add('hidden');
          programCourseHint.textContent = '';
        }
      } else if (shouldUseSelect) {
        const emptyPlaceholder = department === 'Senior High School' || department === 'College' ? 'No program added yet' : '';
        setSelectOptions(programCourseSelect, programOptions, 'Select program / course', selectedValue, emptyPlaceholder);
        programCourseSelect.dataset.selectedValue = programCourseSelect.value;

        programCourseSelect.classList.remove('hidden');
        programCourseSelect.disabled = false;
        programCourseSelect.required = true;
        programCourseSelect.name = 'programCourse';

        programCourseInput.classList.add('hidden');
        programCourseInput.disabled = true;
        programCourseInput.required = false;
        programCourseInput.name = '';
        programCourseInput.placeholder = 'e.g. BS in Computer Science';

        if (programCourseHint) {
          programCourseHint.classList.remove('hidden');
          programCourseHint.textContent = department === 'Senior High School'
            ? 'Select the Senior High School strand.'
            : 'Select the option that matches the department.';
        }
      } else {
        if (programCourseSelect.value && !programCourseInput.value) {
          programCourseInput.value = programCourseSelect.value;
        }

        programCourseInput.classList.remove('hidden');
        programCourseInput.disabled = false;
        programCourseInput.required = true;
        programCourseInput.name = 'programCourse';
        programCourseInput.placeholder = 'e.g. BS in Computer Science';

        programCourseSelect.classList.add('hidden');
        programCourseSelect.disabled = true;
        programCourseSelect.required = false;
        programCourseSelect.name = '';

        if (programCourseHint) {
          programCourseHint.classList.add('hidden');
          programCourseHint.textContent = '';
        }
      }
    }

    function syncAcademicFields() {
      if (!yearLevelSelect || !semesterSelect) {
        return;
      }

      const department = departmentSelect ? departmentSelect.value : '';
      const isBasicEducation = department === 'Elementary' || department === 'Junior High School';
      const isSeniorHighSchool = department === 'Senior High School';
      const isYearAndSemesterRequired = department !== '' && !isBasicEducation;
      const selectedYearLevel = yearLevelSelect.value || yearLevelSelect.dataset.selectedValue || '';
      const yearOptions = isSeniorHighSchool ? seniorHighSchoolYearLevelOptions : collegeYearLevelOptions;

      setSelectOptions(yearLevelSelect, yearOptions, 'Select year level', selectedYearLevel);
      yearLevelSelect.dataset.selectedValue = yearLevelSelect.value;

      yearLevelSelect.required = isYearAndSemesterRequired;
      semesterSelect.required = isYearAndSemesterRequired;

      if (yearLevelRequiredMark) {
        yearLevelRequiredMark.classList.toggle('hidden', !isYearAndSemesterRequired);
      }
      if (semesterRequiredMark) {
        semesterRequiredMark.classList.toggle('hidden', !isYearAndSemesterRequired);
      }
      if (yearLevelOptionalMark) {
        yearLevelOptionalMark.classList.toggle('hidden', !isBasicEducation);
      }
      if (semesterOptionalMark) {
        semesterOptionalMark.classList.toggle('hidden', !isBasicEducation);
      }
    }

    function sanitizeContactNumberInput(event) {
      const cleanedValue = event.target.value.replace(/\D/g, '').slice(0, 11);
      if (event.target.value !== cleanedValue) {
        event.target.value = cleanedValue;
      }
    }

    if (activeGrantId && grantNames[activeGrantId]) {
      grantLabelEl.textContent = grantNames[activeGrantId] + ' (Grant #' + activeGrantId + ')';
      grantIdField.value = activeGrantId;
      if (appliedGrantTypeLabel) {
        appliedGrantTypeLabel.textContent = grantNames[activeGrantId];
      }
    } else {
      grantLabelEl.textContent = 'No specific grant selected. You may go back to Step 1 to choose a grant.';
      grantIdField.value = '';
      if (appliedGrantTypeLabel) {
        appliedGrantTypeLabel.textContent = 'No grant selected';
      }
    }

    // optional: auto-select scholarship type based on grant id
    if (activeGrantId) {
      const type = getAutoScholarshipType(activeGrantId);
      if (type === 'Student Assistance' && studentAssistanceRadio) {
        studentAssistanceRadio.checked = true;
      } else if (type === 'Academic' && academicRadio) {
        academicRadio.checked = true;
      } else if (type === 'Kabayani' && kabayaniRadio) {
        kabayaniRadio.checked = true;
      } else if (type === 'Others' && othersRadio) {
        othersRadio.checked = true;
      }
    }

    if (othersRadio) {
      othersRadio.addEventListener('change', syncOthersSpecify);
    }

    if (departmentSelect) {
      departmentSelect.addEventListener('change', () => {
        if (programCourseSelect) {
          programCourseSelect.dataset.selectedValue = '';
        }
        if (programCourseInput) {
          programCourseInput.value = '';
        }
        if (yearLevelSelect) {
          yearLevelSelect.dataset.selectedValue = '';
        }
        syncProgramCourseField();
        syncAcademicFields();
      });
    }

    if (programCourseSelect) {
      programCourseSelect.addEventListener('change', () => {
        programCourseSelect.dataset.selectedValue = programCourseSelect.value;
      });
    }

    if (yearLevelSelect) {
      yearLevelSelect.addEventListener('change', () => {
        yearLevelSelect.dataset.selectedValue = yearLevelSelect.value;
      });
    }

    [academicRadio, kabayaniRadio, studentAssistanceRadio, othersRadio].forEach((radio) => {
      if (radio) {
        radio.addEventListener('change', () => {
          syncProgramCourseField();
          syncAcademicFields();
        });
      }
    });

    contactNumberInputs.forEach((input) => {
      input.addEventListener('input', sanitizeContactNumberInput);
      input.addEventListener('paste', () => {
        requestAnimationFrame(() => sanitizeContactNumberInput({ target: input }));
      });
    });

    syncOthersSpecify();
    syncProgramCourseField();
    syncAcademicFields();

  </script>
</body>
</html>

