<?php
require_once __DIR__ . "/includes/school-term-filter.php";

$summaryRecords = [];
$summaryLoadError = "";

$verbalFromAverage = static function (?float $value): string {
  if ($value === null || $value <= 0) {
    return "";
  }
  if ($value >= 3.5) {
    return "Excellent";
  }
  if ($value >= 2.5) {
    return "Good";
  }
  if ($value >= 1.5) {
    return "Fair";
  }
  return "Poor";
};

if (($conn ?? null) instanceof mysqli) {
  $evaluationTableResult = $conn->query("SHOW TABLES LIKE 'department_head_evaluations'");
  $hasEvaluationTable = $evaluationTableResult instanceof mysqli_result && $evaluationTableResult->num_rows > 0;
  if ($evaluationTableResult instanceof mysqli_result) {
    $evaluationTableResult->free();
  }

  if ($hasEvaluationTable) {
    $whereClauses = ["1 = 1"];
    $params = [];
    $paramTypes = "";

    if ($selectedSchoolYear !== "") {
      $whereClauses[] = "TRIM(COALESCE(school_year, '')) = ?";
      $params[] = $selectedSchoolYear;
      $paramTypes .= "s";
    }
    if ($selectedSemester !== "") {
      $whereClauses[] = "TRIM(COALESCE(semester, '')) = ?";
      $params[] = $selectedSemester;
      $paramTypes .= "s";
    }

    $sql = "
      SELECT
        id,
        application_id,
        applicant_name,
        overall_total,
        strengths,
        recommendations,
        updated_at
      FROM department_head_evaluations
      WHERE " . implode(" AND ", $whereClauses) . "
      ORDER BY updated_at DESC, id DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
      if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
      }

      if ($stmt->execute()) {
        $result = $stmt->get_result();
        $seenApplicationIds = [];

        while ($row = $result->fetch_assoc()) {
          $applicationId = (int)($row["application_id"] ?? 0);
          $applicationKey = (string)$applicationId;
          if (isset($seenApplicationIds[$applicationKey])) {
            continue;
          }
          $seenApplicationIds[$applicationKey] = true;

          $overallTotal = (int)($row["overall_total"] ?? 0);
          $weightedMean = $overallTotal > 0 ? ($overallTotal / 15) : null;

          $summaryRecords[] = [
            "application_id" => $applicationId,
            "name" => trim((string)($row["applicant_name"] ?? "")),
            "weighted_mean" => $weightedMean,
            "verbal_description" => $verbalFromAverage($weightedMean),
            "strengths" => trim((string)($row["strengths"] ?? "")),
            "recommendations" => trim((string)($row["recommendations"] ?? "")),
          ];
        }

        if ($result instanceof mysqli_result) {
          $result->free();
        }
      } else {
        $summaryLoadError = "Unable to load summary report records.";
      }

      $stmt->close();
    } else {
      $summaryLoadError = "Unable to prepare summary report query.";
    }
  }
}

if (!empty($summaryRecords)) {
  usort($summaryRecords, static function (array $left, array $right): int {
    $leftName = strtolower(trim((string)($left["name"] ?? "")));
    $rightName = strtolower(trim((string)($right["name"] ?? "")));
    if ($leftName !== $rightName) {
      return $leftName <=> $rightName;
    }
    return ((int)($left["application_id"] ?? 0)) <=> ((int)($right["application_id"] ?? 0));
  });
}

$headerSemesterLabel = $selectedSemester !== "" ? $selectedSemester : $displaySemester;
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Student Assistants' Evaluation Summary Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <style>
      @page {
        size: 8.5in 13in;
        margin: 12mm 10mm;
      }

      ::-webkit-scrollbar {
        width: 6px;
      }

      ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
        border-radius: 999px;
      }

      .report-header {
        margin-bottom: 0.5rem;
        text-align: center;
      }

      .header-top {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 0.25rem;
      }

      .header-left {
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      .header-left img {
        width: 80px;
        height: 80px;
        object-fit: contain;
      }

      .header-left-text {
        line-height: 1.1;
        text-align: left;
      }

      .header-left-text h1 {
        font-weight: 700;
        font-size: 16pt;
        margin: 0;
      }

      .header-left-text p {
        margin: 0;
        font-size: 10pt;
      }

      .header-right {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        align-items: center;
      }

      .header-right img {
        width: 100px;
        height: 80px;
        object-fit: contain;
      }

      .title-line {
        letter-spacing: 0.5px;
      }

      .thin-rule {
        border-top: 1px solid #222;
      }

      .report-stage {
        width: 100%;
        max-width: 1250px;
        margin: 0 auto;
      }

      .report-wrapper {
        width: 100%;
      }

      .summary-table {
        width: 100%;
        table-layout: fixed;
      }

      .summary-table col.col-seq {
        width: 4%;
      }

      .summary-table col.col-name {
        width: 26%;
      }

      .summary-table col.col-weighted {
        width: 11%;
      }

      .summary-table col.col-verbal {
        width: 14%;
      }

      .summary-table col.col-strength {
        width: 20%;
      }

      .summary-table col.col-comment {
        width: 25%;
      }

      .summary-table th,
      .summary-table td {
        vertical-align: middle;
        overflow-wrap: break-word;
      }

      @media print {
        #sidebar,
        .topbar,
        .page-header,
        .term-filter-bar,
        #sidebarToggle {
          display: none !important;
        }

        html,
        body {
          margin: 0 !important;
          padding: 0 !important;
          background: #fff !important;
        }

        main {
          margin-left: 0 !important;
          padding: 0 !important;
        }

        .report-stage {
          max-width: none !important;
        }

        .report-wrapper {
          width: 100% !important;
          max-width: none !important;
          border: none !important;
          box-shadow: none !important;
          border-radius: 0 !important;
          padding: 0 !important;
        }

        .report-wrapper,
        .report-wrapper * {
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }

        .report-wrapper .overflow-x-auto {
          overflow: visible !important;
        }

        .summary-table {
          width: 100% !important;
          table-layout: fixed !important;
          font-size: 10px !important;
        }

        .summary-table col.col-seq {
          width: 4% !important;
        }

        .summary-table col.col-name {
          width: 30% !important;
        }

        .summary-table col.col-weighted {
          width: 11% !important;
        }

        .summary-table col.col-verbal {
          width: 14% !important;
        }

        .summary-table col.col-strength {
          width: 18% !important;
        }

        .summary-table col.col-comment {
          width: 23% !important;
        }

        .summary-table th,
        .summary-table td {
          padding: 3px 4px !important;
          line-height: 1.2 !important;
        }
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
  <body class="bg-[#eef2f7] font-sans">
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

      <main class="ml-0 md:ml-64 flex flex-col min-h-screen pt-14 bg-[#eef2f7]">
        <header
          class="topbar hidden fixed top-0 left-0 md:left-64 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
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
            SUMMARY EVALUATION REPORT
          </h2>
          </div>
        </section>

        <form class="term-filter-bar px-4 sm:px-6 mt-4 flex flex-wrap justify-end gap-2" method="get" action="summary-report.php">
          <select
            class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
            name="school_year"
            aria-label="Select academic year"
            onchange="this.form.submit()"
          >
            <option value="" <?php echo $selectedSchoolYear === "" ? "selected" : ""; ?>>All School Years</option>
            <?php foreach ($schoolYearOptions as $option): ?>
              <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedSchoolYear === $option ? "selected" : ""; ?>>
                <?php echo htmlspecialchars($option); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select
            class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
            name="semester"
            aria-label="Select semester"
            onchange="this.form.submit()"
          >
            <option value="" <?php echo $selectedSemester === "" ? "selected" : ""; ?>>All Semesters</option>
            <?php foreach ($semesterOptions as $option): ?>
              <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedSemester === $option ? "selected" : ""; ?>>
                <?php echo htmlspecialchars($option); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button
            type="button"
            onclick="window.print()"
            class="inline-flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-[#0d8ddb] px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-[#0a6fac]"
            aria-label="Print summary report"
          >
            <i class="fas fa-print text-[11px]"></i>
            <span>Print</span>
          </button>
          <?php if ($selectedSchoolYear !== "" || $selectedSemester !== ""): ?>
            <a
              href="summary-report.php"
              class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm"
            >
              Clear
            </a>
          <?php endif; ?>
        </form>

        <section class="px-4 sm:px-6 py-4 lg:py-6">
          <div class="report-stage">
            <div class="report-wrapper bg-white border border-slate-200 rounded-md shadow-sm p-5 sm:p-6 lg:p-8">
            <header class="report-header">
              <div class="header-top">
                <div class="header-left">
                  <img src="../img/SMCCNEWLOGO.png" alt="Seal of Saint Michael College of Caraga" />
                  <div class="header-left-text">
                    <h1 class="text-center">Saint Michael College of Caraga</h1>
                    <p class="text-center">
                      Brgy. 4, Nasipit, Agusan del Norte, Philippines<br />
                      District 8, Brgy. Triangulo, Nasipit, Agusan del Norte, Philippines
                    </p>
                    <p class="text-center">Tel. Nos. +63 085 343-3251 / +63 085 283-3113</p>
                    <p class="text-center">
                      <a href="http://www.smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">www.smccnasipit.edu.ph</a>
                    </p>
                  </div>
                </div>
                <div class="header-right">
                  <img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC ISO 9001 logo" />
                </div>
              </div>
            </header>

            <p class="text-center text-xs -mt-2">
              Website:
              <a class="text-blue-700 underline" href="http://www.smccnasipit.edu.ph">www.smccnasipit.edu.ph</a>,
              Email:
              <a class="text-blue-700 underline" href="mailto:communications@smccnasipit.edu.ph">communications@smccnasipit.edu.ph</a>
            </p>
            <p class="text-center font-semibold title-line text-sm">OFFICE OF THE ADMISSION &amp; SCHOLARSHIP</p>
            <div class="thin-rule my-1"></div>
            <section class="text-center mb-4">
              <h2 class="font-bold text-base">STUDENT ASSISTANTS' EVALUATION SUMMARY REPORT</h2>
              <p class="font-semibold text-sm"><?php echo htmlspecialchars($headerSemesterLabel); ?>, S.Y. <?php echo htmlspecialchars($displaySchoolYear); ?></p>
            </section>

            <div class="overflow-x-auto mb-6">
              <table class="summary-table text-xs border border-black border-collapse">
                <colgroup>
                  <col class="col-seq" />
                  <col class="col-name" />
                  <col class="col-weighted" />
                  <col class="col-verbal" />
                  <col class="col-strength" />
                  <col class="col-comment" />
                </colgroup>
                <thead>
                  <tr class="bg-yellow-200">
                    <th class="border border-black p-1">Seq.</th>
                    <th class="border border-black p-1">NAME OF STUDENT ASSISTANT</th>
                    <th class="border border-black p-1">Weighted<br />Mean</th>
                    <th class="border border-black p-1">Verbal<br />Description</th>
                    <th class="border border-black p-1">Strength(s)/Areas for Improvement</th>
                    <th class="border border-black p-1">Evaluator's Comment(s)/Recommendation</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($summaryLoadError !== ""): ?>
                    <tr>
                      <td class="border border-black p-2 text-center" colspan="6"><?php echo htmlspecialchars($summaryLoadError); ?></td>
                    </tr>
                  <?php elseif (empty($summaryRecords)): ?>
                    <tr>
                      <td class="border border-black p-2 text-center" colspan="6">No department evaluation results found for the selected term.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($summaryRecords as $index => $record): ?>
                      <tr>
                        <td class="border border-black p-1 text-center"><?php echo htmlspecialchars((string)($index + 1)); ?></td>
                        <td class="border border-black p-1"><?php echo htmlspecialchars((string)($record["name"] !== "" ? $record["name"] : "N/A")); ?></td>
                        <td class="border border-black p-1 text-center">
                          <?php
                            $weightedMean = $record["weighted_mean"];
                            echo $weightedMean === null ? "" : htmlspecialchars(number_format((float)$weightedMean, 2));
                          ?>
                        </td>
                        <td class="border border-black p-1 text-center"><?php echo htmlspecialchars((string)($record["verbal_description"] ?? "")); ?></td>
                        <td class="border border-black p-1 whitespace-pre-wrap">
                          <?php echo $record["strengths"] !== "" ? nl2br(htmlspecialchars((string)$record["strengths"])) : "&nbsp;"; ?>
                        </td>
                        <td class="border border-black p-1 whitespace-pre-wrap">
                          <?php echo $record["recommendations"] !== "" ? nl2br(htmlspecialchars((string)$record["recommendations"])) : "&nbsp;"; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div class="mt-8 text-sm space-y-6">
              <div>
                <p class="font-semibold">Prepared by:</p>
                <div class="mt-2">
                  <p class="font-bold">ARLYN B. TUYOGON, MMBM</p>
                  <p class="text-xs">Head, Admission &amp; Scholarship</p>
                </div>
              </div>
              <div>
                <p class="font-semibold">Checked by:</p>
                <div class="mt-2">
                  <p class="font-bold">FELMARIE MANLUNAS, MACDDS</p>
                  <p class="text-xs">Head, Student Affairs &amp; Services</p>
                </div>
              </div>
              <div>
                <p class="font-semibold">Noted by:</p>
                <div class="mt-2">
                  <p class="font-bold">RICKY E. DESTACAMENTO, RGC, MAED</p>
                  <p class="text-xs">Head, HRMDO</p>
                </div>
              </div>
              <div>
                <p class="font-semibold">Approved by:</p>
                <div class="mt-2">
                  <p class="font-bold">REV. FR. RONNIEL G. BABANO, STL</p>
                  <p class="text-xs">School President</p>
                </div>
              </div>
            </div>
          </div>
          </div>
        </section>
      </main>
    </div>

    <script>
      document.addEventListener("DOMContentLoaded", function () {
        var sidebar = document.getElementById("sidebar");
        var toggleBtn = document.getElementById("sidebarToggle");

        if (sidebar && toggleBtn) {
          toggleBtn.addEventListener("click", function () {
            sidebar.classList.toggle("-translate-x-full");
          });

          sidebar.querySelectorAll("li").forEach(function (item) {
            item.addEventListener("click", function () {
              if (window.innerWidth < 768) {
                sidebar.classList.add("-translate-x-full");
              }
            });
          });
        }
      });
    </script>
  <script>
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("sidebar");
  if (!sidebar) {
    return;
  }

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









