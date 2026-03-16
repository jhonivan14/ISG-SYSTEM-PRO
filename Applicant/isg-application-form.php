<?php
session_start();
require_once "../db.php";

$errors = [];

function read_post_field($key) {
  return isset($_POST[$key]) ? trim($_POST[$key]) : "";
}

function normalize_contact_number($value) {
  return preg_replace('/\D+/', '', trim((string)$value));
}

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

function validate_birth_date(&$errors, $field, $label) {
  $value = read_post_field($field);
  if ($value === "") {
    return;
  }

  if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
    $errors[] = $label . " must use the format mm/dd/yyyy.";
    return;
  }

  $date = DateTime::createFromFormat('m/d/Y', $value);
  $hasValidDate = $date instanceof DateTime && $date->format('m/d/Y') === $value;
  if (!$hasValidDate) {
    $errors[] = $label . " must be a valid date in mm/dd/yyyy format.";
  }
}

function infer_scholarship_type($grantId) {
  if ($grantId === 1) {
    return "Student Assistance";
  }
  if ($grantId === 2) {
    return "Academic";
  }
  if ($grantId === 4) {
    return "Kabayani";
  }
  if ($grantId > 0) {
    return "Others";
  }

  return "";
}

function uses_student_assistant_program_options($grantId, $scholarshipType) {
  return $grantId === 1 || $scholarshipType === "Student Assistance";
}

$grantNames = [
  1 => "Student Assistant",
  2 => "Academic Scholarship Program",
  3 => "Executive Student Government (ESG) President Scholarship Program",
  4 => "Kabayani Scholarship Program",
  5 => "Kabayani Loyalty Grant",
  6 => "Discount for Persons with Disability (PWD)",
  7 => "Discount for Children of Employees",
  8 => "Discount for Sibling of Employees",
  9 => "Sibling Discount",
  10 => "DXSM-FM Grant",
  11 => "Michaelinian Mirror Grant (Editor-in-Chief)",
  12 => "Grant for the Dependents of a Lot Donor",
  13 => "Grant for the Dependents of a Board of Trustees (BOT) Member",
  14 => "SMCC Alumni Discount",
  15 => "Michaelinian Stakeholders Grant",
];

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

$currentYear = (int)date("Y");
$currentMonth = (int)date("n");
$currentSchoolYearStart = $currentMonth < 6 ? $currentYear - 1 : $currentYear;
$currentSchoolYear = $currentSchoolYearStart . "-" . ($currentSchoolYearStart + 1);
$schoolYearOptions = [$currentSchoolYear];
$currentGrantId = (int)($_POST["grant_id"] ?? $_GET["grant"] ?? 0);
$currentGrantTitle = $grantNames[$currentGrantId] ?? "";
$selectedScholarshipType = read_post_field("scholarshipType");
$effectiveScholarshipType = $selectedScholarshipType !== "" ? $selectedScholarshipType : infer_scholarship_type($currentGrantId);
$kabayaniSpecifyValue = read_post_field("kabayaniSpecify");
$othersSpecifyValue = read_post_field("othersSpecify");
$programCourseValue = read_post_field("programCourse");
$showStudentAssistantProgramSelect = uses_student_assistant_program_options($currentGrantId, $effectiveScholarshipType);
if ($othersSpecifyValue === "" && $effectiveScholarshipType === "Others" && $currentGrantTitle !== "") {
  $othersSpecifyValue = $currentGrantTitle;
}
$selectedSchoolYear = read_post_field("schoolYear");
$selectedSemester = read_post_field("semester");
 
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $grantId = $currentGrantId;

  if ($grantId <= 0) {
    $errors[] = "Missing grant selection.";
  }
 
  
  $requiredFields = [
    "scholarshipType" => "Scholarship type is required.",
    "applicantName" => "Applicant name is required.",
    "programCourse" => "Program/course is required.",
    "yearLevel" => "Year level is required.",
    "schoolYear" => "School year is required.",
    "semester" => "Semester is required.",
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

  if (
    uses_student_assistant_program_options($grantId, read_post_field("scholarshipType")) &&
    $programCourseValue !== "" &&
    !in_array($programCourseValue, $studentAssistantPrograms, true)
  ) {
    $errors[] = "Please select a valid Student Assistant program/course.";
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
  <script src="https://cdn.tailwindcss.com"></script>
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
      <div class="flex-1">
        <p class="text-[10px] sm:text-xs text-blue-100 uppercase tracking-[0.18em]">
          SMCC Admission and Scholarship Office
        </p>
        <div class="flex flex-wrap items-center gap-2">
          <h1 class="text-white text-sm sm:text-base font-semibold leading-tight">
            Institutional Scholarship Grants
          </h1>
          <span class="inline-flex items-center gap-1 px-2 py-[2px] rounded-full bg-white/10 text-[10px] sm:text-[11px] text-blue-50">
            Step 2 of 3
          </span>
        </div>
        <p class="text-[10px] sm:text-xs text-blue-100">
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
            Please select the type of scholarship or grant you are applying for. This will help the office
            categorize your application.
          </p>

          <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
            <div class="space-y-3">
              <label class="flex items-center gap-2 rounded-2xl border border-transparent bg-white px-3 py-2 shadow-sm hover:border-[#0d8ddb]/60 cursor-pointer transition">
                <input
                  class="h-4 w-4 text-[#0d8ddb] focus:ring-[#fcdc2f] border-gray-300"
                  id="academic"
                  name="scholarshipType"
                  type="radio"
                  value="Academic"
                  <?php echo $effectiveScholarshipType === "Academic" ? "checked" : ""; ?>
                  required
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
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="programCourse">
                Program / Course Enrolled <span class="text-red-600">*</span>
              </label>
              <select
                class="<?php echo $showStudentAssistantProgramSelect ? "" : "hidden "; ?>w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="programCourseSelect"
                <?php echo $showStudentAssistantProgramSelect ? 'name="programCourse" required' : 'disabled'; ?>
              >
                <option value="">Select program / course</option>
                <?php foreach ($studentAssistantPrograms as $programOption): ?>
                  <option value="<?php echo htmlspecialchars($programOption); ?>" <?php echo $programCourseValue === $programOption ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($programOption); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <input
                class="<?php echo $showStudentAssistantProgramSelect ? "hidden " : ""; ?>w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="programCourseInput"
                <?php echo $showStudentAssistantProgramSelect ? 'disabled' : 'name="programCourse" required'; ?>
                placeholder="e.g. BS in Computer Science"
                type="text"
                value="<?php echo htmlspecialchars($programCourseValue); ?>"
              />
              <p
                id="programCourseHint"
                class="<?php echo $showStudentAssistantProgramSelect ? "" : "hidden "; ?>mt-1 text-[11px] sm:text-xs text-[#052c6a]/75"
              >
                
              </p>
            </div>

            <div>
              <label class="block text-sm text-[#052c6a] font-semibold mb-1.5" for="yearLevel">
                Year Level <span class="text-red-600">*</span>
              </label>
              <select
                class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                id="yearLevel"
                name="yearLevel"
                required
              >
                <option disabled selected value="">Select year level</option>
                <option value="1st Year">1st Year</option>
                <option value="2nd Year">2nd Year</option>
                <option value="3rd Year">3rd Year</option>
                <option value="4th Year">4th Year</option>
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
                  Semester <span class="text-red-600">*</span>
                </label>
                <select
                  class="w-full border border-[#0d8ddb]/60 rounded-xl px-3 py-2 text-sm
                         focus:outline-none focus:ring-2 focus:ring-[#fcdc2f] focus:border-[#0d8ddb]"
                  id="semester"
                  name="semester"
                  required
                >
                  <option disabled selected value="">Select semester</option>
                  <?php $semesterOptions = ["1st Semester", "2nd Semester"]; ?>
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
                <option value="Prefer not to say">Prefer not to say</option>
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
                inputmode="numeric"
                maxlength="10"
                minlength="10"
                name="dateOfBirth"
                pattern="\d{2}/\d{2}/\d{4}"
                placeholder="mm/dd/yyyy"
                required
                title="Please enter date of birth in mm/dd/yyyy format."
                type="text"
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
          </div>
        </fieldset>

        <!-- BUTTONS -->
        <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3">
          <p class="text-[11px] sm:text-xs text-[#052c6a]/70 text-center sm:text-left">
            After submitting this form, you will proceed to
            <span class="font-semibold text-[#052c6a]">Step 3: Upload Requirements</span>
            for the grant you selected.
          </p>

          <button
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2
                   bg-[#0d8ddb] text-white font-semibold px-8 py-2.5 rounded-full
                   shadow-md hover:bg-[#0b63d1] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#0d8ddb] focus:ring-offset-white transition"
            type="submit"
          >
            <span>Submit &amp; Go to Step 3</span>
            <i class="fas fa-arrow-right text-xs"></i>
          </button>
        </div>
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

    const grantIdFromUrl = getQueryParam('grant');
    const grantLabelEl = document.getElementById('selectedGrantLabel');
    const grantIdField = document.getElementById('grantIdField');
    const activeGrantId = grantIdFromUrl || grantIdField.value;
    const academicRadio = document.getElementById('academic');
    const kabayaniRadio = document.getElementById('kabayani');
    const studentAssistanceRadio = document.getElementById('studentAssistance');
    const othersRadio = document.getElementById('others');
    const othersSpecifyInput = document.getElementById('othersSpecify');
    const programCourseSelect = document.getElementById('programCourseSelect');
    const programCourseInput = document.getElementById('programCourseInput');
    const programCourseHint = document.getElementById('programCourseHint');
    const dateOfBirthInput = document.getElementById('dateOfBirth');
    const contactNumberInputs = document.querySelectorAll('#contactNumber, #motherContact, #fatherContact');

    function getAutoScholarshipType(grantId) {
      const idNum = parseInt(grantId, 10);
      if (idNum === 1) return 'Student Assistance';
      if (idNum === 2) return 'Academic';
      if (idNum === 4) return 'Kabayani';
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

    function syncProgramCourseField() {
      if (!programCourseSelect || !programCourseInput) {
        return;
      }

      const shouldUseSelect = usesStudentAssistantPrograms();

      if (shouldUseSelect) {
        if (programCourseInput.value && !programCourseSelect.value) {
          programCourseSelect.value = programCourseInput.value;
        }

        programCourseSelect.classList.remove('hidden');
        programCourseSelect.disabled = false;
        programCourseSelect.required = true;
        programCourseSelect.name = 'programCourse';

        programCourseInput.classList.add('hidden');
        programCourseInput.disabled = true;
        programCourseInput.required = false;
        programCourseInput.name = '';

        if (programCourseHint) {
          programCourseHint.classList.remove('hidden');
        }
      } else {
        if (programCourseSelect.value && !programCourseInput.value) {
          programCourseInput.value = programCourseSelect.value;
        }

        programCourseInput.classList.remove('hidden');
        programCourseInput.disabled = false;
        programCourseInput.required = true;
        programCourseInput.name = 'programCourse';

        programCourseSelect.classList.add('hidden');
        programCourseSelect.disabled = true;
        programCourseSelect.required = false;
        programCourseSelect.name = '';

        if (programCourseHint) {
          programCourseHint.classList.add('hidden');
        }
      }
    }

    function sanitizeContactNumberInput(event) {
      const cleanedValue = event.target.value.replace(/\D/g, '').slice(0, 11);
      if (event.target.value !== cleanedValue) {
        event.target.value = cleanedValue;
      }
    }

    function sanitizeBirthDateInput(event) {
      const cleanedValue = event.target.value.replace(/[^\d/]/g, '').slice(0, 10);
      if (event.target.value !== cleanedValue) {
        event.target.value = cleanedValue;
      }
    }

    if (activeGrantId && grantNames[activeGrantId]) {
      grantLabelEl.textContent = grantNames[activeGrantId] + ' (Grant #' + activeGrantId + ')';
      grantIdField.value = activeGrantId;
    } else {
      grantLabelEl.textContent = 'No specific grant selected. You may go back to Step 1 to choose a grant.';
      grantIdField.value = '';
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

    [academicRadio, kabayaniRadio, studentAssistanceRadio, othersRadio].forEach((radio) => {
      if (radio) {
        radio.addEventListener('change', syncProgramCourseField);
      }
    });

    contactNumberInputs.forEach((input) => {
      input.addEventListener('input', sanitizeContactNumberInput);
      input.addEventListener('paste', () => {
        requestAnimationFrame(() => sanitizeContactNumberInput({ target: input }));
      });
    });

    if (dateOfBirthInput) {
      dateOfBirthInput.addEventListener('input', sanitizeBirthDateInput);
      dateOfBirthInput.addEventListener('paste', () => {
        requestAnimationFrame(() => sanitizeBirthDateInput({ target: dateOfBirthInput }));
      });
    }

    syncOthersSpecify();
    syncProgramCourseField();

  </script>
</body>
</html>

