<?php
// Guide: Dedicated admin page for the applicant name summary list.
// Trace: bootstrap filters -> load all applicants -> render summary list.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once "../db.php";
require_once __DIR__ . "/includes/school-term-filter.php";

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

$filterClauses = [];
$filterParams = [];
$filterTypes = "";
if ($activeSchoolYearFilter !== "") {
  $filterClauses[] = "school_year = ?";
  $filterParams[] = $activeSchoolYearFilter;
  $filterTypes .= "s";
}
if ($activeSemesterFilter !== "") {
  $filterClauses[] = "semester = ?";
  $filterParams[] = $activeSemesterFilter;
  $filterTypes .= "s";
}

$summaryApplicants = [];
$summaryQuery = "SELECT id, created_at, applicant_name, program_course, reference_number, grant_id, school_year, semester, status
  FROM applications
  WHERE 1 = 1";
if (!empty($filterClauses)) {
  $summaryQuery .= " AND " . implode(" AND ", $filterClauses);
}
$summaryQuery .= " ORDER BY created_at DESC, id DESC";

if ($stmt = $conn->prepare($summaryQuery)) {
  if (!empty($filterParams)) {
    $stmt->bind_param($filterTypes, ...$filterParams);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
      $grantId = (int)($row["grant_id"] ?? 0);
      $status = trim((string)($row["status"] ?? ""));
      if ($status === "") {
        $status = "Pending";
      }
      $summaryApplicants[] = [
        "id" => (int)($row["id"] ?? 0),
        "submitted_at" => trim((string)($row["created_at"] ?? "")),
        "name" => trim((string)($row["applicant_name"] ?? "")),
        "program_course" => trim((string)($row["program_course"] ?? "")),
        "reference_number" => trim((string)($row["reference_number"] ?? "")),
        "grant" => $grantLabels[$grantId] ?? "Others",
        "term" => trim((string)($row["semester"] ?? "") . " " . (string)($row["school_year"] ?? "")),
        "status" => $status,
      ];
    }
    $result->free();
  }
  $stmt->close();
}

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
$totalApplicants = count($summaryApplicants);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Summary of Applicants</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <style>
      ::-webkit-scrollbar { width: 6px; }
      ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
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
      .print-only { display: none; }
      @media print {
        @page {
          size: A4 portrait;
          margin: 0.45in;
        }
        body {
          background: #ffffff !important;
          color: #0f172a !important;
        }
        .no-print {
          display: none !important;
        }
        .print-only {
          display: block !important;
        }
        main {
          margin-left: 0 !important;
          padding-top: 0 !important;
          min-height: auto !important;
          background: #ffffff !important;
        }
        .print-area {
          margin-top: 0 !important;
          padding: 0 !important;
        }
        .print-card {
          border: 0 !important;
          box-shadow: none !important;
          padding: 0 !important;
        }
        .print-list {
          border-color: #cbd5e1 !important;
        }
        .print-row {
          break-inside: avoid;
          page-break-inside: avoid;
        }
      }
    </style>
  </head>
  <body class="bg-white font-sans">
    <div class="min-h-screen">
      <aside
        id="sidebar"
        class="no-print flex flex-col bg-gradient-to-b from-[#031f4f] via-[#0a4b86] to-[#0f9ad8] text-white w-64 h-screen fixed left-0 top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out overflow-y-auto shadow-[12px_0_28px_-12px_rgba(4,31,79,0.65)]"
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
              <button onclick="window.location.href='../logout.php'" class="w-full flex items-center justify-center gap-2 text-[11px] font-semibold bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 px-3 py-2 rounded-full shadow-md hover:shadow-lg transition-all duration-150" type="button">
                <i class="fas fa-sign-out-alt text-xs"></i><span>Logout</span>
              </button>
            </div>
          </div>
        </div>
      </aside>

      <main class="ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pt-14">
        <section class="no-print fixed top-0 left-0 md:left-64 right-0 z-20 bg-white border-b border-slate-200 px-4 sm:px-6 py-3 shadow-sm">
          <div class="flex items-center gap-2">
            <button id="sidebarToggle" class="md:hidden inline-flex items-center justify-center p-2 rounded bg-slate-700 text-white hover:bg-slate-800 focus:outline-none transition-colors" type="button">
              <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-slate-800 text-lg font-semibold flex items-center gap-2">
              <i class="fas fa-users"></i>
              SUMMARY OF APPLICANTS
            </h2>
          </div>
        </section>

        <form class="no-print px-4 sm:px-6 mt-4 flex flex-wrap justify-end gap-2" method="get" action="summary-of-applicants.php">
          <select class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none" name="school_year" onchange="this.form.submit()">
            <option value="" <?= $rawSelectedSchoolYear !== null && $activeSchoolYearFilter === "" ? "selected" : ""; ?>>All School Years</option>
            <?php foreach ($schoolYearOptions as $option): ?>
              <option value="<?= htmlspecialchars($option) ?>" <?= $activeSchoolYearFilter === $option ? "selected" : ""; ?>><?= htmlspecialchars($option) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none" name="semester" onchange="this.form.submit()">
            <option value="" <?= $activeSemesterFilter === "" ? "selected" : ""; ?>>All Semesters</option>
            <?php foreach ($semesterOptions as $option): ?>
              <option value="<?= htmlspecialchars($option) ?>" <?= $activeSemesterFilter === $option ? "selected" : ""; ?>><?= htmlspecialchars($option) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($rawSelectedSchoolYear !== null || $rawSelectedSemester !== null): ?>
            <a href="summary-of-applicants.php" class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm">Clear</a>
          <?php endif; ?>
          <button
            class="inline-flex items-center gap-2 rounded-full border border-[#052c6a] bg-[#052c6a] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#041f4f]"
            type="button"
            onclick="window.print()"
          >
            <i class="fas fa-print text-[11px]"></i>
            <span>Print</span>
          </button>
        </form>

        <section class="print-area px-4 sm:px-6 pb-10 mt-4">
          <div class="print-card rounded-lg border border-[#d9e7ff] bg-white p-4 shadow-sm">
            <div class="print-only mb-4 border-b border-slate-300 pb-3 text-center">
              <p class="text-sm font-semibold uppercase tracking-wide text-slate-700">Saint Michael College of Caraga</p>
              <h1 class="mt-1 text-xl font-bold text-slate-900">Summary of Applicants</h1>
              <p class="mt-1 text-xs text-slate-600">
                <?= htmlspecialchars($activeSemesterFilter !== "" ? $activeSemesterFilter : "All Semesters") ?>,
                S.Y. <?= htmlspecialchars($activeSchoolYearFilter !== "" ? $activeSchoolYearFilter : "All School Years") ?>
              </p>
            </div>
            <div>
              <p class="text-[#052c6a] text-sm font-semibold">Summary of Applicants</p>
              <p class="text-xs text-[#052c6a]/70">
                Showing <?= htmlspecialchars((string)$totalApplicants) ?> applicants under the selected filters.
              </p>
            </div>
            <div class="print-list mt-4 divide-y divide-[#e1eaf8] rounded-lg border border-[#e1eaf8]">
              <?php if (empty($summaryApplicants)): ?>
                <div class="px-4 py-3 text-sm text-[#052c6a]/70">No applicants found for the selected filters.</div>
              <?php else: ?>
                <?php foreach ($summaryApplicants as $applicant): ?>
                  <?php
                  $statusKey = strtolower(trim((string)$applicant["status"]));
                  $statusClass = "bg-slate-100 text-slate-700";
                  if ($statusKey === "pending" || $statusKey === "") {
                    $statusClass = "bg-[#fff8d8] text-[#7a5800]";
                  } elseif ($statusKey === "reapplied") {
                    $statusClass = "bg-[#e5f1ff] text-[#052c6a]";
                  } elseif ($statusKey === "approved") {
                    $statusClass = "bg-emerald-50 text-emerald-700";
                  } elseif ($statusKey === "rejected" || $statusKey === "declined") {
                    $statusClass = "bg-red-50 text-[#ba2a2a]";
                  }
                  ?>
                  <div class="print-row flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                      <p class="break-anywhere text-sm font-semibold text-[#052c6a]">
                        <?= htmlspecialchars($applicant["name"] !== "" ? $applicant["name"] : "Unnamed Applicant") ?>
                      </p>
                      <p class="break-anywhere mt-1 text-xs text-[#052c6a]/65">
                        <?= htmlspecialchars($applicant["grant"]) ?>
                        <?php if ($applicant["reference_number"] !== ""): ?>
                          &middot; Ref: <?= htmlspecialchars($applicant["reference_number"]) ?>
                        <?php endif; ?>
                      </p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-bold <?= htmlspecialchars($statusClass) ?>">
                      <?= htmlspecialchars($applicant["status"]) ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
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



