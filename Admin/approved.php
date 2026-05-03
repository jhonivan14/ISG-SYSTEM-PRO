<?php
// Guide: Approved applicants page with messaging, panelist routing, and scholar confirmation actions.
// Trace: handle confirm POST -> load approved applicants/panelists -> render modals -> action scripts.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once '../db.php';

$categoryDefinitions = [
  [
    "label" => "Student Assistant",
    "slug" => "student-assistant",
    "keywords" => ["student assistant"],
  ],
  [
    "label" => "Kabayani Scholarship",
    "slug" => "kabayani",
    "keywords" => ["kabayani"],
  ],
  [
    "label" => "Academic Scholar",
    "slug" => "academic",
    "keywords" => ["academic"],
  ],
  [
    "label" => "Others",
    "slug" => "others",
    "keywords" => [],
  ],
];

$grantLabels = [
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
];

$messageToastMessage = "";
$messageToastType = "";
$panelistToastMessage = "";
$panelistToastType = "";
if (isset($_GET["message_status"])) {
  $messageStatus = (string)$_GET["message_status"];
  $messageError = $_SESSION["message_error"] ?? "";
  unset($_SESSION["message_error"]);

  if ($messageStatus === "sent") {
    $messageToastMessage = "Message sent successfully.";
    $messageToastType = "success";
  } elseif ($messageStatus === "error") {
    $messageToastMessage = $messageError !== "" ? (string)$messageError : "Failed to send message. Please try again.";
    $messageToastType = "error";
  }
}
if (isset($_GET["panelist_status"])) {
  $panelistStatus = (string)$_GET["panelist_status"];
  $panelistError = $_SESSION["panelist_error"] ?? "";
  $panelistSentCount = (int)($_SESSION["panelist_sent_count"] ?? 0);
  unset($_SESSION["panelist_sent_count"], $_SESSION["panelist_error"]);

  if ($panelistStatus === "sent") {
    $panelistToastMessage = $panelistSentCount > 0
      ? "Sent to panelist successfully ({$panelistSentCount})."
      : "Sent to panelist successfully.";
    $panelistToastType = "success";
  } elseif ($panelistStatus === "error") {
    $panelistToastMessage = $panelistError !== "" ? (string)$panelistError : "Failed to send to panelist. Please try again.";
    $panelistToastType = "error";
  }
}

// Handle scholar confirmation requests before the approved-applicants list is assembled.

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["confirm_applicant_id"])) {
  $confirmApplicantId = (int)($_POST["confirm_applicant_id"] ?? 0);
  $confirmError = "";

  if ($confirmApplicantId <= 0) {
    $confirmError = "Invalid applicant selected for confirmation.";
  } else {
    $confirmStmt = $conn->prepare("SELECT id, grant_id, status FROM applications WHERE id = ? LIMIT 1");
    if ($confirmStmt) {
      $confirmStmt->bind_param("i", $confirmApplicantId);
      $confirmStmt->execute();
      $confirmResult = $confirmStmt->get_result();
      $confirmRow = $confirmResult ? $confirmResult->fetch_assoc() : null;
      $confirmStmt->close();

      if (!$confirmRow) {
        $confirmError = "Applicant not found.";
      } else {
        $status = strtolower(trim((string)($confirmRow["status"] ?? "")));
        $grantId = (int)($confirmRow["grant_id"] ?? 0);
        if ($status !== "approved") {
          $confirmError = "Only approved applicants can be confirmed.";
        } elseif ($grantId === 1) {
          $confirmError = "For Student Assistant applicants, use 'Send to Panelist'.";
        } else {
          header("Location: institutional-scholars.php?applicant_id=" . urlencode((string)$confirmApplicantId) . "&source=approved");
          exit;
        }
      }
    } else {
      $confirmError = "Unable to process confirmation right now.";
    }
  }

  $_SESSION["confirm_error"] = $confirmError;
  header("Location: approved.php?confirm_status=error");
  exit;
}

$currentYear = (int)date("Y");
$currentMonth = (int)date("n");
$currentSchoolYearStart = $currentMonth < 6 ? $currentYear - 1 : $currentYear;
$currentSchoolYear = $currentSchoolYearStart . "-" . ($currentSchoolYearStart + 1);
$schoolYearOptions = [];

$schoolYearResult = $conn->query("SELECT DISTINCT school_year FROM applications WHERE school_year IS NOT NULL AND TRIM(school_year) <> ''");
if ($schoolYearResult) {
  while ($row = $schoolYearResult->fetch_assoc()) {
    $value = trim((string)($row["school_year"] ?? ""));
    if ($value !== "") {
      $schoolYearOptions[] = $value;
    }
  }
  $schoolYearResult->free();
}

if (!in_array($currentSchoolYear, $schoolYearOptions, true)) {
  $schoolYearOptions[] = $currentSchoolYear;
}

$schoolYearOptions = array_values(array_unique($schoolYearOptions));
usort($schoolYearOptions, function ($a, $b) {
  $aYear = (int)substr($a, 0, 4);
  $bYear = (int)substr($b, 0, 4);
  if ($aYear === $bYear) {
    return strcmp($a, $b);
  }
  return $aYear <=> $bYear;
});
$semesterOptions = ["1st Semester", "2nd Semester"];

$selectedSchoolYear = isset($_GET["school_year"]) ? trim((string)$_GET["school_year"]) : "";
$selectedSemester = isset($_GET["semester"]) ? trim((string)$_GET["semester"]) : "";
if ($selectedSchoolYear !== "" && !in_array($selectedSchoolYear, $schoolYearOptions, true)) {
  array_unshift($schoolYearOptions, $selectedSchoolYear);
}
if ($selectedSemester !== "" && !in_array($selectedSemester, $semesterOptions, true)) {
  array_unshift($semesterOptions, $selectedSemester);
}

$filterClauses = [];
$filterParams = [];
$filterTypes = "";
if ($selectedSchoolYear !== "") {
  $filterClauses[] = "school_year = ?";
  $filterParams[] = $selectedSchoolYear;
  $filterTypes .= "s";
}
if ($selectedSemester !== "") {
  $filterClauses[] = "semester = ?";
  $filterParams[] = $selectedSemester;
  $filterTypes .= "s";
}

$approvedApplicants = [];
$approvedQuery = "SELECT id, applicant_name, email_address, grant_id, status FROM applications WHERE status = 'Approved'";
if (!empty($filterClauses)) {
  $approvedQuery .= " AND " . implode(" AND ", $filterClauses);
}
$approvedQuery .= " ORDER BY created_at DESC";
if ($stmt = $conn->prepare($approvedQuery)) {
  if (!empty($filterParams)) {
    $stmt->bind_param($filterTypes, ...$filterParams);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $grantId = (int)($row["grant_id"] ?? 0);
      $grantLabel = $grantLabels[$grantId] ?? "Others";
    $status = isset($row["status"]) ? trim((string)$row["status"]) : "Approved";
    if ($status === "") {
      $status = "Approved";
    }

    $approvedApplicants[] = [
      "id" => (int)($row["id"] ?? 0),
      "name" => $row["applicant_name"] ?? "",
      "email" => $row["email_address"] ?? "",
      "grant_id" => $grantId,
      "grant" => $grantLabel,
      "status" => $status,
    ];
  }
    $result->free();
  }
$stmt->close();
}

$panelists = [];
$panelistError = "";
$panelistResult = $conn->query("SELECT username, full_name, status FROM panelists ORDER BY full_name ASC");
if ($panelistResult) {
  while ($row = $panelistResult->fetch_assoc()) {
    $status = strtolower(trim((string)($row["status"] ?? "active")));
    if ($status !== "inactive") {
      $panelists[] = [
        "username" => $row["username"] ?? "",
        "full_name" => $row["full_name"] ?? "",
      ];
    }
  }
  $panelistResult->free();
} else {
  $panelistError = "Panelist accounts table is not available.";
}

$categories = [];
foreach ($categoryDefinitions as $definition) {
  $categories[$definition["slug"]] = [
    "label" => $definition["label"],
    "slug" => $definition["slug"],
    "count" => 0,
  ];
}

foreach ($approvedApplicants as &$applicant) {
  $grant = strtolower($applicant["grant"]);
  $matchedSlug = "others";

  foreach ($categoryDefinitions as $definition) {
    foreach ($definition["keywords"] as $keyword) {
      if (stripos($grant, strtolower($keyword)) !== false) {
        $matchedSlug = $definition["slug"];
        break 2;
      }
    }
  }

  $applicant["category_slug"] = $matchedSlug;
  if (isset($categories[$matchedSlug])) {
    $categories[$matchedSlug]["count"]++;
  }
}
unset($applicant);
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Approved Applicants</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
      .filter-button.active {
        background-color: #0d8ddb;
        color: #ffffff;
        border-color: #0d8ddb;
        box-shadow: 0 10px 20px rgba(13, 141, 219, 0.18);
      }
      .controls-float {
        border-radius: 18px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border: 1px solid rgba(13, 141, 219, 0.14);
        box-shadow: 0 14px 30px rgba(5, 44, 106, 0.1), 0 2px 6px rgba(5, 44, 106, 0.06);
      }
      .table-float {
        border-radius: 18px;
        overflow: hidden;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
      }
      .app-table {
        border-collapse: separate;
        border-spacing: 0;
      }
      .app-table thead tr {
        background: linear-gradient(90deg, #052c6a 0%, #0d8ddb 100%);
      }
      .app-table thead th {
        color: #ffffff !important;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        font-size: 10px;
        padding: 11px 8px;
        border-right: 1px solid rgba(255, 255, 255, 0.24) !important;
      }
      .app-table thead th:last-child {
        border-right: none !important;
      }
      .app-table tbody td {
        padding: 11px 8px;
        border-right: 1px solid rgba(5, 44, 106, 0.12) !important;
        border-bottom: 1px solid rgba(5, 44, 106, 0.1) !important;
        color: #052c6a;
      }
      .app-table tbody td:last-child {
        border-right: none !important;
      }
      .app-table tbody tr:last-child td {
        border-bottom: none !important;
      }
      .app-table tbody tr:nth-child(even) {
        background-color: #f5faff;
      }
      .app-table tbody tr {
        transition: background-color 0.2s ease;
      }
      .app-table tbody tr:hover {
        background-color: #eaf5ff !important;
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

      <!-- Main content -->
      <main class="ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pt-14">
        <!-- Top bar -->
        <header
          class="hidden fixed top-0 left-0 md:left-64 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
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
              Account ▾
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
            APPROVED APPLICATIONS
          </h2>
          </div>
        </section>

        <div class="mt-12"></div>

        <!-- Academic Year / Semester Filters -->
        <section class="px-4 sm:px-6 mt-4">
          <?php if (isset($_GET["confirm_status"])): ?>
            <?php
              $confirmStatus = (string)$_GET["confirm_status"];
              $confirmError = $_SESSION["confirm_error"] ?? "";
              unset($_SESSION["confirm_error"]);
            ?>
            <?php if ($confirmStatus === "error"): ?>
              <div class="mb-3 rounded-lg border border-red-400 bg-red-50 px-4 py-3 text-xs font-semibold text-red-700">
                <?= htmlspecialchars($confirmError !== "" ? $confirmError : "Unable to confirm applicant.") ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
          <div class="controls-float flex flex-col gap-3 p-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-2">
              <?php foreach ($categories as $category): ?>
                <button
                  type="button"
                  class="filter-button rounded-full border border-[#0d8ddb] px-3 py-2 text-xs font-semibold text-[#0d8ddb] transition"
                  data-filter-category="<?= htmlspecialchars($category["slug"]) ?>"
                  data-filter-label="<?= htmlspecialchars($category["label"]) ?>"
                >
                  <?= htmlspecialchars($category["label"]) ?>
                  <span class="ml-1 rounded-full bg-[#e5f1ff] px-2 py-0.5 text-[10px] font-bold text-[#052c6a]">
                    <?= htmlspecialchars($category["count"]) ?>
                  </span>
                </button>
              <?php endforeach; ?>
            </div>
            <form class="flex flex-wrap items-center gap-2 lg:justify-end" method="get" action="approved.php">
              <div class="flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-white px-3 py-2 shadow-sm">
                <i class="fas fa-search text-[#7c8191] text-xs"></i>
                <input
                  id="approvedSearch"
                  type="text"
                  class="w-40 bg-transparent text-xs font-semibold text-[#052c6a] outline-none placeholder:text-[#7c8191]"
                  placeholder="Search approved..."
                  aria-label="Search approved applicants"
                />
              </div>
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
              <?php if ($selectedSchoolYear !== "" || $selectedSemester !== ""): ?>
                <a
                  href="approved.php"
                  class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm"
                >
                  Clear
                </a>
              <?php endif; ?>
            </form>
          </div>
        </section>

        <!-- Approved table -->
        <section class="px-4 sm:px-6 pb-10 mt-4">
          <div class="controls-float overflow-hidden">
            <div class="overflow-x-auto table-float">
              <table class="app-table min-w-full text-xs text-center">
                <thead>
                  <tr class="bg-white border-b border-[#0d8ddb]">
                    <th class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]">Applicant Name</th>
                    <th class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]">ISG Grant</th>
                    <th class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]">Status</th>
                    <th class="py-2 px-2 font-semibold text-[#fcdc2f]">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($approvedApplicants)): ?>
                    <tr>
                      <td colspan="4" class="py-3 text-center text-[#052c6a]">No approved applicants.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($approvedApplicants as $applicant): ?>
                      <?php
                        $searchText = strtolower($applicant["name"] . " " . $applicant["grant"] . " " . $applicant["status"]);
                      ?>
                      <tr
                        class="border-b border-[#0d8ddb]"
                        data-approved-row
                        data-search-text="<?= htmlspecialchars($searchText) ?>"
                        data-category="<?= htmlspecialchars($applicant["category_slug"]) ?>"
                      >
                        <td class="border-r border-[#0d8ddb] py-2 text-left px-2 text-[#052c6a]">
                          <?= htmlspecialchars($applicant["name"]) ?>
                        </td>
                        <td class="border-r border-[#0d8ddb] py-2 text-left px-2 text-[#052c6a]">
                          <?= htmlspecialchars($applicant["grant"]) ?>
                        </td>
                        <td class="border-r border-[#0d8ddb] py-2">
                          <span class="bg-green-500 text-white rounded px-2 py-0.5 inline-block">
                            <?= htmlspecialchars($applicant["status"]) ?>
                          </span>
                        </td>
                        <td class="py-2">
                          <div class="flex flex-wrap items-center justify-center gap-2">
                            <button
                              class="bg-[#0d8ddb] text-white rounded px-3 py-1 text-xs"
                              type="button"
                              onclick="window.location.href='view-application.php?id=<?= htmlspecialchars((string)$applicant['id']) ?>'"
                            >
                              View Details
                            </button>
                            <button
                              class="bg-[#052c6a] text-white rounded px-3 py-1 text-xs hover:bg-[#031f4d]"
                              type="button"
                              data-send-message
                              data-applicant-name="<?= htmlspecialchars($applicant["name"]) ?>"
                              data-applicant-id="<?= htmlspecialchars((string)$applicant["id"]) ?>"
                              data-applicant-email="<?= htmlspecialchars($applicant["email"]) ?>"
                            >
                              Send Message
                            </button>
                            <?php if ((int)$applicant["grant_id"] !== 1): ?>
                              <form method="post" class="m-0 inline-flex items-center">
                                <input type="hidden" name="confirm_applicant_id" value="<?= htmlspecialchars((string)$applicant["id"]) ?>" />
                                <button
                                  class="inline-flex items-center bg-green-600 text-white rounded px-3 py-1 text-xs hover:bg-green-700"
                                  type="submit"
                                  data-confirm-applicant
                                  data-applicant-name="<?= htmlspecialchars($applicant["name"]) ?>"
                                  data-grant-id="<?= htmlspecialchars((string)$applicant["grant_id"]) ?>"
                                >
                                  Confirm
                                </button>
                              </form>
                            <?php endif; ?>
                            <?php if ((int)$applicant["grant_id"] === 1): ?>
                              <button
                                class="border border-[#0d8ddb] text-[#0d8ddb] rounded px-3 py-1 text-xs hover:bg-white/15 hover:text-white"
                                type="button"
                                data-send-panelist
                                data-applicant-name="<?= htmlspecialchars($applicant["name"]) ?>"
                                data-applicant-id="<?= htmlspecialchars((string)$applicant["id"]) ?>"
                              >
                                Send to Panelist
                              </button>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <tr data-approved-empty class="hidden">
                      <td colspan="4" class="py-3 text-center text-[#052c6a]">No matching approved applicants.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Send Message Modal -->
        <div
          id="sendMessageModal"
          class="fixed inset-0 z-40 hidden items-center justify-center bg-black/40 px-4"
          aria-hidden="true"
        >
          <div class="w-full max-w-md rounded-lg bg-white shadow-lg">
            <div class="flex items-center justify-between border-b border-[#0d8ddb] px-4 py-3">
              <h2 class="text-sm font-semibold text-[#052c6a]">Send Message</h2>
              <button id="sendMessageClose" class="text-[#052c6a] hover:text-[#0d8ddb]" type="button">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <form action="send_message.php" method="post" class="px-4 py-3 space-y-3">
              <input type="hidden" name="applicant_id" id="recipientId" value="" />
              <div>
                <label class="block text-xs font-semibold text-[#052c6a] mb-1">Recipient</label>
                <input
                  type="text"
                  id="recipientName"
                  class="w-full rounded border border-[#0d8ddb] px-3 py-2 text-xs text-[#052c6a] bg-gray-50"
                  readonly
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#052c6a] mb-1">Recipient Email</label>
                <input
                  type="text"
                  id="recipientEmail"
                  name="recipient_email"
                  class="w-full rounded border border-[#0d8ddb] px-3 py-2 text-xs text-[#052c6a] bg-gray-50"
                  readonly
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#052c6a] mb-1" for="messageBody">Message</label>
                <textarea
                  id="messageBody"
                  name="message_body"
                  rows="5"
                  required
                  class="w-full rounded border border-[#0d8ddb] px-3 py-2 text-xs text-[#052c6a] focus:outline-none"
                  placeholder="Type your message here..."
                ></textarea>
              </div>
              <div class="flex justify-end gap-2">
                <button
                  type="button"
                  id="sendMessageCancel"
                  class="rounded border border-[#0d8ddb] px-3 py-2 text-xs font-semibold text-[#052c6a]"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  class="rounded bg-[#0d8ddb] px-3 py-2 text-xs font-semibold text-white hover:bg-[#0b7cc0]"
                >
                  Send
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Send to Panelist Modal -->
        <div
          id="sendPanelistModal"
          class="fixed inset-0 z-40 hidden items-center justify-center bg-black/40 px-4"
          aria-hidden="true"
        >
          <div class="w-full max-w-md rounded-lg bg-white shadow-lg">
            <div class="flex items-center justify-between border-b border-[#0d8ddb] px-4 py-3">
              <h2 class="text-sm font-semibold text-[#052c6a]">Send to Panelist</h2>
              <button id="sendPanelistClose" class="text-[#052c6a] hover:text-[#0d8ddb]" type="button">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <form action="send_to_panelist.php" method="post" class="px-4 py-3 space-y-3">
              <input type="hidden" name="applicant_id" id="panelistApplicantId" value="" />
              <div>
                <label class="block text-xs font-semibold text-[#052c6a] mb-1">Applicant</label>
                <input
                  type="text"
                  id="panelistApplicantName"
                  class="w-full rounded border border-[#0d8ddb] px-3 py-2 text-xs text-[#052c6a] bg-gray-50"
                  readonly
                />
              </div>
              <div>
                <div class="flex items-center justify-between">
                  <label class="block text-xs font-semibold text-[#052c6a] mb-1">Panelists</label>
                  <div class="flex items-center gap-2 text-[10px] text-[#0d8ddb]">
                    <button type="button" id="panelistSelectAll" class="underline">Select all</button>
                    <button type="button" id="panelistClearAll" class="underline">Clear</button>
                  </div>
                </div>
                <?php if ($panelistError !== ""): ?>
                  <div class="rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    <?= htmlspecialchars($panelistError) ?>
                  </div>
                <?php elseif (empty($panelists)): ?>
                  <div class="rounded border border-yellow-200 bg-yellow-50 px-3 py-2 text-xs text-yellow-800">
                    No active panelist accounts found.
                  </div>
                <?php else: ?>
                  <div class="max-h-40 space-y-2 overflow-y-auto rounded border border-[#0d8ddb] px-3 py-2 text-xs text-[#052c6a]">
                    <?php foreach ($panelists as $panelist): ?>
                      <label class="flex items-center gap-2">
                        <input
                          type="checkbox"
                          class="panelist-checkbox"
                          name="panelist_usernames[]"
                          value="<?= htmlspecialchars($panelist["username"]) ?>"
                        />
                        <span>
                          <?= htmlspecialchars($panelist["full_name"] !== "" ? $panelist["full_name"] : $panelist["username"]) ?>
                        </span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <p id="panelistSelectError" class="mt-2 text-[10px] text-red-600 hidden">
                  Please select at least one panelist.
                </p>
              </div>
              <div class="flex justify-end gap-2">
                <button
                  type="button"
                  id="sendPanelistCancel"
                  class="rounded border border-[#0d8ddb] px-3 py-2 text-xs font-semibold text-[#052c6a]"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  id="sendPanelistSubmit"
                  class="rounded bg-[#0d8ddb] px-3 py-2 text-xs font-semibold text-white hover:bg-[#0b7cc0]"
                  <?php echo (!empty($panelistError) || empty($panelists)) ? "disabled" : ""; ?>
                >
                  Send
                </button>
              </div>
            </form>
          </div>
        </div>
      </main>
    </div>

    <script>
      const messageToastMessage = <?= json_encode($messageToastMessage, JSON_UNESCAPED_SLASHES) ?>;
      const messageToastType = <?= json_encode($messageToastType, JSON_UNESCAPED_SLASHES) ?>;
      const panelistToastMessage = <?= json_encode($panelistToastMessage, JSON_UNESCAPED_SLASHES) ?>;
      const panelistToastType = <?= json_encode($panelistToastType, JSON_UNESCAPED_SLASHES) ?>;

      function showApprovedToast(message, type, queryParams) {
        if (!message) {
          return;
        }

        const cleanUrl = new URL(window.location.href);
        queryParams.forEach((queryParam) => {
          cleanUrl.searchParams.delete(queryParam);
        });
        window.history.replaceState({}, document.title, cleanUrl.toString());

        if (typeof Swal === "undefined") {
          window.alert(message);
          return;
        }

        Swal.fire({
          toast: true,
          position: "top-end",
          showConfirmButton: false,
          icon: type === "error" ? "error" : "success",
          title: message,
          timer: type === "error" ? 4200 : 3200,
          timerProgressBar: true,
          background: type === "error" ? "#fef2f2" : "#f0fdf4",
          color: type === "error" ? "#991b1b" : "#166534",
        });
      }

      // Sidebar toggle for mobile
      document.addEventListener("DOMContentLoaded", () => {
        showApprovedToast(messageToastMessage, messageToastType, ["message_status"]);
        showApprovedToast(panelistToastMessage, panelistToastType, ["panelist_status"]);

        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");

        if (toggleBtn && sidebar) {
          toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
          });

          // Close sidebar when clicking any nav item on small screens
          sidebar.querySelectorAll("li").forEach((item) => {
            item.addEventListener("click", () => {
              if (window.innerWidth < 768) {
                sidebar.classList.add("-translate-x-full");
              }
            });
          });
        }
      });

      // Search + category filter for approved applicants
      document.addEventListener("DOMContentLoaded", () => {
        const searchInput = document.getElementById("approvedSearch");
        const filterButtons = document.querySelectorAll("[data-filter-category]");
        const rows = document.querySelectorAll("[data-approved-row]");
        const emptyRow = document.querySelector("[data-approved-empty]");
        let activeCategory = "";

        const approvedTitle = document.getElementById("approvedTableTitle");

        const applyFilters = () => {
          const query = (searchInput?.value || "").trim().toLowerCase();
          let visible = 0;

          rows.forEach((row) => {
            const text = row.dataset.searchText || "";
            const category = row.dataset.category || "";
            const matchesCategory = activeCategory === "" || category === activeCategory;
            const matchesSearch = query === "" || text.includes(query);
            const matches = matchesCategory && matchesSearch;
            row.style.display = matches ? "table-row" : "none";
            if (matches) visible++;
          });

          if (emptyRow) {
            emptyRow.style.display = visible === 0 ? "table-row" : "none";
          }

          filterButtons.forEach((button) => {
            const isActive = button.dataset.filterCategory === activeCategory;
            button.classList.toggle("active", isActive);
          });

          if (approvedTitle) {
            if (activeCategory === "") {
              approvedTitle.textContent = "All Approved Applicants";
            } else {
              const activeButton = document.querySelector(
                `[data-filter-category="${activeCategory}"]`
              );
              const label = activeButton?.dataset.filterLabel || "Filtered Approved Applicants";
              approvedTitle.textContent = label.toUpperCase();
            }
          }
        };

        filterButtons.forEach((button) => {
          button.addEventListener("click", () => {
            const slug = button.dataset.filterCategory || "";
            activeCategory = activeCategory === slug ? "" : slug;
            applyFilters();
          });
        });

        if (searchInput) {
          searchInput.addEventListener("input", applyFilters);
        }

        applyFilters();
      });

      // Send message modal
      document.addEventListener("DOMContentLoaded", () => {
        const modal = document.getElementById("sendMessageModal");
        const closeBtn = document.getElementById("sendMessageClose");
        const cancelBtn = document.getElementById("sendMessageCancel");
        const recipientName = document.getElementById("recipientName");
        const recipientEmail = document.getElementById("recipientEmail");
        const recipientId = document.getElementById("recipientId");
        const messageBody = document.getElementById("messageBody");

        const closeModal = () => {
          if (modal) {
            modal.classList.add("hidden");
            modal.classList.remove("flex");
          }
          if (messageBody) {
            messageBody.value = "";
          }
        };

        document.querySelectorAll("[data-send-message]").forEach((button) => {
          button.addEventListener("click", () => {
            const name = button.getAttribute("data-applicant-name") || "";
            const id = button.getAttribute("data-applicant-id") || "";
            const email = button.getAttribute("data-applicant-email") || "";

            if (recipientName) recipientName.value = name;
            if (recipientEmail) recipientEmail.value = email;
            if (recipientId) recipientId.value = id;
            if (modal) {
              modal.classList.remove("hidden");
              modal.classList.add("flex");
            }
          });
        });

        [closeBtn, cancelBtn].forEach((btn) => {
          if (btn) {
            btn.addEventListener("click", closeModal);
          }
        });

        if (modal) {
          modal.addEventListener("click", (event) => {
            if (event.target === modal) {
              closeModal();
            }
          });
        }
      });

      // Confirm routing
      document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll("[data-confirm-applicant]").forEach((button) => {
          button.addEventListener("click", (event) => {
            event.preventDefault();
            const form = button.closest("form");
            if (!form) return;

            const applicantName = button.getAttribute("data-applicant-name") || "this applicant";
            const grantId = parseInt(button.getAttribute("data-grant-id") || "0", 10);
            const destination =
              grantId === 1
                ? "Interview Evaluation and Applicant Ranks"
                : "Official Institutional Scholars";

            const shouldContinue = window.confirm(
              `Confirm ${applicantName}? This will route the applicant to ${destination}.`
            );

            if (shouldContinue) {
              form.submit();
            }
          });
        });
      });

      // Send to panelist modal
      document.addEventListener("DOMContentLoaded", () => {
        const modal = document.getElementById("sendPanelistModal");
        const closeBtn = document.getElementById("sendPanelistClose");
        const cancelBtn = document.getElementById("sendPanelistCancel");
        const applicantName = document.getElementById("panelistApplicantName");
        const applicantId = document.getElementById("panelistApplicantId");
        const selectAllBtn = document.getElementById("panelistSelectAll");
        const clearAllBtn = document.getElementById("panelistClearAll");
        const checkboxes = () => Array.from(document.querySelectorAll(".panelist-checkbox"));
        const errorEl = document.getElementById("panelistSelectError");
        const form = modal ? modal.querySelector("form") : null;

        const closeModal = () => {
          if (modal) {
            modal.classList.add("hidden");
            modal.classList.remove("flex");
          }
        };

        document.querySelectorAll("[data-send-panelist]").forEach((button) => {
          button.addEventListener("click", () => {
            const name = button.getAttribute("data-applicant-name") || "";
            const id = button.getAttribute("data-applicant-id") || "";
            if (applicantName) applicantName.value = name;
            if (applicantId) applicantId.value = id;
            if (errorEl) {
              errorEl.classList.add("hidden");
            }
            if (modal) {
              modal.classList.remove("hidden");
              modal.classList.add("flex");
            }
          });
        });

        if (selectAllBtn) {
          selectAllBtn.addEventListener("click", () => {
            checkboxes().forEach((box) => {
              box.checked = true;
            });
            if (errorEl) errorEl.classList.add("hidden");
          });
        }

        if (clearAllBtn) {
          clearAllBtn.addEventListener("click", () => {
            checkboxes().forEach((box) => {
              box.checked = false;
            });
            if (errorEl) errorEl.classList.add("hidden");
          });
        }

        if (form) {
          form.addEventListener("submit", (event) => {
            const hasSelection = checkboxes().some((box) => box.checked);
            if (!hasSelection) {
              event.preventDefault();
              if (errorEl) errorEl.classList.remove("hidden");
            }
          });
        }

        [closeBtn, cancelBtn].forEach((btn) => {
          if (btn) {
            btn.addEventListener("click", closeModal);
          }
        });

        if (modal) {
          modal.addEventListener("click", (event) => {
            if (event.target === modal) {
              closeModal();
            }
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










