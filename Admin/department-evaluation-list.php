<?php
// Guide: Department evaluation monitor with evaluation window controls and term filters.
// Trace: load evaluation settings -> fetch assistant records -> render status list -> table/sidebar scripts.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once __DIR__ . "/includes/school-term-filter.php";

$openedSchoolYearOptions = $schoolYearOptions;
$rawSelectedSchoolYear = array_key_exists("school_year", $_GET)
  ? trim((string)$_GET["school_year"])
  : null;
$rawSelectedSemester = array_key_exists("semester", $_GET)
  ? trim((string)$_GET["semester"])
  : null;
$activeSchoolYearFilter = $rawSelectedSchoolYear === null ? $displaySchoolYear : $selectedSchoolYear;
$activeSemesterFilter = $rawSelectedSemester === null ? "" : $selectedSemester;

$assistantEvaluationRecords = [];
$isEvaluationWindowOpen = false;
$evaluationWindowNotice = "";
$evaluationWindowError = "";

// Helpers below keep program-year parsing, category filtering, and record-key generation consistent.

function isgSplitProgramYearForDepartmentList(string $programYear): array
{
  $value = trim($programYear);
  if ($value === "") {
    return ["", ""];
  }

  $parts = preg_split('/\s*\/\s*/', $value, 2);
  $course = trim((string)($parts[0] ?? ""));
  $yearLevel = trim((string)($parts[1] ?? ""));
  if ($course === "") {
    $course = $value;
  }

  return [$course, $yearLevel];
}

function isgDepartmentEvaluationAssistantCategorySql(string $categoryColumn = "category", string $grantColumn = "grant_applied"): string
{
  return "(
    LOWER(TRIM(COALESCE({$categoryColumn}, ''))) = 'student_assistant'
    OR (
      LOWER(TRIM(COALESCE({$categoryColumn}, ''))) = 'official'
      AND LOWER(TRIM(COALESCE({$grantColumn}, ''))) LIKE '%assistant%'
    )
  )";
}

function isgDepartmentEvaluationRecordKey(int $applicationId, string $schoolYear, string $semester): string
{
  return strtolower(trim((string)$applicationId)) . "|" . strtolower(trim($schoolYear)) . "|" . strtolower(trim($semester));
}

function isgDepartmentEvaluationSemesterSortRank(string $semester): int
{
  $value = strtolower(trim($semester));
  if ($value === "1st semester") {
    return 1;
  }
  if ($value === "2nd semester") {
    return 2;
  }
  if ($value === "summer") {
    return 3;
  }
  return 9;
}

// Load evaluation-window settings and student assistant records for the selected term.

if (($conn ?? null) instanceof mysqli) {
  $createWindowTableSql = "CREATE TABLE IF NOT EXISTS department_evaluation_window (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    is_open TINYINT(1) NOT NULL DEFAULT 0,
    opened_at DATETIME DEFAULT NULL,
    opened_by VARCHAR(100) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  $windowTableReady = $conn->query($createWindowTableSql) === true;

  if (!$windowTableReady) {
    $evaluationWindowError = "Unable to access evaluation window settings.";
  } else {
    $windowAction = $_SERVER["REQUEST_METHOD"] === "POST"
      ? trim((string)($_POST["evaluation_window_action"] ?? ""))
      : "";

    if ($windowAction === "open" || $windowAction === "close") {
      if ($windowAction === "open") {
        $windowSql = "INSERT INTO department_evaluation_window (id, is_open, opened_at, opened_by)
          VALUES (1, 1, NOW(), 'admin')
          ON DUPLICATE KEY UPDATE
            is_open = VALUES(is_open),
            opened_at = VALUES(opened_at),
            opened_by = VALUES(opened_by)";
      } else {
        $windowSql = "INSERT INTO department_evaluation_window (id, is_open, opened_at, opened_by)
          VALUES (1, 0, NULL, 'admin')
          ON DUPLICATE KEY UPDATE
            is_open = VALUES(is_open),
            opened_at = VALUES(opened_at),
            opened_by = VALUES(opened_by)";
      }

      if ($conn->query($windowSql) === true) {
        $evaluationWindowNotice = $windowAction === "open"
          ? "Evaluation window is now open."
          : "Evaluation window is now closed.";
      } else {
        $evaluationWindowError = $windowAction === "open"
          ? "Unable to open the evaluation window."
          : "Unable to close the evaluation window.";
      }
    }

    $windowStateResult = $conn->query("SELECT is_open FROM department_evaluation_window WHERE id = 1 LIMIT 1");
    if ($windowStateResult instanceof mysqli_result) {
      $windowRow = $windowStateResult->fetch_assoc();
      if (is_array($windowRow)) {
        $isEvaluationWindowOpen = ((int)($windowRow["is_open"] ?? 0)) === 1;
      }
      $windowStateResult->free();
    }
  }

  $hasInstitutionalTable = false;
  $institutionalTableResult = $conn->query("SHOW TABLES LIKE 'institutional_scholar_records'");
  if ($institutionalTableResult instanceof mysqli_result) {
    $hasInstitutionalTable = $institutionalTableResult->num_rows > 0;
    $institutionalTableResult->free();
  }

  if ($hasInstitutionalTable) {
    $schoolYearOptions = $openedSchoolYearOptions;
    $semesterOptions = [];
    $assistantTermResult = $conn->query("
      SELECT DISTINCT
        TRIM(COALESCE(academic_year, '')) AS academic_year,
        TRIM(COALESCE(semester, '')) AS semester
      FROM institutional_scholar_records
      WHERE " . isgDepartmentEvaluationAssistantCategorySql() . "
        AND TRIM(COALESCE(assigned_office, '')) <> ''
      ORDER BY academic_year ASC, semester ASC
    ");
    if ($assistantTermResult instanceof mysqli_result) {
      while ($termRow = $assistantTermResult->fetch_assoc()) {
        $academicYear = trim((string)($termRow["academic_year"] ?? ""));
        $semester = trim((string)($termRow["semester"] ?? ""));
        if ($academicYear !== "" && !in_array($academicYear, $schoolYearOptions, true)) {
          $schoolYearOptions[] = $academicYear;
        }
        if ($semester !== "" && !in_array($semester, $semesterOptions, true)) {
          $semesterOptions[] = $semester;
        }
      }
      $assistantTermResult->free();

      usort($schoolYearOptions, function ($a, $b) {
        $aYear = (int)substr((string)$a, 0, 4);
        $bYear = (int)substr((string)$b, 0, 4);
        if ($aYear === $bYear) {
          return strcmp((string)$a, (string)$b);
        }
        return $aYear <=> $bYear;
      });

      usort($semesterOptions, function ($a, $b) {
        $rankCompare = isgDepartmentEvaluationSemesterSortRank((string)$a) <=> isgDepartmentEvaluationSemesterSortRank((string)$b);
        if ($rankCompare !== 0) {
          return $rankCompare;
        }
        return strcmp((string)$a, (string)$b);
      });
    }

    if ($activeSchoolYearFilter !== "" && !in_array($activeSchoolYearFilter, $schoolYearOptions, true)) {
      array_unshift($schoolYearOptions, $activeSchoolYearFilter);
    }
    if ($activeSemesterFilter !== "" && !in_array($activeSemesterFilter, $semesterOptions, true)) {
      array_unshift($semesterOptions, $activeSemesterFilter);
    }
    if (empty($semesterOptions)) {
      $semesterOptions = ["1st Semester", "2nd Semester"];
    }
  }

  if ($hasInstitutionalTable) {
    $assistantRecordsByKey = [];
    $hasContractEndedColumn = false;
    $contractEndedColumnResult = $conn->query("SHOW COLUMNS FROM institutional_scholar_records LIKE 'contract_ended'");
    if ($contractEndedColumnResult instanceof mysqli_result) {
      $hasContractEndedColumn = $contractEndedColumnResult->num_rows > 0;
      $contractEndedColumnResult->free();
    }

      $isrWhereClauses = [
        isgDepartmentEvaluationAssistantCategorySql(),
        "TRIM(COALESCE(assigned_office, '')) <> ''",
      ];
      $isrParams = [];
      $isrTypes = "";

      if ($hasContractEndedColumn) {
        $isrWhereClauses[] = "COALESCE(contract_ended, 0) = 0";
      }
      if ($activeSchoolYearFilter !== "") {
        $isrWhereClauses[] = "academic_year = ?";
        $isrParams[] = $activeSchoolYearFilter;
        $isrTypes .= "s";
      }
      if ($activeSemesterFilter !== "") {
        $isrWhereClauses[] = "semester = ?";
        $isrParams[] = $activeSemesterFilter;
        $isrTypes .= "s";
      }

    $isrSql = "
      SELECT
          id,
          scholar_id,
          full_name,
          program_year,
          assigned_office,
          academic_year,
          semester,
          category,
          grant_applied
        FROM institutional_scholar_records
        WHERE " . implode(" AND ", $isrWhereClauses) . "
        ORDER BY
          CASE
            WHEN LOWER(TRIM(COALESCE(category, ''))) = 'official'
              AND LOWER(TRIM(COALESCE(grant_applied, ''))) LIKE '%assistant%'
            THEN 0
            ELSE 1
          END,
          updated_at DESC,
          id DESC
      ";
    $isrStmt = $conn->prepare($isrSql);
    if ($isrStmt) {
      if (!empty($isrParams)) {
        $isrStmt->bind_param($isrTypes, ...$isrParams);
      }
      if ($isrStmt->execute()) {
        $isrResult = $isrStmt->get_result();
        while ($row = $isrResult->fetch_assoc()) {
          $recordId = (int)($row["id"] ?? 0);
          if ($recordId <= 0) {
            continue;
          }

          $applicantId = 0 - $recordId;
          $academicYear = trim((string)($row["academic_year"] ?? ""));
          $semester = trim((string)($row["semester"] ?? ""));
          $recordKey = isgDepartmentEvaluationRecordKey($applicantId, $academicYear, $semester);
          [$programCourse, $yearLevel] = isgSplitProgramYearForDepartmentList((string)($row["program_year"] ?? ""));

          $assistantRecordsByKey[$recordKey] = [
            "applicantId" => $applicantId,
            "name" => trim((string)($row["full_name"] ?? "")),
            "course" => $programCourse,
            "yearLevel" => $yearLevel,
            "office" => trim((string)($row["assigned_office"] ?? "")),
            "status" => "not yet evaluated",
            "academicYear" => $academicYear,
            "semester" => $semester,
            "evaluationId" => 0,
            "evaluatedAt" => null,
            "sourceType" => "institutional",
          ];
        }
        $isrResult->free();
      }
      $isrStmt->close();
    }

    $evaluationTableResult = $conn->query("SHOW TABLES LIKE 'department_head_evaluations'");
    $hasEvaluationTable = $evaluationTableResult instanceof mysqli_result && $evaluationTableResult->num_rows > 0;
    if ($evaluationTableResult instanceof mysqli_result) {
      $evaluationTableResult->free();
    }

    if ($hasEvaluationTable) {
      $historicalTermResult = $conn->query("
        SELECT DISTINCT
          TRIM(COALESCE(school_year, '')) AS school_year,
          TRIM(COALESCE(semester, '')) AS semester
        FROM department_head_evaluations
        WHERE application_id <> 0
      ");
      if ($historicalTermResult instanceof mysqli_result) {
        while ($historicalTermRow = $historicalTermResult->fetch_assoc()) {
          $schoolYear = trim((string)($historicalTermRow["school_year"] ?? ""));
          $semester = trim((string)($historicalTermRow["semester"] ?? ""));
          if ($schoolYear !== "" && !in_array($schoolYear, $schoolYearOptions, true)) {
            $schoolYearOptions[] = $schoolYear;
          }
          if ($semester !== "" && !in_array($semester, $semesterOptions, true)) {
            $semesterOptions[] = $semester;
          }
        }
        $historicalTermResult->free();
      }

      $evaluationStatusByRecordKey = [];
      $evaluationStatusSql = "
        SELECT
          dhe.id,
          dhe.application_id,
          TRIM(COALESCE(dhe.applicant_name, '')) AS applicant_name,
          TRIM(COALESCE(dhe.assigned_office, '')) AS assigned_office,
          TRIM(COALESCE(dhe.school_year, '')) AS school_year,
          TRIM(COALESCE(dhe.semester, '')) AS semester,
          dhe.updated_at,
          TRIM(COALESCE(isr.program_year, '')) AS program_year
        FROM department_head_evaluations dhe
        LEFT JOIN institutional_scholar_records isr
          ON isr.id = ABS(dhe.application_id)
        WHERE dhe.application_id <> 0
          AND (
            isr.id IS NULL
            OR COALESCE(isr.contract_ended, 0) = 0
            OR TRIM(COALESCE(isr.academic_year, '')) <> TRIM(COALESCE(dhe.school_year, ''))
            OR TRIM(COALESCE(isr.semester, '')) <> TRIM(COALESCE(dhe.semester, ''))
          )
          AND (
            " . ($activeSchoolYearFilter !== "" ? "TRIM(COALESCE(dhe.school_year, '')) = ?" : "1 = 1") . "
          )
          AND (
            " . ($activeSemesterFilter !== "" ? "TRIM(COALESCE(dhe.semester, '')) = ?" : "1 = 1") . "
          )
        ORDER BY dhe.updated_at DESC, dhe.id DESC
      ";
      $evaluationStatusStmt = $conn->prepare($evaluationStatusSql);
      if ($evaluationStatusStmt) {
        $evaluationParams = [];
        $evaluationTypes = "";
        if ($activeSchoolYearFilter !== "") {
          $evaluationParams[] = $activeSchoolYearFilter;
          $evaluationTypes .= "s";
        }
        if ($activeSemesterFilter !== "") {
          $evaluationParams[] = $activeSemesterFilter;
          $evaluationTypes .= "s";
        }
        if (!empty($evaluationParams)) {
          $evaluationStatusStmt->bind_param($evaluationTypes, ...$evaluationParams);
        }
        if ($evaluationStatusStmt->execute()) {
          $evaluationStatusResult = $evaluationStatusStmt->get_result();
          while ($evaluationRow = $evaluationStatusResult->fetch_assoc()) {
            $applicationId = (int)($evaluationRow["application_id"] ?? 0);
            $schoolYear = trim((string)($evaluationRow["school_year"] ?? ""));
            $semester = trim((string)($evaluationRow["semester"] ?? ""));
            $recordKey = isgDepartmentEvaluationRecordKey($applicationId, $schoolYear, $semester);
            if (isset($evaluationStatusByRecordKey[$recordKey])) {
              continue;
            }

            $programYear = trim((string)($evaluationRow["program_year"] ?? ""));
            [$programCourse, $yearLevel] = isgSplitProgramYearForDepartmentList($programYear);
            if (!isset($assistantRecordsByKey[$recordKey])) {
              $assistantRecordsByKey[$recordKey] = [
                "applicantId" => $applicationId,
                "name" => trim((string)($evaluationRow["applicant_name"] ?? "")),
                "course" => $programCourse,
                "yearLevel" => $yearLevel,
                "office" => trim((string)($evaluationRow["assigned_office"] ?? "")),
                "status" => "not yet evaluated",
                "academicYear" => $schoolYear,
                "semester" => $semester,
                "evaluationId" => 0,
                "evaluatedAt" => null,
                "sourceType" => "evaluation_history",
              ];
            }

            $evaluatedAt = trim((string)($evaluationRow["updated_at"] ?? ""));
            $evaluatedAtTs = $evaluatedAt !== "" ? strtotime($evaluatedAt) : false;
            $evaluationStatusByRecordKey[$recordKey] = [
              "evaluationId" => (int)($evaluationRow["id"] ?? 0),
              "evaluatedAt" => $evaluatedAt,
              "sortTs" => $evaluatedAtTs !== false ? (int)$evaluatedAtTs : 0,
            ];
          }
          $evaluationStatusResult->free();
        }
        $evaluationStatusStmt->close();
      }

      foreach ($assistantRecordsByKey as $recordKey => $record) {
        $recordKey = isgDepartmentEvaluationRecordKey(
          (int)($record["applicantId"] ?? 0),
          (string)($record["academicYear"] ?? ""),
          (string)($record["semester"] ?? "")
        );
        $matchedEvaluation = $evaluationStatusByRecordKey[$recordKey] ?? null;

        if ($matchedEvaluation !== null) {
          $assistantRecordsByKey[$recordKey]["status"] = "evaluated";
          $assistantRecordsByKey[$recordKey]["evaluatedAt"] = (string)($matchedEvaluation["evaluatedAt"] ?? "");
          $assistantRecordsByKey[$recordKey]["evaluationId"] = (int)($matchedEvaluation["evaluationId"] ?? 0);
        }
      }
    }

    if (!empty($schoolYearOptions)) {
      $schoolYearOptions = array_values(array_unique($schoolYearOptions));
      usort($schoolYearOptions, function ($a, $b) {
        $aYear = (int)substr((string)$a, 0, 4);
        $bYear = (int)substr((string)$b, 0, 4);
        if ($aYear === $bYear) {
          return strcmp((string)$a, (string)$b);
        }
        return $aYear <=> $bYear;
      });
    }
    if (!empty($semesterOptions)) {
      $semesterOptions = array_values(array_unique($semesterOptions));
      usort($semesterOptions, function ($a, $b) {
        $rankCompare = isgDepartmentEvaluationSemesterSortRank((string)$a) <=> isgDepartmentEvaluationSemesterSortRank((string)$b);
        if ($rankCompare !== 0) {
          return $rankCompare;
        }
        return strcmp((string)$a, (string)$b);
      });
    }
    if ($activeSchoolYearFilter !== "" && !in_array($activeSchoolYearFilter, $schoolYearOptions, true)) {
      array_unshift($schoolYearOptions, $activeSchoolYearFilter);
    }
    if ($activeSemesterFilter !== "" && !in_array($activeSemesterFilter, $semesterOptions, true)) {
      array_unshift($semesterOptions, $activeSemesterFilter);
    }
    if (empty($semesterOptions)) {
      $semesterOptions = ["1st Semester", "2nd Semester"];
    }

    if (!empty($assistantRecordsByKey)) {
      $assistantEvaluationRecords = array_values($assistantRecordsByKey);
    }
  }

  if (!empty($assistantEvaluationRecords)) {
    usort($assistantEvaluationRecords, static function (array $left, array $right): int {
      $leftOffice = strtolower(trim((string)($left["office"] ?? "")));
      $rightOffice = strtolower(trim((string)($right["office"] ?? "")));
      if ($leftOffice !== $rightOffice) {
        return $leftOffice <=> $rightOffice;
      }

      $leftName = strtolower(trim((string)($left["name"] ?? "")));
      $rightName = strtolower(trim((string)($right["name"] ?? "")));
      return $leftName <=> $rightName;
    });
  }
}

$evaluationWindowActionUrl = "department-evaluation-list.php";
$evaluationWindowActionParams = [];
if ($rawSelectedSchoolYear !== null) {
  $evaluationWindowActionParams["school_year"] = $rawSelectedSchoolYear;
}
if ($rawSelectedSemester !== null) {
  $evaluationWindowActionParams["semester"] = $rawSelectedSemester;
}
if (!empty($evaluationWindowActionParams)) {
  $evaluationWindowActionUrl .= "?" . http_build_query($evaluationWindowActionParams);
}
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Student Assistants Evaluation List</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
      rel="stylesheet"
    />
    <style>
      ::-webkit-scrollbar {
        width: 6px;
      }
      ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
        border-radius: 999px;
      }
          #sidebar nav ul {
        padding: 0.35rem 0.5rem 5.5rem;
      }
      #sidebar li[data-nav] {
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
      #sidebar li[data-nav]:hover {
        transform: translateX(2px);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.16);
      }
    </style>
  </head>
  <body class="bg-white font-sans">
    <div class="min-h-screen">
      <!-- Sidebar -->
      <aside
        id="sidebar"
        class="flex flex-col bg-gradient-to-b from-[#031f4f] via-[#0a4b86] to-[#0f9ad8] text-white w-64 h-screen fixed left-0 top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out overflow-y-auto shadow-[12px_0_28px_-12px_rgba(4,31,79,0.65)]"
      >
        <div
          class="mx-3 mt-3 rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm"
        >
          <div class="flex items-center gap-3">
            <div class="relative shrink-0">
              <span class="absolute -inset-1 rounded-full bg-white/15 blur-sm"></span>
              <img
                src="../img/SMCCNEWLOGO.png"
                class="relative rounded-full w-14 h-14 object-cover ring-2 ring-white/45"
                alt="SMCC Logo"
              />
            </div>
            <div class="min-w-0">
              <p class="text-[10px] uppercase tracking-[0.14em] text-blue-100/85">
                SMCC Scholarship
              </p>
              <p class="text-sm font-semibold leading-tight text-white">
                Admission and Scholarship Office
              </p>
              <p class="text-[10px] text-blue-100/80 mt-1">
                Admin Management Portal
              </p>
            </div>
          </div>
        </div>
        <nav class="flex-1 mt-2">
          <ul class="text-xs font-semibold">
<li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
               data-nav="adminDashboard.php" onclick="window.location.href='adminDashboard.php'"
            >
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="applicant.php" onclick="window.location.href='applicant.php'"
            >
              <i class="fas fa-user-graduate w-5"></i>
              <span>Applicants</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="approved.php" onclick="window.location.href='approved.php'"
            >
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="interviewEvaluation.php" onclick="window.location.href='interviewEvaluation.php'"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="ranks.php" onclick="window.location.href='ranks.php'"
            >
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="list-of-qualified.php" onclick="window.location.href='list-of-qualified.php'"
            >
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="department-evaluation-list.php" onclick="window.location.href='department-evaluation-list.php'"
            >
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="summary-report.php" onclick="window.location.href='summary-report.php'"
            >
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="institutional-scholars.php" onclick="window.location.href='institutional-scholars.php'"
            >
              <i class="fas fa-chart-line w-5"></i>
              <span>Institutional Scholars</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="accounts.php" onclick="window.location.href='accounts.php'"
            >
              <i class="fas fa-user-circle w-5"></i>
              <span>Accounts</span>
            </li>
          </ul>
        </nav>

                <div class="absolute bottom-0 left-0 w-full p-2">
          <div class="rounded-xl border border-white/20 bg-white/10 backdrop-blur-sm overflow-hidden">
            <div class="h-px w-full bg-gradient-to-r from-transparent via-[#8bcfff] to-transparent opacity-80"></div>

            <div class="px-4 pt-2 pb-1 flex items-center gap-2 text-[11px] text-blue-100/90">
              <div class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center">
                <i class="fas fa-user-shield text-[12px]"></i>
              </div>
              <div class="leading-tight">
                <p class="font-semibold">Admin Account</p>
                <p class="text-[10px] text-blue-200/80">Institutional Scholarship</p>
              </div>
            </div>

            <!-- Logout button -->
            <div class="px-3 pb-3 pt-1">
              <button
                onclick="window.location.href='../logout.php'"
                class="w-full flex items-center justify-center gap-2 text-[11px] font-semibold
                       bg-gradient-to-r from-red-500 to-red-600
                       hover:from-red-600 hover:to-red-700
                       px-3 py-2 rounded-full shadow-md hover:shadow-lg
                       transition-all duration-150"
                type="button"
              >
                <i class="fas fa-sign-out-alt text-xs"></i>
                <span>Logout</span>
              </button>
            </div>
          </div>
        </div>
      </aside>

      <main class="ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pt-14">
        <header
          class="hidden fixed top-0 left-0 md:left-64 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
        >
          <div class="flex items-center gap-2">
            <button
              id="sidebarToggleTop"
              class="md:hidden inline-flex items-center justify-center p-2 rounded bg-[#0d8ddb] focus:outline-none"
              type="button"
            >
              <i class="fas fa-bars"></i>
            </button>
            <span class="text-[11px] font-semibold md:hidden">Admission &amp; Scholarship</span>
          </div>
          <div class="flex gap-2 text-xs">
            <button class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 flex items-center gap-1 font-normal" type="button">
              <i class="fas fa-user"></i>
              Admin panel
            </button>
            <button class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 font-normal" type="button">Account</button>
          </div>
        </header>

        <section
          class="page-header fixed top-0 left-0 md:left-64 right-0 z-20 bg-white border-b border-slate-200 px-4 sm:px-6 py-3 shadow-sm"
        >
                    <div class="flex items-center gap-2">
            <button
              id="sidebarToggle"
              class="md:hidden inline-flex items-center justify-center p-2 rounded bg-slate-700 text-white hover:bg-slate-800 focus:outline-none transition-colors"
              type="button"
            >
              <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-slate-800 text-lg font-semibold flex items-center gap-2">
            <i class="fas fa-flag"></i>
            DEPARTMENTAL EVALUATION
          </h2>
          </div>
        </section>

        <section class="mt-12 px-3 sm:px-4 lg:px-6 py-4 bg-gray-100 flex-1 min-h-[calc(100vh-3rem)]">
          <div class="w-full space-y-4 h-full flex flex-col">
            <div class="bg-white rounded-lg shadow-sm border border-[#e5e7eb] px-4 sm:px-6 py-4 space-y-3">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                  <h2 class="text-lg font-semibold text-[#052c6a]">Student Assistants Evaluation List</h2>
                  <p class="text-xs text-gray-500 mt-1">
                    Evaluation window:
                    <span
                      id="evalWindowLabel"
                      class="font-semibold <?php echo $isEvaluationWindowOpen ? "text-green-600" : "text-[#052c6a]"; ?>"
                    >
                      <?php echo $isEvaluationWindowOpen ? "Open" : "Closed"; ?>
                    </span>
                  </p>
                </div>
                <form method="post" action="<?php echo htmlspecialchars($evaluationWindowActionUrl); ?>" class="m-0">
                  <input type="hidden" name="evaluation_window_action" value="<?php echo $isEvaluationWindowOpen ? "close" : "open"; ?>" />
                  <button
                    id="openEvalBtn"
                    type="submit"
                    class="<?php echo $isEvaluationWindowOpen
                      ? "bg-red-500 hover:bg-red-600"
                      : "bg-[#0d8ddb] hover:bg-[#0b7cc4]"; ?> text-white text-sm font-semibold px-4 py-2 rounded shadow-sm transition"
                  >
                    <?php echo $isEvaluationWindowOpen ? "Close Evaluation" : "Open for Evaluation"; ?>
                  </button>
                </form>
              </div>
              <?php if ($evaluationWindowError !== ""): ?>
                <p class="text-xs font-semibold text-red-600"><?php echo htmlspecialchars($evaluationWindowError); ?></p>
              <?php elseif ($evaluationWindowNotice !== ""): ?>
                <p class="text-xs font-semibold text-green-600"><?php echo htmlspecialchars($evaluationWindowNotice); ?></p>
              <?php endif; ?>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                  <label for="searchInput" class="text-xs text-gray-600">Search</label>
                  <input
                    id="searchInput"
                    type="text"
                    placeholder="Search name, course, office..."
                    class="mt-1 w-full border border-[#e5e7eb] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]"
                  />
                </div>
                <form method="get" action="department-evaluation-list.php" class="contents">
                  <div>
                    <label for="yearFilter" class="text-xs text-gray-600">Academic Year</label>
                    <select
                      id="yearFilter"
                      name="school_year"
                      class="mt-1 w-full border border-[#e5e7eb] rounded px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]"
                      aria-label="Select academic year"
                      onchange="this.form.submit()"
                    >
                      <option value="" <?php echo $rawSelectedSchoolYear !== null && $activeSchoolYearFilter === "" ? "selected" : ""; ?>>All Academic Years</option>
                      <?php foreach ($schoolYearOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $activeSchoolYearFilter === $option ? "selected" : ""; ?>>
                          <?php echo htmlspecialchars($option); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label for="semFilter" class="text-xs text-gray-600">Semester</label>
                    <select
                      id="semFilter"
                      name="semester"
                      class="mt-1 w-full border border-[#e5e7eb] rounded px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]"
                      aria-label="Select semester"
                      onchange="this.form.submit()"
                    >
                      <option value="" <?php echo $activeSemesterFilter === "" ? "selected" : ""; ?>>All Semesters</option>
                      <?php foreach ($semesterOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $activeSemesterFilter === $option ? "selected" : ""; ?>>
                          <?php echo htmlspecialchars($option); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <?php if ($rawSelectedSchoolYear !== null || $rawSelectedSemester !== null): ?>
                      <a
                        href="department-evaluation-list.php"
                        class="inline-flex items-center mt-2 rounded border border-[#e5e7eb] bg-white px-3 py-1.5 text-xs font-semibold text-[#052c6a]"
                      >
                        Reset to Current
                      </a>
                    <?php endif; ?>
                  </div>
                </form>
              </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-[#e5e7eb] flex-1 flex flex-col min-h-[420px]">
              <div class="flex items-center gap-2 text-xs text-gray-500 px-4 sm:px-6 pt-4">
                <span class="h-2 w-2 rounded-full bg-green-500 inline-block"></span> Evaluated
                <span class="h-2 w-2 rounded-full bg-yellow-400 inline-block ml-3"></span> Not yet evaluated
              </div>
              <div class="px-4 sm:px-6 pb-6 pt-3 overflow-x-auto flex-1">
                <table class="min-w-full text-xs border border-[#e5e7eb]">
                  <thead class="bg-gray-50 text-[#052c6a]">
                    <tr>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Timestamp</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Name</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Course</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Year Level</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Assigned Office</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Academic Year</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Semester</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Status</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Action</th>
                    </tr>
                  </thead>
                  <tbody id="assistantRows" class="divide-y divide-[#e5e7eb]">
                    <!-- Filled by JS -->
                  </tbody>
                </table>
              </div>
            </div>
          </div>
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
          sidebar.querySelectorAll("li").forEach((item) => {
            item.addEventListener("click", () => {
              if (window.innerWidth < 768) {
                sidebar.classList.add("-translate-x-full");
              }
            });
          });
        }
      });

      // Populate student assistants list and keep term-specific evaluation state.
      document.addEventListener("DOMContentLoaded", () => {
        const assistantData = <?php echo json_encode($assistantEvaluationRecords, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

        const tbody = document.getElementById("assistantRows");
        const searchInput = document.getElementById("searchInput");
        const yearFilter = document.getElementById("yearFilter");
        const semFilter = document.getElementById("semFilter");
        if (!tbody) return;

        let evalOpen = <?php echo $isEvaluationWindowOpen ? "true" : "false"; ?>;
        let searchTerm = "";
        let yearSelection = yearFilter && yearFilter.value !== "" ? yearFilter.value : "all";
        let semSelection = semFilter && semFilter.value !== "" ? semFilter.value : "all";

        const renderRows = () => {
          tbody.innerHTML = "";
          const filtered = assistantData.filter((item) => {
            const matchesSearch =
              !searchTerm ||
              item.name.toLowerCase().includes(searchTerm) ||
              item.course.toLowerCase().includes(searchTerm) ||
              item.office.toLowerCase().includes(searchTerm) ||
              item.academicYear.toLowerCase().includes(searchTerm) ||
              item.semester.toLowerCase().includes(searchTerm);
            const matchesYear = yearSelection === "all" || item.academicYear === yearSelection;
            const matchesSem = semSelection === "all" || item.semester === semSelection;
            return matchesSearch && matchesYear && matchesSem;
          });

          if (filtered.length === 0) {
            tbody.innerHTML = `
              <tr>
                <td colspan="9" class="px-3 py-6 text-center text-gray-500 italic">
                  No student assistants found.
                </td>
              </tr>
            `;
            return;
          }

          filtered.forEach((item) => {
            const row = document.createElement("tr");

            const timestamp =
              item.status === "evaluated" && item.evaluatedAt
                ? new Date(item.evaluatedAt).toLocaleString()
                : "--";

            const statusClasses =
              item.status === "evaluated"
                ? "bg-green-100 text-green-800"
                : "bg-yellow-100 text-yellow-800";

            const actionHref =
              item.status === "evaluated"
                ? `department-evaluation-indi.php?evaluation_id=${encodeURIComponent(String(item.evaluationId || ""))}#evaluation-details`
                : "";

            row.innerHTML = `
              <td class="px-3 py-2 text-[#052c6a]">${timestamp}</td>
              <td class="px-3 py-2 text-[#052c6a] font-semibold">${item.name}</td>
              <td class="px-3 py-2 text-[#052c6a]">${item.course}</td>
              <td class="px-3 py-2 text-[#052c6a]">${item.yearLevel}</td>
              <td class="px-3 py-2 text-[#052c6a]">${item.office}</td>
              <td class="px-3 py-2 text-[#052c6a]">${item.academicYear || "N/A"}</td>
              <td class="px-3 py-2 text-[#052c6a]">${item.semester || "N/A"}</td>
              <td class="px-3 py-2">
                <span class="px-2 py-1 rounded-full text-[11px] ${statusClasses}">
                  ${item.status === "evaluated" ? "Evaluated" : "Not yet evaluated"}
                </span>
              </td>
              <td class="px-3 py-2">
                ${
                  item.status === "evaluated"
                    ? `<a
                        href="${actionHref}"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-[#0d8ddb] text-white text-[11px] hover:bg-[#0b7cc4]"
                      >
                        <i class="fas fa-eye"></i>
                        View Evaluation
                      </a>`
                    : evalOpen
                      ? `<span class="inline-flex rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-[11px] font-semibold text-amber-700">Waiting for Head</span>`
                      : `<span class="inline-flex rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-[11px] font-semibold text-slate-600">Evaluation Closed</span>`
                }
              </td>
            `;

            tbody.appendChild(row);
          });
        };

        if (searchInput) {
          searchInput.addEventListener("input", (e) => {
            searchTerm = e.target.value.trim().toLowerCase();
            renderRows();
          });
        }

        if (yearFilter) {
          yearFilter.addEventListener("change", (e) => {
            yearSelection = e.target.value || "all";
            renderRows();
          });
        }

        if (semFilter) {
          semFilter.addEventListener("change", (e) => {
            semSelection = e.target.value || "all";
            renderRows();
          });
        }

        renderRows();
      });
    </script>
  <script>
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("sidebar");
  if (!sidebar) {
    return;
  }

  // Highlight the current admin page in the shared sidebar menu.

  const currentPage = window.location.pathname.split("/").pop().toLowerCase();
  const sidebarAliases = {
    "view-application.php": "applicant.php",
    "department-evaluation-indi.php": "department-evaluation-list.php",
    "summary-reports.php": "summary-report.php",
    "list-0f-qualified.php": "list-of-qualified.php"
  };
  const activePage = sidebarAliases[currentPage] || currentPage;

  sidebar.querySelectorAll("li[data-nav]").forEach((item) => {
    const target = (item.dataset.nav || "").toLowerCase();
    const isActive = target === activePage;
    item.classList.toggle("bg-[#fcdc2f]", isActive);
    item.classList.toggle("bg-opacity-90", isActive);
    item.classList.toggle("text-[#052c6a]", isActive);
    item.classList.toggle("hover:bg-white/15", !isActive);
  });
});
</script>
</body>
</html>










