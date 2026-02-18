<?php
session_start();
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

$pendingApplicants = [];
$pendingQuery = "SELECT id, created_at, applicant_name, program_course, grant_id, status
  FROM applications
  WHERE (status IS NULL
    OR TRIM(status) = ''
    OR LOWER(TRIM(status)) = 'pending')";
if (!empty($filterClauses)) {
  $pendingQuery .= " AND " . implode(" AND ", $filterClauses);
}
$pendingQuery .= " ORDER BY created_at DESC";
if ($stmt = $conn->prepare($pendingQuery)) {
  if (!empty($filterParams)) {
    $stmt->bind_param($filterTypes, ...$filterParams);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $applicationId = (int)($row["id"] ?? 0);
      $grantId = (int)($row["grant_id"] ?? 0);
    $grantLabel = $grantLabels[$grantId] ?? "Others";
    $submittedAtRaw = $row["created_at"] ?? "";
    $submittedAt = $submittedAtRaw ? date("Y-m-d h:i A", strtotime($submittedAtRaw)) : "";
    $status = isset($row["status"]) ? trim((string)$row["status"]) : "";
    if ($status === "") {
      $status = "Pending";
    }

    $pendingApplicants[] = [
      "id" => $applicationId,
      "submitted_at" => $submittedAt,
      "name" => $row["applicant_name"] ?? "",
      "program_course" => $row["program_course"] ?? "",
      "grant" => $grantLabel,
        "status" => $status,
      ];
    }
    $result->free();
  }
  $stmt->close();
}

$categories = [];
foreach ($categoryDefinitions as $definition) {
  $categories[$definition["slug"]] = [
    "label" => $definition["label"],
    "slug" => $definition["slug"],
    "count" => 0,
  ];
}

foreach ($pendingApplicants as &$applicant) {
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

$pendingCount = count($pendingApplicants);

$declinedApplicants = [];
$declinedQuery = "SELECT id, created_at, applicant_name, program_course, grant_id, status
  FROM applications
  WHERE LOWER(TRIM(status)) IN ('declined', 'rejected')";
if (!empty($filterClauses)) {
  $declinedQuery .= " AND " . implode(" AND ", $filterClauses);
}
$declinedQuery .= " ORDER BY created_at DESC";
if ($stmt = $conn->prepare($declinedQuery)) {
  if (!empty($filterParams)) {
    $stmt->bind_param($filterTypes, ...$filterParams);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $applicationId = (int)($row["id"] ?? 0);
      $grantId = (int)($row["grant_id"] ?? 0);
    $grantLabel = $grantLabels[$grantId] ?? "Others";
    $submittedAtRaw = $row["created_at"] ?? "";
    $submittedAt = $submittedAtRaw ? date("Y-m-d h:i A", strtotime($submittedAtRaw)) : "";
    $status = isset($row["status"]) ? trim((string)$row["status"]) : "Rejected";
    if ($status === "") {
      $status = "Declined";
    }

    $declinedApplicants[] = [
      "id" => $applicationId,
      "submitted_at" => $submittedAt,
      "name" => $row["applicant_name"] ?? "",
      "program_course" => $row["program_course"] ?? "",
      "grant" => $grantLabel,
        "status" => $status,
      ];
    }
    $result->free();
  }
  $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        background-color: #052c6a; /* navy blue */
        border-radius: 3px;
      }
      .filter-button.active {
        background-color: #0d8ddb;
        color: #ffffff;
        border-color: #0d8ddb;
        box-shadow: 0 10px 20px rgba(13, 141, 219, 0.18);
      }
      .table-float {
        border-radius: 18px;
        overflow: hidden;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        border: 1px solid rgba(13, 141, 219, 0.2);
        box-shadow: 0 14px 30px rgba(5, 44, 106, 0.12), 0 2px 6px rgba(5, 44, 106, 0.06);
      }
      .table-float-danger {
        background: linear-gradient(180deg, #ffffff 0%, #fff9f9 100%);
        border-color: rgba(244, 67, 54, 0.22);
        box-shadow: 0 14px 30px rgba(210, 54, 42, 0.14), 0 2px 6px rgba(210, 54, 42, 0.08);
      }
      .app-table {
        border-collapse: separate;
        border-spacing: 0;
      }
      .app-table thead tr {
        background: linear-gradient(90deg, #052c6a 0%, #0d8ddb 100%);
      }
      .app-table-danger thead tr {
        background: linear-gradient(90deg, #ba2a2a 0%, #e94a4a 100%);
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
      .app-table-danger tbody td {
        border-right: 1px solid rgba(186, 42, 42, 0.16) !important;
        border-bottom: 1px solid rgba(186, 42, 42, 0.14) !important;
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
      .app-table-danger tbody tr:nth-child(even) {
        background-color: #fff5f5;
      }
      .app-table tbody tr {
        transition: background-color 0.2s ease;
      }
      .app-table tbody tr:hover {
        background-color: #eaf5ff !important;
      }
      .app-table-danger tbody tr:hover {
        background-color: #ffeaea !important;
      }
    </style>
  </head>
  <body class="bg-white font-sans">
    <div class="min-h-screen">
      <!-- Sidebar -->
      <aside
        id="sidebar"
        class="flex flex-col bg-[#052c6a] text-white w-56 h-screen fixed left-0 top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out overflow-y-auto"
      >
        <div
          class="flex items-center gap-3 px-4 py-4 border-b border-[#0d8ddb]"
        >
          <img
            src="../img/SMCCNEWLOGO.png"
            class="rounded-full w-16 h-16 object-cover"
            alt="SMCC Logo"
          />
          <span class="text-sm font-normal">
            Admission and Scholarship Office
          </span>
        </div>

        <nav class="flex-1">
          <ul class="text-xs font-semibold">
            <li
              class="flex items-center gap-2 px-4 py-3"
            >
              <i class="fas fa-trophy w-5"></i>
              <span>Dashboard</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
               data-nav="adminDashboard.php" onclick="window.location.href='adminDashboard.php'"
            >
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="applicant.php" onclick="window.location.href='applicant.php'"
            >
              <i class="fas fa-user-graduate w-5"></i>
              <span>Applicants</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="approved.php" onclick="window.location.href='approved.php'"
            >
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="interviewEvaluation.php" onclick="window.location.href='interviewEvaluation.php'"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="ranks.php" onclick="window.location.href='ranks.php'"
            >
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="list-of-qualified.php" onclick="window.location.href='list-of-qualified.php'"
            >
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="department-evaluation-list.php" onclick="window.location.href='department-evaluation-list.php'"
            >
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="summary-report.php" onclick="window.location.href='summary-report.php'"
            >
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="institutional-scholars.php" onclick="window.location.href='institutional-scholars.php'"
            >
              <i class="fas fa-chart-line w-5"></i>
              <span>Institutional Scholars</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="accounts.php" onclick="window.location.href='accounts.php'"
            >
              <i class="fas fa-user-circle w-5"></i>
              <span>Accounts</span>
            </li>
          </ul>
        </nav>
       
        <div class="absolute bottom-0 left-0 w-full">
        <div class="h-px w-full bg-gradient-to-r from-transparent via-[#0d8ddb] to-transparent opacity-60"></div>

   
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
      </aside>

      <!-- UPPER content -->
      <main class="ml-0 md:ml-56 flex flex-col min-h-screen">
        <!-- Top bar -->
        <header
          class="fixed top-0 left-0 md:left-56 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
        >
          <div class="flex items-center gap-2">
            <!-- Mobile menu button -->
            <button
              id="sidebarToggle"
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
          class="page-header mt-12 border-b border-[#0d8ddb] px-4 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between"
        >
          <h2 class="text-[#0d8ddb] text-lg font-semibold flex items-center gap-2 mb-2 sm:mb-0">
            <i class="fas fa-flag"></i>
            APPLICANTS
          </h2>
        </section>

        <!-- Dashboard Main Page -->

        <!-- Academic Year / Semester Filters -->
        <form class="px-4 sm:px-6 mt-4 flex flex-wrap justify-end gap-2" method="get" action="applicant.php">
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
              href="applicant.php"
              class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm"
            >
              Clear
            </a>
          <?php endif; ?>
        </form>

        <!-- Table -->
        <section class="px-4 sm:px-6 pb-6 mt-4">
          <div class="rounded-lg border border-[#0d8ddb] bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <p class="text-[#0d8ddb] text-sm font-semibold">Pending Applicants</p>
                <p class="text-xs text-[#052c6a]">
                  Showing <?= htmlspecialchars($pendingCount) ?> pending applicants across all grant categories.
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <?php foreach ($categories as $category): ?>
                  <button
                    type="button"
                    class="filter-button rounded-full border border-[#0d8ddb] px-3 py-2 text-xs font-semibold text-[#0d8ddb] transition"
                    data-filter-category="<?= htmlspecialchars($category["slug"]) ?>"
                  >
                    <?= htmlspecialchars($category["label"]) ?>
                    <span class="ml-1 rounded-full bg-[#e5f1ff] px-2 py-0.5 text-[10px] font-bold text-[#052c6a]">
                      <?= htmlspecialchars($category["count"]) ?>
                    </span>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="mt-4 overflow-x-auto table-float">
              <table
                class="app-table min-w-full text-xs text-center"
              >
                <thead>
                  <tr class="bg-white border-b border-[#0d8ddb]">
                    <th
                      class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]"
                    >
                      Timestamp
                    </th>
                    <th
                      class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]"
                    >
                      Applicant Name
                    </th>
                    <th
                      class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]"
                    >
                      Program / Course
                    </th>
                    <th
                      class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]"
                    >
                      ISG Grant
                    </th>
                    <th
                      class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]"
                    >
                      Application Status
                    </th>
                    <th class="py-2 px-2 font-semibold text-[#fcdc2f]">
                      Action
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($pendingApplicants)): ?>
                    <tr>
                      <td colspan="6" class="py-3 text-center text-[#052c6a]">
                        No pending applicants at the moment.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($pendingApplicants as $applicant): ?>
                      <tr
                        class="border-b border-[#0d8ddb]"
                        data-applicant-row="<?= htmlspecialchars($applicant["category_slug"]) ?>"
                      >
                        <td
                          class="border-r border-[#0d8ddb] py-2 text-left px-2 text-[#052c6a]"
                        >
                          <?= htmlspecialchars($applicant["submitted_at"]) ?>
                        </td>
                        <td
                          class="border-r border-[#0d8ddb] py-2 text-left px-2 text-[#052c6a]"
                        >
                          <?= htmlspecialchars($applicant["name"]) ?>
                        </td>
                        <td
                          class="border-r border-[#0d8ddb] py-2 text-left px-2 text-[#052c6a]"
                        >
                          <?= htmlspecialchars($applicant["program_course"]) ?>
                        </td>
                        <td
                          class="border-r border-[#0d8ddb] py-2 text-left px-2 text-[#052c6a]"
                        >
                          <?= htmlspecialchars($applicant["grant"]) ?>
                        </td>
                        <td class="border-r border-[#0d8ddb] py-2">
                          <span
                            class="bg-[#fcdc2f] text-[#052c6a] rounded px-2 py-0.5 inline-block"
                          >
                            <?= htmlspecialchars($applicant["status"]) ?>
                          </span>
                        </td>
                        <td class="py-2">
                          <div class="flex flex-wrap justify-center gap-2">
                            <button
                              class="bg-[#0d8ddb] text-white rounded px-3 py-1 text-xs"
                              type="button"
                              onclick="window.location.href='view-application.php?id=<?= htmlspecialchars((string)$applicant['id']) ?>'"
                            >
                              Review Application
                            </button>
                            <button
                              class="border border-[#f44336] text-[#f44336] rounded px-3 py-1 text-xs hover:bg-[#f44336] hover:text-white"
                              type="button"
                            >
                              Delete
                            </button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="px-4 sm:px-6 pb-10 mt-2">
          <div class="rounded-lg border border-[#f44336] bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-2">
              <p class="text-[#f44336] text-sm font-semibold">Declined Applicants</p>
              <p class="text-xs text-[#052c6a]">
                Showing <?= htmlspecialchars((string)count($declinedApplicants)) ?> declined applicants.
              </p>
            </div>

            <div class="mt-4 overflow-x-auto table-float table-float-danger">
              <table class="app-table app-table-danger min-w-full text-xs text-center">
                <thead>
                  <tr class="bg-white border-b border-[#f44336]">
                    <th class="border-r border-[#f44336] py-2 px-2 font-semibold text-[#f44336]">
                      Timestamp
                    </th>
                    <th class="border-r border-[#f44336] py-2 px-2 font-semibold text-[#f44336]">
                      Applicant Name
                    </th>
                    <th class="border-r border-[#f44336] py-2 px-2 font-semibold text-[#f44336]">
                      Program / Course
                    </th>
                    <th class="border-r border-[#f44336] py-2 px-2 font-semibold text-[#f44336]">
                      ISG Grant
                    </th>
                    <th class="border-r border-[#f44336] py-2 px-2 font-semibold text-[#f44336]">
                      Application Status
                    </th>
                    <th class="py-2 px-2 font-semibold text-[#f44336]">
                      Action
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($declinedApplicants)): ?>
                    <tr>
                      <td colspan="6" class="py-3 text-center text-[#052c6a]">
                        No declined applicants at the moment.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($declinedApplicants as $applicant): ?>
                      <tr class="border-b border-[#f44336]">
                        <td class="border-r border-[#f44336] py-2 text-left px-2 text-[#052c6a]">
                          <?= htmlspecialchars($applicant["submitted_at"]) ?>
                        </td>
                        <td class="border-r border-[#f44336] py-2 text-left px-2 text-[#052c6a]">
                          <?= htmlspecialchars($applicant["name"]) ?>
                        </td>
                        <td class="border-r border-[#f44336] py-2 text-left px-2 text-[#052c6a]">
                          <?= htmlspecialchars($applicant["program_course"]) ?>
                        </td>
                        <td class="border-r border-[#f44336] py-2 text-left px-2 text-[#052c6a]">
                          <?= htmlspecialchars($applicant["grant"]) ?>
                        </td>
                        <td class="border-r border-[#f44336] py-2">
                          <span class="bg-red-500 text-white rounded px-2 py-0.5 inline-block">
                            <?= htmlspecialchars($applicant["status"]) ?>
                          </span>
                        </td>
                        <td class="py-2">
                          <div class="flex flex-wrap justify-center gap-2">
                            <button
                              class="bg-[#0d8ddb] text-white rounded px-3 py-1 text-xs"
                              type="button"
                              onclick="window.location.href='view-application.php?id=<?= htmlspecialchars((string)$applicant['id']) ?>'"
                            >
                              View Details
                            </button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
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
            item.addEventListener("click", () => {
              if (window.innerWidth < 768) {
                sidebar.classList.add("-translate-x-full");
              }
            });
          });
        }
      });

      document.addEventListener("DOMContentLoaded", () => {
        // Pending applicant filters
        const filterButtons = document.querySelectorAll("[data-filter-category]");
        const applicantRows = document.querySelectorAll("[data-applicant-row]");
        let activeCategory = "";

        const applyCategoryFilter = () => {
          applicantRows.forEach((row) => {
            const matches =
              activeCategory === "" || row.dataset.applicantRow === activeCategory;
            row.style.display = matches ? "table-row" : "none";
          });

          filterButtons.forEach((button) => {
            const isActive = button.dataset.filterCategory === activeCategory;
            button.classList.toggle("active", isActive);
          });
        };

        filterButtons.forEach((button) => {
          button.addEventListener("click", () => {
            const slug = button.dataset.filterCategory;
            activeCategory = activeCategory === slug ? "" : slug;
            applyCategoryFilter();
          });
        });

        applyCategoryFilter();
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
    item.classList.toggle("hover:bg-[#0d8ddb]", !isActive);
  });
});
</script>
</body>
</html>

