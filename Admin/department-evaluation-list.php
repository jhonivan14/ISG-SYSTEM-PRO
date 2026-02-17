<?php require_once __DIR__ . "/includes/school-term-filter.php"; ?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Student Assistants Evaluation List</title>
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
        background-color: #052c6a;
        border-radius: 3px;
      }
    </style>
  </head>
  <body class="bg-white font-sans">
    <div class="min-h-screen">
      <aside
        id="sidebar"
        class="flex flex-col bg-[#052c6a] text-white w-56 h-screen fixed left-0 top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out overflow-y-auto"
      >
        <div class="flex items-center gap-3 px-4 py-4 border-b border-[#0d8ddb]">
          <img src="../img/SMCCNEWLOGO.png" class="rounded-full w-16 h-16 object-cover" alt="SMCC Logo" />
          <span class="text-sm font-normal">Admission and Scholarship Office</span>
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
          <div class="px-3 pb-3 pt-1">
            <button
              onclick="window.location.href='../logout.php'"
              class="w-full flex items-center justify-center gap-2 text-[11px] font-semibold bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 px-3 py-2 rounded-full shadow-md hover:shadow-lg transition-all duration-150"
            >
              <i class="fas fa-sign-out-alt text-xs"></i>
              <span>Logout</span>
            </button>
          </div>
        </div>
      </aside>

      <main class="ml-0 md:ml-56 flex flex-col min-h-screen">
        <header
          class="fixed top-0 left-0 md:left-56 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
        >
          <div class="flex items-center gap-2">
            <button
              id="sidebarToggle"
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
          class="page-header mt-12 border-b border-[#0d8ddb] px-4 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between"
        >
          <h2 class="text-[#0d8ddb] text-lg font-semibold flex items-center gap-2 mb-2 sm:mb-0">
            <i class="fas fa-flag"></i>
            DEPARTMENTAL EVALUATION
          </h2>
        </section>

        <section class="mt-12 px-3 sm:px-4 lg:px-6 py-4 bg-gray-100 flex-1 min-h-[calc(100vh-3rem)]">
          <div class="w-full space-y-4 h-full flex flex-col">
            <div class="bg-white rounded-lg shadow-sm border border-[#e5e7eb] px-4 sm:px-6 py-4 space-y-3">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                  <h2 class="text-lg font-semibold text-[#052c6a]">Student Assistants Evaluation List</h2>
                  <p class="text-sm text-gray-600">Open the window per semester to show status and actions.</p>
                  <p class="text-xs text-gray-500 mt-1">Evaluation window: <span id="evalWindowLabel" class="font-semibold text-[#052c6a]">Closed</span></p>
                </div>
                <button
                  id="openEvalBtn"
                  class="bg-[#0d8ddb] hover:bg-[#0b7cc4] text-white text-sm font-semibold px-4 py-2 rounded shadow-sm transition"
                >
                  Open for evaluation
                </button>
              </div>
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
                      <option value="" <?php echo $selectedSchoolYear === "" ? "selected" : ""; ?>>All Academic Years</option>
                      <?php foreach ($schoolYearOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedSchoolYear === $option ? "selected" : ""; ?>>
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
                      <option value="" <?php echo $selectedSemester === "" ? "selected" : ""; ?>>All Semesters</option>
                      <?php foreach ($semesterOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedSemester === $option ? "selected" : ""; ?>>
                          <?php echo htmlspecialchars($option); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <?php if ($selectedSchoolYear !== "" || $selectedSemester !== ""): ?>
                      <a
                        href="department-evaluation-list.php"
                        class="inline-flex items-center mt-2 rounded border border-[#e5e7eb] bg-white px-3 py-1.5 text-xs font-semibold text-[#052c6a]"
                      >
                        Clear
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

      // Populate student assistants list; hide status/action until opened
      document.addEventListener("DOMContentLoaded", () => {
        const assistantData = [
          {
            name: "John Michael Santos",
            course: "BSIT",
            yearLevel: "3rd Year",
            office: "Learning Resource Center",
            status: "evaluated",
            academicYear: "2024-2025",
            semester: "2nd Semester",
            evaluatedAt: "2025-03-15T09:30:00",
          },
          {
            name: "Maria Johnson",
            course: "BSA",
            yearLevel: "2nd Year",
            office: "Accounting Office",
            status: "not yet evaluated",
            academicYear: "2024-2025",
            semester: "2nd Semester",
            evaluatedAt: null,
          },
          {
            name: "Aisha Khan",
            course: "BSBA",
            yearLevel: "4th Year",
            office: "Registrar",
            status: "evaluated",
            academicYear: "2024-2025",
            semester: "1st Semester",
            evaluatedAt: "2025-03-12T14:10:00",
          },
          {
            name: "Daniel Lee",
            course: "BSIT",
            yearLevel: "1st Year",
            office: "IT Department",
            status: "not yet evaluated",
            academicYear: "2024-2025",
            semester: "1st Semester",
            evaluatedAt: null,
          },
        ];

        const tbody = document.getElementById("assistantRows");
        const openBtn = document.getElementById("openEvalBtn");
        const windowLabel = document.getElementById("evalWindowLabel");
        const searchInput = document.getElementById("searchInput");
        const yearFilter = document.getElementById("yearFilter");
        const semFilter = document.getElementById("semFilter");
        if (!tbody) return;

        let evalOpen = false;
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
              item.office.toLowerCase().includes(searchTerm);
            const matchesYear = yearSelection === "all" || item.academicYear === yearSelection;
            const matchesSem = semSelection === "all" || item.semester === semSelection;
            return matchesSearch && matchesYear && matchesSem;
          });

          filtered.forEach((item) => {
            const row = document.createElement("tr");

            const timestamp =
              evalOpen && item.status === "evaluated" && item.evaluatedAt
                ? new Date(item.evaluatedAt).toLocaleString()
                : "--";

            const statusClasses =
              item.status === "evaluated"
                ? "bg-green-100 text-green-800"
                : "bg-yellow-100 text-yellow-800";

            const disabled = !evalOpen || item.status !== "evaluated";

            row.innerHTML = `
              <td class="px-3 py-2 text-[#052c6a]">${timestamp}</td>
              <td class="px-3 py-2 text-[#052c6a] font-semibold">${item.name}</td>
              <td class="px-3 py-2 text-[#052c6a]">${item.course}</td>
              <td class="px-3 py-2 text-[#052c6a]">${item.yearLevel}</td>
              <td class="px-3 py-2 text-[#052c6a]">${item.office}</td>
              <td class="px-3 py-2">
                ${
                  evalOpen
                    ? `<span class="px-2 py-1 rounded-full text-[11px] ${statusClasses}">
                        ${item.status === "evaluated" ? "Evaluated" : "Not yet evaluated"}
                      </span>`
                    : `<span class="text-[11px] text-red-600 italic">Not yet opened</span>`
                }
              </td>
              <td class="px-3 py-2">
                ${
                  evalOpen
                    ? `<a
                        href="${disabled ? "#" : "department-evaluation-indi.php#evaluation-details"}"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded text-white text-[11px] ${
                          disabled ? "bg-gray-400 cursor-not-allowed opacity-70" : "bg-[#0d8ddb] hover:bg-[#0b7cc4]"
                        }"
                        ${disabled ? "aria-disabled='true' tabindex='-1'" : ""}
                      >
                        <i class="fas fa-eye"></i>
                        View Evaluation
                      </a>`
                    : `<span class="text-[11px] text-red-600 italic">Not yet opened</span>`
                }
              </td>
            `;

            tbody.appendChild(row);
          });
        };

        if (openBtn && windowLabel) {
          openBtn.addEventListener("click", () => {
            evalOpen = true;
            windowLabel.textContent = "Open";
            openBtn.textContent = "Evaluation is open";
            openBtn.disabled = true;
            openBtn.classList.remove("hover:bg-[#0b7cc4]");
            openBtn.classList.add("bg-gray-400", "cursor-not-allowed", "opacity-80");
            renderRows();
          });
        }

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

