<?php
// Guide: Dedicated admin page for declined applicant history.
// Trace: bootstrap filters -> backfill declined snapshots -> query history -> render table.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once "../db.php";
require_once __DIR__ . "/includes/school-term-filter.php";
require_once __DIR__ . "/includes/application-decline-history.php";

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
  15 => "Michaelinian Stakeholders Grant",
];

applicationDeclineHistoryBackfillCurrent($conn);

$filterClauses = [];
$filterParams = [];
$filterTypes = "";
if ($activeSchoolYearFilter !== "") {
  $filterClauses[] = "h.school_year = ?";
  $filterParams[] = $activeSchoolYearFilter;
  $filterTypes .= "s";
}
if ($activeSemesterFilter !== "") {
  $filterClauses[] = "h.semester = ?";
  $filterParams[] = $activeSemesterFilter;
  $filterTypes .= "s";
}

$declinedApplicants = [];
$historyQuery = "
  SELECT
    h.id,
    h.application_id,
    h.reference_number,
    h.applicant_name,
    h.program_course,
    h.grant_id,
    h.school_year,
    h.semester,
    h.status,
    h.declined_at
  FROM application_decline_history h
  LEFT JOIN applications a ON a.id = h.application_id
";
if (!empty($filterClauses)) {
  $historyQuery .= " WHERE " . implode(" AND ", $filterClauses);
}
$historyQuery .= " ORDER BY h.declined_at DESC, h.id DESC";

if ($stmt = $conn->prepare($historyQuery)) {
  if (!empty($filterParams)) {
    $stmt->bind_param($filterTypes, ...$filterParams);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
      $grantId = (int)($row["grant_id"] ?? 0);
      $declinedAtRaw = (string)($row["declined_at"] ?? "");
      $declinedApplicants[] = [
        "history_id" => (int)($row["id"] ?? 0),
        "application_id" => (int)($row["application_id"] ?? 0),
        "declined_at" => $declinedAtRaw !== "" ? date("Y-m-d h:i A", strtotime($declinedAtRaw)) : "",
        "name" => (string)($row["applicant_name"] ?? ""),
        "program_course" => (string)($row["program_course"] ?? ""),
        "reference_number" => (string)($row["reference_number"] ?? ""),
        "grant" => $grantLabels[$grantId] ?? "Others",
        "term" => trim((string)($row["semester"] ?? "") . " " . (string)($row["school_year"] ?? "")),
        "status" => trim((string)($row["status"] ?? "Rejected")) ?: "Rejected",
      ];
    }
    $result->free();
  }
  $stmt->close();
}

$totalDeclined = count($declinedApplicants);
$applicantPageParams = [];
if ($activeSchoolYearFilter !== "") {
  $applicantPageParams["school_year"] = $activeSchoolYearFilter;
}
if ($activeSemesterFilter !== "") {
  $applicantPageParams["semester"] = $activeSemesterFilter;
}
$applicantPageSuffix = !empty($applicantPageParams) ? "?" . http_build_query($applicantPageParams) : "";
$pendingPageUrl = "applicant.php" . $applicantPageSuffix;
$declinedPageUrl = "declined-applicants.php" . $applicantPageSuffix;
$summaryPageUrl = "summary-of-applicants.php" . $applicantPageSuffix;
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Declined Applicants</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <style>
      ::-webkit-scrollbar { width: 6px; }
      ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #ffb4b4 0%, #e94a4a 100%);
        border-radius: 999px;
      }
      #sidebar > nav > ul { padding: 0.35rem 0.5rem 5.5rem; }
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
      .app-table {
        border-collapse: separate;
        border-spacing: 0;
      }
      .app-table thead tr {
        background: linear-gradient(90deg, #ba2a2a 0%, #e94a4a 100%);
      }
      .app-table thead th {
        color: #ffffff;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        font-size: 10px;
        padding: 11px 8px;
        border-right: 1px solid rgba(255, 255, 255, 0.24);
      }
      .app-table tbody td {
        padding: 11px 8px;
        border-right: 1px solid rgba(186, 42, 42, 0.16);
        border-bottom: 1px solid rgba(186, 42, 42, 0.14);
        color: #052c6a;
      }
      .app-table tbody tr:nth-child(even) { background-color: #fff5f5; }
      .app-table tbody tr:hover { background-color: #ffeaea; }
    </style>
  </head>
  <body class="bg-white font-sans">
    <div class="min-h-screen">
      <aside
        id="sidebar"
        class="flex flex-col bg-gradient-to-b from-[#031f4f] via-[#0a4b86] to-[#0f9ad8] text-white w-64 h-screen fixed left-0 top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out overflow-y-auto shadow-[12px_0_28px_-12px_rgba(4,31,79,0.65)]"
      >
        <div class="mx-3 mt-3 rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
          <div class="flex items-center gap-3">
            <img src="../img/SMCCNEWLOGO.png" class="rounded-full w-14 h-14 object-cover ring-2 ring-white/45" alt="SMCC Logo" />
            <div class="min-w-0">
              <p class="text-[10px] uppercase tracking-[0.14em] text-blue-100/85">SMCC Scholarship</p>
              <p class="text-sm font-semibold leading-tight text-white">Admission and Scholarship Office</p>
              <p class="text-[10px] text-blue-100/80 mt-1">Admin Management Portal</p>
            </div>
          </div>
        </div>

        <nav class="flex-1 mt-2">
          <ul class="text-xs font-semibold">
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="adminDashboard.php" onclick="window.location.href='adminDashboard.php'">
              <i class="fas fa-home w-5"></i><span>Home</span>
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
                    <a href="applicant.php" class="block rounded-lg px-3 py-2 text-blue-50 hover:bg-white/15">
                      Pending Applicants
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
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="approved.php" onclick="window.location.href='approved.php'">
              <i class="fas fa-thumbs-up w-5"></i><span>Approved Applications</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="interviewEvaluation.php" onclick="window.location.href='interviewEvaluation.php'">
              <i class="fas fa-check-circle w-5"></i><span>Interview Evaluation</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="ranks.php" onclick="window.location.href='ranks.php'">
              <i class="fas fa-star w-5"></i><span>Applicant Ranks</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="list-of-qualified.php" onclick="window.location.href='list-of-qualified.php'">
              <i class="fas fa-list w-5"></i><span>List of Qualified</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="department-evaluation-list.php" onclick="window.location.href='department-evaluation-list.php'">
              <i class="fas fa-building w-5"></i><span>Departmental Evaluation</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="summary-report.php" onclick="window.location.href='summary-report.php'">
              <i class="fas fa-flag w-5"></i><span>Summary Evaluation Report</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="institutional-scholars.php" onclick="window.location.href='institutional-scholars.php'">
              <i class="fas fa-chart-line w-5"></i><span>Institutional Scholars</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="accounts.php" onclick="window.location.href='accounts.php'">
              <i class="fas fa-user-circle w-5"></i><span>Settings</span>
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

      <main class="ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pt-14">
        <section class="fixed top-0 left-0 md:left-64 right-0 z-20 bg-white border-b border-slate-200 px-4 sm:px-6 py-3 shadow-sm">
          <div class="flex items-center gap-2">
            <button id="sidebarToggle" class="md:hidden inline-flex items-center justify-center p-2 rounded bg-slate-700 text-white hover:bg-slate-800 focus:outline-none transition-colors" type="button">
              <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-slate-800 text-lg font-semibold flex items-center gap-2">
              <i class="fas fa-user-times"></i>
              DECLINED APPLICANTS
            </h2>
          </div>
        </section>

        <form class="px-4 sm:px-6 mt-4 flex flex-wrap justify-end gap-2" method="get" action="declined-applicants.php">
          <a href="applicant.php" class="inline-flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm">
            <i class="fas fa-arrow-left"></i>
            Back to Applicants
          </a>
          <select class="rounded-full border border-[#f44336] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none" name="school_year" onchange="this.form.submit()">
            <option value="" <?= $rawSelectedSchoolYear !== null && $activeSchoolYearFilter === "" ? "selected" : ""; ?>>All School Years</option>
            <?php foreach ($schoolYearOptions as $option): ?>
              <option value="<?= htmlspecialchars($option) ?>" <?= $activeSchoolYearFilter === $option ? "selected" : ""; ?>>
                <?= htmlspecialchars($option) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select class="rounded-full border border-[#f44336] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none" name="semester" onchange="this.form.submit()">
            <option value="" <?= $activeSemesterFilter === "" ? "selected" : ""; ?>>All Semesters</option>
            <?php foreach ($semesterOptions as $option): ?>
              <option value="<?= htmlspecialchars($option) ?>" <?= $activeSemesterFilter === $option ? "selected" : ""; ?>>
                <?= htmlspecialchars($option) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($rawSelectedSchoolYear !== null || $rawSelectedSemester !== null): ?>
            <a href="declined-applicants.php" class="inline-flex items-center rounded-full border border-[#f44336] bg-white px-3 py-2 text-xs font-semibold text-[#f44336] shadow-sm">Clear</a>
          <?php endif; ?>
        </form>

        <section class="px-4 sm:px-6 pb-10 mt-4">
          <div class="rounded-lg border border-[#f44336] bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <p class="text-[#f44336] text-sm font-semibold">Declined Applicant History</p>
                <p class="text-xs text-[#052c6a]">
                  Showing <?= htmlspecialchars((string)$totalDeclined) ?> declined records. Reapplied applicants remain listed here for monitoring.
                </p>
              </div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-[18px] border border-red-200 shadow-sm">
              <table class="app-table min-w-full text-xs text-center">
                <thead>
                  <tr>
                    <th>Declined At</th>
                    <th>Applicant Name</th>
                    <th>Program / Course</th>
                    <th>Reference Number</th>
                    <th>ISG Grant</th>
                    <th>Term</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($declinedApplicants)): ?>
                    <tr>
                      <td colspan="8" class="py-3 text-center text-[#052c6a]">No declined applicant history yet.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($declinedApplicants as $applicant): ?>
                      <tr>
                        <td class="text-left"><?= htmlspecialchars($applicant["declined_at"]) ?></td>
                        <td class="text-left"><?= htmlspecialchars($applicant["name"]) ?></td>
                        <td class="text-left"><?= htmlspecialchars($applicant["program_course"]) ?></td>
                        <td class="text-left"><?= htmlspecialchars($applicant["reference_number"]) ?></td>
                        <td class="text-left"><?= htmlspecialchars($applicant["grant"]) ?></td>
                        <td class="text-left"><?= htmlspecialchars($applicant["term"]) ?></td>
                        <td>
                          <span class="inline-flex rounded bg-red-500 px-2 py-0.5 text-white"><?= htmlspecialchars($applicant["status"]) ?></span>
                        </td>
                        <td>
                          <?php if ((int)$applicant["application_id"] > 0): ?>
                            <button class="rounded bg-[#0d8ddb] px-3 py-1 text-xs text-white" type="button" onclick="window.location.href='view-application.php?id=<?= htmlspecialchars((string)$applicant["application_id"]) ?>'">
                              View Details
                            </button>
                          <?php else: ?>
                            <span class="text-slate-500">N/A</span>
                          <?php endif; ?>
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
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");

        if (toggleBtn && sidebar) {
          toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
          });
        }

        if (!sidebar) return;
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
          const isActive = (item.dataset.nav || "").toLowerCase() === activePage;
          item.classList.toggle("bg-[#fcdc2f]", isActive);
          item.classList.toggle("bg-opacity-90", isActive);
          item.classList.toggle("text-[#052c6a]", isActive);
          item.classList.toggle("hover:bg-white/15", !isActive);
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



