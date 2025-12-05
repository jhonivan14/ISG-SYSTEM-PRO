<?php
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

$pendingApplicants = [
  ["submitted_at" => "2025-01-08 09:15 AM", "name" => "Maria Johnson", "grant" => "Student Assistant", "status" => "Pending"],
  ["submitted_at" => "2025-01-08 09:25 AM", "name" => "Mark Christian Joven Balatayo", "grant" => "Kabayani Scholarship", "status" => "Pending"],
  ["submitted_at" => "2025-01-08 09:33 AM", "name" => "Jhon Ivan Tabanao", "grant" => "Academic Scholarship", "status" => "Pending"],
  ["submitted_at" => "2025-01-08 09:40 AM", "name" => "Carlos Martinez", "grant" => "Student Assistant", "status" => "Pending"],
  ["submitted_at" => "2025-01-08 10:02 AM", "name" => "Aisha Khan", "grant" => "Academic Scholar", "status" => "Pending"],
  ["submitted_at" => "2025-01-08 10:08 AM", "name" => "Daniel Lee", "grant" => "Kabayani Scholarship", "status" => "Pending"],
  ["submitted_at" => "2025-01-08 10:20 AM", "name" => "Sophia Nguyen", "grant" => "Others", "status" => "Pending"],
  ["submitted_at" => "2025-01-08 10:45 AM", "name" => "Emily Davis", "grant" => "Others", "status" => "Pending"],
];

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
              class="bg-[#fcdc2f] bg-opacity-90 text-[#052c6a] flex items-center gap-2 px-4 py-3 cursor-pointer"
            >
              <i class="fas fa-trophy w-5"></i>
              <span>Dashboard</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
               onclick="window.location.href='adminDashboard.php'"
            >
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='applicant.php'"
            >
              <i class="fas fa-user-graduate w-5"></i>
              <span>Applicants</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='approved.php'"
            >
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='interviewEvaluation.php'"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='ranks.php'"
            >
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='list-of-qualified.php'"
            >
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='department-evaluation-list.php'"
            >
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='summary-report.php'"
            >
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='institutional-scholars.php'"
            >
              <i class="fas fa-chart-line w-5"></i>
              <span>Institutional Scholars</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='settings.php'"
            >
              <i class="fas fa-cogs w-5"></i>
              <span>Settings</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='accounts.php'"
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

        <!-- Dashboard Main Page -->
        <section
          class="mt-12 border-b border-[#0d8ddb] px-4 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between"
        >
        </section>

        <!-- Academic Year / Semester Filters -->
        <div class="px-4 sm:px-6 mt-4 flex flex-wrap justify-end gap-2">
          <select
            class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
            aria-label="Select academic year"
          >
            <option selected>Academic Year</option>
            <option>2024-2025</option>
            <option>2025-2026</option>
            <option>2026-2027</option>
          </select>
          <select
            class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
            aria-label="Select semester"
          >
            <option selected>Semester</option>
            <option>1st Semester</option>
            <option>2nd Semester</option>
          </select>
        </div>

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
  </body>
</html>
