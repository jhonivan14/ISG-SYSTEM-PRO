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

$pendingApplicants = [];
$pendingQuery = "SELECT created_at, applicant_name, program_course, grant_id, status FROM applications ORDER BY created_at DESC";
if ($result = $conn->query($pendingQuery)) {
  while ($row = $result->fetch_assoc()) {
    $grantId = (int)($row["grant_id"] ?? 0);
    $grantLabel = $grantLabels[$grantId] ?? "Others";
    $submittedAtRaw = $row["created_at"] ?? "";
    $submittedAt = $submittedAtRaw ? date("Y-m-d h:i A", strtotime($submittedAtRaw)) : "";
    $status = isset($row["status"]) ? trim((string)$row["status"]) : "";
    if ($status === "") {
      $status = "Pending";
    }

    $pendingApplicants[] = [
      "submitted_at" => $submittedAt,
      "name" => $row["applicant_name"] ?? "",
      "program_course" => $row["program_course"] ?? "",
      "grant" => $grantLabel,
      "status" => $status,
    ];
  }
  $result->free();
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

$chartYears = [];
$barData = [];
$lineData = [];

$currentYear = (int)date("Y");
$currentMonth = (int)date("n");
$currentSchoolYearStart = $currentMonth < 6 ? $currentYear - 1 : $currentYear;
$currentSchoolYear = $currentSchoolYearStart . "-" . ($currentSchoolYearStart + 1);

$yearResult = $conn->query("SELECT DISTINCT school_year FROM applications WHERE school_year IS NOT NULL AND TRIM(school_year) <> ''");
if ($yearResult) {
  while ($row = $yearResult->fetch_assoc()) {
    $value = trim((string)($row["school_year"] ?? ""));
    if ($value !== "") {
      $chartYears[] = $value;
    }
  }
  $yearResult->free();
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

$barQuery = "SELECT school_year, semester, COUNT(*) AS total
  FROM applications
  WHERE school_year IS NOT NULL AND TRIM(school_year) <> ''
    AND semester IS NOT NULL AND TRIM(semester) <> ''
  GROUP BY school_year, semester
  ORDER BY school_year ASC";
if ($result = $conn->query($barQuery)) {
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

$lineQuery = "SELECT school_year,
    COUNT(*) AS total,
    SUM(CASE WHEN LOWER(TRIM(status)) = 'approved' THEN 1 ELSE 0 END) AS qualified
  FROM applications
  WHERE school_year IS NOT NULL AND TRIM(school_year) <> ''
  GROUP BY school_year
  ORDER BY school_year ASC";
if ($result = $conn->query($lineQuery)) {
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
?>

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
    <!-- Charts CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
      /* Custom scrollbar for sidebar */
      ::-webkit-scrollbar {
        width: 6px;
      }
      ::-webkit-scrollbar-thumb {
        background-color: #052c6a; /* navy blue */
        border-radius: 3px;
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

      <!-- Main content -->
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

        <!-- Dashboard header -->
        <section
          class="mt-12 border-b border-[#0d8ddb] px-4 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between"
        >
          <h2
            class="text-[#0d8ddb] text-lg font-semibold flex items-center gap-2 mb-2 sm:mb-0"
          >
            <i class="fas fa-trophy"></i>
            Admin Dashboard
          </h2>
          <div class="flex text-xs text-[#052c6a] space-x-4 sm:space-x-6">
            <div class="text-right">
              <div class="text-[#0d8ddb]">Total Scholars</div>
              <div class="text-[#fcdc2f] font-semibold">120</div>
            </div>
            <div class="text-right">
              <div class="text-[#0d8ddb]">Applicants</div>
              <div class="text-[#052c6a] font-semibold">450</div>
            </div>
            <div class="text-right">
              <div class="text-[#0d8ddb]">Panelists</div>
              <div class="text-[#fcdc2f] font-semibold">18</div>
            </div>
            <div class="text-right">
              <div class="text-[#0d8ddb]">Head of offices</div>
              <div class="text-[#0d8ddb] font-semibold">20</div>
            </div>
          </div>
        </section>

        <!-- Stats cards above charts -->
        <section
          class="px-4 sm:px-6 pt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
        >
          <!-- Total Scholars -->
          <div
            class="bg-[#052c6a] text-white rounded-lg p-4 flex items-center justify-between shadow"
          >
            <div>
              <p class="text-xs text-[#fcdc2f] uppercase tracking-wide">
                Total Scholars
              </p>
              <p class="text-2xl font-bold mt-1">120</p>
              <p class="text-[11px] text-gray-200 mt-1">
                Currently enrolled scholars
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
              <p class="text-2xl font-bold mt-1">450</p>
              <p class="text-[11px] text-gray-500 mt-1">
                Total application submitted
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
              <p class="text-2xl font-bold mt-1">18</p>
              <p class="text-[11px] text-[#052c6a] mt-1">
                Waiting for interview schedule
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
              <p class="text-2xl font-bold mt-1">20</p>
              <p class="text-[11px] text-white mt-1">Active evaluators</p>
            </div>
            <div class="text-3xl opacity-90">
              <i class="fas fa-user-tie"></i>
            </div>
          </div>
        </section>

        <!-- Charts -->
        <section class="px-4 sm:px-6 space-y-6 mt-4">
          <!-- Applicants Statistical Report (Bar Chart) -->
          <div class="bg-gray-50 border border-[#0d8ddb] rounded p-4">
            <div class="text-[#052c6a] text-sm font-semibold mb-2">
              Applicants Statistical Report
            </div>
            <div class="border-2 border-[#0d8ddb] rounded h-64 md:h-80">
              <canvas id="applicantsBarChart" class="w-full h-full"></canvas>
            </div>
          </div>

          <!-- Trends (Line Chart) -->
          <div class="bg-gray-50 border border-[#0d8ddb] rounded p-4">
            <div class="text-[#052c6a] text-sm font-semibold mb-2">
              Yearly Trend
            </div>
            <div class="border-2 border-[#0d8ddb] rounded h-64 md:h-80">
              <canvas id="trendLineChart" class="w-full h-full"></canvas>
            </div>
          </div>
        </section>

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
                <?php endforeach; ?>
              </div>
            </div>

            <div class="mt-4 overflow-x-auto">
              <table
                class="min-w-full border border-[#0d8ddb] text-xs text-center"
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
                      <td colspan="5" class="py-3 text-center text-[#052c6a]">
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

      // Chart.js helpers for consistent styling
      const brandBlue = "#0d8ddb";
      const brandNavy = "#052c6a";
      const brandYellow = "#fcdc2f";

      // handy RGBA for brand colors
      const purpleBlueFill = "rgba(106, 110, 230, 0.9)";
      const tealBlueFill = "rgba(65, 155, 180, 0.9)";

      document.addEventListener("DOMContentLoaded", () => {
        // === BAR CHART: grouped by YEAR with 1st & 2nd Sem per year ===
        const barLabels = <?php echo json_encode($chartYears); ?>;
        const firstSem = <?php echo json_encode($firstSemCounts); ?>;
        const secondSem = <?php echo json_encode($secondSemCounts); ?>;

        const barCtx = document.getElementById("applicantsBarChart");
        if (barCtx && window.Chart) {
          new Chart(barCtx, {
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
        }

        // === LINE CHART (yearly trend) ===
        const trendCtx = document.getElementById("trendLineChart");
        if (trendCtx && window.Chart) {
          new Chart(trendCtx, {
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
    item.classList.toggle("hover:bg-[#0d8ddb]", !isActive);
  });
});
</script>
</body>
</html>

