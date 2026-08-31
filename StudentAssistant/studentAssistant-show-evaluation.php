<?php
require_once __DIR__ . "/studentAssistant-auth.php";
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
$headRole = isgNormalizeEvaluatorRole((string)($_SESSION["evaluator_role"] ?? headExpectedRole()));
$headOffice = trim((string)($_SESSION["head_office"] ?? ""));
$evaluatorScope = isgLoadEvaluatorScope($conn, $headUsername, $headRole);
$headOffice = (string)($evaluatorScope["office"] ?? $headOffice);

$grantId = 1;
$grantLabel = "Student Assistant";
$evaluatedApplicants = [];
$loadError = "";
$hasScholarTable = false;
$selectedSchoolYear = trim((string)($_GET["school_year"] ?? ""));
$selectedSemester = trim((string)($_GET["semester"] ?? ""));
$schoolYearOptions = [];
$semesterOptions = ["1st Semester", "2nd Semester"];

function departmentHeadNormalizeSchoolYear(string $schoolYear): string
{
  $value = trim($schoolYear);
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

function departmentHeadSortSchoolYears(array &$schoolYears): void
{
  $schoolYears = array_values(array_unique(array_filter($schoolYears, static function ($schoolYear): bool {
    return trim((string)$schoolYear) !== "";
  })));

  usort($schoolYears, static function (string $left, string $right): int {
    if (preg_match('/^(\d{4})/', $left, $leftMatch) && preg_match('/^(\d{4})/', $right, $rightMatch)) {
      return ((int)$rightMatch[1]) <=> ((int)$leftMatch[1]);
    }
    return strcmp($right, $left);
  });
}

function departmentHeadLoadOpenedSchoolYears(mysqli $conn): array
{
  $schoolYears = [];
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
      $value = departmentHeadNormalizeSchoolYear((string)($row["school_year"] ?? ""));
      if ($value !== "") {
        $schoolYears[] = $value;
      }
    }
    $result->free();
  }

  departmentHeadSortSchoolYears($schoolYears);
  return $schoolYears;
}

$schoolYearOptions = departmentHeadLoadOpenedSchoolYears($conn);

if ((string)($evaluatorScope["error"] ?? "") !== "") {
  $loadError = (string)$evaluatorScope["error"];
} else {
  $scholarTableResult = $conn->query("SHOW TABLES LIKE 'institutional_scholar_records'");
  $hasScholarTable = $scholarTableResult instanceof mysqli_result && $scholarTableResult->num_rows > 0;
  if ($scholarTableResult instanceof mysqli_result) {
    $scholarTableResult->free();
  }

  $headEvaluationTableResult = $conn->query("SHOW TABLES LIKE 'department_head_evaluations'");
  $hasHeadEvaluationTable = $headEvaluationTableResult instanceof mysqli_result && $headEvaluationTableResult->num_rows > 0;
  if ($headEvaluationTableResult instanceof mysqli_result) {
    $headEvaluationTableResult->free();
  }

  if (!$hasScholarTable) {
    $loadError = "Scholar records table is not available.";
  } elseif (!$hasHeadEvaluationTable) {
    $loadError = "No evaluation entries yet.";
  } else {
    $evaluationRestriction = isgEvaluatorEvaluationRestriction($evaluatorScope);
    $filterOptionStmt = $conn->prepare(
      "SELECT DISTINCT
        TRIM(COALESCE(school_year, '')) AS school_year,
        TRIM(COALESCE(semester, '')) AS semester
      FROM department_head_evaluations
      WHERE head_username = ?
        AND evaluator_role = ?
        " . $evaluationRestriction["sql"]
    );
    if ($filterOptionStmt) {
      $filterTypes = "ss" . $evaluationRestriction["types"];
      $filterParams = array_merge([$headUsername, $headRole], $evaluationRestriction["params"]);
      $filterOptionStmt->bind_param($filterTypes, ...$filterParams);
      if ($filterOptionStmt->execute()) {
        $filterOptionResult = $filterOptionStmt->get_result();
        while ($filterOptionRow = $filterOptionResult->fetch_assoc()) {
          $optionSchoolYear = trim((string)($filterOptionRow["school_year"] ?? ""));
          $optionSemester = trim((string)($filterOptionRow["semester"] ?? ""));
          if ($optionSchoolYear !== "" && !in_array($optionSchoolYear, $schoolYearOptions, true)) {
            $schoolYearOptions[] = $optionSchoolYear;
          }
          if ($optionSemester !== "" && !in_array($optionSemester, $semesterOptions, true)) {
            $semesterOptions[] = $optionSemester;
          }
        }
        if ($filterOptionResult instanceof mysqli_result) {
          $filterOptionResult->free();
        }
      }
      $filterOptionStmt->close();
    }

    departmentHeadSortSchoolYears($schoolYearOptions);
    if ($selectedSchoolYear !== "" && !in_array($selectedSchoolYear, $schoolYearOptions, true)) {
      array_unshift($schoolYearOptions, $selectedSchoolYear);
    }
    if ($selectedSemester !== "" && !in_array($selectedSemester, $semesterOptions, true)) {
      array_unshift($semesterOptions, $selectedSemester);
    }

    $evaluatedQuery = "SELECT
        dhe.id AS evaluation_id,
        0 - ABS(dhe.application_id) AS reference_id,
        COALESCE(NULLIF(TRIM(dhe.applicant_name), ''), isr.full_name) AS applicant_name,
        COALESCE(NULLIF(TRIM(isr.program_year), ''), '') AS program_course,
        dhe.semester,
        dhe.school_year,
        dhe.evaluation_date,
        dhe.updated_at
      FROM department_head_evaluations dhe
      INNER JOIN institutional_scholar_records isr
        ON isr.id = ABS(dhe.application_id)
      WHERE dhe.head_username = ?
        AND dhe.evaluator_role = ?
        AND dhe.application_id <> 0
        " . isgEvaluatorEvaluationRestriction($evaluatorScope, "dhe")["sql"] . "
        AND (
          LOWER(TRIM(COALESCE(isr.category, ''))) = 'student_assistant'
          OR (
            LOWER(TRIM(COALESCE(isr.category, ''))) = 'official'
            AND LOWER(TRIM(COALESCE(isr.grant_applied, ''))) LIKE '%assistant%'
          )
        )
        AND (
          COALESCE(isr.contract_ended, 0) = 0
          OR TRIM(COALESCE(isr.academic_year, '')) <> TRIM(COALESCE(dhe.school_year, ''))
          OR TRIM(COALESCE(isr.semester, '')) <> TRIM(COALESCE(dhe.semester, ''))
        )
        AND (? = '' OR TRIM(COALESCE(dhe.school_year, '')) = ?)
        AND (? = '' OR TRIM(COALESCE(dhe.semester, '')) = ?)
      ORDER BY
        dhe.updated_at DESC,
        CASE
          WHEN LOWER(TRIM(COALESCE(isr.category, ''))) = 'official'
            AND LOWER(TRIM(COALESCE(isr.grant_applied, ''))) LIKE '%assistant%'
          THEN 0
          ELSE 1
        END,
        isr.id DESC";

    if ($stmt = $conn->prepare($evaluatedQuery)) {
      $evaluatedRestriction = isgEvaluatorEvaluationRestriction($evaluatorScope, "dhe");
      $evaluatedTypes = "ss" . $evaluatedRestriction["types"] . "ssss";
      $evaluatedParams = array_merge(
        [$headUsername, $headRole],
        $evaluatedRestriction["params"],
        [
        $selectedSchoolYear,
        $selectedSchoolYear,
        $selectedSemester,
        $selectedSemester
        ]
      );
      $stmt->bind_param($evaluatedTypes, ...$evaluatedParams);
      if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
          $evaluationId = (int)($row["evaluation_id"] ?? 0);
          $referenceId = (int)($row["reference_id"] ?? 0);
          if ($evaluationId <= 0 || $referenceId === 0) {
            continue;
          }
          $updatedAtRaw = trim((string)($row["updated_at"] ?? ""));
          $evaluationDateRaw = trim((string)($row["evaluation_date"] ?? ""));
          $displayUpdatedAt = "";
          if ($updatedAtRaw !== "") {
            $displayUpdatedAt = date("Y-m-d h:i A", strtotime($updatedAtRaw));
          } elseif ($evaluationDateRaw !== "") {
            $displayUpdatedAt = date("Y-m-d", strtotime($evaluationDateRaw));
          }
          $evaluatedApplicants[] = [
            "evaluation_id" => $evaluationId,
            "id" => $referenceId,
            "name" => (string)($row["applicant_name"] ?? ""),
            "program_course" => (string)($row["program_course"] ?? ""),
            "semester" => trim((string)($row["semester"] ?? "")),
            "school_year" => trim((string)($row["school_year"] ?? "")),
            "updated_at" => $displayUpdatedAt,
          ];
        }
        $result->free();
      } else {
        $loadError = "Unable to load evaluation entries.";
      }
      $stmt->close();
    } else {
      $loadError = "Unable to prepare evaluation entries query.";
    }
  }
}

$evaluatedCount = count($evaluatedApplicants);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Show Evaluation</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <link rel="stylesheet" href="../assets/css/tailwind.css">
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
        --blue: #0d8ddb;
      }
      body {
        font-family: "Space Grotesk", sans-serif;
        color: #0b1b3a;
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
      .page-shell { position: relative; z-index: 1; }
      .glass-card {
        background: rgba(255, 255, 255, 0.88);
        border: 1px solid rgba(13, 141, 219, 0.25);
        box-shadow: 0 18px 45px rgba(5, 44, 106, 0.12);
        backdrop-filter: blur(12px);
      }
      #sidebar nav ul { padding: 0.35rem 0.5rem 5.5rem; }
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
      .table-hover tbody tr:hover { background-color: #f5f9ff; }
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
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='studentAssistantDashboard.php'">
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='studentAssistant-my-sas.php'">
              <i class="fas fa-user-friends w-5"></i>
              <span>My SA's</span>
            </li>
            <li class="panel-nav-item active gap-2 cursor-pointer" onclick="window.location.href='studentAssistant-show-evaluation.php'">
              <i class="fas fa-check-circle w-5"></i>
              <span>Show Evaluation</span>
            </li>
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='studentAssistant-changePassword.php'">
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
              <i class="fas fa-check-circle"></i>
              Show Evaluation
            </h2>
          </div>
        </header>

        <section class="mt-6 glass-card rounded-3xl p-5 mx-4 sm:mx-6">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-sm font-semibold text-[#0d8ddb]">Show Evaluation</p>
              <p class="text-xs text-[#052c6a]">View evaluation entries for approved student assistants.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
              <form method="get" action="studentAssistant-show-evaluation.php" class="flex flex-wrap items-center gap-2">
                <select
                  class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                  name="school_year"
                  aria-label="Select school year"
                  onchange="this.form.submit()"
                >
                  <option value="" <?= $selectedSchoolYear === "" ? "selected" : "" ?>>All School Years</option>
                  <?php foreach ($schoolYearOptions as $option): ?>
                    <option value="<?= htmlspecialchars($option) ?>" <?= $selectedSchoolYear === $option ? "selected" : "" ?>>
                      <?= htmlspecialchars($option) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select
                  class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                  name="semester"
                  aria-label="Select semester"
                  onchange="this.form.submit()"
                >
                  <option value="" <?= $selectedSemester === "" ? "selected" : "" ?>>All Semesters</option>
                  <?php foreach ($semesterOptions as $option): ?>
                    <option value="<?= htmlspecialchars($option) ?>" <?= $selectedSemester === $option ? "selected" : "" ?>>
                      <?= htmlspecialchars($option) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if ($selectedSchoolYear !== "" || $selectedSemester !== ""): ?>
                  <a
                    href="studentAssistant-show-evaluation.php"
                    class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm"
                  >
                    Clear
                  </a>
                <?php endif; ?>
              </form>
              <div class="flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-white px-3 py-2 shadow-sm">
                <i class="fas fa-search text-[#7c8191] text-xs"></i>
                <input
                  id="showEvalSearch"
                  type="text"
                  class="w-44 bg-transparent text-xs font-semibold text-[#052c6a] outline-none placeholder:text-[#7c8191]"
                  placeholder="Search evaluations..."
                  aria-label="Search evaluations"
                />
              </div>
              <span class="rounded-full bg-[#052c6a] px-3 py-1 text-white shadow-sm">
                Entries: <?= htmlspecialchars((string)$evaluatedCount) ?>
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
                  <th class="border-r border-white/10 px-3 py-3">Applicant Name</th>
                  <th class="border-r border-white/10 px-3 py-3">Program / Course</th>
                  <th class="border-r border-white/10 px-3 py-3">Semester / S.Y.</th>
                  <th class="border-r border-white/10 px-3 py-3">Last Updated</th>
                  <th class="border-r border-white/10 px-3 py-3">Grant</th>
                  <th class="px-3 py-3">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($evaluatedApplicants)): ?>
                  <tr>
                    <td colspan="6" class="px-3 py-4 text-center text-[#052c6a]">
                      No evaluation entries yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($evaluatedApplicants as $applicant): ?>
                    <?php
                      $termLabel = "";
                      if (($applicant["semester"] ?? "") !== "" && ($applicant["school_year"] ?? "") !== "") {
                        $termLabel = (string)$applicant["semester"] . ", S.Y. " . (string)$applicant["school_year"];
                      } elseif (($applicant["semester"] ?? "") !== "") {
                        $termLabel = (string)$applicant["semester"];
                      } elseif (($applicant["school_year"] ?? "") !== "") {
                        $termLabel = (string)$applicant["school_year"];
                      }
                      $searchText = strtolower(
                        ($applicant["name"] ?? "") . " " .
                        ($applicant["program_course"] ?? "") . " " .
                        $termLabel . " " .
                        ($applicant["updated_at"] ?? "")
                      );
                    ?>
                    <tr class="border-b border-[#0d8ddb]" data-show-eval-row data-search-text="<?= htmlspecialchars($searchText) ?>">
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($applicant["name"]) ?>
                      </td>
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($applicant["program_course"]) ?>
                      </td>
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($termLabel !== "" ? $termLabel : "N/A") ?>
                      </td>
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($applicant["updated_at"]) ?>
                      </td>
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($grantLabel) ?>
                      </td>
                      <td class="px-3 py-2">
                        <button
                          class="rounded-full border border-[#052c6a] px-3 py-1 text-[11px] font-semibold text-[#052c6a] hover:bg-[#052c6a] hover:text-white"
                          type="button"
                          onclick="window.location.href='studentAssistant-evaluation-view.php?evaluation_id=<?= urlencode((string)($applicant['evaluation_id'] ?? 0)) ?>'"
                        >
                          View Evaluation
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr data-show-eval-empty class="hidden">
                    <td colspan="6" class="px-3 py-4 text-center text-[#052c6a]">
                      No matching evaluations.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
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
        }

        const searchInput = document.getElementById("showEvalSearch");
        const rows = Array.from(document.querySelectorAll("[data-show-eval-row]"));
        const emptyRow = document.querySelector("[data-show-eval-empty]");

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

