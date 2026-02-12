<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Institutional Scholars</title>
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

      .table-zebra tbody tr:nth-child(even) {
        background: #f8fafc;
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
            <li class="flex items-center gap-2 px-4 py-3">
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
              data-nav="isg-scholars.php" onclick="window.location.href='institutional-scholars.php'"
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
            OFFICIAL INSTITUTIONAL SCHOLARS
          </h2>
        </section>

        <section class="mt-12 px-3 sm:px-4 lg:px-6 py-4 bg-gray-100 flex-1 min-h-[calc(100vh-3rem)]">
          <div class="w-full space-y-4 h-full flex flex-col">
            <div class="bg-white rounded-xl shadow-sm border border-[#e5e7eb] px-4 sm:px-6 py-5">
              <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div>
                  <h1 class="text-xl font-bold text-[#052c6a]">Institutional Scholars Storage</h1>
                </div>
              </div>

              <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 mt-4 text-xs">
                <div class="rounded-lg border border-[#dbeafe] bg-[#eff6ff] px-3 py-2">
                  <p class="text-[#1e3a8a] font-semibold">Official Scholars</p>
                  <p id="count-official" class="text-lg font-bold text-[#052c6a]">0</p>
                </div>
                <div class="rounded-lg border border-[#dcfce7] bg-[#f0fdf4] px-3 py-2">
                  <p class="text-[#166534] font-semibold">Student Assistant</p>
                  <p id="count-student-assistant" class="text-lg font-bold text-[#14532d]">0</p>
                </div>
                <div class="rounded-lg border border-[#fae8ff] bg-[#fdf4ff] px-3 py-2">
                  <p class="text-[#86198f] font-semibold">Kabayani</p>
                  <p id="count-kabayani" class="text-lg font-bold text-[#701a75]">0</p>
                </div>
                <div class="rounded-lg border border-[#fef9c3] bg-[#fefce8] px-3 py-2">
                  <p class="text-[#854d0e] font-semibold">Academic</p>
                  <p id="count-academic" class="text-lg font-bold text-[#713f12]">0</p>
                </div>
                <div class="rounded-lg border border-[#fee2e2] bg-[#fef2f2] px-3 py-2">
                  <p class="text-[#991b1b] font-semibold">Others</p>
                  <p id="count-others" class="text-lg font-bold text-[#7f1d1d]">0</p>
                </div>
              </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-[#e5e7eb] overflow-hidden flex-1 flex flex-col min-h-[420px]">
              <div class="px-4 sm:px-6 py-4 border-b border-[#e5e7eb] bg-[#f8fafc]">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div>
                    <h2 class="text-base font-bold text-[#052c6a]">Scholar Category Tables</h2>
                    <p class="text-xs text-gray-600">Blanko pa ang table rows, ready na para sa next data entry/storage.</p>
                  </div>
                  <span class="inline-flex items-center gap-2 text-[11px] font-semibold text-[#0f172a] bg-white border border-[#e2e8f0] px-3 py-1 rounded-full">
                    Active: <span id="activeCategoryLabel" class="text-[#052c6a]">Official Scholars</span>
                  </span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                  <button type="button" data-category="official" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-transparent bg-[#052c6a] text-white shadow-sm">Official Scholars</button>
                  <button type="button" data-category="student_assistant" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-[#e2e8f0] bg-white text-[#334155]">Student Assistant</button>
                  <button type="button" data-category="kabayani" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-[#e2e8f0] bg-white text-[#334155]">Kabayani</button>
                  <button type="button" data-category="academic" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-[#e2e8f0] bg-white text-[#334155]">Academic</button>
                  <button type="button" data-category="others" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-[#e2e8f0] bg-white text-[#334155]">Others</button>
                </div>
              </div>

              <div class="px-4 sm:px-6 py-4 overflow-x-auto flex-1">
                <table class="table-zebra min-w-full text-xs border border-[#dbe2ea] rounded-lg overflow-hidden">
                  <thead class="bg-gradient-to-r from-[#052c6a] to-[#0d8ddb] text-white">
                    <tr>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">No.</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Scholar ID</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Full Name</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Program / Year</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Assigned Office</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Semester</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Academic Year</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Remarks</th>
                    </tr>
                  </thead>
                  <tbody id="scholarRows" class="divide-y divide-[#e5e7eb] bg-white">
                    <tr>
                      <td colspan="8" class="px-3 py-8 text-center text-gray-500 italic">No records yet for Official Scholars.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>

    <script>
      const categoryConfig = {
        official: {
          label: "Official Scholars",
          storageKey: "isg_scholars_official"
        },
        student_assistant: {
          label: "Student Assistant",
          storageKey: "isg_scholars_student_assistant"
        },
        kabayani: {
          label: "Kabayani",
          storageKey: "isg_scholars_kabayani"
        },
        academic: {
          label: "Academic",
          storageKey: "isg_scholars_academic"
        },
        others: {
          label: "Others",
          storageKey: "isg_scholars_others"
        }
      };

      let activeCategory = "official";

      function safeParseArray(value) {
        try {
          const parsed = JSON.parse(value);
          return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
          return [];
        }
      }

      function escapeHtml(value) {
        return String(value ?? "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/\"/g, "&quot;")
          .replace(/'/g, "&#39;");
      }

      function getCategoryRecords(category) {
        const config = categoryConfig[category];
        if (!config) return [];

        const raw = localStorage.getItem(config.storageKey);
        if (raw === null) {
          localStorage.setItem(config.storageKey, "[]");
          return [];
        }

        return safeParseArray(raw);
      }

      function updateCounts() {
        const counts = {
          official: getCategoryRecords("official").length,
          student_assistant: getCategoryRecords("student_assistant").length,
          kabayani: getCategoryRecords("kabayani").length,
          academic: getCategoryRecords("academic").length,
          others: getCategoryRecords("others").length
        };

        document.getElementById("count-official").textContent = counts.official;
        document.getElementById("count-student-assistant").textContent = counts.student_assistant;
        document.getElementById("count-kabayani").textContent = counts.kabayani;
        document.getElementById("count-academic").textContent = counts.academic;
        document.getElementById("count-others").textContent = counts.others;
      }

      function renderTable(category) {
        const config = categoryConfig[category];
        const tableBody = document.getElementById("scholarRows");
        const records = getCategoryRecords(category);

        document.getElementById("activeCategoryLabel").textContent = config.label;
        tableBody.innerHTML = "";

        if (records.length === 0) {
          tableBody.innerHTML =
            '<tr><td colspan="8" class="px-3 py-8 text-center text-gray-500 italic">No records yet for ' +
            escapeHtml(config.label) +
            '.</td></tr>';
          return;
        }

        records.forEach((record, index) => {
          const row = document.createElement("tr");
          row.innerHTML =
            '<td class="px-3 py-2">' + (index + 1) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.scholar_id) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.full_name) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.program_year) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.assigned_office) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.semester) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.academic_year) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.remarks) + "</td>";
          tableBody.appendChild(row);
        });
      }

      function setActiveCategoryButton(selectedCategory) {
        document.querySelectorAll(".category-btn").forEach((button) => {
          const isActive = button.dataset.category === selectedCategory;
          button.classList.toggle("bg-[#052c6a]", isActive);
          button.classList.toggle("text-white", isActive);
          button.classList.toggle("shadow-sm", isActive);
          button.classList.toggle("border-transparent", isActive);
          button.classList.toggle("bg-white", !isActive);
          button.classList.toggle("text-[#334155]", !isActive);
          button.classList.toggle("border-[#e2e8f0]", !isActive);
        });
      }

      function setupCategorySwitching() {
        document.querySelectorAll(".category-btn").forEach((button) => {
          button.addEventListener("click", () => {
            activeCategory = button.dataset.category;
            setActiveCategoryButton(activeCategory);
            renderTable(activeCategory);
          });
        });
      }

      function setupSidebar() {
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
      }

      function markActiveSidebarItem() {
        const sidebar = document.getElementById("sidebar");
        if (!sidebar) return;

        const currentPage = window.location.pathname.split("/").pop().toLowerCase();
        const sidebarAliases = {
          "view-application.php": "applicant.php",
          "department-evaluation-indi.php": "department-evaluation-list.php",
          "summary-reports.php": "summary-report.php",
          "list-0f-qualified.php": "list-of-qualified.php",
          "institutional-scholars.php": "isg-scholars.php"
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
      }

      document.addEventListener("DOMContentLoaded", () => {
        setupSidebar();
        markActiveSidebarItem();
        setupCategorySwitching();
        updateCounts();
        setActiveCategoryButton(activeCategory);
        renderTable(activeCategory);
      });
    </script>
  </body>
</html>