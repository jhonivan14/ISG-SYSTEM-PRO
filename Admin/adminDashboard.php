<?php
// Guide: Admin home dashboard that aggregates counts and chart-ready datasets.
// Trace: load dashboard filters -> gather totals/trends -> render cards/charts -> sidebar scripts.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once '../db.php';
require_once __DIR__ . "/includes/school-term-filter.php";
require_once __DIR__ . "/includes/applicant-sidebar-badge.php";
require_once "../scholarship-grants.php";

$showLoginSuccess = isset($_GET["login"]) && trim((string)$_GET["login"]) === "success";

$dashboardSchoolYear = $displaySchoolYear;
$totalScholarsCount = 0;
$registeredPanelistsCount = 0;
$registeredHeadOfficesCount = 0;
$pendingCount = 0;
$dashboardGrantLabels = [
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
$dashboardGrantLabels = isg_load_scholarship_grant_names($conn);
$requestedDashboardGrantId = (int)($_GET["grant_id"] ?? 0);
$dashboardGrantId = array_key_exists($requestedDashboardGrantId, $dashboardGrantLabels) ? $requestedDashboardGrantId : 0;
$dashboardGrantLabel = $dashboardGrantId > 0
  ? $dashboardGrantLabels[$dashboardGrantId]
  : "All Grants and Discounts";
$dashboardRefreshParams = ["school_year" => $dashboardSchoolYear];
if ($dashboardGrantId > 0) {
  $dashboardRefreshParams["grant_id"] = $dashboardGrantId;
}

// Helper: build a stable scholar key so repeated source rows are counted only once.

function adminDashboardScholarCountKey(array $row): string
{
  $sourceApplicationId = (int)($row["source_application_id"] ?? 0);
  if ($sourceApplicationId > 0) {
    return "src-" . $sourceApplicationId;
  }

  $scholarId = strtolower(trim((string)($row["scholar_id"] ?? "")));
  if ($scholarId !== "") {
    return "sid-" . $scholarId;
  }

  $fullName = strtolower(trim((string)($row["full_name"] ?? "")));
  $programYear = strtolower(trim((string)($row["program_year"] ?? "")));
  $academicYear = strtolower(trim((string)($row["academic_year"] ?? "")));
  if ($fullName === "" && $programYear === "" && $academicYear === "") {
    return "";
  }

  return "name-" . sha1($fullName . "|" . $programYear . "|" . $academicYear);
}

$scholarTableResult = $conn->query("SHOW TABLES LIKE 'institutional_scholar_records'");
if ($scholarTableResult instanceof mysqli_result) {
  $hasScholarTable = $scholarTableResult->num_rows > 0;
  $scholarTableResult->free();
  if ($hasScholarTable) {
    $hasContractEndedColumn = false;
    $contractEndedColumnResult = $conn->query("SHOW COLUMNS FROM institutional_scholar_records LIKE 'contract_ended'");
    if ($contractEndedColumnResult instanceof mysqli_result) {
      $hasContractEndedColumn = $contractEndedColumnResult->num_rows > 0;
      $contractEndedColumnResult->free();
    }

    $scholarWhereClauses = ["TRIM(COALESCE(academic_year, '')) = ?"];
    if ($hasContractEndedColumn) {
      $scholarWhereClauses[] = "COALESCE(contract_ended, 0) = 0";
    }

    $scholarCountSql = "
      SELECT
        source_application_id,
        scholar_id,
        full_name,
        program_year,
        academic_year
      FROM institutional_scholar_records
      WHERE " . implode(" AND ", $scholarWhereClauses) . "
      ORDER BY id DESC
    ";
    $scholarCountStmt = $conn->prepare($scholarCountSql);
    if ($scholarCountStmt) {
      $scholarCountStmt->bind_param("s", $dashboardSchoolYear);
      if ($scholarCountStmt->execute()) {
        $scholarCountResult = $scholarCountStmt->get_result();
        $uniqueScholarKeys = [];
        while ($scholarRow = $scholarCountResult->fetch_assoc()) {
          $rowKey = adminDashboardScholarCountKey($scholarRow);
          if ($rowKey !== "") {
            $uniqueScholarKeys[$rowKey] = true;
          }
        }
        $totalScholarsCount = count($uniqueScholarKeys);
        if ($scholarCountResult instanceof mysqli_result) {
          $scholarCountResult->free();
        }
      }
      $scholarCountStmt->close();
    }
  }
}

$panelistCountResult = $conn->query("SELECT COUNT(*) AS total FROM panelists");
if ($panelistCountResult instanceof mysqli_result) {
  $panelistCountRow = $panelistCountResult->fetch_assoc();
  $registeredPanelistsCount = (int)($panelistCountRow["total"] ?? 0);
  $panelistCountResult->free();
}

$headOfficeCountResult = $conn->query("SELECT COUNT(*) AS total FROM head_offices");
if ($headOfficeCountResult instanceof mysqli_result) {
  $headOfficeCountRow = $headOfficeCountResult->fetch_assoc();
  $registeredHeadOfficesCount = (int)($headOfficeCountRow["total"] ?? 0);
  $headOfficeCountResult->free();
}

$pendingQuery = "
  SELECT COUNT(*) AS total
  FROM applications
  WHERE (
      status IS NULL
      OR TRIM(status) = ''
      OR LOWER(TRIM(status)) = 'pending'
      OR LOWER(TRIM(status)) = 'reapplied'
    )
    AND TRIM(COALESCE(school_year, '')) = ?
";
$pendingStmt = $conn->prepare($pendingQuery);
if ($pendingStmt) {
  $pendingStmt->bind_param("s", $dashboardSchoolYear);
  if ($pendingStmt->execute()) {
    $pendingResult = $pendingStmt->get_result();
    $pendingRow = $pendingResult ? $pendingResult->fetch_assoc() : null;
    if (is_array($pendingRow)) {
      $pendingCount = (int)($pendingRow["total"] ?? 0);
    }
    if ($pendingResult instanceof mysqli_result) {
      $pendingResult->free();
    }
  }
  $pendingStmt->close();
}

$chartYears = [];
$barData = [];
$lineData = [];
$grantCategoryLabels = [
  "student_assistance" => "Student Assistance",
  "academic" => "Academic",
  "kabayani" => "Kabayani",
  "others" => "Others",
];
$grantCategoryCounts = array_fill_keys(array_keys($grantCategoryLabels), 0);

$yearSql = "
  SELECT DISTINCT school_year
  FROM applications
  WHERE school_year IS NOT NULL
    AND TRIM(school_year) <> ''
";
if ($dashboardGrantId > 0) {
  $yearSql .= " AND grant_id = ?";
}
$yearSql .= " ORDER BY school_year ASC";
$yearStmt = $conn->prepare($yearSql);
if ($yearStmt) {
  if ($dashboardGrantId > 0) {
    $yearStmt->bind_param("i", $dashboardGrantId);
  }
  if ($yearStmt->execute()) {
    $yearResult = $yearStmt->get_result();
    if ($yearResult instanceof mysqli_result) {
      while ($row = $yearResult->fetch_assoc()) {
        $value = trim((string)($row["school_year"] ?? ""));
        if ($value !== "") {
          $chartYears[] = $value;
        }
      }
      $yearResult->free();
    }
  }
  $yearStmt->close();
}
if (!in_array($currentSchoolYear, $chartYears, true)) {
  $chartYears[] = $currentSchoolYear;
}
$chartYears = array_values(array_unique($chartYears));
usort($chartYears, function ($a, $b) {
  $aYear = (int)substr($a, 0, 4);
  $bYear = (int)substr($b, 0, 4);
  if ($aYear === $bYear) {
    return strcmp($a, $b);
  }
  return $aYear <=> $bYear;
});

$barSql = "
  SELECT school_year, semester, COUNT(*) AS total
  FROM applications
  WHERE school_year IS NOT NULL
    AND TRIM(school_year) <> ''
    AND semester IS NOT NULL
    AND TRIM(semester) <> ''
";
if ($dashboardGrantId > 0) {
  $barSql .= " AND grant_id = ?";
}
$barSql .= "
  GROUP BY school_year, semester
  ORDER BY school_year ASC
";
$barStmt = $conn->prepare($barSql);
if ($barStmt) {
  if ($dashboardGrantId > 0) {
    $barStmt->bind_param("i", $dashboardGrantId);
  }
  if ($barStmt->execute()) {
    $result = $barStmt->get_result();
    if ($result instanceof mysqli_result) {
      while ($row = $result->fetch_assoc()) {
        $year = (string)($row["school_year"] ?? "");
        $semester = (string)($row["semester"] ?? "");
        $total = (int)($row["total"] ?? 0);
        if ($year !== "") {
          if (!isset($barData[$year])) {
            $barData[$year] = [
              "1st Semester" => 0,
              "2nd Semester" => 0,
            ];
          }
          if (isset($barData[$year][$semester])) {
            $barData[$year][$semester] = $total;
          }
        }
      }
      $result->free();
    }
  }
  $barStmt->close();
}

$lineSql = "
  SELECT school_year,
    COUNT(*) AS total,
    SUM(CASE WHEN LOWER(TRIM(status)) = 'approved' THEN 1 ELSE 0 END) AS qualified
  FROM applications
  WHERE school_year IS NOT NULL
    AND TRIM(school_year) <> ''
";
if ($dashboardGrantId > 0) {
  $lineSql .= " AND grant_id = ?";
}
$lineSql .= "
  GROUP BY school_year
  ORDER BY school_year ASC
";
$lineStmt = $conn->prepare($lineSql);
if ($lineStmt) {
  if ($dashboardGrantId > 0) {
    $lineStmt->bind_param("i", $dashboardGrantId);
  }
  if ($lineStmt->execute()) {
    $result = $lineStmt->get_result();
    if ($result instanceof mysqli_result) {
      while ($row = $result->fetch_assoc()) {
        $year = (string)($row["school_year"] ?? "");
        if ($year !== "") {
          $lineData[$year] = [
            "total" => (int)($row["total"] ?? 0),
            "qualified" => (int)($row["qualified"] ?? 0),
          ];
        }
      }
      $result->free();
    }
  }
  $lineStmt->close();
}

$grantCategorySql = "
  SELECT
    CASE
      WHEN grant_id = 1 THEN 'student_assistance'
      WHEN grant_id = 2 THEN 'academic'
      WHEN grant_id IN (4, 5) THEN 'kabayani'
      ELSE 'others'
    END AS grant_category,
    COUNT(*) AS total
  FROM applications
  WHERE TRIM(COALESCE(school_year, '')) = ?
";
if ($dashboardGrantId > 0) {
  $grantCategorySql .= " AND grant_id = ?";
}
$grantCategorySql .= " GROUP BY grant_category";
$grantCategoryStmt = $conn->prepare($grantCategorySql);
if ($grantCategoryStmt) {
  if ($dashboardGrantId > 0) {
    $grantCategoryStmt->bind_param("si", $dashboardSchoolYear, $dashboardGrantId);
  } else {
    $grantCategoryStmt->bind_param("s", $dashboardSchoolYear);
  }
  if ($grantCategoryStmt->execute()) {
    $result = $grantCategoryStmt->get_result();
    if ($result instanceof mysqli_result) {
      while ($row = $result->fetch_assoc()) {
        $categoryKey = (string)($row["grant_category"] ?? "others");
        if (array_key_exists($categoryKey, $grantCategoryCounts)) {
          $grantCategoryCounts[$categoryKey] = (int)($row["total"] ?? 0);
        }
      }
      $result->free();
    }
  }
  $grantCategoryStmt->close();
}

$firstSemCounts = [];
$secondSemCounts = [];
$lineApplicants = [];
$lineQualified = [];
foreach ($chartYears as $year) {
  $firstSemCounts[] = $barData[$year]["1st Semester"] ?? 0;
  $secondSemCounts[] = $barData[$year]["2nd Semester"] ?? 0;
  $lineApplicants[] = $lineData[$year]["total"] ?? 0;
  $lineQualified[] = $lineData[$year]["qualified"] ?? 0;
}
$grantCategoryChartLabels = [];
$grantCategoryChartCounts = [];
foreach ($grantCategoryLabels as $categoryKey => $categoryLabel) {
  $grantCategoryChartLabels[] = $categoryLabel;
  $grantCategoryChartCounts[] = $grantCategoryCounts[$categoryKey] ?? 0;
}
$grantCategoryTotal = array_sum($grantCategoryChartCounts);
?>

<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Admin Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
      rel="stylesheet"
    />
    <!-- Charts CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
      /* Custom scrollbar for sidebar */
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
      .school-print-header {
        color: #000;
        font-family: "Times New Roman", serif;
      }
      .school-print-header h1,
      .school-print-header h2,
      .school-print-header p {
        margin: 0;
      }
      .school-print-header header {
        margin-bottom: 0.5rem;
        text-align: center;
      }
      .school-print-header .header-top {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 0.15rem;
      }
      .school-print-header .header-left {
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      .school-print-header .header-left img {
        width: 76px;
        height: 76px;
        object-fit: contain;
      }
      .school-print-header .header-left-text {
        line-height: 1.2;
        text-align: left;
      }
      .school-print-header .header-left-text h1 {
        font-size: 16pt;
        font-weight: 700;
      }
      .school-print-header .header-left-text p {
        font-size: 10pt;
      }
      .school-print-header .header-right {
        display: flex;
        align-items: center;
        gap: 0.4rem;
      }
      .school-print-header .header-right img {
        width: 96px;
        height: 74px;
        object-fit: contain;
      }
      .school-print-header .title-line {
        font-weight: 700;
        letter-spacing: 0.02em;
      }

      @media print {
        @page {
          size: letter portrait;
          margin: 10mm;
        }
        html,
        body {
          background: #ffffff !important;
          -webkit-print-color-adjust: exact;
          print-color-adjust: exact;
        }
        #sidebar,
        .page-header,
        .no-print {
          display: none !important;
        }
        main {
          margin-left: 0 !important;
          padding-top: 0 !important;
          background: #ffffff !important;
        }
        .dashboard-report-section {
          margin: 0 !important;
          padding: 0 !important;
          width: 100% !important;
        }
        .dashboard-chart-grid {
          display: block !important;
        }
        .print-panel {
          box-shadow: none !important;
          border-color: #cbd5e1 !important;
          background: #ffffff !important;
          border-radius: 2px !important;
          margin: 0 0 10px 0 !important;
          padding: 10px !important;
          break-inside: avoid;
          page-break-inside: avoid;
        }
        .print-only {
          display: block !important;
        }
        .print-only h1 {
          font-size: 18px !important;
          line-height: 1.2 !important;
        }
        .print-only p,
        .print-panel p {
          font-size: 10px !important;
          line-height: 1.25 !important;
        }
        .print-panel .text-sm {
          font-size: 12px !important;
          line-height: 1.25 !important;
        }
        .chart-box {
          align-items: center !important;
          display: flex !important;
          height: 170px !important;
          justify-content: center !important;
          overflow: hidden !important;
          width: 100% !important;
        }
        .pie-report-layout {
          display: block !important;
        }
        .pie-chart-box {
          height: 240px !important;
          margin: 0 auto !important;
          max-width: 360px !important;
          width: 360px !important;
        }
        .grant-category-summary {
          display: grid !important;
          gap: 5px !important;
          grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
          margin-top: 8px !important;
        }
        .grant-category-summary > div {
          padding: 5px 8px !important;
        }
        canvas {
          display: block !important;
          margin: 0 auto !important;
          max-height: 100% !important;
          max-width: 100% !important;
        }
        .school-print-header .header-top {
          flex-wrap: nowrap !important;
          align-items: center !important;
          justify-content: center !important;
          gap: 0.75rem !important;
        }
        .school-print-header .header-left {
          flex-direction: row !important;
          align-items: center !important;
          gap: 0.5rem !important;
        }
        .school-print-header .header-left-text {
          text-align: left !important;
        }
        .school-print-header .header-left-text h1 {
          font-size: 16pt !important;
          line-height: 1.2 !important;
        }
        .school-print-header .header-left-text p {
          font-size: 10pt !important;
          line-height: 1.2 !important;
        }
      }
      .print-only {
        display: none;
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
          class="hidden fixed top-0 left-0 md:left-64 right-0 z-20 h-12 items-center justify-between bg-gradient-to-b from-[#04295f] via-[#0a4676] to-[#0d517f] text-white text-xs px-4"
        >
          <div class="flex items-center gap-2">
            <!-- Mobile menu button -->
            <button
              id="sidebarToggleTop"
              class="md:hidden inline-flex items-center justify-center p-2 rounded border border-white/35 bg-white/15 hover:bg-white/25 focus:outline-none transition-colors"
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
              class="bg-[#f6d968] text-[#07366f] rounded px-3 py-1 flex items-center gap-1 font-semibold shadow-sm"
              type="button"
            >
              <i class="fas fa-user"></i>
              Admin panel
            </button>
            <button
              class="bg-white/20 text-white rounded px-3 py-1 font-normal border border-white/35 hover:bg-white/30 transition-colors"
              type="button"
            >
              Account ▾
            </button>
          </div>
        </header>

        <!-- Dashboard header -->
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
            ADMIN DASHBOARD
            </h2>
          </div>
        </section>

        

        <!-- Stats cards above charts -->
        <section
          class="px-4 sm:px-6 pt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 no-print"
        >
          <!-- Total Scholars -->
          <div
            class="bg-[#052c6a] text-white rounded-lg p-4 flex items-center justify-between shadow"
          >
            <div>
              <p class="text-xs text-[#fcdc2f] uppercase tracking-wide">
                Total Scholars
              </p>
              <p class="text-2xl font-bold mt-1"><?php echo htmlspecialchars(number_format($totalScholarsCount)); ?></p>
              <p class="text-[11px] text-gray-200 mt-1">
                Institutional scholars for S.Y. <?php echo htmlspecialchars($dashboardSchoolYear); ?>
              </p>
            </div>
            <div class="text-3xl opacity-80">
              <i class="fas fa-user-graduate"></i>
            </div>
          </div>

          <!-- Applicants -->
          <div
            class="bg-white border border-[#0d8ddb] text-[#052c6a] rounded-lg p-4 flex items-center justify-between shadow-sm"
          >
            <div>
              <p class="text-xs text-[#0d8ddb] uppercase tracking-wide">
                Applicants
              </p>
              <p class="text-2xl font-bold mt-1"><?php echo htmlspecialchars(number_format($pendingCount)); ?></p>
              <p class="text-[11px] text-gray-500 mt-1">
                Pending applicants for S.Y. <?php echo htmlspecialchars($dashboardSchoolYear); ?>
              </p>
            </div>
            <div class="text-3xl text-[#0d8ddb] opacity-90">
              <i class="fas fa-users"></i>
            </div>
          </div>

          <!-- Panelists -->
          <div
            class="bg-[#fcdc2f] text-[#052c6a] rounded-lg p-4 flex items-center justify-between shadow"
          >
            <div>
              <p class="text-xs uppercase tracking-wide">Panelists</p>
              <p class="text-2xl font-bold mt-1"><?php echo htmlspecialchars(number_format($registeredPanelistsCount)); ?></p>
              <p class="text-[11px] text-[#052c6a] mt-1">
                Registered panelist accounts
              </p>
            </div>
            <div class="text-3xl opacity-90">
              <i class="fas fa-user-tie"></i>
            </div>
          </div>

          <!-- Head of Offices -->
          <div
            class="bg-green-500 text-white rounded-lg p-4 flex items-center justify-between shadow-sm"
          >
            <div>
              <p class="text-xs uppercase tracking-wide">Head of Offices</p>
              <p class="text-2xl font-bold mt-1"><?php echo htmlspecialchars(number_format($registeredHeadOfficesCount)); ?></p>
              <p class="text-[11px] text-white mt-1">Registered evaluator accounts</p>
            </div>
            <div class="text-3xl opacity-90">
              <i class="fas fa-user-tie"></i>
            </div>
          </div>
        </section>

        <!-- Charts -->
        <section class="dashboard-report-section px-4 sm:px-6 space-y-4 mt-3">
          <div class="print-only school-print-header mb-4">
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
              <h2 class="font-bold text-base">Statistical Report</h2>
              <p class="font-semibold text-sm">
                <?php echo htmlspecialchars($dashboardGrantLabel); ?> for S.Y. <?php echo htmlspecialchars($dashboardSchoolYear); ?>
              </p>
            </section>
          </div>

          <div class="bg-white border border-slate-200 rounded-lg px-3 py-2 shadow-sm no-print">
            <form method="get" action="adminDashboard.php" class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
              <div class="flex flex-col gap-1 lg:max-w-[420px] lg:flex-1">
                <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-600" for="dashboardGrantFilter">
                  Grant / Discount Filter
                </label>
                <select
                  id="dashboardGrantFilter"
                  name="grant_id"
                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700"
                  onchange="this.form.submit()"
                >
                  <option value="">All grants and discounts</option>
                  <?php foreach ($dashboardGrantLabels as $grantId => $grantName): ?>
                    <option value="<?php echo htmlspecialchars((string)$grantId); ?>" <?php echo $dashboardGrantId === (int)$grantId ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($grantName); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="flex flex-wrap items-end gap-2 lg:justify-end">
                <div class="flex flex-col gap-1">
                  <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-600" for="dashboardSchoolYearFilter">
                    School Year
                  </label>
                  <select
                    id="dashboardSchoolYearFilter"
                    name="school_year"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700"
                    onchange="this.form.submit()"
                  >
                    <?php foreach ($schoolYearOptions as $schoolYearOption): ?>
                      <option value="<?php echo htmlspecialchars($schoolYearOption); ?>" <?php echo $dashboardSchoolYear === $schoolYearOption ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($schoolYearOption); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <a
                  href="adminDashboard.php?<?php echo htmlspecialchars(http_build_query($dashboardRefreshParams)); ?>"
                  class="inline-flex items-center rounded-full border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                >
                  Refresh
                </a>
                <button
                  type="button"
                  onclick="window.print()"
                  class="inline-flex items-center gap-2 rounded-full border border-[#052c6a] bg-[#052c6a] px-3 py-2 text-xs font-semibold text-white hover:bg-[#041f4f]"
                >
                  <i class="fas fa-print text-[11px]"></i>
                  <span>Print Report</span>
                </button>
              </div>
            </form>
          </div>

          <div class="dashboard-chart-grid grid gap-6 xl:grid-cols-2">
            <!-- Grant Category Distribution (Pie Chart) -->
            <div class="bg-gray-50 border border-[#0d8ddb] rounded p-4 print-panel">
              <div class="mb-3">
                <div class="text-[#052c6a] text-sm font-semibold">
                  Applications by Grant Category
                </div>
                <p class="text-[11px] text-slate-500 mt-1">
                  Grant filter: <?php echo htmlspecialchars($dashboardGrantLabel); ?>, S.Y. <?php echo htmlspecialchars($dashboardSchoolYear); ?>.
                </p>
              </div>
              <div class="pie-report-layout grid gap-4 2xl:grid-cols-[minmax(0,1fr)_220px] 2xl:items-center">
                <div class="chart-box pie-chart-box border-2 border-[#0d8ddb] rounded h-64 md:h-80 bg-white">
                  <?php if ($grantCategoryTotal > 0): ?>
                    <canvas id="grantCategoryPieChart" class="w-full h-full"></canvas>
                  <?php else: ?>
                    <div class="flex h-full items-center justify-center px-4 text-center text-sm text-slate-500">
                      No application data for this school year.
                    </div>
                  <?php endif; ?>
                </div>
                <div class="grant-category-summary grid gap-2 text-sm sm:grid-cols-2 2xl:grid-cols-1">
                  <?php foreach ($grantCategoryLabels as $categoryKey => $categoryLabel): ?>
                    <?php
                      $categoryCount = $grantCategoryCounts[$categoryKey] ?? 0;
                      $categoryPercent = $grantCategoryTotal > 0 ? ($categoryCount / $grantCategoryTotal) * 100 : 0;
                    ?>
                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2">
                      <span class="font-semibold text-[#052c6a]"><?php echo htmlspecialchars($categoryLabel); ?></span>
                      <span class="text-right text-xs text-slate-500">
                        <span class="block font-bold text-[#0d8ddb]"><?php echo htmlspecialchars(number_format($categoryCount)); ?></span>
                        <?php echo htmlspecialchars(number_format($categoryPercent, 1)); ?>%
                      </span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- Applicants Statistical Report (Bar Chart) -->
            <div class="bg-gray-50 border border-[#0d8ddb] rounded p-4 print-panel">
              <div class="mb-2">
                <div class="text-[#052c6a] text-sm font-semibold">
                  Applicants Statistical Report
                </div>
                <p class="text-[11px] text-slate-500 mt-1">
                  Grant filter: <?php echo htmlspecialchars($dashboardGrantLabel); ?>
                </p>
              </div>
              <div class="chart-box border-2 border-[#0d8ddb] rounded h-64 md:h-80">
                <canvas id="applicantsBarChart" class="w-full h-full"></canvas>
              </div>
            </div>
          </div>

          <!-- Trends (Line Chart) -->
          <div class="bg-gray-50 border border-[#0d8ddb] rounded p-4 print-panel">
            <div class="mb-2">
              <div class="text-[#052c6a] text-sm font-semibold">
                Yearly Trend
              </div>
              <p class="text-[11px] text-slate-500 mt-1">
                Grant filter: <?php echo htmlspecialchars($dashboardGrantLabel); ?>
              </p>
            </div>
            <div class="chart-box border-2 border-[#0d8ddb] rounded h-64 md:h-80">
              <canvas id="trendLineChart" class="w-full h-full"></canvas>
            </div>
          </div>
        </section>

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

      // Chart.js helpers for consistent styling
      const brandBlue = "#0d8ddb";
      const brandNavy = "#052c6a";
      const brandYellow = "#fcdc2f";

      // handy RGBA for brand colors
      const purpleBlueFill = "rgba(106, 110, 230, 0.9)";
      const tealBlueFill = "rgba(65, 155, 180, 0.9)";
      const dashboardCharts = [];

      document.addEventListener("DOMContentLoaded", () => {
        // === BAR CHART: grouped by YEAR with 1st & 2nd Sem per year ===
        const barLabels = <?php echo json_encode($chartYears); ?>;
        const firstSem = <?php echo json_encode($firstSemCounts); ?>;
        const secondSem = <?php echo json_encode($secondSemCounts); ?>;
        const pieLabels = <?php echo json_encode($grantCategoryChartLabels); ?>;
        const pieData = <?php echo json_encode($grantCategoryChartCounts); ?>;

        const pieCtx = document.getElementById("grantCategoryPieChart");
        if (pieCtx && window.Chart) {
          const pieChart = new Chart(pieCtx, {
            type: "pie",
            data: {
              labels: pieLabels,
              datasets: [
                {
                  data: pieData,
                  backgroundColor: [brandBlue, brandYellow, "#16a34a", "#94a3b8"],
                  borderColor: "#ffffff",
                  borderWidth: 2,
                },
              ],
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  position: "bottom",
                  labels: {
                    color: brandNavy,
                    boxWidth: 12,
                    padding: 14,
                  },
                },
                tooltip: {
                  callbacks: {
                    label: (context) => {
                      const total = context.dataset.data.reduce((sum, value) => sum + Number(value || 0), 0);
                      const value = Number(context.parsed || 0);
                      const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : "0.0";
                      return `${context.label}: ${value} (${percentage}%)`;
                    },
                  },
                },
              },
            },
          });
          dashboardCharts.push({ chart: pieChart, printWidth: 340, printHeight: 220 });
        }

        const barCtx = document.getElementById("applicantsBarChart");
        if (barCtx && window.Chart) {
          const barChart = new Chart(barCtx, {
            type: "bar",
            data: {
              labels: barLabels,
              datasets: [
                {
                  label: "1st Sem",
                  data: firstSem,
                  backgroundColor: purpleBlueFill,
                  borderColor: brandNavy,
                  borderWidth: 1,
                  barPercentage: 0.8,
                  categoryPercentage: 0.7,
                },
                {
                  label: "2nd Sem",
                  data: secondSem,
                  backgroundColor: tealBlueFill,
                  borderColor: brandNavy,
                  borderWidth: 1,
                  barPercentage: 0.8,
                  categoryPercentage: 0.7,
                },
              ],
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              scales: {
                x: {
                  ticks: { color: brandNavy },
                  grid: { color: "rgba(0,0,0,0.05)" },
                },
                y: {
                  beginAtZero: true,
                  ticks: { color: brandNavy },
                  grid: { color: "rgba(0,0,0,0.08)" },
                },
              },
              plugins: {
                legend: { labels: { color: brandNavy } },
                tooltip: { enabled: true },
              },
            },
          });
          dashboardCharts.push({ chart: barChart, printWidth: 650, printHeight: 170 });
        }

        // === LINE CHART (yearly trend) ===
        const trendCtx = document.getElementById("trendLineChart");
        if (trendCtx && window.Chart) {
          const trendChart = new Chart(trendCtx, {
            type: "line",
            data: {
              labels: <?php echo json_encode($chartYears); ?>,
              datasets: [
                {
                  label: "Applicants",
                  data: <?php echo json_encode($lineApplicants); ?>,
                  borderColor: "#c81dff",
                  backgroundColor: "rgba(200, 29, 255, 0.15)",
                  pointBackgroundColor: "#ffffff",
                  pointBorderColor: "#c81dff",
                  pointRadius: 4,
                  tension: 0.3,
                },
                {
                  label: "Qualified",
                  data: <?php echo json_encode($lineQualified); ?>,
                  borderColor: "#ccff33",
                  backgroundColor: "rgba(204, 255, 51, 0.15)",
                  pointBackgroundColor: "#ffffff",
                  pointBorderColor: "#ccff33",
                  pointRadius: 4,
                  tension: 0.3,
                },
              ],
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              scales: {
                x: {
                  ticks: { color: brandNavy },
                  grid: { color: "rgba(0,0,0,0.05)" },
                },
                y: {
                  beginAtZero: true,
                  ticks: { color: brandNavy },
                  grid: { color: "rgba(0,0,0,0.08)" },
                },
              },
              plugins: {
                legend: { labels: { color: brandNavy } },
                tooltip: { enabled: true },
              },
            },
          });
          dashboardCharts.push({ chart: trendChart, printWidth: 650, printHeight: 170 });
        }
      });

      window.addEventListener("beforeprint", () => {
        dashboardCharts.forEach(({ chart, printWidth, printHeight }) => {
          chart.resize(printWidth, printHeight);
        });
      });

      window.addEventListener("afterprint", () => {
        dashboardCharts.forEach(({ chart }) => {
          chart.resize();
        });
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
<?php if ($showLoginSuccess): ?>
<script>
document.addEventListener("DOMContentLoaded", () => {
  const cleanUrl = new URL(window.location.href);
  cleanUrl.searchParams.delete("login");
  window.history.replaceState({}, document.title, cleanUrl.toString());
});
</script>
<?php endif; ?>
    <script>
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        if (!sidebar) return;

        const currentPage = window.location.pathname.split("/").pop().toLowerCase();
        const applicantPages = new Set([
          "applicant.php",
          "declined-applicants.php",
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









