<?php
// Guide: Interview evaluation summary page for panelist-assigned student assistant applicants.
// Trace: load shared filters/sent applicants -> aggregate latest panel scores -> render summary table -> preview/sidebar scripts.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
$defaultBatchLabel = "All Batches";
$hasSemesterFilter = array_key_exists("semester", $_GET);
require_once __DIR__ . "/includes/school-term-filter.php";
require_once __DIR__ . "/includes/applicant-sidebar-badge.php";
require_once __DIR__ . "/includes/panelist-sent-applicants.php";

$criteriaCount = 12;
$ratingScale = [
  5 => [
    "description" => "Excellent",
    "interpretation" => "Outstanding performance; far exceeds expectations.",
  ],
  4 => [
    "description" => "Very Good",
    "interpretation" => "Above average; exceeds expectations.",
  ],
  3 => [
    "description" => "Good",
    "interpretation" => "Meets expectations satisfactorily.",
  ],
  2 => [
    "description" => "Fair",
    "interpretation" => "Below expectations; needs improvement.",
  ],
  1 => [
    "description" => "Poor",
    "interpretation" => "Far below expectations.",
  ],
];
$resolveScaleDetails = static function (float $weightedMean) use ($ratingScale): array {
  $baseRating = (int)floor($weightedMean);
  if ($baseRating < 1) {
    $baseRating = 1;
  }
  if ($baseRating > 5) {
    $baseRating = 5;
  }

  return $ratingScale[$baseRating] ?? $ratingScale[1];
};
$formatWeightedMean = static function (float $weightedMean): string {
  $truncatedWeightedMean = floor($weightedMean * 100) / 100;
  return number_format($truncatedWeightedMean, 2, ".", "");
};
$interviewSummariesByApplicantId = [];

if (($conn ?? null) instanceof mysqli && !empty($panelistSentApplicants)) {
  $applicantIds = [];
  foreach ($panelistSentApplicants as $sentApplicant) {
    $applicantId = (int)($sentApplicant["id"] ?? 0);
    if ($applicantId > 0) {
      $applicantIds[] = $applicantId;
    }
  }

  $applicantIds = array_values(array_unique($applicantIds));
  if (!empty($applicantIds)) {
    foreach ($applicantIds as $applicantId) {
      $interviewSummariesByApplicantId[$applicantId] = [
        "assigned_panel_count" => 0,
        "rated_panel_count" => 0,
        "total_points_sum" => 0.0,
      ];
    }

    $placeholders = implode(", ", array_fill(0, count($applicantIds), "?"));
    $types = str_repeat("i", count($applicantIds));
    $panelAssignmentSql = "
      SELECT application_id, COUNT(DISTINCT panelist_username) AS assigned_panel_count
      FROM panelist_queue
      WHERE application_id IN ({$placeholders})
      GROUP BY application_id
    ";
    $panelAssignmentStmt = $conn->prepare($panelAssignmentSql);
    if ($panelAssignmentStmt) {
      $panelAssignmentStmt->bind_param($types, ...$applicantIds);
      if ($panelAssignmentStmt->execute()) {
        $panelAssignmentResult = $panelAssignmentStmt->get_result();
        while ($panelAssignmentRow = $panelAssignmentResult->fetch_assoc()) {
          $applicantId = (int)($panelAssignmentRow["application_id"] ?? 0);
          if ($applicantId <= 0 || !isset($interviewSummariesByApplicantId[$applicantId])) {
            continue;
          }

          $interviewSummariesByApplicantId[$applicantId]["assigned_panel_count"] = (int)($panelAssignmentRow["assigned_panel_count"] ?? 0);
        }
        $panelAssignmentResult->free();
      }
      $panelAssignmentStmt->close();
    }

    $evaluationSql = "
      SELECT ie.applicant_id, ie.interviewer_name, ie.total_points
      FROM interview_evaluations ie
      INNER JOIN (
        SELECT applicant_id, interviewer_name, MAX(id) AS latest_id
        FROM interview_evaluations
        WHERE applicant_id IN ({$placeholders})
        GROUP BY applicant_id, interviewer_name
      ) latest ON latest.latest_id = ie.id
      ORDER BY ie.applicant_id ASC, ie.interviewer_name ASC
    ";

    $evaluationStmt = $conn->prepare($evaluationSql);
    if ($evaluationStmt) {
      $evaluationStmt->bind_param($types, ...$applicantIds);
      if ($evaluationStmt->execute()) {
        $evaluationResult = $evaluationStmt->get_result();
        while ($evaluationRow = $evaluationResult->fetch_assoc()) {
          $applicantId = (int)($evaluationRow["applicant_id"] ?? 0);
          if ($applicantId <= 0 || !isset($interviewSummariesByApplicantId[$applicantId])) {
            continue;
          }

          $totalPoints = is_numeric($evaluationRow["total_points"] ?? null)
            ? (float)$evaluationRow["total_points"]
            : 0.0;
          if ($totalPoints < 0) {
            $totalPoints = 0.0;
          }

          $interviewSummariesByApplicantId[$applicantId]["rated_panel_count"]++;
          $interviewSummariesByApplicantId[$applicantId]["total_points_sum"] += $totalPoints;
        }
        $evaluationResult->free();
      }
      $evaluationStmt->close();
    }

    foreach ($interviewSummariesByApplicantId as $applicantId => $summary) {
      $assignedPanelCount = (int)($summary["assigned_panel_count"] ?? 0);
      $ratedPanelCount = (int)($summary["rated_panel_count"] ?? 0);
      if ($assignedPanelCount <= 0 || $ratedPanelCount <= 0 || $ratedPanelCount < $assignedPanelCount) {
        continue;
      }

      $weightedMean = ((float)($summary["total_points_sum"] ?? 0.0)) / ($criteriaCount * $ratedPanelCount);
      $scaleDetails = $resolveScaleDetails($weightedMean);

      $interviewSummariesByApplicantId[$applicantId]["weighted_mean"] = $weightedMean;
      $interviewSummariesByApplicantId[$applicantId]["weighted_mean_display"] = $formatWeightedMean($weightedMean);
      $interviewSummariesByApplicantId[$applicantId]["verbal_description"] = (string)($scaleDetails["description"] ?? "");
      $interviewSummariesByApplicantId[$applicantId]["verbal_interpretation"] = (string)($scaleDetails["interpretation"] ?? "");
    }
  }
}

$headerSemesterLabel = $activeSemesterFilter !== "" ? $activeSemesterFilter : "All Semesters";
$fromApproved = isset($_GET["source"]) && strtolower((string)$_GET["source"]) === "approved";
$nextRoute = isset($_GET["next"]) ? strtolower(trim((string)$_GET["next"])) : "";
$routeApplicantId = (int)($_GET["applicant_id"] ?? 0);
$showProceedToRanks = $fromApproved && $nextRoute === "ranks";
$showClearFilters = $rawSelectedSchoolYear !== null || $rawSelectedBatch !== null || $hasSemesterFilter;
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Interview Evaluation</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
      rel="stylesheet"
    />
    <style>
      /* Custom scrollbar for sidebar */
      ::-webkit-scrollbar {
        width: 6px;
      }
      ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
        border-radius: 999px;
      }
      /* Print styling */
      @page {
        size: Legal;
        margin: 12mm 10mm 12mm 10mm;
      }
      .paper h1,
      .paper h2,
      .paper p {
        margin: 0;
      }
      .paper header {
        margin-bottom: 0.5rem;
        text-align: center;
      }
      .paper .header-top {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 0.15rem;
      }
      .paper .header-left {
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      .paper .header-left img {
        width: 76px;
        height: 76px;
        object-fit: contain;
      }
      .paper .header-left-text {
        line-height: 1.2;
        text-align: left;
      }
      .paper .header-left-text h1 {
        font-weight: 700;
        font-size: 16pt;
      }
      .paper .header-left-text p {
        font-size: 10pt;
      }
      .paper .header-right {
        display: flex;
        align-items: center;
        gap: 0.4rem;
      }
      .paper .header-right img {
        width: 96px;
        height: 74px;
        object-fit: contain;
      }
      .title-line {
        font-weight: 700;
        letter-spacing: 0.02em;
      }
      .plain-table {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
      .plain-table table {
        border-collapse: collapse;
        width: 100%;
        min-width: 760px;
      }
      .plain-table th,
      .plain-table td {
        border: 1px solid #000;
        font-size: 10pt;
        padding: 6px 7px;
        text-align: center;
      }
      .plain-table td:nth-child(2) {
        text-align: left;
      }
      .plain-table thead th {
        background: #f1c40f;
        color: #000;
        font-weight: 700;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      @media (max-width: 767px) {
        .paper .header-left {
          flex-direction: column;
          align-items: center;
          text-align: center;
        }
        .paper .header-left-text {
          text-align: center;
        }
        .paper .header-left img {
          width: 62px;
          height: 62px;
        }
        .paper .header-right img {
          width: 82px;
          height: 62px;
        }
        .paper .header-left-text h1 {
          font-size: 13.5pt;
        }
        .paper .header-left-text p {
          font-size: 9pt;
        }
        .plain-table th,
        .plain-table td {
          font-size: 9pt;
          padding: 5px;
        }
      }
      .sig-role {
        font-size: 10pt;
      }
      @media print {
        html, body {
          margin: 0 !important;
          padding: 0 !important;
          width: 100% !important;
          background: #fff !important;
        }
        #sidebar,
        .print-btn-bar,
        .admin-topbar,
        main > .page-header {
          display: none !important;
        }
        main, section {
          margin: 0 !important;
          padding: 0 !important;
          width: 100% !important;
        }
        .paper {
          border: none !important;
          box-shadow: none !important;
          padding: 0 !important;
          margin: 0 auto !important;
          background: #fff !important;
        }
        .paper-wrap {
          max-width: 100% !important;
          width: 100% !important;
          padding: 0 4px 12px 4px !important;
        }
        .plain-table {
          overflow: visible !important;
        }
        .plain-table table {
          min-width: 0 !important;
        }
        .plain-table .remarks-content {
          display: none !important;
        }
        .paper .header-top {
          flex-wrap: nowrap !important;
          align-items: center !important;
          justify-content: center !important;
          gap: 0.75rem !important;
        }
        .paper .header-left {
          flex-direction: row !important;
          align-items: center !important;
          gap: 0.5rem !important;
        }
        .paper .header-left-text {
          text-align: left !important;
        }
      }
          #sidebar > nav > ul {
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
      .panelist-eval-modal[hidden] {
        display: none !important;
      }
      .panelist-eval-frame {
        width: 100%;
        height: 100%;
        border: 0;
        background: #fff;
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
            <li class="mb-1">
              <details class="group">
                <summary
                  class="flex cursor-pointer list-none items-center gap-2 rounded-[0.85rem] px-4 py-3 text-left hover:bg-white/15"
                  style="list-style: none;"
                  data-nav="applicant.php"
                >
                  <i class="fas fa-user-graduate w-5"></i>
                  <span class="flex-1">Applicants</span>
                  <i class="fas fa-chevron-down text-[10px] transition group-open:rotate-180"></i>
                </summary>
                <ul class="ml-8 mt-1 space-y-1 border-l border-white/20 pl-3 text-[11px] font-semibold">
                  <li>
                    <a href="applicant.php" class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-blue-50 hover:bg-white/15">
                      <span>Pending Applicants</span>
                      <span class="inline-flex min-w-[1.65rem] items-center justify-center rounded-full bg-gradient-to-r from-[#fcdc2f] to-[#ffe889] px-2 py-0.5 text-[10px] font-extrabold leading-none text-[#052c6a] shadow-[0_0_0_1px_rgba(255,255,255,0.35),0_6px_14px_rgba(252,220,47,0.28)]">
                        <?= htmlspecialchars($sidebarPendingApplicantBadge ?? '0') ?>
                      </span>
                    </a>
                  </li>
                  <li>
                    <a href="declined-applicants.php" class="block rounded-lg px-3 py-2 text-blue-50 hover:bg-white/15">
                      Declined Applicants
                    </a>
                  </li>
                  <li>
                    <a href="reserved-applicants.php" class="block rounded-lg px-3 py-2 text-blue-50 hover:bg-white/15">
                      Reserved Applicants
                    </a>
                  </li>
                  <li>
                    <a href="summary-of-applicants.php" class="block rounded-lg px-3 py-2 text-blue-50 hover:bg-white/15">
                      Summary of Applicants
                    </a>
                  </li>
                </ul>
              </details>
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
              <span>Settings</span>
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

      <!-- Main content -->
      <main class="ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pt-14">
        <!-- Top bar -->
        <header
          class="admin-topbar hidden fixed top-0 left-0 md:left-64 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
        >
          <div class="flex items-center gap-2">
            <!-- Mobile menu button -->
            <button
              id="sidebarToggleTop"
              class="md:hidden inline-flex items-center justify-center p-2 rounded bg-[#0d8ddb] focus:outline-none"
              type="button"
            >
              <i class="fas fa-bars"></i>
            </button>
            <span class="text-[11px] font-semibold md:hidden">
              Admission &amp; Scholarship
            </span>
          </div>
          <div class="flex gap-2 text-xs">
            <button
              class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 flex items-center gap-1 font-normal"
              type="button"
            >
              <i class="fas fa-user"></i>
              Admin panel
            </button>
            <button
              class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 font-normal"
              type="button"
            >
              Account
            </button>
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
            INTERVIEW EVALUATION
          </h2>
          </div>
        </section>

        <!-- Print-friendly Interview Result -->
        <section class="px-3 sm:px-6 py-4 sm:py-6">
          <?php if ($showProceedToRanks): ?>
            <div class="mb-3 rounded-lg border border-[#0d8ddb] bg-[#e8f3ff] px-4 py-3 text-xs font-semibold text-[#052c6a] no-print">
              Applicant routed from Approved Applications.
              <a
                href="ranks.php<?php echo $routeApplicantId > 0 ? '?applicant_id=' . urlencode((string)$routeApplicantId) . '&source=interview' : ''; ?>"
                class="ml-2 inline-flex items-center rounded border border-[#0d8ddb] bg-white px-2 py-1 text-[11px] font-semibold text-[#0d8ddb] hover:bg-[#0d8ddb] hover:text-white"
              >
                Proceed to Ranks
              </a>
            </div>
          <?php endif; ?>

          <div class="no-print print-btn-bar mb-3 rounded-lg border border-[#0d8ddb] bg-white p-3 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <form class="flex flex-wrap items-center gap-2 lg:justify-end" method="get" action="interviewEvaluation.php">
                <select
                  id="academicYear"
                  name="school_year"
                  class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                  aria-label="Select academic year"
                  onchange="this.form.submit()"
                >
                  <option value="" <?php echo $rawSelectedSchoolYear !== null && $activeSchoolYearFilter === "" ? "selected" : ""; ?>>All School Years</option>
                  <?php foreach ($schoolYearOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $activeSchoolYearFilter === $option ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($option); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select
                  id="semesterSelect"
                  name="semester"
                  class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
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
                <select
                  id="batchSelect"
                  name="batch"
                  class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                  aria-label="Select batch"
                  onchange="this.form.submit()"
                >
                  <option value="" <?php echo $activeBatchFilter === "" ? "selected" : ""; ?>>All Batches</option>
                  <?php foreach ($batchOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $activeBatchFilter === $option ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($option); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if ($showClearFilters): ?>
                  <a
                    href="interviewEvaluation.php"
                    class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm"
                  >
                    Clear
                  </a>
                <?php endif; ?>
              </form>
              <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-[#0d8ddb] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#0a6fac]"
              >
                <i class="fas fa-print"></i>
                <span>Print</span>
              </button>
            </div>
          </div>

          <div class="bg-white border border-[#0d8ddb] rounded shadow-sm p-4 md:p-6 paper">
            <div class="w-full mx-auto paper-wrap">
              <header>
                <div class="header-top">
                  <div class="header-left">
                    <img src="../img/SMCCNEWLOGO.png" alt="Seal of Saint Michael College of Caraga" />
                    <div class="header-left-text">
                      <h1 class="text-center">Saint Michael College of Caraga</h1>
                      <p class="text-center">Brgy. 4, Nasipit, Agusan del Norte, Philippines</p>
                      <p class="text-center">Tel. No. 085 225-0208</p>
                      <p class="text-center">
                        Website: <a href="http://www.smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">www.smccnasipit.edu.ph</a>,
                        Email: <a href="mailto:communications@smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">communications@smccnasipit.edu.ph</a>
                      </p>
                    </div>
                  </div>
                  <div class="header-right">
                    <img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC ISO 9001 logo" />
                  </div>
                </div>
              </header>

              <div class="text-center mb-1">
                <div class="title-line">Office of the Admission &amp; Scholarship</div>
              </div>
              <hr class="border-black mb-3" />

              <section class="text-center mb-4">
                <h2 class="font-bold text-base">Student Assistance Applicants' Interview Result</h2>
                <p class="font-semibold text-sm"><?php echo htmlspecialchars($headerSemesterLabel); ?>, S.Y. <?php echo htmlspecialchars($activeSchoolYearFilter !== "" ? $activeSchoolYearFilter : "All School Years"); ?></p>
                <p class="font-semibold text-sm"><?php echo htmlspecialchars($displayBatch); ?></p>
              </section>

              <section class="plain-table mb-8">
                <table>
                  <thead>
                    <tr>
                      <th style="width: 40px;">SEQ.</th>
                      <th>NAME OF APPLICANT</th>
                      <th style="width: 110px;">WEIGHTED MEAN</th>
                      <th style="width: 140px;">VERBAL DESCRIPTION</th>
                      <th style="width: 150px;">VERBAL INTERPRETATION</th>
                      <th style="width: 120px;">REMARKS</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($panelistSentApplicants)): ?>
                      <?php foreach ($panelistSentApplicants as $index => $sentApplicant): ?>
                        <?php $summary = $interviewSummariesByApplicantId[(int)($sentApplicant["id"] ?? 0)] ?? []; ?>
                        <?php $ratedPanelCount = (int)($summary["rated_panel_count"] ?? 0); ?>
                        <?php $applicantId = (int)($sentApplicant["id"] ?? 0); ?>
                        <tr>
                          <td><?= htmlspecialchars((string)($index + 1)) ?></td>
                          <td><?= htmlspecialchars((string)($sentApplicant["name"] ?? "")) ?></td>
                          <td><?= htmlspecialchars((string)($summary["weighted_mean_display"] ?? "")) ?></td>
                          <td><?= htmlspecialchars((string)($summary["verbal_description"] ?? "")) ?></td>
                          <td><?= htmlspecialchars((string)($summary["verbal_interpretation"] ?? "")) ?></td>
                          <td class="remarks-cell">
                            <?php if ($applicantId > 0 && $ratedPanelCount > 0): ?>
                              <button
                                type="button"
                                data-open-panelist-eval
                                data-applicant-id="<?= htmlspecialchars((string)$applicantId) ?>"
                                data-applicant-name="<?= htmlspecialchars((string)($sentApplicant["name"] ?? "")) ?>"
                                class="remarks-content inline-flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-white px-3 py-1.5 text-[11px] font-semibold text-[#0d8ddb] shadow-sm hover:bg-[#0d8ddb] hover:text-white"
                              >
                                <i class="fas fa-eye"></i>
                                <span>View Eval.</span>
                              </button>
                            <?php else: ?>
                              <span class="remarks-content text-[11px] text-slate-400">No evaluation yet</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </section>

              <div class="mt-6 grid grid-cols-1 gap-6 max-w-3xl">
                <div>
                  <div class="text-[10pt] mb-1">Prepared by:</div>
                  <div class="font-semibold">ARLYN B. TUYOGON, MMBM</div>
                  <div class="sig-role">Head, Admission &amp; Scholarship</div>
                </div>

                <div>
                  <div class="text-[10pt] mb-1">Checked by:</div>
                  <div class="font-semibold">FELMARIE MANLUNAS, MACDDS</div>
                  <div class="sig-role">Head, Student Affairs &amp; Services</div>
                </div>

                <div>
                  <div class="text-[10pt] mb-1">Noted by:</div>
                  <div class="font-semibold">RICKY E. DESTACAMENTO, RGC, MAED</div>
                  <div class="sig-role">Head, HRMDO</div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <div
          id="panelistEvalModal"
          class="panelist-eval-modal fixed inset-0 z-50 bg-slate-950/60 px-3 py-4 sm:px-6 sm:py-6"
          hidden
        >
          <div class="mx-auto flex h-full max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
              <div>
                <p class="text-sm font-semibold text-slate-800">Panelist Evaluation</p>
                <p id="panelistEvalModalLabel" class="text-xs text-slate-500">Loading evaluation...</p>
              </div>
              <div class="flex items-center gap-2">
                <button
                  id="panelistEvalPrintBtn"
                  type="button"
                  class="inline-flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-[#0d8ddb] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#0a6fac]"
                >
                  <i class="fas fa-print"></i>
                  <span>Print</span>
                </button>
                <button
                  id="panelistEvalCloseBtn"
                  type="button"
                  class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-100"
                >
                  <i class="fas fa-times"></i>
                  <span>Close</span>
                </button>
              </div>
            </div>
            <div class="relative flex-1 bg-slate-100">
              <div
                id="panelistEvalLoading"
                class="absolute inset-0 flex items-center justify-center bg-slate-100 text-sm font-semibold text-slate-500"
              >
                Loading evaluation...
              </div>
              <iframe
                id="panelistEvalFrame"
                class="panelist-eval-frame"
                src="about:blank"
                title="Panelist Evaluation Preview"
              ></iframe>
            </div>
          </div>
        </div>
      </main>
    </div>

    <script>
      // Sidebar toggle for mobile
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");

        if (toggleBtn && sidebar) {
          toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
          });

          // Close sidebar when clicking any nav item on small screens
          sidebar.querySelectorAll("li").forEach((item) => {
            item.addEventListener("click", (event) => {
              if (event.target.closest("summary")) {
                return;
              }
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

  // Highlight the current admin page in the shared sidebar menu.

  const currentPage = window.location.pathname.split("/").pop().toLowerCase();
  const sidebarAliases = {
    "summary-of-applicants.php": "applicant.php",
    "declined-applicants.php": "applicant.php",
    "reserved-applicants.php": "applicant.php",
    "view-application.php": "applicant.php",
    "department-evaluation-indi.php": "department-evaluation-list.php",
    "summary-reports.php": "summary-report.php",
    "list-0f-qualified.php": "list-of-qualified.php"
  };
  const activePage = sidebarAliases[currentPage] || currentPage;

  sidebar.querySelectorAll("[data-nav]").forEach((item) => {
    const target = (item.dataset.nav || "").toLowerCase();
    const isActive = target === activePage;
    item.classList.toggle("bg-[#fcdc2f]", isActive);
    item.classList.toggle("bg-opacity-90", isActive);
    item.classList.toggle("text-[#052c6a]", isActive);
    item.classList.toggle("hover:bg-white/15", !isActive);
  });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  // Open an iframe preview of the latest panelist evaluation and allow in-modal printing.
  const modal = document.getElementById("panelistEvalModal");
  const frame = document.getElementById("panelistEvalFrame");
  const loading = document.getElementById("panelistEvalLoading");
  const closeBtn = document.getElementById("panelistEvalCloseBtn");
  const printBtn = document.getElementById("panelistEvalPrintBtn");
  const label = document.getElementById("panelistEvalModalLabel");
  const openButtons = document.querySelectorAll("[data-open-panelist-eval]");

  if (!modal || !frame || !loading || !closeBtn || !printBtn || !label || openButtons.length === 0) {
    return;
  }

  const previewBaseUrl = "../Panelist/panelist_eval_view.php";
  let lastFocusedElement = null;

  const closeModal = () => {
    modal.hidden = true;
    document.body.classList.remove("overflow-hidden");
    frame.src = "about:blank";
    loading.classList.add("hidden");
    label.textContent = "Loading evaluation...";
    if (lastFocusedElement instanceof HTMLElement) {
      lastFocusedElement.focus();
    }
  };

  const openModal = (button) => {
    const applicantId = parseInt(button.dataset.applicantId || "0", 10);
    const applicantName = (button.dataset.applicantName || "").trim();
    if (!applicantId) {
      return;
    }

    lastFocusedElement = button;
    label.textContent = applicantName !== "" ? applicantName : "Loading evaluation...";
    loading.classList.remove("hidden");
    frame.src = `${previewBaseUrl}?applicant_id=${encodeURIComponent(String(applicantId))}&admin_preview=1`;
    modal.hidden = false;
    document.body.classList.add("overflow-hidden");
  };

  openButtons.forEach((button) => {
    button.addEventListener("click", () => openModal(button));
  });

  frame.addEventListener("load", () => {
    loading.classList.add("hidden");
  });

  closeBtn.addEventListener("click", closeModal);

  printBtn.addEventListener("click", () => {
    if (!frame.contentWindow) {
      return;
    }

    frame.contentWindow.focus();
    frame.contentWindow.print();
  });

  modal.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !modal.hidden) {
      closeModal();
    }
  });
});
</script>
    <script>
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        if (!sidebar) return;

        const currentPage = window.location.pathname.split("/").pop().toLowerCase();
        const applicantPages = new Set([
          "applicant.php",
          "declined-applicants.php",
          "reserved-applicants.php",
          "summary-of-applicants.php",
          "view-application.php"
        ]);
        const applicantMenuTrigger = sidebar.querySelector('summary[data-nav="applicant.php"]');
        const applicantMenu = applicantMenuTrigger ? applicantMenuTrigger.closest("details") : null;
        if (applicantMenu) {
          applicantMenu.open = applicantPages.has(currentPage);
        }

        const applicantSubmenuAliases = {
          "view-application.php": "applicant.php"
        };
        const activeApplicantSubmenu = applicantSubmenuAliases[currentPage] || currentPage;
        sidebar.querySelectorAll('details a[href]').forEach((link) => {
          const linkPage = link.getAttribute("href").split("?")[0].split("#")[0].split("/").pop().toLowerCase();
          const isActive = linkPage === activeApplicantSubmenu;
          link.classList.toggle("bg-white/15", isActive);
          link.classList.toggle("text-white", isActive);
          link.classList.toggle("font-bold", isActive);
          link.classList.toggle("text-blue-50", !isActive);
          link.classList.toggle("hover:bg-white/15", !isActive);
        });
      });
    </script>  </body>
</html>

















