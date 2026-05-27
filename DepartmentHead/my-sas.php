<?php
require_once __DIR__ . "/head-auth.php";
headRequireLogin();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once "../db.php";
date_default_timezone_set("Asia/Manila");

$headName = trim((string)($_SESSION["head_name"] ?? ""));
if ($headName === "") {
  $headName = "Head of Office";
}
$headUsername = trim((string)($_SESSION["head_username"] ?? ""));
$headOffice = trim((string)($_SESSION["head_office"] ?? ""));

$grantLabel = "Student Assistant";
$approvedApplicants = [];
$loadError = "";
$hasScholarTable = false;
$hasHeadEvaluationTable = false;
$isEvaluationWindowOpen = false;
$evaluationWindowOpenTerms = [];

function headDashboardDisplayStatusLabel(string $status): string
{
  $key = strtolower(trim($status));
  if ($key === "" || $key === "approved") return "Approved";
  if ($key === "official_scholar" || $key === "official scholar") return "Official Scholar";
  if ($key === "for_renewal" || $key === "for renewal") return "For Renewal";
  if ($key === "renewed") return "Renewed";
  if ($key === "expired") return "Expired";
  if ($key === "contract_ended" || $key === "contract ended") return "Contract Ended";
  return ucwords(str_replace("_", " ", $key));
}

function headDashboardStatusBadgeClass(string $statusLabel): string
{
  $key = strtolower(trim($statusLabel));
  if ($key === "approved" || $key === "official scholar") {
    return "bg-green-500 text-white";
  }
  if ($key === "for renewal") {
    return "bg-amber-500 text-white";
  }
  if ($key === "renewed") {
    return "bg-emerald-600 text-white";
  }
  if ($key === "expired") {
    return "bg-red-500 text-white";
  }
  if ($key === "contract ended") {
    return "bg-slate-600 text-white";
  }
  return "bg-blue-500 text-white";
}

function headDashboardScholarKey(array $row): string
{
  $scholarId = strtolower(trim((string)($row["scholar_id"] ?? "")));
  if ($scholarId !== "") {
    return "sid-" . $scholarId;
  }

  $fullName = strtolower(trim((string)($row["full_name"] ?? "")));
  $programYear = strtolower(trim((string)($row["program_year"] ?? "")));
  $semester = strtolower(trim((string)($row["semester"] ?? "")));
  $academicYear = strtolower(trim((string)($row["academic_year"] ?? "")));
  $assignedOffice = strtolower(trim((string)($row["assigned_office"] ?? "")));
  if ($fullName === "" && $programYear === "" && $semester === "" && $academicYear === "" && $assignedOffice === "") {
    return "";
  }

  return "name-" . sha1($fullName . "|" . $programYear . "|" . $semester . "|" . $academicYear . "|" . $assignedOffice);
}

function headDashboardEvaluationKey(int $applicationId, string $schoolYear, string $semester): string
{
  return strtolower(trim((string)$applicationId)) . "|" . strtolower(trim($schoolYear)) . "|" . strtolower(trim($semester));
}

function headEnsureDepartmentEvaluationWindowTable(mysqli $conn): bool
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

function headLoadOpenEvaluationTerms(mysqli $conn): array
{
  $terms = [];
  if (!headEnsureDepartmentEvaluationWindowTable($conn)) {
    return $terms;
  }

  $result = $conn->query("SELECT school_year, semester FROM department_evaluation_window WHERE is_open = 1");
  if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
      $schoolYear = strtolower(trim((string)($row["school_year"] ?? "")));
      $semester = strtolower(trim((string)($row["semester"] ?? "")));
      if ($schoolYear !== "" && $semester !== "") {
        $terms[$schoolYear . "|" . $semester] = true;
      }
    }
    $result->free();
  }

  return $terms;
}

if (($conn ?? null) instanceof mysqli) {
  $evaluationWindowOpenTerms = headLoadOpenEvaluationTerms($conn);
  $isEvaluationWindowOpen = !empty($evaluationWindowOpenTerms);
}

if ($headOffice === "" && $headUsername !== "") {
  $officeStmt = $conn->prepare("SELECT office FROM head_offices WHERE username = ? AND status = 'active' LIMIT 1");
  if ($officeStmt) {
    $officeStmt->bind_param("s", $headUsername);
    if ($officeStmt->execute()) {
      $officeResult = $officeStmt->get_result();
      $officeRow = $officeResult ? $officeResult->fetch_assoc() : null;
      if (is_array($officeRow)) {
        $headOffice = trim((string)($officeRow["office"] ?? ""));
        $_SESSION["head_office"] = $headOffice;
      }
      if ($officeResult instanceof mysqli_result) {
        $officeResult->free();
      }
    }
    $officeStmt->close();
  }
}

if ($headOffice === "") {
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
    $approvedApplicantMap = [];
    $studentAssistantQuery = "SELECT
        id,
        scholar_id,
        full_name,
        program_year,
        semester,
        academic_year,
        assigned_office,
        category,
        grant_applied,
        status,
        created_at
      FROM institutional_scholar_records
      WHERE (
          LOWER(TRIM(COALESCE(category, ''))) = 'student_assistant'
          OR (
            LOWER(TRIM(COALESCE(category, ''))) = 'official'
            AND LOWER(TRIM(COALESCE(grant_applied, ''))) LIKE '%assistant%'
          )
        )
        AND LOWER(TRIM(COALESCE(assigned_office, ''))) = ?
        AND COALESCE(contract_ended, 0) = 0
      ORDER BY
        CASE
          WHEN LOWER(TRIM(COALESCE(category, ''))) = 'official'
            AND LOWER(TRIM(COALESCE(grant_applied, ''))) LIKE '%assistant%'
          THEN 0
          ELSE 1
        END,
        created_at DESC,
        id DESC";

    if ($scholarStmt = $conn->prepare($studentAssistantQuery)) {
      $scholarStmt->bind_param("s", $headOfficeKey);
      if ($scholarStmt->execute()) {
        $scholarResult = $scholarStmt->get_result();
        while ($scholarRow = $scholarResult->fetch_assoc()) {
          $scholarRecordId = (int)($scholarRow["id"] ?? 0);
          if ($scholarRecordId <= 0) {
            continue;
          }

          $scholarId = trim((string)($scholarRow["scholar_id"] ?? ""));
          $submittedAtRaw = trim((string)($scholarRow["created_at"] ?? ""));
          $submittedAt = $submittedAtRaw !== "" ? date("Y-m-d h:i A", strtotime($submittedAtRaw)) : "";
          $recordKey = headDashboardScholarKey($scholarRow);
          if ($recordKey === "") {
            $recordKey = "scholar-" . ($scholarId !== "" ? $scholarId : (string)$scholarRecordId);
          }
          if (isset($approvedApplicantMap[$recordKey])) {
            continue;
          }

          $evaluationReferenceId = 0 - $scholarRecordId;
          $recordSemester = trim((string)($scholarRow["semester"] ?? ""));
          $recordAcademicYear = trim((string)($scholarRow["academic_year"] ?? ""));
          $recordTermKey = strtolower($recordAcademicYear) . "|" . strtolower($recordSemester);
          $approvedApplicantMap[$recordKey] = [
            "id" => $evaluationReferenceId,
            "record_key" => $recordKey,
            "submitted_at" => $submittedAt,
            "sort_timestamp" => $submittedAtRaw !== "" ? (int)strtotime($submittedAtRaw) : 0,
            "name" => trim((string)($scholarRow["full_name"] ?? "")),
            "program_course" => trim((string)($scholarRow["program_year"] ?? "")),
            "semester" => $recordSemester,
            "academic_year" => $recordAcademicYear,
            "status_text" => headDashboardDisplayStatusLabel((string)($scholarRow["status"] ?? "official_scholar")),
            "can_evaluate" => true,
            "is_evaluation_window_open" => isset($evaluationWindowOpenTerms[$recordTermKey]),
            "has_evaluation" => false,
            "evaluation_url" => "SaEvaluation.php?application_id=" . urlencode((string)$evaluationReferenceId),
          ];
        }
        if ($scholarResult instanceof mysqli_result) {
          $scholarResult->free();
        }
        $approvedApplicants = array_values($approvedApplicantMap);
      } else {
        $loadError = "Unable to load student assistants from scholar records.";
      }
      $scholarStmt->close();
    } else {
      $loadError = "Unable to prepare student assistant scholar records query.";
    }
  }

  if (!empty($approvedApplicants)) {
    usort($approvedApplicants, static function (array $left, array $right): int {
      $leftTs = (int)($left["sort_timestamp"] ?? 0);
      $rightTs = (int)($right["sort_timestamp"] ?? 0);
      if ($leftTs === $rightTs) {
        return strcmp((string)($left["record_key"] ?? ""), (string)($right["record_key"] ?? ""));
      }
      return $rightTs <=> $leftTs;
    });
  }

  $headEvaluationTableResult = $conn->query("SHOW TABLES LIKE 'department_head_evaluations'");
  if ($headEvaluationTableResult instanceof mysqli_result) {
    $hasHeadEvaluationTable = $headEvaluationTableResult->num_rows > 0;
    $headEvaluationTableResult->free();
  }

  if ($hasHeadEvaluationTable && $hasScholarTable && !empty($approvedApplicants)) {
    $evaluatedRecordKeys = [];
    $evaluatedQuery = "SELECT
        application_id,
        TRIM(COALESCE(school_year, '')) AS school_year,
        TRIM(COALESCE(semester, '')) AS semester
      FROM department_head_evaluations dhe
      WHERE dhe.head_username = ?
        AND dhe.application_id <> 0
        AND LOWER(TRIM(COALESCE(dhe.assigned_office, ''))) = ?";

    if ($evalStmt = $conn->prepare($evaluatedQuery)) {
      $evalStmt->bind_param("ss", $headUsername, $headOfficeKey);
      if ($evalStmt->execute()) {
        $evalResult = $evalStmt->get_result();
        while ($evalRow = $evalResult->fetch_assoc()) {
          $applicationId = (int)($evalRow["application_id"] ?? 0);
          if ($applicationId !== 0) {
            $recordKey = headDashboardEvaluationKey(
              $applicationId,
              (string)($evalRow["school_year"] ?? ""),
              (string)($evalRow["semester"] ?? "")
            );
            $evaluatedRecordKeys[$recordKey] = true;
          }
        }
        if ($evalResult instanceof mysqli_result) {
          $evalResult->free();
        }
      }
      $evalStmt->close();
    }

    if (!empty($evaluatedRecordKeys)) {
      foreach ($approvedApplicants as $approvedIndex => $approvedApplicant) {
        $recordKey = headDashboardEvaluationKey(
          (int)($approvedApplicant["id"] ?? 0),
          (string)($approvedApplicant["academic_year"] ?? ""),
          (string)($approvedApplicant["semester"] ?? "")
        );
        if (isset($evaluatedRecordKeys[$recordKey])) {
          $approvedApplicants[$approvedIndex]["has_evaluation"] = true;
        }
      }
    }
  }
}

$approvedCount = count($approvedApplicants);
$readyToEvaluateCount = 0;
foreach ($approvedApplicants as $approvedApplicantRow) {
  if (
    ($approvedApplicantRow["can_evaluate"] ?? false) === true
    && ($approvedApplicantRow["has_evaluation"] ?? false) !== true
    && ($approvedApplicantRow["is_evaluation_window_open"] ?? false) === true
  ) {
    $readyToEvaluateCount++;
  }
}

$windowStatusLabel = $isEvaluationWindowOpen ? "Open" : "Closed";
$windowStatusClass = $isEvaluationWindowOpen ? "text-emerald-700 bg-emerald-50 border-emerald-200" : "text-amber-700 bg-amber-50 border-amber-200";
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>My SA's</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
      rel="stylesheet"
    />
    <link
      href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <style>
      :root {
        --navy: #052c6a;
        --navy-deep: #041c43;
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

      .heading-font {
        font-family: "Fraunces", serif;
      }

      .glass-card {
        background: rgba(255, 255, 255, 0.88);
        border: 1px solid rgba(13, 141, 219, 0.25);
        box-shadow: 0 18px 45px rgba(5, 44, 106, 0.12);
        backdrop-filter: blur(12px);
      }

      .stat-card {
        border: 1px solid rgba(13, 141, 219, 0.25);
        box-shadow: 0 12px 28px rgba(5, 44, 106, 0.12);
      }

      .badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        background: rgba(13, 141, 219, 0.12);
        color: var(--blue);
      }

      .table-hover tbody tr:hover {
        background-color: #f5f9ff;
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
                <p class="font-semibold truncate"><?= htmlspecialchars($headName) ?></p>
                <p class="text-[10px] text-blue-200/80 truncate">
                  <?= htmlspecialchars($headUsername !== "" ? $headUsername : "head-office") ?>
                </p>
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
              <i class="fas fa-user-friends"></i>
              My SA's
            </h2>
          </div>
        </header>

        <section class="px-4 sm:px-6">
          <section class="mt-6 glass-card rounded-3xl p-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-2">
              <span class="badge">Assigned Student Assistants</span>
              <h1 class="heading-font text-3xl text-[#052c6a]">My SA's</h1>
              <p class="text-xs text-[#42506a]">
                This page loads the student assistants currently assigned to your office from `institutional_scholar_records`.
              </p>
              <p class="text-sm font-semibold text-[#052c6a]">
                Office: <?= htmlspecialchars($headOffice !== "" ? $headOffice : "N/A") ?>
              </p>
            </div>
          </section>

          <section class="grid gap-4 pt-6 md:grid-cols-3">
            <div class="stat-card rounded-2xl bg-gradient-to-br from-[#052c6a] to-[#0b3f8f] p-5 text-white">
              <p class="text-xs uppercase tracking-wide text-[#fcdc2f]">Total My SA's</p>
              <p class="mt-2 text-3xl font-bold"><?= htmlspecialchars((string)$approvedCount) ?></p>
              <p class="mt-1 text-[11px] text-blue-100">
                Student assistants assigned under your office.
              </p>
            </div>
            <div class="stat-card rounded-2xl bg-white p-5 text-[#052c6a]">
              <p class="text-xs uppercase tracking-wide text-[#0d8ddb]">Ready to Evaluate</p>
              <p class="mt-2 text-3xl font-bold"><?= htmlspecialchars((string)$readyToEvaluateCount) ?></p>
              <p class="mt-1 text-[11px] text-slate-500">
                Records with no saved evaluation while the window is open.
              </p>
            </div>
            <div class="stat-card rounded-2xl bg-white p-5 text-[#052c6a]">
              <p class="text-xs uppercase tracking-wide text-[#0d8ddb]">Evaluation Window</p>
              <div class="mt-2 inline-flex rounded-full border px-3 py-1 text-xs font-semibold <?= htmlspecialchars($windowStatusClass) ?>">
                <?= htmlspecialchars($windowStatusLabel) ?>
              </div>
              <p class="mt-3 text-[11px] text-slate-500">
                This controls whether evaluation forms can be opened from this page.
              </p>
            </div>
          </section>

          <section class="mt-6 glass-card rounded-3xl p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="text-sm font-semibold text-[#0d8ddb]">My SA's List</p>
                <p class="text-xs text-[#052c6a]">
                  Showing <?= htmlspecialchars((string)$approvedCount) ?> records for <?= htmlspecialchars($grantLabel) ?>.
                </p>
              </div>
              <div class="flex flex-wrap items-center gap-2 text-xs">
                <div class="flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-white px-3 py-2 shadow-sm">
                  <i class="fas fa-search text-[#7c8191] text-xs"></i>
                  <input
                    id="mySASearch"
                    type="text"
                    class="w-44 bg-transparent text-xs font-semibold text-[#052c6a] outline-none placeholder:text-[#7c8191]"
                    placeholder="Search My SA's..."
                    aria-label="Search My SA's"
                  />
                </div>
                <span class="rounded-full bg-[#fcdc2f] px-3 py-1 text-[#052c6a] shadow-sm">
                  Total: <?= htmlspecialchars((string)$approvedCount) ?>
                </span>
              </div>
            </div>

            <div class="mt-4 overflow-x-auto">
              <?php if ($loadError !== ""): ?>
                <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                  <?= htmlspecialchars($loadError) ?>
                </div>
              <?php endif; ?>
              <table class="table-hover min-w-full overflow-hidden rounded-2xl border border-[#0d8ddb] text-xs text-left">
                <thead>
                  <tr class="bg-gradient-to-r from-[#052c6a] to-[#0b3f8f] text-white">
                    <th class="border-r border-white/10 px-3 py-3">Timestamp</th>
                    <th class="border-r border-white/10 px-3 py-3">Applicant Name</th>
                    <th class="border-r border-white/10 px-3 py-3">Program / Course</th>
                    <th class="border-r border-white/10 px-3 py-3">Grant</th>
                    <th class="border-r border-white/10 px-3 py-3">Status</th>
                    <th class="px-3 py-3">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($approvedApplicants)): ?>
                    <tr>
                      <td colspan="6" class="px-3 py-4 text-center text-[#052c6a]">
                        No student assistants found for your office.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($approvedApplicants as $applicant): ?>
                      <?php
                        $searchText = strtolower(
                          ($applicant["name"] ?? "") . " " .
                          ($applicant["program_course"] ?? "") . " " .
                          ($applicant["status_text"] ?? "")
                        );
                        $statusLabel = (string)($applicant["status_text"] ?? "Approved");
                        $statusBadgeClass = headDashboardStatusBadgeClass($statusLabel);
                      ?>
                      <tr class="border-b border-[#0d8ddb]" data-my-sa-row data-search-text="<?= htmlspecialchars($searchText) ?>" data-record-key="<?= htmlspecialchars((string)($applicant["record_key"] ?? "")) ?>">
                        <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                          <?= htmlspecialchars($applicant["submitted_at"]) ?>
                        </td>
                        <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                          <?= htmlspecialchars($applicant["name"]) ?>
                        </td>
                        <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                          <?= htmlspecialchars($applicant["program_course"]) ?>
                        </td>
                        <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                          <?= htmlspecialchars($grantLabel) ?>
                        </td>
                        <td class="border-r border-[#0d8ddb] px-3 py-2">
                          <span class="rounded-full px-2 py-1 text-[10px] shadow-sm <?= htmlspecialchars($statusBadgeClass) ?>">
                            <?= htmlspecialchars($statusLabel) ?>
                          </span>
                        </td>
                        <td class="px-3 py-2">
                          <?php if (($applicant["can_evaluate"] ?? false) === true && ($applicant["has_evaluation"] ?? false) === true): ?>
                            <span class="inline-flex rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700">
                              Evaluated
                            </span>
                          <?php elseif (($applicant["can_evaluate"] ?? false) === true && ($applicant["is_evaluation_window_open"] ?? false) === true): ?>
                            <button
                              class="rounded-full bg-[#0d8ddb] px-3 py-1 text-[11px] font-semibold text-white shadow-sm hover:bg-[#0b7cc0]"
                              type="button"
                              onclick="window.location.href='<?= htmlspecialchars((string)($applicant['evaluation_url'] ?? 'SaEvaluation.php')) ?>'"
                            >
                              Open Evaluation Form
                            </button>
                          <?php elseif (($applicant["can_evaluate"] ?? false) === true): ?>
                            <span class="inline-flex rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-[10px] font-semibold text-amber-700">
                              Evaluation Window Closed
                            </span>
                          <?php else: ?>
                            <span class="inline-flex rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-[10px] font-semibold text-slate-600">
                              View Only
                            </span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <tr data-my-sa-empty class="hidden">
                      <td colspan="6" class="px-3 py-4 text-center text-[#052c6a]">
                        No matching student assistants.
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>
        </section>
      </main>
    </div>

    <script>
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");
        if (toggleBtn && sidebar) {
          toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
          });
        }

        const searchInput = document.getElementById("mySASearch");
        const rows = Array.from(document.querySelectorAll("[data-my-sa-row]"));
        const emptyRow = document.querySelector("[data-my-sa-empty]");

        const applySearch = () => {
          const query = (searchInput?.value || "").trim().toLowerCase();
          let visible = 0;
          rows.forEach((row) => {
            const text = row.dataset.searchText || "";
            const matches = query === "" || text.includes(query);
            row.style.display = matches ? "table-row" : "none";
            if (matches) visible++;
          });
          if (emptyRow) {
            emptyRow.style.display = visible === 0 ? "table-row" : "none";
          }
        };

        if (searchInput) {
          searchInput.addEventListener("input", applySearch);
        }
        applySearch();
      });
    </script>
  </body>
</html>
