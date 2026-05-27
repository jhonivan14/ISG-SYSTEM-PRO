<?php
session_start();
require_once "../db.php";
require_once "../application-reference.php";
require_once "../upload-storage.php";
require_once "../scholarship-grants.php";

$errors = [];
$success = false;
$applicationId = null;
$referenceNumber = null;
$referenceWarning = "";
$requirements = [];
$requiredRequirements = [];
$files = null;
$allowedExt = ["pdf", "jpg", "jpeg", "png"];
$uploadDir = "";

function draft_value($draft, $key) {
  return isset($draft[$key]) ? $draft[$key] : "";
}

function draft_int($draft, $key) {
  return isset($draft[$key]) ? (int)$draft[$key] : 0;
}

function ensure_applications_department_column(mysqli $conn): void {
  $columnResult = $conn->query("SHOW COLUMNS FROM applications LIKE 'department'");
  $hasDepartmentColumn = $columnResult instanceof mysqli_result && $columnResult->num_rows > 0;
  if ($columnResult instanceof mysqli_result) {
    $columnResult->free();
  }

  if (!$hasDepartmentColumn) {
    $conn->query("ALTER TABLE applications ADD COLUMN department VARCHAR(100) DEFAULT NULL AFTER applicant_name");
  }
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
  } else {
    $selectedGrant = isg_get_scholarship_grant($conn, $grantId);
    if (!$selectedGrant) {
      $errors[] = "Invalid grant selection.";
    } else {
      $requiredRequirements = $selectedGrant["requirements"] ?? [];
    }
  }
}

$grantRequiresUploads = !empty($requiredRequirements);

if (empty($errors)) {
  ensure_applications_department_column($conn);

  $scholarshipType = draft_value($draft, "scholarship_type");
  $kabayaniSpecify = draft_value($draft, "kabayani_specify");
  $othersSpecify = draft_value($draft, "others_specify");
  $applicantName = draft_value($draft, "applicant_name");
  $department = draft_value($draft, "department");
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

  $duplicateSql = "
    SELECT id, reference_number
    FROM applications
    WHERE LOWER(TRIM(email_address)) = LOWER(TRIM(?))
      AND TRIM(COALESCE(school_year, '')) = ?
      AND TRIM(COALESCE(semester, '')) = ?
    LIMIT 1
  ";
  $duplicateStmt = $conn->prepare($duplicateSql);

  if ($duplicateStmt) {
    $duplicateStmt->bind_param("sss", $emailAddress, $schoolYear, $semester);
    if ($duplicateStmt->execute()) {
      $duplicateResult = $duplicateStmt->get_result();
      $duplicateApplication = $duplicateResult instanceof mysqli_result ? $duplicateResult->fetch_assoc() : null;

      if ($duplicateApplication) {
        $duplicateReference = trim((string)($duplicateApplication["reference_number"] ?? ""));
        $displaySchoolYear = trim((string)$schoolYear) !== "" ? "S.Y. " . trim((string)$schoolYear) : "the selected school year";
        $displaySemester = trim((string)$semester) !== "" ? trim((string)$semester) : "the selected semester";
        $duplicateMessage = "You already have an application for " . $displaySemester . ", " . $displaySchoolYear . ". Only 1 application per semester is allowed.";
        if ($duplicateReference !== "") {
          $duplicateMessage .= " Please use your reference number " . $duplicateReference . " to track or update your application.";
        } else {
          $duplicateMessage .= " Please contact the scholarship office if you need to update your application.";
        }
        $errors[] = $duplicateMessage;
      }

      if ($duplicateResult instanceof mysqli_result) {
        $duplicateResult->free();
      }
    } else {
      $errors[] = "Failed to check existing applications.";
    }

    $duplicateStmt->close();
  } else {
    $errors[] = "Failed to prepare duplicate application check.";
  }
}

if (empty($errors)) {
  $sql = "INSERT INTO applications (
    grant_id,
    scholarship_type,
    kabayani_specify,
    others_specify,
    applicant_name,
    department,
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
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
  )";

  if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param(
      "isssssssssssisssissssisssssis",
      $grantId,
      $scholarshipType,
      $kabayaniSpecify,
      $othersSpecify,
      $applicantName,
      $department,
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
      if ($applicationId > 0) {
        $referenceNumber = assignApplicationReference($conn, $applicationId);
        if ($referenceNumber === null) {
          $referenceWarning = "Your application was saved, but the reference number is temporarily unavailable.";
        }
      }
    } else {
      $errors[] = "Failed to save application.";
    }

    $stmt->close();
  } else {
    $errors[] = "Failed to prepare database statement.";
  }
}

if (empty($errors) && $grantRequiresUploads) {
  $requirements = $requiredRequirements;
  $files = $_FILES["files"] ?? null;

  if (!$files || !isset($files["name"]) || !is_array($files["name"])) {
    $errors[] = "Missing uploaded files.";
  } elseif (count($files["name"]) < count($requirements)) {
    $errors[] = "Please upload one file for each required document.";
  } else {
    $uploadDir = applicationUploadDirectory($applicationId);

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
    $fileCount = count($requirements);

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

      $relativePath = storedUploadDbPath($applicationId, $storedFileName);

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
      <?php if ($referenceNumber !== null): ?>
        <div class="mt-4 rounded-2xl border border-[#c7dcff] bg-[#eff6ff] px-4 py-4 text-left">
          <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#0d8ddb]">
            Application Reference Number
          </p>
          <p class="mt-2 text-lg font-extrabold tracking-wide text-[#052c6a]">
            <?php echo htmlspecialchars($referenceNumber); ?>
          </p>
          <p class="mt-2 text-xs text-[#052c6a]/75">
            Save this reference number. You can use it on the applicant tracking page to check your application status.
          </p>
        </div>
      <?php elseif ($referenceWarning !== ""): ?>
        <div class="mt-4 rounded-2xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-left text-xs text-yellow-800">
          <?php echo htmlspecialchars($referenceWarning); ?>
        </div>
      <?php endif; ?>
      <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-center">
        <?php if ($referenceNumber !== null): ?>
          <a
            href="tracking-dashboard.php?reference=<?php echo urlencode($referenceNumber); ?>"
            class="inline-flex items-center justify-center px-5 py-2 rounded-full border border-[#0d8ddb] text-[#0d8ddb] text-sm font-semibold hover:bg-[#eff6ff]"
          >
            Track Application
          </a>
        <?php endif; ?>
        <a
          href="../index.php"
          class="inline-flex items-center justify-center px-5 py-2 rounded-full bg-[#0d8ddb] text-white text-sm font-semibold hover:bg-[#0b63d1]"
        >
          Back to homepage
        </a>
      </div>
    <?php else: ?>
      <h1 class="text-xl font-bold text-red-600">Submission Failed</h1>
      <div class="mt-3 text-left text-xs sm:text-sm text-[#052c6a] space-y-1">
        <?php foreach ($errors as $error): ?>
          <div><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
      </div>
      <a
        href="../index.php"
        class="mt-4 inline-flex items-center justify-center px-5 py-2 rounded-full bg-[#0d8ddb] text-white text-sm font-semibold hover:bg-[#0b63d1]"
      >
        Back to homepage
      </a>
    <?php endif; ?>
  </div>
</body>
</html>
