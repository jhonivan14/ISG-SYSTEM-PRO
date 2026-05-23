<?php
require_once "../db.php";
require_once "../application-reference.php";
require_once "../upload-storage.php";
require_once "../Admin/includes/application-decline-history.php";

function updateReadPostField(string $key): string
{
  return isset($_POST[$key]) ? trim((string)$_POST[$key]) : "";
}

function updateNormalizeContactNumber(string $value): string
{
  return preg_replace('/\D+/', '', trim($value));
}

function updateNormalizeBirthDateForStorage(string $value): string
{
  $value = trim($value);
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

function updateFormatBirthDateForInput(string $value): string
{
  $value = trim($value);
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

function updateCanModifySubmission(string $status): bool
{
  $key = strtolower(trim($status));
  return $key === "" || $key === "pending" || $key === "reapplied" || $key === "rejected" || $key === "declined";
}

function updateIsReapplySubmission(string $status): bool
{
  $key = strtolower(trim($status));
  return $key === "rejected" || $key === "declined";
}

function updateIsReappliedSubmission(string $status): bool
{
  return strtolower(trim($status)) === "reapplied";
}

function updateSubmissionLockMessage(string $status): string
{
  $key = strtolower(trim($status));

  if ($key === "approved") {
    return "This submission can no longer be updated because the application has already moved forward from the initial review stage.";
  }

  if ($key === "rejected" || $key === "declined") {
    return "This submission can no longer be updated because the application review has already been completed.";
  }

  return "This submission can no longer be updated because the scholarship office has already changed the application status.";
}

function updateValidateContactNumber(array &$errors, array &$formData, string $field, string $label): void
{
  $value = trim((string)($formData[$field] ?? ""));
  if ($value === "") {
    return;
  }

  $normalizedValue = updateNormalizeContactNumber($value);
  $formData[$field] = $normalizedValue;

  if (!preg_match('/^\d{11}$/', $normalizedValue)) {
    $errors[] = $label . " must contain exactly 11 digits.";
  }
}

function updateValidateBirthDate(array &$errors, string $value, string $label): void
{
  $value = trim($value);
  if ($value === "") {
    return;
  }

  if (updateNormalizeBirthDateForStorage($value) === "") {
    $errors[] = $label . " must be a valid date.";
  }
}

function updateUsesStudentAssistantPrograms(int $grantId, string $scholarshipType): bool
{
  return $grantId === 1 || $scholarshipType === "Student Assistance";
}

function updateCurrentSchoolYear(): string
{
  $currentYear = (int)date("Y");
  $currentMonth = (int)date("n");
  $schoolYearStart = $currentMonth < 6 ? $currentYear - 1 : $currentYear;
  return $schoolYearStart . "-" . ($schoolYearStart + 1);
}

function loadApplicantUploads(mysqli $conn, int $applicationId): array
{
  $uploads = [];
  $stmt = $conn->prepare(
    "SELECT id, requirement_label, original_file_name, stored_file_name, stored_path, mime_type, uploaded_at
     FROM application_uploads
     WHERE application_id = ?
     ORDER BY uploaded_at DESC, id DESC"
  );

  if (!$stmt) {
    return $uploads;
  }

  $stmt->bind_param("i", $applicationId);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $uploads[] = $row;
    }
  }
  $stmt->close();

  return $uploads;
}

function mapApplicationToUpdateForm(array $application): array
{
  return [
    "scholarshipType" => (string)($application["scholarship_type"] ?? ""),
    "kabayaniSpecify" => (string)($application["kabayani_specify"] ?? ""),
    "othersSpecify" => (string)($application["others_specify"] ?? ""),
    "applicantName" => (string)($application["applicant_name"] ?? ""),
    "programCourse" => (string)($application["program_course"] ?? ""),
    "yearLevel" => (string)($application["year_level"] ?? ""),
    "schoolYear" => (string)($application["school_year"] ?? ""),
    "semester" => (string)($application["semester"] ?? ""),
    "permanentAddress" => (string)($application["permanent_address"] ?? ""),
    "gender" => (string)($application["gender"] ?? ""),
    "age" => (string)($application["age"] ?? ""),
    "dateOfBirth" => updateFormatBirthDateForInput((string)($application["date_of_birth"] ?? "")),
    "contactNumber" => (string)($application["contact_number"] ?? ""),
    "emailAddress" => (string)($application["email_address"] ?? ""),
    "estimatedIncome" => (string)($application["estimated_income"] ?? ""),
    "motherName" => (string)($application["mother_name"] ?? ""),
    "motherContact" => (string)($application["mother_contact"] ?? ""),
    "motherOccupation" => (string)($application["mother_occupation"] ?? ""),
    "motherCompanyName" => (string)($application["mother_company_name"] ?? ""),
    "motherCompanyAddress" => (string)($application["mother_company_address"] ?? ""),
    "motherAge" => (string)($application["mother_age"] ?? ""),
    "fatherName" => (string)($application["father_name"] ?? ""),
    "fatherContact" => (string)($application["father_contact"] ?? ""),
    "fatherOccupation" => (string)($application["father_occupation"] ?? ""),
    "fatherCompanyName" => (string)($application["father_company_name"] ?? ""),
    "fatherCompanyAddress" => (string)($application["father_company_address"] ?? ""),
    "fatherAge" => (string)($application["father_age"] ?? ""),
  ];
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

$grantRequirements = [
  1 => ["2x2 ID Picture", "Application Letter", "Resume", "Form 138 / Report of Grades", "Certificate of Indigency", "Certificate of Good Moral Character"],
  2 => ["Certification (Top 1 or Top 2)", "2x2 ID Picture", "Form 138 / Grades", "Certificate of Indigency", "Certificate of Good Moral Character"],
  3 => ["2x2 ID Picture", "Endorsement Letter from OSAS"],
  4 => ["2x2 ID Picture", "Endorsement Letter"],
  5 => ["2x2 ID Picture", "Certification from External Linkages Coordinator", "Proof of Relationship", "Endorsement Letter from Retiree"],
  6 => ["2x2 ID Picture", "PWD ID (2 photocopies)"],
  7 => ["2x2 ID Picture", "Certification from HRMDO"],
  8 => ["2x2 ID Picture", "Certification from HRMDO"],
  9 => ["2x2 ID Picture"],
  10 => ["2x2 ID Picture", "Endorsement Letter from Station Manager"],
  11 => ["2x2 ID Picture", "Endorsement Letter from Publication In-Charge"],
  12 => ["2x2 ID Picture", "Board Resolution / Certification"],
  13 => ["2x2 ID Picture", "Board Resolution / Certification", "Endorsement Letter"],
  14 => ["2x2 ID Picture", "Certification from Alumni Association"],
  15 => [],
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

$referenceNumber = normalizeApplicationReference((string)($_GET["reference"] ?? $_POST["reference"] ?? ""));
$errors = [];
$application = null;
$uploads = [];
$uploadsByLabel = [];
$schoolYearOptions = [updateCurrentSchoolYear()];
$yearLevelOptions = ["1st Year", "2nd Year", "3rd Year", "4th Year"];
$semesterOptions = ["1st Semester", "2nd Semester"];
$genderOptions = ["Male", "Female", "Prefer not to say"];
$allowedExt = ["pdf", "jpg", "jpeg", "png"];
$formData = [];
$submissionUpdatesAllowed = false;
$submissionUpdateLockMessage = "";
$isReapplySubmission = false;

backfillMissingApplicationReferences($conn);

if ($referenceNumber !== "") {
  $applicationStmt = $conn->prepare("SELECT * FROM applications WHERE reference_number = ? LIMIT 1");
  if ($applicationStmt) {
    $applicationStmt->bind_param("s", $referenceNumber);
    $applicationStmt->execute();
    $applicationResult = $applicationStmt->get_result();
    $application = $applicationResult ? $applicationResult->fetch_assoc() : null;
    $applicationStmt->close();
  }
}

if (!$application) {
  $errors[] = $referenceNumber === ""
    ? "Missing application reference number."
    : "Application not found for that reference number.";
}

if ($application) {
  $formData = mapApplicationToUpdateForm($application);
  $submissionUpdatesAllowed = updateCanModifySubmission((string)($application["status"] ?? ""));
  $isReapplySubmission = updateIsReapplySubmission((string)($application["status"] ?? ""));
  $submissionUpdateLockMessage = updateSubmissionLockMessage((string)($application["status"] ?? ""));
  $existingSchoolYear = trim((string)($application["school_year"] ?? ""));
  if ($existingSchoolYear !== "" && !in_array($existingSchoolYear, $schoolYearOptions, true)) {
    $schoolYearOptions[] = $existingSchoolYear;
  }
  sort($schoolYearOptions);

  $uploads = loadApplicantUploads($conn, (int)$application["id"]);
  foreach ($uploads as $upload) {
    $label = trim((string)($upload["requirement_label"] ?? ""));
    if ($label === "") {
      $label = "Document";
    }
    if (!isset($uploadsByLabel[$label])) {
      $uploadsByLabel[$label] = [];
    }
    $uploadsByLabel[$label][] = $upload;
  }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $application) {
  if (!$submissionUpdatesAllowed) {
    header("Location: tracking-dashboard.php?reference=" . urlencode($referenceNumber) . "&update_status=locked");
    exit;
  }

  $grantId = (int)($application["grant_id"] ?? 0);
  $requirements = $grantRequirements[$grantId] ?? [];
  $grantRequiresUploads = !empty($requirements);
  $formData = [
    "scholarshipType" => updateReadPostField("scholarshipType"),
    "kabayaniSpecify" => updateReadPostField("kabayaniSpecify"),
    "othersSpecify" => updateReadPostField("othersSpecify"),
    "applicantName" => updateReadPostField("applicantName"),
    "programCourse" => updateReadPostField("programCourse"),
    "yearLevel" => updateReadPostField("yearLevel"),
    "schoolYear" => updateReadPostField("schoolYear"),
    "semester" => updateReadPostField("semester"),
    "permanentAddress" => updateReadPostField("permanentAddress"),
    "gender" => updateReadPostField("gender"),
    "age" => updateReadPostField("age"),
    "dateOfBirth" => updateReadPostField("dateOfBirth"),
    "contactNumber" => updateReadPostField("contactNumber"),
    "emailAddress" => updateReadPostField("emailAddress"),
    "estimatedIncome" => updateReadPostField("estimatedIncome"),
    "motherName" => updateReadPostField("motherName"),
    "motherContact" => updateReadPostField("motherContact"),
    "motherOccupation" => updateReadPostField("motherOccupation"),
    "motherCompanyName" => updateReadPostField("motherCompanyName"),
    "motherCompanyAddress" => updateReadPostField("motherCompanyAddress"),
    "motherAge" => updateReadPostField("motherAge"),
    "fatherName" => updateReadPostField("fatherName"),
    "fatherContact" => updateReadPostField("fatherContact"),
    "fatherOccupation" => updateReadPostField("fatherOccupation"),
    "fatherCompanyName" => updateReadPostField("fatherCompanyName"),
    "fatherCompanyAddress" => updateReadPostField("fatherCompanyAddress"),
    "fatherAge" => updateReadPostField("fatherAge"),
  ];

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
    "fatherName" => "Father's name is required.",
  ];

  foreach ($requiredFields as $field => $message) {
    if (trim((string)($formData[$field] ?? "")) === "") {
      $errors[] = $message;
    }
  }

  if (
    updateUsesStudentAssistantPrograms($grantId, (string)$formData["scholarshipType"]) &&
    $formData["programCourse"] !== "" &&
    !in_array($formData["programCourse"], $studentAssistantPrograms, true)
  ) {
    $errors[] = "Please select a valid Student Assistant program/course.";
  }

  if ($formData["emailAddress"] !== "") {
    $isValidEmail = filter_var($formData["emailAddress"], FILTER_VALIDATE_EMAIL);
    $isGmail = (bool)preg_match('/@gmail\.com$/i', (string)$formData["emailAddress"]);
    if (!$isValidEmail || !$isGmail) {
      $errors[] = "Email address must be a valid @gmail.com address.";
    }
  }

  updateValidateContactNumber($errors, $formData, "contactNumber", "Contact number");
  updateValidateContactNumber($errors, $formData, "motherContact", "Mother's contact number");
  updateValidateContactNumber($errors, $formData, "fatherContact", "Father's contact number");
  updateValidateBirthDate($errors, (string)$formData["dateOfBirth"], "Date of birth");

  $files = $_FILES["files"] ?? null;
  if ($grantRequiresUploads) {
    if (!$files || !isset($files["name"]) || !is_array($files["name"])) {
      $errors[] = "Unable to process uploaded files.";
    } else {
      foreach ($requirements as $index => $requirementLabel) {
        $uploadError = $files["error"][$index] ?? UPLOAD_ERR_NO_FILE;
        $originalName = trim((string)($files["name"][$index] ?? ""));
        $existingUpload = $uploadsByLabel[$requirementLabel][0] ?? null;

        if ($uploadError === UPLOAD_ERR_NO_FILE) {
          if (!$existingUpload) {
            $errors[] = "Please upload " . $requirementLabel . ".";
          }
          continue;
        }

        if ($uploadError !== UPLOAD_ERR_OK) {
          $errors[] = "Upload failed for " . $requirementLabel . ".";
          continue;
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
          $errors[] = "Invalid file type for " . $requirementLabel . ".";
        }
      }
    }
  }

  if (empty($errors)) {
    if ($isReapplySubmission) {
      applicationDeclineHistorySnapshot($conn, (int)$application["id"], "applicant_reapply");
    }

    $nextStatus = ($isReapplySubmission || updateIsReappliedSubmission((string)($application["status"] ?? "")))
      ? "Reapplied"
      : "Pending";
    $dateOfBirthForStorage = updateNormalizeBirthDateForStorage((string)$formData["dateOfBirth"]);
    $updateStmt = $conn->prepare(
      "UPDATE applications SET
        scholarship_type = ?, kabayani_specify = ?, others_specify = ?, applicant_name = ?, program_course = ?,
        year_level = ?, school_year = ?, semester = ?, permanent_address = ?, gender = ?, age = ?,
        date_of_birth = ?, contact_number = ?, email_address = ?, estimated_income = ?, mother_name = ?,
        mother_contact = ?, mother_company_name = ?, mother_company_address = ?, mother_age = ?, mother_occupation = ?,
        father_name = ?, father_contact = ?, father_company_name = ?, father_company_address = ?, father_age = ?,
        father_occupation = ?, status = ?
       WHERE id = ?"
    );

    if (!$updateStmt) {
      $errors[] = "Failed to prepare the application update.";
    } else {
      $age = (int)$formData["age"];
      $estimatedIncome = (int)$formData["estimatedIncome"];
      $motherAge = $formData["motherAge"] !== "" ? (int)$formData["motherAge"] : 0;
      $fatherAge = $formData["fatherAge"] !== "" ? (int)$formData["fatherAge"] : 0;
      $applicationId = (int)$application["id"];

      $updateStmt->bind_param(
        str_repeat("s", 28) . "i",
        $formData["scholarshipType"],
        $formData["kabayaniSpecify"],
        $formData["othersSpecify"],
        $formData["applicantName"],
        $formData["programCourse"],
        $formData["yearLevel"],
        $formData["schoolYear"],
        $formData["semester"],
        $formData["permanentAddress"],
        $formData["gender"],
        $age,
        $dateOfBirthForStorage,
        $formData["contactNumber"],
        $formData["emailAddress"],
        $estimatedIncome,
        $formData["motherName"],
        $formData["motherContact"],
        $formData["motherCompanyName"],
        $formData["motherCompanyAddress"],
        $motherAge,
        $formData["motherOccupation"],
        $formData["fatherName"],
        $formData["fatherContact"],
        $formData["fatherCompanyName"],
        $formData["fatherCompanyAddress"],
        $fatherAge,
        $formData["fatherOccupation"],
        $nextStatus,
        $applicationId
      );

      if (!$updateStmt->execute()) {
        $errors[] = "Failed to update the application details.";
      }
      $updateStmt->close();

      if (empty($errors) && $grantRequiresUploads) {
        $uploadDir = applicationUploadDirectory((int)$application["id"]);
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
          $errors[] = "Failed to create the upload directory.";
        } else {
          foreach ($requirements as $index => $requirementLabel) {
            $uploadError = $files["error"][$index] ?? UPLOAD_ERR_NO_FILE;
            if ($uploadError !== UPLOAD_ERR_OK) {
              continue;
            }

            $tmpName = (string)($files["tmp_name"][$index] ?? "");
            $originalName = (string)($files["name"][$index] ?? "");
            $mimeType = (string)($files["type"][$index] ?? "");
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $storedFileName = uniqid("req_", true) . "." . $ext;
            $storedPath = $uploadDir . DIRECTORY_SEPARATOR . $storedFileName;

            if (!move_uploaded_file($tmpName, $storedPath)) {
              $errors[] = "Failed to save the file for " . $requirementLabel . ".";
              continue;
            }

            $relativePath = storedUploadDbPath((int)$application["id"], $storedFileName);
            $existingUpload = $uploadsByLabel[$requirementLabel][0] ?? null;

            if ($existingUpload) {
              $updateUploadStmt = $conn->prepare(
                "UPDATE application_uploads
                 SET original_file_name = ?, stored_file_name = ?, stored_path = ?, mime_type = ?, uploaded_at = NOW()
                 WHERE id = ?"
              );

              if (!$updateUploadStmt) {
                $errors[] = "Failed to prepare the upload update for " . $requirementLabel . ".";
                continue;
              }

              $uploadId = (int)($existingUpload["id"] ?? 0);
              $updateUploadStmt->bind_param(
                "ssssi",
                $originalName,
                $storedFileName,
                $relativePath,
                $mimeType,
                $uploadId
              );

              if (!$updateUploadStmt->execute()) {
                $errors[] = "Failed to update the upload record for " . $requirementLabel . ".";
              } else {
                $oldAbsolutePath = resolveStoredUploadPath((string)($existingUpload["stored_path"] ?? ""));
                if ($oldAbsolutePath !== "" && is_file($oldAbsolutePath)) {
                  @unlink($oldAbsolutePath);
                }
              }

              $updateUploadStmt->close();
            } else {
              $insertUploadStmt = $conn->prepare(
                "INSERT INTO application_uploads (
                  application_id, requirement_label, original_file_name, stored_file_name, stored_path, mime_type, uploaded_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())"
              );

              if (!$insertUploadStmt) {
                $errors[] = "Failed to prepare the upload insert for " . $requirementLabel . ".";
                continue;
              }

              $applicationId = (int)$application["id"];
              $insertUploadStmt->bind_param(
                "isssss",
                $applicationId,
                $requirementLabel,
                $originalName,
                $storedFileName,
                $relativePath,
                $mimeType
              );

              if (!$insertUploadStmt->execute()) {
                $errors[] = "Failed to save the upload record for " . $requirementLabel . ".";
              }

              $insertUploadStmt->close();
            }
          }
        }
      }

      if (empty($errors)) {
        $updateStatus = $isReapplySubmission ? "reapplied" : "updated";
        header("Location: tracking-dashboard.php?reference=" . urlencode($referenceNumber) . "&update_status=" . urlencode($updateStatus));
        exit;
      }
    }
  }
}

$grantId = $application ? (int)($application["grant_id"] ?? 0) : 0;
$grantTitle = $grantNames[$grantId] ?? "Scholarship Application";
$requirementList = $grantRequirements[$grantId] ?? [];
$showStudentAssistantProgramSelect = updateUsesStudentAssistantPrograms($grantId, (string)($formData["scholarshipType"] ?? ""));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Update Submission</title>
  <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { font-family: "IBM Plex Sans", sans-serif; background: linear-gradient(180deg, #e8f3ff 0%, #f8fbff 38%, #eef6ff 100%); }

    .top-brand,
    .top-brand * {
      font-family: sans-serif;
    }
  </style>
</head>
<body class="min-h-screen text-[#052c6a]">
  <header class="top-brand sticky top-0 z-20 bg-gradient-to-r from-[#052c6a] via-[#0d8ddb] to-[#1d4ed8] shadow-md">
    <div class="w-full flex items-center gap-3 px-4 sm:px-6 lg:px-10 py-3">
      <div class="flex items-center justify-center">
        <img
          src="../img/SMCCNEWLOGO.png"
          alt="SMCC Logo"
          class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover bg-white shadow-md border border-white"
        />
      </div>

      <div class="flex-1 min-w-0">
        <p class="text-[10px] leading-4 sm:text-xs text-blue-100 uppercase tracking-[0.12em] sm:tracking-[0.18em]">
          SMCC Admission and Scholarship Office
        </p>
        <div class="mt-1 flex flex-wrap items-center gap-1.5 sm:gap-2">
          <h1 class="text-white text-sm sm:text-base font-semibold leading-tight">
            Institutional Scholarship Grants
          </h1>
          <span class="inline-flex max-w-full items-center gap-1 rounded-full bg-white/10 px-2 py-[2px] text-[10px] sm:text-[11px] text-blue-50">
            Update Submission
          </span>
        </div>
        <p class="mt-1 text-[10px] leading-4 sm:text-xs text-blue-100">
          Edit pending application details and uploaded requirements.
        </p>
      </div>
    </div>
  </header>

  <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between gap-3 text-sm text-[#052c6a]/75">
      <a href="tracking-dashboard.php?reference=<?php echo urlencode($referenceNumber); ?>" class="inline-flex items-center gap-2 hover:text-[#0d8ddb] hover:underline">
        <span>&larr;</span><span>Back to Tracking Dashboard</span>
      </a>
      <?php if ($application): ?>
        <span class="rounded-full border border-[#d6e5ff] bg-white px-4 py-2 font-semibold">Reference: <?php echo htmlspecialchars($referenceNumber); ?></span>
      <?php endif; ?>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">
        <?php foreach ($errors as $error): ?>
          <div><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$application): ?>
      <section class="rounded-[2rem] border border-[#cfe2ff] bg-white px-6 py-10 shadow-sm">
        <h2 class="text-2xl font-extrabold text-[#052c6a]">Application not available</h2>
        <p class="mt-3 max-w-xl text-sm leading-6 text-[#052c6a]/80">The reference number is missing or invalid. Open the applicant portal or the tracking dashboard and try again.</p>
      </section>
    <?php elseif (!$submissionUpdatesAllowed): ?>
      <section class="rounded-[2rem] border border-[#cfe2ff] bg-white px-6 py-10 shadow-sm">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#0d8ddb]">Submission Locked</p>
        <h2 class="mt-2 text-2xl font-extrabold text-[#052c6a]">Update submission is no longer available</h2>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-[#052c6a]/80">
          <?php echo htmlspecialchars($submissionUpdateLockMessage); ?> You may still review your application details from the tracking dashboard.
        </p>
        <div class="mt-5 flex flex-col gap-2 sm:flex-row">
          <a
            href="tracking-dashboard.php?reference=<?php echo urlencode($referenceNumber); ?>"
            class="inline-flex items-center justify-center rounded-full bg-[#052c6a] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#0d8ddb]"
          >
            Back to Tracking Dashboard
          </a>
        </div>
      </section>
    <?php else: ?>
      <section class="rounded-[2rem] border border-[#cfe2ff] bg-white px-5 py-6 shadow-sm sm:px-6 lg:px-8">
        <div class="rounded-[1.75rem] bg-gradient-to-br from-[#052c6a] via-[#0d8ddb] to-[#38bdf8] px-6 py-6 text-white">
          <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-blue-100"><?php echo htmlspecialchars($isReapplySubmission ? "Application Reapply" : "Submission Update"); ?></p>
          <h2 class="mt-2 text-2xl font-extrabold"><?php echo htmlspecialchars((string)($application["applicant_name"] ?? "Applicant")); ?></h2>
          <p class="mt-2 text-sm leading-6 text-blue-50"><?php echo htmlspecialchars($isReapplySubmission ? "Edit the rejected application below, replace any document that needs correction, and submit it again for admin review." : "Review the saved form below, correct any missing details, and replace any document that needs a clearer copy."); ?></p>
        </div>

        <form action="update-submission.php" method="post" enctype="multipart/form-data" class="mt-6 space-y-8">
          <input type="hidden" name="reference" value="<?php echo htmlspecialchars($referenceNumber); ?>" />
          <input type="hidden" name="scholarshipType" value="<?php echo htmlspecialchars((string)$formData["scholarshipType"]); ?>" />
          <?php if ((string)$formData["scholarshipType"] !== "Kabayani"): ?>
            <input type="hidden" name="kabayaniSpecify" value="<?php echo htmlspecialchars((string)$formData["kabayaniSpecify"]); ?>" />
          <?php endif; ?>
          <?php if ((string)$formData["scholarshipType"] !== "Others"): ?>
            <input type="hidden" name="othersSpecify" value="<?php echo htmlspecialchars((string)$formData["othersSpecify"]); ?>" />
          <?php endif; ?>

          <section class="rounded-[1.75rem] border border-[#d9e7ff] bg-[#f8fbff] px-5 py-5">
            <h3 class="text-lg font-semibold text-[#052c6a]">Application Summary</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
              <div class="rounded-xl border border-[#d6e5ff] bg-white px-4 py-3"><p class="text-xs uppercase tracking-[0.16em] text-[#0d8ddb]">Grant / Discount</p><p class="mt-2 text-sm font-semibold"><?php echo htmlspecialchars($grantTitle); ?></p></div>
              <div class="rounded-xl border border-[#d6e5ff] bg-white px-4 py-3"><p class="text-xs uppercase tracking-[0.16em] text-[#0d8ddb]">Scholarship Type</p><p class="mt-2 text-sm font-semibold"><?php echo htmlspecialchars((string)$formData["scholarshipType"]); ?></p></div>
              <div class="rounded-xl border border-[#d6e5ff] bg-white px-4 py-3"><p class="text-xs uppercase tracking-[0.16em] text-[#0d8ddb]">Current Status</p><p class="mt-2 text-sm font-semibold"><?php echo htmlspecialchars((string)($application["status"] !== "" ? $application["status"] : "Pending")); ?></p></div>
            </div>
            <?php if ((string)$formData["scholarshipType"] === "Kabayani"): ?>
              <div class="mt-4">
                <label class="mb-1 block text-sm font-semibold" for="kabayaniSpecify">Kabayani Specification</label>
                <input id="kabayaniSpecify" name="kabayaniSpecify" type="text" value="<?php echo htmlspecialchars((string)$formData["kabayaniSpecify"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" />
              </div>
            <?php endif; ?>
            <?php if ((string)$formData["scholarshipType"] === "Others"): ?>
              <div class="mt-4">
                <label class="mb-1 block text-sm font-semibold" for="othersSpecify">Other Grant / Discount</label>
                <input id="othersSpecify" name="othersSpecify" type="text" value="<?php echo htmlspecialchars((string)$formData["othersSpecify"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" />
              </div>
            <?php endif; ?>
          </section>

          <section class="rounded-[1.75rem] border border-[#d9e7ff] bg-white px-5 py-5">
            <h3 class="text-lg font-semibold text-[#052c6a]">Applicant Profile</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-semibold" for="applicantName">Name of Applicant</label>
                <input id="applicantName" name="applicantName" type="text" value="<?php echo htmlspecialchars((string)$formData["applicantName"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" required />
              </div>
              <div>
                <label class="mb-1 block text-sm font-semibold" for="programCourseSelect">Program / Course Enrolled</label>
                <select id="programCourseSelect" class="<?php echo $showStudentAssistantProgramSelect ? "" : "hidden "; ?>w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" <?php echo $showStudentAssistantProgramSelect ? 'name="programCourse" required' : 'disabled'; ?>>
                  <option value="">Select program / course</option>
                  <?php foreach ($studentAssistantPrograms as $programOption): ?>
                    <option value="<?php echo htmlspecialchars($programOption); ?>" <?php echo (string)$formData["programCourse"] === $programOption ? "selected" : ""; ?>><?php echo htmlspecialchars($programOption); ?></option>
                  <?php endforeach; ?>
                </select>
                <input id="programCourseInput" <?php echo $showStudentAssistantProgramSelect ? 'disabled' : 'name="programCourse" required'; ?> type="text" value="<?php echo htmlspecialchars((string)$formData["programCourse"]); ?>" class="<?php echo $showStudentAssistantProgramSelect ? "hidden " : ""; ?>w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" placeholder="Program / course" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-semibold" for="yearLevel">Year Level</label>
                <select id="yearLevel" name="yearLevel" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" required>
                  <option value="">Select year level</option>
                  <?php foreach ($yearLevelOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (string)$formData["yearLevel"] === $option ? "selected" : ""; ?>><?php echo htmlspecialchars($option); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-semibold" for="schoolYear">School Year</label>
                <select id="schoolYear" name="schoolYear" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" required>
                  <option value="">Select school year</option>
                  <?php foreach ($schoolYearOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (string)$formData["schoolYear"] === $option ? "selected" : ""; ?>><?php echo htmlspecialchars($option); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-semibold" for="semester">Semester</label>
                <select id="semester" name="semester" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" required>
                  <option value="">Select semester</option>
                  <?php foreach ($semesterOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (string)$formData["semester"] === $option ? "selected" : ""; ?>><?php echo htmlspecialchars($option); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-semibold" for="permanentAddress">Permanent Address</label>
                <textarea id="permanentAddress" name="permanentAddress" rows="3" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" required><?php echo htmlspecialchars((string)$formData["permanentAddress"]); ?></textarea>
              </div>
              <div>
                <label class="mb-1 block text-sm font-semibold" for="gender">Gender</label>
                <select id="gender" name="gender" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" required>
                  <option value="">Select gender</option>
                  <?php foreach ($genderOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (string)$formData["gender"] === $option ? "selected" : ""; ?>><?php echo htmlspecialchars($option); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-semibold" for="age">Age</label>
                <input id="age" name="age" type="number" min="0" value="<?php echo htmlspecialchars((string)$formData["age"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" required />
              </div>
              <div>
                <label class="mb-1 block text-sm font-semibold" for="dateOfBirth">Date of Birth</label>
                <input id="dateOfBirth" name="dateOfBirth" type="date" max="<?php echo htmlspecialchars(date('Y-m-d')); ?>" value="<?php echo htmlspecialchars((string)$formData["dateOfBirth"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" required />
              </div>
              <div>
                <label class="mb-1 block text-sm font-semibold" for="contactNumber">Contact Number</label>
                <input id="contactNumber" name="contactNumber" type="tel" maxlength="11" value="<?php echo htmlspecialchars((string)$formData["contactNumber"]); ?>" placeholder="09XXXXXXXXX" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" required />
              </div>
              <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-semibold" for="emailAddress">Email Address</label>
                <input id="emailAddress" name="emailAddress" type="email" value="<?php echo htmlspecialchars((string)$formData["emailAddress"]); ?>" placeholder="name@gmail.com" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" required />
              </div>
              <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-semibold" for="estimatedIncome">Estimated Gross Income of the Family per Month</label>
                <input id="estimatedIncome" name="estimatedIncome" type="number" min="0" value="<?php echo htmlspecialchars((string)$formData["estimatedIncome"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" required />
              </div>
            </div>
          </section>

          <section class="rounded-[1.75rem] border border-[#d9e7ff] bg-[#f8fbff] px-5 py-5">
            <h3 class="text-lg font-semibold text-[#052c6a]">Mother's Information</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <div><label class="mb-1 block text-sm font-semibold" for="motherName">Mother's Name</label><input id="motherName" name="motherName" type="text" value="<?php echo htmlspecialchars((string)$formData["motherName"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" required /></div>
              <div><label class="mb-1 block text-sm font-semibold" for="motherContact">Contact Number</label><input id="motherContact" name="motherContact" type="tel" maxlength="11" value="<?php echo htmlspecialchars((string)$formData["motherContact"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" /></div>
              <div><label class="mb-1 block text-sm font-semibold" for="motherOccupation">Occupation</label><input id="motherOccupation" name="motherOccupation" type="text" value="<?php echo htmlspecialchars((string)$formData["motherOccupation"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" /></div>
              <div><label class="mb-1 block text-sm font-semibold" for="motherCompanyName">Company's Name</label><input id="motherCompanyName" name="motherCompanyName" type="text" value="<?php echo htmlspecialchars((string)$formData["motherCompanyName"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" /></div>
              <div><label class="mb-1 block text-sm font-semibold" for="motherCompanyAddress">Company's Address</label><textarea id="motherCompanyAddress" name="motherCompanyAddress" rows="2" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20"><?php echo htmlspecialchars((string)$formData["motherCompanyAddress"]); ?></textarea></div>
              <div><label class="mb-1 block text-sm font-semibold" for="motherAge">Age</label><input id="motherAge" name="motherAge" type="number" min="0" value="<?php echo htmlspecialchars((string)$formData["motherAge"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" /></div>
            </div>
          </section>

          <section class="rounded-[1.75rem] border border-[#d9e7ff] bg-white px-5 py-5">
            <h3 class="text-lg font-semibold text-[#052c6a]">Father's Information</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <div><label class="mb-1 block text-sm font-semibold" for="fatherName">Father's Name</label><input id="fatherName" name="fatherName" type="text" value="<?php echo htmlspecialchars((string)$formData["fatherName"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" required /></div>
              <div><label class="mb-1 block text-sm font-semibold" for="fatherContact">Contact Number</label><input id="fatherContact" name="fatherContact" type="tel" maxlength="11" value="<?php echo htmlspecialchars((string)$formData["fatherContact"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" /></div>
              <div><label class="mb-1 block text-sm font-semibold" for="fatherOccupation">Occupation</label><input id="fatherOccupation" name="fatherOccupation" type="text" value="<?php echo htmlspecialchars((string)$formData["fatherOccupation"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" /></div>
              <div><label class="mb-1 block text-sm font-semibold" for="fatherCompanyName">Company's Name</label><input id="fatherCompanyName" name="fatherCompanyName" type="text" value="<?php echo htmlspecialchars((string)$formData["fatherCompanyName"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" /></div>
              <div><label class="mb-1 block text-sm font-semibold" for="fatherCompanyAddress">Company's Address</label><textarea id="fatherCompanyAddress" name="fatherCompanyAddress" rows="2" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20"><?php echo htmlspecialchars((string)$formData["fatherCompanyAddress"]); ?></textarea></div>
              <div><label class="mb-1 block text-sm font-semibold" for="fatherAge">Age</label><input id="fatherAge" name="fatherAge" type="number" min="0" value="<?php echo htmlspecialchars((string)$formData["fatherAge"]); ?>" class="w-full rounded-xl border border-[#0d8ddb]/60 px-3 py-2 text-sm focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" /></div>
            </div>
          </section>

          <section class="rounded-[1.75rem] border border-[#d9e7ff] bg-[#f8fbff] px-5 py-5">
            <h3 class="text-lg font-semibold text-[#052c6a]">Submitted Requirements</h3>
            <p class="mt-2 text-sm leading-6 text-[#052c6a]/80">Leave a file input empty if the existing document is still correct and readable. Upload a new file only for the requirement you want to replace.</p>
            <?php if (empty($requirementList)): ?>
              <div class="mt-4 rounded-xl border border-[#d6e5ff] bg-white px-4 py-4 text-sm text-[#052c6a]/80">This grant currently has no documentary requirements.</div>
            <?php else: ?>
              <div class="mt-4 space-y-4">
                <?php foreach ($requirementList as $index => $requirementLabel): ?>
                  <?php $currentUpload = $uploadsByLabel[$requirementLabel][0] ?? null; ?>
                  <div class="rounded-xl border border-[#d6e5ff] bg-white px-4 py-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                      <div>
                        <p class="text-sm font-semibold text-[#052c6a]"><?php echo htmlspecialchars($requirementLabel); ?></p>
                        <?php if ($currentUpload): ?>
                          <p class="mt-1 text-xs text-[#052c6a]/75">Current file: <span class="font-semibold"><?php echo htmlspecialchars((string)($currentUpload["original_file_name"] ?? "Uploaded document")); ?></span></p>
                          <div class="mt-2 flex flex-wrap gap-2">
                            <a href="application-upload.php?reference=<?php echo urlencode($referenceNumber); ?>&stored_path=<?php echo urlencode((string)($currentUpload["stored_path"] ?? "")); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded-full border border-[#0d8ddb] px-3 py-1 text-xs font-semibold text-[#0d8ddb] hover:bg-[#eff6ff]">View</a>
                            <a href="application-upload.php?reference=<?php echo urlencode($referenceNumber); ?>&stored_path=<?php echo urlencode((string)($currentUpload["stored_path"] ?? "")); ?>&mode=download" class="inline-flex items-center justify-center rounded-full border border-[#d6e5ff] px-3 py-1 text-xs font-semibold text-[#052c6a]/80 hover:border-[#0d8ddb] hover:text-[#0d8ddb]">Download</a>
                          </div>
                        <?php else: ?>
                          <p class="mt-1 text-xs font-semibold text-rose-600">No uploaded file found yet for this requirement.</p>
                        <?php endif; ?>
                      </div>
                      <div class="w-full lg:max-w-sm">
                        <input type="hidden" name="requirements[]" value="<?php echo htmlspecialchars($requirementLabel); ?>" />
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-[#0d8ddb]">Replace with new file</label>
                        <input type="file" name="files[]" accept=".pdf,.jpg,.jpeg,.png" class="block w-full rounded-xl border border-[#b7c7ff] px-3 py-2 text-xs sm:text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[#0d8ddb] file:px-3 file:py-1.5 file:text-xs file:text-white focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20" />
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-[#052c6a]/75"><?php echo htmlspecialchars($isReapplySubmission ? "Submitting this reapplication will keep the same reference number and mark the application as Reapplied for admin review." : "Saving changes will keep the same reference number and send the application back for admin review when it is not yet approved."); ?></p>
            <button type="submit" class="inline-flex items-center justify-center rounded-full bg-[#0d8ddb] px-6 py-3 text-sm font-semibold text-white shadow-md transition hover:-translate-y-[1px] hover:bg-[#0b63d1]"><?php echo htmlspecialchars($isReapplySubmission ? "Submit Reapplication" : "Save Updated Submission"); ?></button>
          </div>
        </form>
      </section>
    <?php endif; ?>
  </main>

  <script>
    const contactNumberInputs = document.querySelectorAll("#contactNumber, #motherContact, #fatherContact");
    contactNumberInputs.forEach((input) => {
      input.addEventListener("input", () => {
        const cleanedValue = input.value.replace(/\D/g, "").slice(0, 11);
        if (input.value !== cleanedValue) input.value = cleanedValue;
      });
    });
  </script>
</body>
</html>
