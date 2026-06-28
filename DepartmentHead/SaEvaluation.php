<?php
require_once __DIR__ . "/head-auth.php";
headRequireLogin();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once "../db.php";
date_default_timezone_set("Asia/Manila");

$headUsername = trim((string)($_SESSION["head_username"] ?? ""));
$headName = trim((string)($_SESSION["head_name"] ?? ""));
if ($headName === "") {
  $headName = "Head of Office";
}
$headOffice = trim((string)($_SESSION["head_office"] ?? ""));

$applicationId = isset($_GET["application_id"])
  ? (int)$_GET["application_id"]
  : (int)($_POST["application_id"] ?? 0);
if ($applicationId <= 0 && isset($_GET["id"])) {
  $applicationId = (int)$_GET["id"];
}
$scholarRecordId = $applicationId !== 0 ? abs($applicationId) : 0;

$loadError = "";
$applicationProfile = [
  "id" => 0,
  "applicant_name" => "",
  "semester" => "",
  "school_year" => "",
  "assigned_office" => "",
];
$ratingGroups = [
  "a" => ["score-a1", "score-a2", "score-a3", "score-a4", "score-a5"],
  "b" => ["score-b1", "score-b2", "score-b3", "score-b4", "score-b5"],
  "c" => ["score-c1", "score-c2", "score-c3", "score-c4", "score-c5"],
];
$ratingFieldNames = array_merge($ratingGroups["a"], $ratingGroups["b"], $ratingGroups["c"]);
$formRatings = [];
foreach ($ratingFieldNames as $fieldName) {
  $formRatings[$fieldName] = null;
}
$strengthsInput = "";
$areasImprovementInput = "";
$recommendationsInput = "";
$signatureDataInput = "";
$saveSuccess = "";
$saveError = "";
$evaluationDateValue = date("Y-m-d");
$hasEvaluationTable = false;
$isEvaluationWindowOpen = false;

function saBuildHeadDisplayName(string $firstName, string $lastName, string $fallback = ""): string
{
  $parts = [];
  $firstName = trim($firstName);
  $lastName = trim($lastName);
  if ($firstName !== "") {
    $parts[] = $firstName;
  }
  if ($lastName !== "") {
    $parts[] = $lastName;
  }
  $fullName = trim(implode(" ", $parts));
  return $fullName !== "" ? $fullName : trim($fallback);
}

function saEnsureDepartmentHeadEvaluationTable(mysqli $conn): bool
{
  $createTableSql = "CREATE TABLE IF NOT EXISTS department_head_evaluations (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    applicant_name VARCHAR(255) NOT NULL,
    semester VARCHAR(50) DEFAULT NULL,
    school_year VARCHAR(50) DEFAULT NULL,
    assigned_office VARCHAR(120) DEFAULT NULL,
    head_username VARCHAR(100) NOT NULL,
    head_name VARCHAR(150) NOT NULL,
    evaluation_date DATE NOT NULL,
    ratings_json LONGTEXT NOT NULL,
    section_a_total INT NOT NULL DEFAULT 0,
    section_b_total INT NOT NULL DEFAULT 0,
    section_c_total INT NOT NULL DEFAULT 0,
    overall_total INT NOT NULL DEFAULT 0,
    strengths TEXT DEFAULT NULL,
    areas_improvement TEXT DEFAULT NULL,
    recommendations TEXT DEFAULT NULL,
    signature_data LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_application_head_term (application_id, head_username, semester, school_year)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

  if ($conn->query($createTableSql) !== true) {
    return false;
  }

  $indexColumns = [];
  $indexResult = $conn->query("SHOW INDEX FROM department_head_evaluations");
  if ($indexResult instanceof mysqli_result) {
    while ($indexRow = $indexResult->fetch_assoc()) {
      $keyName = trim((string)($indexRow["Key_name"] ?? ""));
      if ($keyName === "") {
        continue;
      }
      $sequence = (int)($indexRow["Seq_in_index"] ?? 0);
      $columnName = trim((string)($indexRow["Column_name"] ?? ""));
      if ($sequence > 0 && $columnName !== "") {
        if (!isset($indexColumns[$keyName])) {
          $indexColumns[$keyName] = [];
        }
        $indexColumns[$keyName][$sequence] = $columnName;
      }
    }
    $indexResult->free();
  }

  if (isset($indexColumns["uniq_application_head"])) {
    ksort($indexColumns["uniq_application_head"]);
    $legacyColumns = array_values($indexColumns["uniq_application_head"]);
    if ($legacyColumns === ["application_id", "head_username"]) {
      if ($conn->query("ALTER TABLE department_head_evaluations DROP INDEX uniq_application_head") !== true) {
        return false;
      }
      unset($indexColumns["uniq_application_head"]);
    }
  }

  if (!isset($indexColumns["uniq_application_head_term"])) {
    $addIndexSql = "ALTER TABLE department_head_evaluations
      ADD UNIQUE KEY uniq_application_head_term (application_id, head_username, semester, school_year)";
    if ($conn->query($addIndexSql) !== true) {
      return false;
    }
  }

  $aiColResult = $conn->query("SHOW COLUMNS FROM department_head_evaluations LIKE 'areas_improvement'");
  $hasAiCol = $aiColResult instanceof mysqli_result && $aiColResult->num_rows > 0;
  if ($aiColResult instanceof mysqli_result) { $aiColResult->free(); }
  if (!$hasAiCol) {
    $conn->query("ALTER TABLE department_head_evaluations ADD COLUMN areas_improvement TEXT DEFAULT NULL AFTER strengths");
  }

  return true;
}

function saEnsureDepartmentEvaluationWindowTable(mysqli $conn): bool
{
  $createWindowTableSql = "CREATE TABLE IF NOT EXISTS department_evaluation_window (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    school_year VARCHAR(20) NOT NULL DEFAULT '',
    semester VARCHAR(50) NOT NULL DEFAULT '',
    is_open TINYINT(1) NOT NULL DEFAULT 0,
    opened_at DATETIME DEFAULT NULL,
    opened_by VARCHAR(100) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_dew_term (school_year, semester)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  if ($conn->query($createWindowTableSql) !== true) {
    return false;
  }

  $columnDefinitions = [
    "school_year" => "VARCHAR(20) NOT NULL DEFAULT '' AFTER id",
    "semester" => "VARCHAR(50) NOT NULL DEFAULT '' AFTER school_year",
    "is_open" => "TINYINT(1) NOT NULL DEFAULT 0 AFTER semester",
    "opened_at" => "DATETIME DEFAULT NULL AFTER is_open",
    "opened_by" => "VARCHAR(100) DEFAULT NULL AFTER opened_at",
    "updated_at" => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER opened_by",
  ];
  foreach ($columnDefinitions as $column => $definition) {
    $columnResult = $conn->query("SHOW COLUMNS FROM department_evaluation_window LIKE '" . $conn->real_escape_string($column) . "'");
    $exists = $columnResult instanceof mysqli_result && $columnResult->num_rows > 0;
    if ($columnResult instanceof mysqli_result) {
      $columnResult->free();
    }
    if (!$exists) {
      $conn->query("ALTER TABLE department_evaluation_window ADD COLUMN $column $definition");
    }
  }
  $conn->query("ALTER TABLE department_evaluation_window MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT");

  $indexResult = $conn->query("SHOW INDEX FROM department_evaluation_window WHERE Key_name = 'uniq_dew_term'");
  $hasTermIndex = $indexResult instanceof mysqli_result && $indexResult->num_rows > 0;
  if ($indexResult instanceof mysqli_result) {
    $indexResult->free();
  }
  if (!$hasTermIndex) {
    $conn->query("CREATE UNIQUE INDEX uniq_dew_term ON department_evaluation_window (school_year, semester)");
  }

  return true;
}

function saIsEvaluationWindowOpenForTerm(mysqli $conn, string $schoolYear, string $semester): bool
{
  $schoolYear = trim($schoolYear);
  $semester = trim($semester);
  if ($schoolYear === "" || $semester === "" || !saEnsureDepartmentEvaluationWindowTable($conn)) {
    return false;
  }

  $stmt = $conn->prepare("SELECT is_open FROM department_evaluation_window WHERE school_year = ? AND semester = ? LIMIT 1");
  if (!$stmt) {
    return false;
  }

  $stmt->bind_param("ss", $schoolYear, $semester);
  $isOpen = false;
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if (is_array($row)) {
      $isOpen = ((int)($row["is_open"] ?? 0)) === 1;
    }
    if ($result instanceof mysqli_result) {
      $result->free();
    }
  }
  $stmt->close();

  return $isOpen;
}

if ($headUsername !== "") {
  $headAccountStmt = $conn->prepare("SELECT name, lastname, office FROM head_offices WHERE username = ? AND status = 'active' LIMIT 1");
  if ($headAccountStmt) {
    $headAccountStmt->bind_param("s", $headUsername);
    if ($headAccountStmt->execute()) {
      $headAccountResult = $headAccountStmt->get_result();
      $headAccountRow = $headAccountResult ? $headAccountResult->fetch_assoc() : null;
      if (is_array($headAccountRow)) {
        $resolvedHeadName = saBuildHeadDisplayName(
          (string)($headAccountRow["name"] ?? ""),
          (string)($headAccountRow["lastname"] ?? ""),
          $headName !== "" ? $headName : $headUsername
        );
        if ($resolvedHeadName !== "") {
          $headName = $resolvedHeadName;
          $_SESSION["head_name"] = $headName;
        }

        $resolvedHeadOffice = trim((string)($headAccountRow["office"] ?? ""));
        if ($resolvedHeadOffice !== "") {
          $headOffice = $resolvedHeadOffice;
          $_SESSION["head_office"] = $headOffice;
        }
      }
      if ($headAccountResult instanceof mysqli_result) {
        $headAccountResult->free();
      }
    }
    $headAccountStmt->close();
  }
}

function saLoadActiveScholarRecord(mysqli $conn, int $recordId, string $officeKey): ?array
{
  $sql = "
    SELECT
      id,
      full_name,
      semester,
      academic_year,
      assigned_office
    FROM institutional_scholar_records
    WHERE id = ?
      AND (
        LOWER(TRIM(COALESCE(category, ''))) = 'student_assistant'
        OR (
          LOWER(TRIM(COALESCE(category, ''))) = 'official'
          AND LOWER(TRIM(COALESCE(grant_applied, ''))) LIKE '%assistant%'
        )
      )
      AND COALESCE(contract_ended, 0) = 0
      AND LOWER(TRIM(COALESCE(assigned_office, ''))) = ?
    ORDER BY
      CASE
        WHEN LOWER(TRIM(COALESCE(category, ''))) = 'official'
          AND LOWER(TRIM(COALESCE(grant_applied, ''))) LIKE '%assistant%'
        THEN 0
        ELSE 1
      END,
      id DESC
    LIMIT 1
  ";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return null;
  }

  $stmt->bind_param("is", $recordId, $officeKey);
  $row = null;
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($result instanceof mysqli_result) {
      $result->free();
    }
  }
  $stmt->close();

  return is_array($row) ? $row : null;
}

if ($applicationId === 0) {
  $loadError = "No applicant selected.";
} elseif ($headOffice === "") {
  $loadError = "No office is assigned to this head account.";
} else {
  $headOfficeKey = strtolower(trim($headOffice));
  $scholarTableResult = $conn->query("SHOW TABLES LIKE 'institutional_scholar_records'");
  $hasScholarTable = $scholarTableResult instanceof mysqli_result && $scholarTableResult->num_rows > 0;
  if ($scholarTableResult instanceof mysqli_result) {
    $scholarTableResult->free();
  }
  if (!$hasScholarTable) {
    $loadError = "Scholar records table is not available.";
  } else {
    $resolvedScholarRow = saLoadActiveScholarRecord(
      $conn,
      $scholarRecordId,
      $headOfficeKey
    );
    if (is_array($resolvedScholarRow)) {
      $scholarRecordId = (int)($resolvedScholarRow["id"] ?? 0);
      $applicationId = 0 - $scholarRecordId;
      $applicationProfile = [
        "id" => $applicationId,
        "applicant_name" => trim((string)($resolvedScholarRow["full_name"] ?? "")),
        "semester" => trim((string)($resolvedScholarRow["semester"] ?? "")),
        "school_year" => trim((string)($resolvedScholarRow["academic_year"] ?? "")),
        "assigned_office" => trim((string)($resolvedScholarRow["assigned_office"] ?? "")),
      ];
    } else {
      $loadError = "Student assistant record not found for your office.";
    }
  }
}

if ($loadError === "" && ($conn ?? null) instanceof mysqli) {
  $isEvaluationWindowOpen = saIsEvaluationWindowOpenForTerm(
    $conn,
    (string)($applicationProfile["school_year"] ?? ""),
    (string)($applicationProfile["semester"] ?? "")
  );

  $hasEvaluationTable = saEnsureDepartmentHeadEvaluationTable($conn);
  if (!$hasEvaluationTable) {
    $saveError = "Unable to prepare evaluation storage.";
  }
}

if ($loadError === "" && $hasEvaluationTable) {
  $existingStmt = $conn->prepare(
    "SELECT ratings_json, strengths, areas_improvement, recommendations, signature_data, evaluation_date
     FROM department_head_evaluations
     WHERE application_id = ? AND head_username = ? AND semester = ? AND school_year = ?
     LIMIT 1"
  );
  if ($existingStmt) {
    $existingStmt->bind_param(
      "isss",
      $applicationId,
      $headUsername,
      $applicationProfile["semester"],
      $applicationProfile["school_year"]
    );
    if ($existingStmt->execute()) {
      $existingResult = $existingStmt->get_result();
      $existingRow = $existingResult ? $existingResult->fetch_assoc() : null;
      if (is_array($existingRow)) {
        $decodedRatings = json_decode((string)($existingRow["ratings_json"] ?? ""), true);
        if (is_array($decodedRatings)) {
          foreach ($formRatings as $fieldName => $unusedValue) {
            $ratingValue = isset($decodedRatings[$fieldName]) ? (int)$decodedRatings[$fieldName] : 0;
            if ($ratingValue >= 1 && $ratingValue <= 5) {
              $formRatings[$fieldName] = $ratingValue;
            }
          }
        }
        $strengthsInput = trim((string)($existingRow["strengths"] ?? ""));
        $areasImprovementInput = trim((string)($existingRow["areas_improvement"] ?? ""));
        $recommendationsInput = trim((string)($existingRow["recommendations"] ?? ""));
        $signatureDataInput = trim((string)($existingRow["signature_data"] ?? ""));
        $existingDate = trim((string)($existingRow["evaluation_date"] ?? ""));
        if ($existingDate !== "") {
          $evaluationDateValue = $existingDate;
        }
      }
      if ($existingResult instanceof mysqli_result) {
        $existingResult->free();
      }
    }
    $existingStmt->close();
  }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $strengthsInput = trim((string)($_POST["strengths"] ?? ""));
  $areasImprovementInput = trim((string)($_POST["areas_improvement"] ?? ""));
  $recommendationsInput = trim((string)($_POST["recommendations"] ?? ""));
  $signatureDataInput = trim((string)($_POST["signature_data"] ?? ""));
  $evaluationDateValue = date("Y-m-d");

  $invalidRatings = [];
  foreach ($formRatings as $fieldName => $unusedValue) {
    $ratingValue = isset($_POST[$fieldName]) ? (int)$_POST[$fieldName] : 0;
    if ($ratingValue < 1 || $ratingValue > 5) {
      $formRatings[$fieldName] = null;
      $invalidRatings[] = $fieldName;
    } else {
      $formRatings[$fieldName] = $ratingValue;
    }
  }

  if ($loadError !== "") {
    $saveError = $loadError;
  } elseif (!$hasEvaluationTable) {
    $saveError = "Evaluation storage is unavailable.";
  } elseif (!$isEvaluationWindowOpen) {
    $saveError = "Evaluation window is currently closed.";
  } elseif (!empty($invalidRatings)) {
    $saveError = "Please complete all ratings before submitting.";
  } elseif ($signatureDataInput === "" || stripos($signatureDataInput, "data:image/") !== 0) {
    $saveError = "Signature is required before saving.";
  } else {
    $sectionATotal = 0;
    foreach ($ratingGroups["a"] as $fieldName) {
      $sectionATotal += (int)($formRatings[$fieldName] ?? 0);
    }
    $sectionBTotal = 0;
    foreach ($ratingGroups["b"] as $fieldName) {
      $sectionBTotal += (int)($formRatings[$fieldName] ?? 0);
    }
    $sectionCTotal = 0;
    foreach ($ratingGroups["c"] as $fieldName) {
      $sectionCTotal += (int)($formRatings[$fieldName] ?? 0);
    }
    $overallTotal = $sectionATotal + $sectionBTotal + $sectionCTotal;

    $ratingsJson = json_encode($formRatings, JSON_UNESCAPED_SLASHES);
    $strengthsValue = $strengthsInput !== "" ? $strengthsInput : null;
    $areasImprovementValue = $areasImprovementInput !== "" ? $areasImprovementInput : null;
    $recommendationsValue = $recommendationsInput !== "" ? $recommendationsInput : null;
    $assignedOfficeValue = $applicationProfile["assigned_office"] !== ""
      ? $applicationProfile["assigned_office"]
      : $headOffice;

    $saveStmt = $conn->prepare(
      "INSERT INTO department_head_evaluations (
        application_id, applicant_name, semester, school_year, assigned_office, head_username, head_name,
        evaluation_date, ratings_json, section_a_total, section_b_total, section_c_total, overall_total,
        strengths, areas_improvement, recommendations, signature_data
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        applicant_name = VALUES(applicant_name),
        semester = VALUES(semester),
        school_year = VALUES(school_year),
        assigned_office = VALUES(assigned_office),
        head_name = VALUES(head_name),
        evaluation_date = VALUES(evaluation_date),
        ratings_json = VALUES(ratings_json),
        section_a_total = VALUES(section_a_total),
        section_b_total = VALUES(section_b_total),
        section_c_total = VALUES(section_c_total),
        overall_total = VALUES(overall_total),
        strengths = VALUES(strengths),
        areas_improvement = VALUES(areas_improvement),
        recommendations = VALUES(recommendations),
        signature_data = VALUES(signature_data)"
    );

    if ($saveStmt) {
      $saveStmt->bind_param(
        "issssssssiiiissss",
        $applicationId,
        $applicationProfile["applicant_name"],
        $applicationProfile["semester"],
        $applicationProfile["school_year"],
        $assignedOfficeValue,
        $headUsername,
        $headName,
        $evaluationDateValue,
        $ratingsJson,
        $sectionATotal,
        $sectionBTotal,
        $sectionCTotal,
        $overallTotal,
        $strengthsValue,
        $areasImprovementValue,
        $recommendationsValue,
        $signatureDataInput
      );
      if ($saveStmt->execute()) {
        $saveSuccess = "Evaluation saved successfully.";
      } else {
        $saveError = "Failed to save evaluation.";
      }
      $saveStmt->close();
    } else {
      $saveError = "Unable to prepare evaluation save.";
    }
  }
}

$displayApplicantName = $applicationProfile["applicant_name"] !== "" ? $applicationProfile["applicant_name"] : "N/A";
$displaySemesterSchoolYear = "N/A";
if ($applicationProfile["semester"] !== "" && $applicationProfile["school_year"] !== "") {
  $displaySemesterSchoolYear = $applicationProfile["semester"] . ", S.Y. " . $applicationProfile["school_year"];
} elseif ($applicationProfile["semester"] !== "") {
  $displaySemesterSchoolYear = $applicationProfile["semester"];
} elseif ($applicationProfile["school_year"] !== "") {
  $displaySemesterSchoolYear = $applicationProfile["school_year"];
}
$displayAreaOfAssignment = $applicationProfile["assigned_office"] !== ""
  ? $applicationProfile["assigned_office"]
  : ($headOffice !== "" ? $headOffice : "N/A");
$displayHeadName = $headName;
$displayHeadUsername = $headUsername !== "" ? $headUsername : "head-office";
$displayEvaluationDate = date("F j, Y", strtotime($evaluationDateValue));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Assistants&#39; Evaluation Form</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
      :root {
        --navy: #052c6a;
        --blue: #0d8ddb;
        --gold: #fcdc2f;
        --ink: #0b1b3a;
      }

      body {
        font-family: "Space Grotesk", sans-serif;
        color: var(--ink);
        background:
          radial-gradient(1200px 700px at 12% -10%, #e7f3ff 0%, transparent 60%),
          radial-gradient(800px 480px at 92% 8%, #fff2c9 0%, transparent 60%),
          linear-gradient(180deg, #f7fbff 0%, #eef4ff 50%, #f4f7fb 100%);
      }

      body::before {
        content: "";
        position: fixed;
        inset: 0;
        background-image:
          linear-gradient(transparent 23px, rgba(5, 44, 106, 0.045) 24px),
          linear-gradient(90deg, transparent 23px, rgba(5, 44, 106, 0.045) 24px);
        background-size: 24px 24px;
        opacity: 0.7;
        pointer-events: none;
        z-index: 0;
      }

      .page-shell {
        position: relative;
        z-index: 1;
      }

      .glass-card {
        background: rgba(255, 255, 255, 0.92);
        border: 1px solid rgba(13, 141, 219, 0.25);
        box-shadow: 0 18px 45px rgba(5, 44, 106, 0.12);
        backdrop-filter: blur(12px);
      }

      ::-webkit-scrollbar {
        width: 6px;
      }
      ::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.12);
      }
      ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
        border-radius: 999px;
      }

      #sidebar nav ul {
        padding: 0.35rem 0.5rem 5.5rem;
      }

      .panel-nav-item {
        border-radius: 0.85rem;
        margin-bottom: 0.25rem;
        padding-left: 0.75rem;
        padding-right: 0.75rem;
        min-height: 2.5rem;
        display: flex;
        align-items: center;
        white-space: nowrap;
        transition: background-color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
      }

      .panel-nav-item:hover {
        transform: translateX(2px);
        background-color: rgba(255, 255, 255, 0.15);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.16);
      }

      .panel-nav-item.active {
        background-color: rgba(252, 220, 47, 0.95);
        color: #052c6a;
        box-shadow: 0 8px 20px rgba(252, 220, 47, 0.25);
      }
    </style>
</head>
<body class="bg-white text-[#0b1b3a] font-sans">
    <div class="min-h-screen page-shell">
      <aside
        id="sidebar"
        class="flex flex-col bg-gradient-to-b from-[#031f4f] via-[#0a4b86] to-[#0f9ad8] text-white w-64 h-screen fixed left-0 top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out overflow-y-auto shadow-[12px_0_28px_-12px_rgba(4,31,79,0.65)]"
      >
        <div class="mx-3 mt-3 rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
          <div class="flex items-center gap-3">
            <div class="relative shrink-0">
              <span class="absolute -inset-1 rounded-full bg-white/15 blur-sm"></span>
              <img
                src="../img/SMCCNEWLOGO.png"
                class="relative rounded-full w-14 h-14 object-cover ring-2 ring-white/45"
                alt="SMCC Logo"
              />
            </div>
            <span class="text-sm font-semibold leading-tight text-white">
              Admission and Scholarship Office
            </span>
          </div>
        </div>

        <nav class="flex-1 mt-2">
          <ul class="text-xs font-semibold">
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='headDashboard.php'">
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li class="panel-nav-item active gap-2 cursor-pointer" onclick="window.location.href='my-sas.php'">
              <i class="fas fa-user-friends w-5"></i>
              <span>My SA's</span>
            </li>
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='show-evaluation.php'">
              <i class="fas fa-check-circle w-5"></i>
              <span>Show Evaluation</span>
            </li>
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='head-changePassword.php'">
              <i class="fas fa-key w-5"></i>
              <span>Change Password</span>
            </li>
          </ul>
        </nav>

        <div class="absolute bottom-0 left-0 w-full p-2">
          <div class="rounded-xl border border-white/20 bg-white/10 backdrop-blur-sm overflow-hidden">
            <div class="h-px w-full bg-gradient-to-r from-transparent via-[#8bcfff] to-transparent opacity-80"></div>
            <div class="px-4 pt-2 pb-1 flex items-center gap-2 text-[11px] text-blue-100/90">
              <div class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center">
                <i class="fas fa-user-tie text-[12px]"></i>
              </div>
              <div class="leading-tight min-w-0">
                <p class="font-semibold truncate"><?= htmlspecialchars($displayHeadName) ?></p>
                <p class="text-[10px] text-blue-200/80 truncate"><?= htmlspecialchars($displayHeadUsername) ?></p>
              </div>
            </div>
            <div class="px-3 pb-3 pt-1">
              <button
                onclick="window.location.href='../logout.php'"
                class="w-full flex items-center justify-center gap-2 text-[11px] font-semibold bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 px-3 py-2 rounded-full shadow-md hover:shadow-lg transition-all duration-150"
                type="button"
              >
                <i class="fas fa-sign-out-alt text-xs"></i>
                <span>Logout</span>
              </button>
            </div>
          </div>
        </div>
      </aside>

      <main class="ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pb-8 pt-14">
        <header
          class="fixed top-0 left-0 md:left-64 right-0 z-20 h-14 flex items-center bg-white border-b border-slate-200 px-4 sm:px-6 shadow-sm"
        >
          <div class="flex items-center gap-2">
            <button
              id="sidebarToggle"
              class="md:hidden inline-flex items-center justify-center p-2 rounded bg-slate-700 text-white hover:bg-slate-800 focus:outline-none transition-colors"
              type="button"
            >
              <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-[#0d4b84] text-lg font-semibold flex items-center gap-2">
              <i class="fas fa-clipboard-check"></i>
              Student Assistant Evaluation
            </h2>
          </div>
        </header>

        <section class="px-4 sm:px-6 pt-6">
          <div class="mx-auto w-full max-w-6xl rounded-3xl glass-card overflow-hidden">
        <header class="border-b border-slate-200 px-10 py-8">
            <h1 class="text-center text-2xl font-semibold uppercase tracking-wide text-slate-800">Student Assistants&#39; Evaluation Form</h1>

            <?php if ($loadError !== ""): ?>
              <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($loadError) ?>
              </div>
            <?php endif; ?>

            <div class="mt-6 grid gap-4 text-sm text-slate-700 md:grid-cols-2">
                <div class="space-y-3">
                    <div class="flex items-center gap-4">
                        <span class="w-48 font-medium">Name of Student Assistant</span>
                        <div class="h-8 flex-1 border-b border-slate-400 flex items-end pb-1 font-semibold text-slate-800">
                          <?= htmlspecialchars($displayApplicantName) ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="w-48 font-medium">Semester &amp; School Year</span>
                        <div class="h-8 flex-1 border-b border-slate-400 flex items-end pb-1 font-semibold text-slate-800">
                          <?= htmlspecialchars($displaySemesterSchoolYear) ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="w-48 font-medium">Area of Assignment</span>
                        <div class="h-8 flex-1 border-b border-slate-400 flex items-end pb-1 font-semibold text-slate-800">
                          <?= htmlspecialchars($displayAreaOfAssignment) ?>
                        </div>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center gap-4">
                        <span class="w-40 font-medium">Head of Office</span>
                        <div class="h-8 flex-1 border-b border-slate-400 flex items-end pb-1 font-semibold text-slate-800">
                          <?= htmlspecialchars($displayHeadName) ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="w-40 font-medium">Date of Evaluation</span>
                        <div class="h-8 flex-1 border-b border-slate-400 flex items-end pb-1 font-semibold text-slate-800">
                          <?= htmlspecialchars($displayEvaluationDate) ?>
                        </div>
                    </div>
                </div>
            </div>

            <p class="mt-6 text-justify text-xs leading-relaxed text-slate-600">
                <span class="font-semibold">Direction:</span> Please rate each item below to determine the performance of the assigned student assistant of your respective office/department. Select the appropriate rating for every item.
            </p>
        </header>

        <section class="px-10 py-6">
            <div class="overflow-hidden rounded-lg border border-slate-300 text-sm text-slate-700">
                <table class="min-w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-100">
                            <th class="w-16 border border-slate-300 px-3 py-3 text-left font-semibold">Scale</th>
                            <th class="w-48 border border-slate-300 px-3 py-3 text-left font-semibold">Verbal Description</th>
                            <th class="border border-slate-300 px-3 py-3 text-left font-semibold">Verbal Interpretation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="border border-slate-300 px-3 py-3 font-semibold">5</td>
                            <td class="border border-slate-300 px-3 py-3">Excellent</td>
                            <td class="border border-slate-300 px-3 py-3 text-xs leading-5">This rating is given to student assistants who consistently exceed expectations and demonstrate outstanding performance in their assigned tasks.</td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-3 font-semibold">4</td>
                            <td class="border border-slate-300 px-3 py-3">Very Good</td>
                            <td class="border border-slate-300 px-3 py-3 text-xs leading-5">This rating is given to student assistants who frequently exceed expectations and demonstrate a high level of performance in their assigned tasks.</td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-3 font-semibold">3</td>
                            <td class="border border-slate-300 px-3 py-3">Good</td>
                            <td class="border border-slate-300 px-3 py-3 text-xs leading-5">This rating is given to student assistants who consistently meet expectations and perform their assigned tasks satisfactorily.</td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-3 font-semibold">2</td>
                            <td class="border border-slate-300 px-3 py-3">Fair</td>
                            <td class="border border-slate-300 px-3 py-3 text-xs leading-5">This rating is given to student assistants who occasionally meet expectations but may have some areas for improvement.</td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-3 font-semibold">1</td>
                            <td class="border border-slate-300 px-3 py-3">Poor</td>
                            <td class="border border-slate-300 px-3 py-3 text-xs leading-5">This rating is given to student assistants who consistently fail to meet expectations and demonstrate unsatisfactory performance in their assigned tasks.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if (!$isEvaluationWindowOpen && $loadError === ""): ?>
              <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Evaluation window is closed. Previous entries stay visible, but you cannot submit changes right now.
              </div>
            <?php endif; ?>

            <form id="evaluation-form" method="POST" class="mt-8 overflow-hidden rounded-lg border border-slate-300">
                <input type="hidden" name="application_id" value="<?= htmlspecialchars((string)$applicationId) ?>" />
                <fieldset <?= (!$isEvaluationWindowOpen || $loadError !== "") ? "disabled" : "" ?>>
                <table class="min-w-full border-collapse text-sm text-slate-700">
                    <thead>
                        <tr class="bg-slate-100">
                            <th class="w-16 border border-slate-300 px-3 py-2 text-left font-semibold"></th>
                            <th class="border border-slate-300 px-3 py-2 text-left font-semibold">Criteria</th>
                            <th class="w-24 border border-slate-300 px-3 py-2 text-center font-semibold">Excellent<br><span class="text-xs">(5)</span></th>
                            <th class="w-24 border border-slate-300 px-3 py-2 text-center font-semibold">Very Good<br><span class="text-xs">(4)</span></th>
                            <th class="w-24 border border-slate-300 px-3 py-2 text-center font-semibold">Good<br><span class="text-xs">(3)</span></th>
                            <th class="w-24 border border-slate-300 px-3 py-2 text-center font-semibold">Fair<br><span class="text-xs">(2)</span></th>
                            <th class="w-24 border border-slate-300 px-3 py-2 text-center font-semibold">Poor<br><span class="text-xs">(1)</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-blue-50/60 font-semibold">
                            <td colspan="7" class="border border-slate-300 px-3 py-2">A. Quality and Quantity of Work</td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">A.1</td>
                            <td class="border border-slate-300 px-3 py-2">Accurate at work assigned</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a1" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a1" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a1" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a1" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a1" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-2">A.2</td>
                            <td class="border border-slate-300 px-3 py-2">Always completes tasks</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a2" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a2" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a2" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a2" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a2" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">A.3</td>
                            <td class="border border-slate-300 px-3 py-2">Works in a timely manner</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a3" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a3" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a3" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a3" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a3" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-2">A.4</td>
                            <td class="border border-slate-300 px-3 py-2">Asks for more work when assigned tasks are done</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a4" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a4" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a4" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a4" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a4" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">A.5</td>
                            <td class="border border-slate-300 px-3 py-2">Easily accepts new responsibilities</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a5" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a5" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a5" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a5" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a5" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-100 font-semibold">
                            <td class="border border-slate-300 px-3 py-2" colspan="2">Total</td>
                            <td id="section-a-total" colspan="5" class="border border-slate-300 px-3 py-2 text-center font-semibold text-slate-800">0</td>
                        </tr>

                        <tr class="bg-blue-50/60 font-semibold">
                            <td colspan="7" class="border border-slate-300 px-3 py-2">B. Interpersonal Skills</td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">B.1</td>
                            <td class="border border-slate-300 px-3 py-2">Answers patrons&#39; questions accurately</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b1" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b1" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b1" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b1" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b1" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-2">B.2</td>
                            <td class="border border-slate-300 px-3 py-2">Deals with patrons well</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b2" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b2" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b2" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b2" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b2" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">B.3</td>
                            <td class="border border-slate-300 px-3 py-2">Deals with personnel well</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b3" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b3" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b3" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b3" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b3" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-2">B.4</td>
                            <td class="border border-slate-300 px-3 py-2">Knows how to effectively communicate with others</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b4" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b4" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b4" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b4" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b4" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">B.5</td>
                            <td class="border border-slate-300 px-3 py-2">Has a good relationship with other student assistants</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b5" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b5" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b5" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b5" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b5" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-100 font-semibold">
                            <td class="border border-slate-300 px-3 py-2" colspan="2">Total</td>
                            <td id="section-b-total" colspan="5" class="border border-slate-300 px-3 py-2 text-center font-semibold text-slate-800">0</td>
                        </tr>

                        <tr class="bg-blue-50/60 font-semibold">
                            <td colspan="7" class="border border-slate-300 px-3 py-2">C. Attendance and Reliability</td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">C.1</td>
                            <td class="border border-slate-300 px-3 py-2">Perfect attendance</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c1" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c1" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c1" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c1" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c1" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-2">C.2</td>
                            <td class="border border-slate-300 px-3 py-2">Reports duty on time; rarely comes late</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c2" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c2" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c2" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c2" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c2" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">C.3</td>
                            <td class="border border-slate-300 px-3 py-2">Following assigned schedule</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c3" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c3" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c3" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c3" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c3" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-2">C.4</td>
                            <td class="border border-slate-300 px-3 py-2">Able to work without direct supervision</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c4" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c4" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c4" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c4" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c4" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">C.5</td>
                            <td class="border border-slate-300 px-3 py-2">Carries out instructions successfully</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c5" value="5" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c5" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c5" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c5" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c5" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-100 font-semibold">
                            <td class="border border-slate-300 px-3 py-2" colspan="2">Total</td>
                            <td id="section-c-total" colspan="5" class="border border-slate-300 px-3 py-2 text-center font-semibold text-slate-800">0</td>
                        </tr>

                        <tr class="bg-slate-100 font-semibold">
                            <td class="border border-slate-300 px-3 py-2" colspan="2">Over-all Total</td>
                            <td id="overall-total" colspan="5" class="border border-slate-300 px-3 py-2 text-center font-semibold text-slate-900">0</td>
                        </tr>
                    </tbody>
                </table>

            <div class="mt-8 space-y-6 text-sm text-slate-700">
                <div>
                    <p class="font-semibold">D. Strength(s):</p>
                    <textarea
                      name="strengths"
                      rows="3"
                      class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring focus:ring-blue-500/20"
                    ><?= htmlspecialchars($strengthsInput) ?></textarea>
                </div>
                <div>
                    <p class="font-semibold">E. Area(s) for Improvement:</p>
                    <textarea
                      name="areas_improvement"
                      rows="3"
                      class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring focus:ring-blue-500/20"
                    ><?= htmlspecialchars($areasImprovementInput) ?></textarea>
                </div>
                <div>
                    <p class="font-semibold">F. Evaluator&#39;s Comment(s)/Recommendation:</p>
                    <textarea
                      name="recommendations"
                      rows="3"
                      class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring focus:ring-blue-500/20"
                    ><?= htmlspecialchars($recommendationsInput) ?></textarea>
                </div>
            </div>

            <div class="mt-10 flex justify-end">
                <div class="w-72 text-sm text-slate-700">
                    <label class="mb-2 block font-semibold" for="signature-pad">Evaluator&#39;s Signature</label>
                    <div class="rounded-lg border border-slate-300 bg-white p-3">
                      <canvas id="signature-pad" class="h-36 w-full rounded border border-dashed border-slate-300"></canvas>
                      <input type="hidden" id="signature-data" name="signature_data" value="<?= htmlspecialchars($signatureDataInput) ?>" />
                      <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                        <span>Draw your signature above.</span>
                        <button type="button" id="signature-clear" class="rounded-full border border-slate-200 px-3 py-1 font-semibold text-slate-600 hover:border-blue-400 hover:text-blue-600">
                          Clear
                        </button>
                      </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex flex-col gap-3 border-t border-slate-200 pt-6 md:flex-row md:items-center md:justify-between">
                <p class="text-xs text-slate-500">
                  <?= !$isEvaluationWindowOpen ? "Evaluation window is closed for this term." : "Complete all ratings and signature before saving." ?>
                </p>
                <button
                  type="submit"
                  class="rounded-full bg-gradient-to-r from-blue-700 to-blue-500 px-6 py-3 text-sm font-semibold uppercase tracking-wide text-white shadow-lg shadow-blue-500/30 transition hover:from-blue-800 hover:to-blue-600 focus:outline-none focus:ring focus:ring-blue-400/40 disabled:cursor-not-allowed disabled:opacity-60"
                  <?php echo ($loadError !== "" || !$isEvaluationWindowOpen) ? "disabled" : ""; ?>
                >
                  Save Evaluation
                </button>
            </div>
                </fieldset>
            </form>
        </section>
              </div>
        </section>
      </main>
    </div>

    <script>
      const departmentEvaluationToastMessage = <?= json_encode($saveSuccess !== "" ? $saveSuccess : $saveError, JSON_UNESCAPED_SLASHES) ?>;
      const departmentEvaluationToastType = <?= json_encode($saveSuccess !== "" ? "success" : ($saveError !== "" ? "error" : ""), JSON_UNESCAPED_SLASHES) ?>;

      function showDepartmentEvaluationToast() {
        if (!departmentEvaluationToastMessage) {
          return;
        }

        if (typeof Swal === "undefined") {
          window.alert(departmentEvaluationToastMessage);
          return;
        }

        Swal.fire({
          toast: true,
          position: "top-end",
          showConfirmButton: false,
          icon: departmentEvaluationToastType === "error" ? "error" : "success",
          title: departmentEvaluationToastMessage,
          timer: departmentEvaluationToastType === "error" ? 4200 : 3200,
          timerProgressBar: true,
          background: departmentEvaluationToastType === "error" ? "#fef2f2" : "#f0fdf4",
          color: departmentEvaluationToastType === "error" ? "#991b1b" : "#166534",
        });
      }

      document.addEventListener("DOMContentLoaded", () => {
        showDepartmentEvaluationToast();

        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");
        if (toggleBtn && sidebar) {
          toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
          });
        }

        const form = document.getElementById("evaluation-form");
        const ratingGroups = <?= json_encode($ratingGroups, JSON_UNESCAPED_SLASHES) ?>;
        const savedRatings = <?= json_encode($formRatings, JSON_UNESCAPED_SLASHES) ?>;

        Object.entries(savedRatings).forEach(([fieldName, ratingValue]) => {
          const value = parseInt(String(ratingValue ?? ""), 10);
          if (Number.isNaN(value) || value < 1 || value > 5) return;
          const selectedInput = document.querySelector(`input[type="radio"][name="${fieldName}"][value="${value}"]`);
          if (selectedInput) {
            selectedInput.checked = true;
          }
        });

        Object.values(ratingGroups).forEach((groupFields) => {
          groupFields.forEach((fieldName) => {
            const radios = Array.from(document.querySelectorAll(`input[type="radio"][name="${fieldName}"]`));
            radios.forEach((radio, index) => {
              radio.required = index === 0;
            });
          });
        });

        const sectionATotalEl = document.getElementById("section-a-total");
        const sectionBTotalEl = document.getElementById("section-b-total");
        const sectionCTotalEl = document.getElementById("section-c-total");
        const overallTotalEl = document.getElementById("overall-total");

        const getFieldScore = (fieldName) => {
          const selected = document.querySelector(`input[type="radio"][name="${fieldName}"]:checked`);
          const value = selected ? parseInt(selected.value, 10) : 0;
          return Number.isNaN(value) ? 0 : value;
        };

        const updateTotals = () => {
          const sectionA = (ratingGroups.a || []).reduce((sum, fieldName) => sum + getFieldScore(fieldName), 0);
          const sectionB = (ratingGroups.b || []).reduce((sum, fieldName) => sum + getFieldScore(fieldName), 0);
          const sectionC = (ratingGroups.c || []).reduce((sum, fieldName) => sum + getFieldScore(fieldName), 0);
          const overall = sectionA + sectionB + sectionC;

          if (sectionATotalEl) sectionATotalEl.textContent = String(sectionA);
          if (sectionBTotalEl) sectionBTotalEl.textContent = String(sectionB);
          if (sectionCTotalEl) sectionCTotalEl.textContent = String(sectionC);
          if (overallTotalEl) overallTotalEl.textContent = String(overall);
        };

        const allRatingInputs = Array.from(document.querySelectorAll('input[type="radio"][name^="score-"]'));
        allRatingInputs.forEach((input) => {
          input.addEventListener("change", updateTotals);
        });
        updateTotals();

        const canvas = document.getElementById("signature-pad");
        const clearBtn = document.getElementById("signature-clear");
        const signatureInput = document.getElementById("signature-data");
        if (!canvas || !clearBtn || !signatureInput) return;

        const ctx = canvas.getContext("2d");
        const getCanvasSize = () => {
          const rect = canvas.getBoundingClientRect();
          return { width: rect.width, height: rect.height };
        };
        const scaleCanvas = () => {
          const { width, height } = getCanvasSize();
          const ratio = window.devicePixelRatio || 1;
          canvas.width = Math.max(1, Math.floor(width * ratio));
          canvas.height = Math.max(1, Math.floor(height * ratio));
          ctx.setTransform(1, 0, 0, 1, 0, 0);
          ctx.scale(ratio, ratio);
          ctx.lineWidth = 2;
          ctx.lineCap = "round";
          ctx.strokeStyle = "#0f172a";
        };
        const renderSignatureFromData = (dataUrl) => {
          if (!dataUrl) return;
          const image = new Image();
          image.onload = () => {
            const { width, height } = getCanvasSize();
            ctx.clearRect(0, 0, width, height);
            ctx.drawImage(image, 0, 0, width, height);
          };
          image.src = dataUrl;
        };

        let drawing = false;
        const getPoint = (event) => {
          const rect = canvas.getBoundingClientRect();
          const clientX = event.touches ? event.touches[0].clientX : event.clientX;
          const clientY = event.touches ? event.touches[0].clientY : event.clientY;
          return { x: clientX - rect.left, y: clientY - rect.top };
        };
        const startDraw = (event) => {
          drawing = true;
          const point = getPoint(event);
          ctx.beginPath();
          ctx.moveTo(point.x, point.y);
          event.preventDefault();
        };
        const draw = (event) => {
          if (!drawing) return;
          const point = getPoint(event);
          ctx.lineTo(point.x, point.y);
          ctx.stroke();
          event.preventDefault();
        };
        const endDraw = () => {
          if (!drawing) return;
          drawing = false;
          signatureInput.value = canvas.toDataURL("image/png");
        };

        scaleCanvas();
        renderSignatureFromData(signatureInput.value.trim());
        window.addEventListener("resize", () => {
          const currentSignature = signatureInput.value.trim();
          scaleCanvas();
          renderSignatureFromData(currentSignature);
        });

        canvas.addEventListener("mousedown", startDraw);
        canvas.addEventListener("mousemove", draw);
        canvas.addEventListener("mouseup", endDraw);
        canvas.addEventListener("mouseleave", endDraw);
        canvas.addEventListener("touchstart", startDraw, { passive: false });
        canvas.addEventListener("touchmove", draw, { passive: false });
        canvas.addEventListener("touchend", endDraw);

        clearBtn.addEventListener("click", () => {
          const { width, height } = getCanvasSize();
          ctx.clearRect(0, 0, width, height);
          signatureInput.value = "";
        });

        if (form) {
          form.addEventListener("submit", (event) => {
            if (signatureInput.value.trim() === "") {
              event.preventDefault();
              alert("Please provide your signature before saving.");
            }
          });
        }
      });
    </script>
</body>
</html>
