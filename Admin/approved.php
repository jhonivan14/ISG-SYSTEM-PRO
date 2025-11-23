<?php
$approvedApplicants = [
  ["name" => "Maria Johnson", "grant" => "Student Assistant", "category" => "student-assistant", "status" => "Approved"],
  ["name" => "James Smith", "grant" => "Student Assistant", "category" => "student-assistant", "status" => "Approved"],
  ["name" => "Mark Christian Joven Balatayo", "grant" => "Kabayani Scholarship", "category" => "kabayani", "status" => "Approved"],
  ["name" => "Daniel Lee", "grant" => "Kabayani Scholarship", "category" => "kabayani", "status" => "Approved"],
  ["name" => "Aisha Khan", "grant" => "Academic Scholar", "category" => "academic", "status" => "Approved"],
  ["name" => "Fatima Al-Sayed", "grant" => "Academic Scholar", "category" => "academic", "status" => "Approved"],
  ["name" => "Sophia Nguyen", "grant" => "Others", "category" => "others", "status" => "Approved"],
  ["name" => "Emily Davis", "grant" => "Others", "category" => "others", "status" => "Approved"],
];

$categoryLabels = [
  "student-assistant" => "Student Assistant",
  "kabayani" => "Kabayani",
  "academic" => "Academic",
  "others" => "Others",
];

$groupedApproved = [];
foreach ($categoryLabels as $slug => $label) {
  $groupedApproved[$slug] = ["label" => $label, "items" => []];
}

foreach ($approvedApplicants as $applicant) {
  $slug = $applicant["category"] ?? "others";
  if (!isset($groupedApproved[$slug])) {
    $slug = "others";
  }
  $groupedApproved[$slug]["items"][] = $applicant;
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
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
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
            >
              <i class="fas fa-user-graduate w-5"></i>
              <span>Applicants</span>
            </li>

            <li
              class=" bg-[#fcdc2f] bg-opacity-90 text-[#052c6a] flex items-center gap-2 px-4 py-3 cursor-pointer"
            >
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-file-alt w-5"></i>
              <span>Qualifying Exam Result</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-chart-line w-5"></i>
              <span>Reports</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-cogs w-5"></i>
              <span>Settings</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
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

        <div class="mt-12"></div>

        <!-- Academic Year / Semester Filters -->
        <section class="px-4 sm:px-6 mt-4">
          <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[#0d8ddb] bg-white p-3 shadow-sm">
            <span class="text-sm font-semibold text-[#052c6a]">Approved Applicants</span>
            <div class="flex flex-wrap items-center gap-2">
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
          </div>
        </section>

        <!-- Approved tables grouped by category -->
        <section class="px-4 sm:px-6 pb-10 mt-4 space-y-6">
          <?php foreach ($groupedApproved as $slug => $group): ?>
            <div class="border border-[#0d8ddb] rounded-lg shadow-sm overflow-hidden" data-approved-group>
              <div class="bg-[#0d8ddb] bg-opacity-5 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2 text-[#052c6a] text-sm font-semibold">
                  <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[#0d8ddb] text-white text-xs">
                    <?= strtoupper(substr($group["label"], 0, 2)) ?>
                  </span>
                  <span><?= htmlspecialchars($group["label"]) ?></span>
                </div>
                <div class="text-xs font-semibold text-[#0d8ddb]">
                  <?= count($group["items"]) ?> approved
                </div>
              </div>

              <div class="overflow-x-auto">
                <table class="min-w-full border-t border-[#0d8ddb] text-xs text-center">
                  <thead>
                    <tr class="bg-white border-b border-[#0d8ddb]">
                      <th class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]">Applicant Name</th>
                      <th class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]">ISG Grant</th>
                      <th class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]">Status</th>
                      <th class="py-2 px-2 font-semibold text-[#fcdc2f]">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($group["items"])): ?>
                      <tr>
                        <td colspan="4" class="py-3 text-center text-[#052c6a]">No approved applicants in this category.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($group["items"] as $applicant): ?>
                        <?php
                          $searchText = strtolower($applicant["name"] . " " . $applicant["grant"] . " " . $applicant["status"]);
                        ?>
                        <tr class="border-b border-[#0d8ddb]" data-approved-row data-search-text="<?= htmlspecialchars($searchText) ?>">
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
                            <div class="flex items-center justify-center gap-2">
                              <button class="bg-[#0d8ddb] text-white rounded px-3 py-1 text-xs" type="button">
                                View Details
                              </button>
                              <button class="border border-[#f44336] text-[#f44336] rounded px-3 py-1 text-xs hover:bg-[#f44336] hover:text-white" type="button">
                                Remove
                              </button>
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
          <?php endforeach; ?>
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

      // Search filter for approved applicants
      document.addEventListener("DOMContentLoaded", () => {
        const searchInput = document.getElementById("approvedSearch");
        const groups = document.querySelectorAll("[data-approved-group]");

        const applySearch = () => {
          const query = (searchInput?.value || "").trim().toLowerCase();

          groups.forEach((group) => {
            const rows = group.querySelectorAll("[data-approved-row]");
            const emptyRow = group.querySelector("[data-approved-empty]");
            let visible = 0;

            rows.forEach((row) => {
              const text = row.dataset.searchText || "";
              const matches = query === "" || text.includes(query);
              row.style.display = matches ? "table-row" : "none";
              if (matches) visible++;
            });

            if (emptyRow) {
              emptyRow.style.display = visible === 0 ? "table-row" : "none";
            }
          });
        };

        if (searchInput) {
          searchInput.addEventListener("input", applySearch);
          applySearch();
        }
      });
    </script>
  </body>
</html>
