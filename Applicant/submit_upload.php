<?php
session_start();
require_once "../db.php";

$errors = [];
$success = false;
$applicationId = null;

function draft_value($draft, $key) {
  return isset($draft[$key]) ? $draft[$key] : "";
}

function draft_int($draft, $key) {
  return isset($draft[$key]) ? (int)$draft[$key] : 0;
}

$draft = $_SESSION["application_draft"] ?? null;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  $errors[] = "Invalid request.";
} elseif (empty($draft)) {
  $errors[] = "Missing application draft. Please complete Step 2 first.";
}

if (empty($errors)) {
  $grantId = draft_int($draft, "grant_id");
  if ($grantId <= 0) {
    $errors[] = "Invalid grant selection.";
  }
}

$noRequirementGrantIds = [15];
$grantRequiresUploads = !in_array($grantId ?? 0, $noRequirementGrantIds, true);

if (empty($errors)) {
  $sql = "INSERT INTO applications (
    grant_id,
    scholarship_type,
    kabayani_specify,
    others_specify,
    applicant_name,
    program_course,
    year_level,
    school_year,
    semester,
    permanent_address,
    gender,
    age,
    date_of_birth,
    contact_number,
    email_address,
    estimated_income,
    mother_name,
    mother_contact,
    mother_company_name,
    mother_company_address,
    mother_age,
    mother_occupation,
    father_name,
    father_contact,
    father_company_name,
    father_company_address,
    father_age,
    father_occupation,
    created_at
  ) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
  )";

  if ($stmt = $conn->prepare($sql)) {
    $scholarshipType = draft_value($draft, "scholarship_type");
    $kabayaniSpecify = draft_value($draft, "kabayani_specify");
    $othersSpecify = draft_value($draft, "others_specify");
    $applicantName = draft_value($draft, "applicant_name");
    $programCourse = draft_value($draft, "program_course");
    $yearLevel = draft_value($draft, "year_level");
    $schoolYear = draft_value($draft, "school_year");
    $semester = draft_value($draft, "semester");
    $permanentAddress = draft_value($draft, "permanent_address");
    $gender = draft_value($draft, "gender");
    $age = draft_int($draft, "age");
    $dateOfBirth = draft_value($draft, "date_of_birth");
    $contactNumber = draft_value($draft, "contact_number");
    $emailAddress = draft_value($draft, "email_address");
    $estimatedIncome = draft_int($draft, "estimated_income");
    $motherName = draft_value($draft, "mother_name");
    $motherContact = draft_value($draft, "mother_contact");
    $motherCompanyName = draft_value($draft, "mother_company_name");
    $motherCompanyAddress = draft_value($draft, "mother_company_address");
    $motherAge = draft_int($draft, "mother_age");
    $motherOccupation = draft_value($draft, "mother_occupation");
    $fatherName = draft_value($draft, "father_name");
    $fatherContact = draft_value($draft, "father_contact");
    $fatherCompanyName = draft_value($draft, "father_company_name");
    $fatherCompanyAddress = draft_value($draft, "father_company_address");
    $fatherAge = draft_int($draft, "father_age");
    $fatherOccupation = draft_value($draft, "father_occupation");

    $stmt->bind_param(
      "issssssssssisssissssisssssis",
      $grantId,
      $scholarshipType,
      $kabayaniSpecify,
      $othersSpecify,
      $applicantName,
      $programCourse,
      $yearLevel,
      $schoolYear,
      $semester,
      $permanentAddress,
      $gender,
      $age,
      $dateOfBirth,
      $contactNumber,
      $emailAddress,
      $estimatedIncome,
      $motherName,
      $motherContact,
      $motherCompanyName,
      $motherCompanyAddress,
      $motherAge,
      $motherOccupation,
      $fatherName,
      $fatherContact,
      $fatherCompanyName,
      $fatherCompanyAddress,
      $fatherAge,
      $fatherOccupation
    );

    if ($stmt->execute()) {
      $applicationId = $stmt->insert_id;
    } else {
      $errors[] = "Failed to save application.";
    }

    $stmt->close();
  } else {
    $errors[] = "Failed to prepare database statement.";
  }
}

if (empty($errors) && $grantRequiresUploads) {
  $requirements = $_POST["requirements"] ?? [];
  $files = $_FILES["files"] ?? null;
  $allowedExt = ["pdf", "jpg", "jpeg", "png"];

  if (!$files || !isset($files["name"]) || !is_array($files["name"])) {
    $errors[] = "Missing uploaded files.";
  } else {
    $uploadBase = realpath(__DIR__ . "/..") . DIRECTORY_SEPARATOR . "uploads";
    $uploadDir = $uploadBase . DIRECTORY_SEPARATOR . "applications" . DIRECTORY_SEPARATOR . $applicationId;

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
      $errors[] = "Failed to create upload directory.";
    }
  }
}

if (empty($errors) && $grantRequiresUploads) {
  $uploadSql = "INSERT INTO application_uploads (
    application_id,
    requirement_label,
    original_file_name,
    stored_file_name,
    stored_path,
    mime_type,
    uploaded_at
  ) VALUES (?, ?, ?, ?, ?, ?, NOW())";

  $uploadStmt = $conn->prepare($uploadSql);

  if (!$uploadStmt) {
    $errors[] = "Failed to prepare upload statement.";
  } else {
    $fileCount = count($files["name"]);

    for ($i = 0; $i < $fileCount; $i++) {
      $error = $files["error"][$i] ?? UPLOAD_ERR_NO_FILE;
      $tmpName = $files["tmp_name"][$i] ?? "";
      $originalName = $files["name"][$i] ?? "";
      $mimeType = $files["type"][$i] ?? "";
      $requirementLabel = $requirements[$i] ?? "Document";

      if ($error !== UPLOAD_ERR_OK) {
        $errors[] = "Upload failed for " . $requirementLabel . ".";
        continue;
      }

      $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
      if (!in_array($ext, $allowedExt, true)) {
        $errors[] = "Invalid file type for " . $requirementLabel . ".";
        continue;
      }

      $storedFileName = uniqid("req_", true) . "." . $ext;
      $storedPath = $uploadDir . DIRECTORY_SEPARATOR . $storedFileName;

      if (!move_uploaded_file($tmpName, $storedPath)) {
        $errors[] = "Failed to save file for " . $requirementLabel . ".";
        continue;
      }

      $relativePath = "uploads/applications/" . $applicationId . "/" . $storedFileName;

      $uploadStmt->bind_param(
        "isssss",
        $applicationId,
        $requirementLabel,
        $originalName,
        $storedFileName,
        $relativePath,
        $mimeType
      );

      if (!$uploadStmt->execute()) {
        $errors[] = "Failed to save file record for " . $requirementLabel . ".";
      }
    }

    $uploadStmt->close();
  }
}

if (empty($errors)) {
  unset($_SESSION["application_draft"]);
  $success = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Submission Status</title>
  <script src="https://cdn.tailwindcss.com"></script>
  
</head>
<body class="min-h-screen bg-gradient-to-b from-[#e0f2ff] via-white to-[#e0f2ff] flex items-center justify-center px-4">
  <div class="max-w-md w-full bg-white rounded-2xl shadow-lg border border-[#cddfff] p-6 text-center">
    <?php if ($success): ?>
      <h1 class="text-xl font-bold text-[#052c6a]">Submission Complete</h1>
      <p class="mt-2 text-sm text-[#052c6a]/80">
        Your application and documents have been submitted successfully.
      </p>
      <a
        href="../index.php"
        class="mt-4 inline-flex items-center justify-center px-5 py-2 rounded-full bg-[#0d8ddb] text-white text-sm font-semibold hover:bg-[#0b63d1]"
      >
        Back to homepage
      </a>
    <?php else: ?>
      <h1 class="text-xl font-bold text-red-600">Submission Failed</h1>
      <div class="mt-3 text-left text-xs sm:text-sm text-[#052c6a] space-y-1">
        <?php foreach ($errors as $error): ?>
          <div><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
      </div>
      <a
        href="applicationReq.php"
        class="mt-4 inline-flex items-center justify-center px-5 py-2 rounded-full bg-[#0d8ddb] text-white text-sm font-semibold hover:bg-[#0b63d1]"
      >
        Back to Step 2
      </a>
    <?php endif; ?>
  </div>
</body>
</html>
