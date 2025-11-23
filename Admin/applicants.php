<?php
$categoryDefinitions = [
  [
    "label" => "Student Assistants",
    "slug" => "student-assistants",
    "color" => "#0d8ddb",
    "keywords" => ["student assistant", "assistant"],
  ],
  [
    "label" => "Kabayani Scholarship",
    "slug" => "kabayani-scholarship",
    "color" => "#0d8ddb",
    "keywords" => ["kabayani"],
  ],
  [
    "label" => "Academic Scholarship",
    "slug" => "academic-scholarship",
    "color" => "#0d8ddb",
    "keywords" => ["academic"],
  ],
  [
    "label" => "Others",
    "slug" => "others",
    "color" => "#0d8ddb",
    "keywords" => ["others"],
  ],
];

$applicants = [
  [
    "application_id" => "0001",
    "name" => "Jhon Ivan Tabanao",
    "course" => "Bachelor of Science in Information Technology",
    "grant" => "Student Assistant",
    "status" => "Pending",
  ],
  [
    "application_id" => "0002",
    "name" => "Mark Christian Joven Balatayo",
    "course" => "Bachelor of Science in Information Technology",
    "grant" => "Kabayani",
    "status" => "Pending",
  ],
  [
    "application_id" => "0003",
    "name" => "John Doe",
    "course" => "Bachelor of Science in Criminology",
    "grant" => "Others",
    "status" => "Pending",
  ],
  [
    "application_id" => "0004",
    "name" => "Jhon Ivan Tabanao",
    "course" => "Bachelor of Science in Information Technology",
    "grant" => "Academic Scholar",
    "status" => "Pending",
  ],
  [
    "application_id" => "0005",
    "name" => "Mark Christian Joven Balatayo",
    "course" => "Bachelor of Science in Information Technology",
    "grant" => "Kabayani",
    "status" => "Pending",
  ],
  [
    "application_id" => "0006",
    "name" => "John Doe",
    "course" => "Bachelor of Science in Criminology",
    "grant" => "Kabayani",
    "status" => "Pending",
  ],
  [
    "application_id" => "0007",
    "name" => "Jhon Ivan Tabanao",
    "course" => "Bachelor of Science in Information Technology",
    "grant" => "Others",
    "status" => "Pending",
  ],
  [
    "application_id" => "0008",
    "name" => "Mark Christian Joven Balatayo",
    "course" => "Bachelor of Science in Information Technology",
    "grant" => "Others",
    "status" => "Pending",
  ],
  [
    "application_id" => "0009",
    "name" => "John Doe",
    "course" => "Bachelor of Science in Criminology",
    "grant" => "Student Assistant",
    "status" => "Pending",
  ],
  [
    "application_id" => "0010",
    "name" => "Jhon Ivan Tabanao",
    "course" => "Bachelor of Science in Information Technology",
    "grant" => "Academic",
    "status" => "Pending",
  ],
  [
    "application_id" => "0011",
    "name" => "Mark Christian Joven Balatayo",
    "course" => "Bachelor of Science in Information Technology",
    "grant" => "Academic",
    "status" => "Pending",
  ],
  [
    "application_id" => "0012",
    "name" => "John Doe",
    "course" => "Bachelor of Science in Criminology",
    "grant" => "Student Assistant",
    "status" => "Pending",
  ],
];

$categories = [];
foreach ($categoryDefinitions as $definition) {
  $categories[$definition["slug"]] = [
    "label" => $definition["label"],
    "slug" => $definition["slug"],
    "count" => 0,
  ];
}

foreach ($applicants as &$applicant) {
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
  } else {
    $categories["others"]["count"]++;
  }
}
unset($applicant);

$orderedCategories = [];
foreach ($categoryDefinitions as $definition) {
  $slug = $definition["slug"];
  if (isset($categories[$slug])) {
    $orderedCategories[] = $categories[$slug];
  }
}
$categories = $orderedCategories;

$totalApplicants = count($applicants);
$pendingApplicants = 0;
foreach ($applicants as $applicant) {
  if (strtolower($applicant["status"]) === "pending") {
    $pendingApplicants++;
  }
}
$grantCategories = count($categories);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Applicants</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600&display=swap" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="../admin.css" />
  </head>
  <body class="bg-[#edf2fb]">
    <div class="min-h-screen">
      <aside id="sidebar" class="admin-sidebar fixed left-0 top-0 z-40 h-full w-56 bg-[#052c6a] text-white transition-all duration-200 ease-in-out">
        <div class="flex items-center gap-3 px-4 py-4 border-b border-[#0d8ddb]">
          <img src="../img/SMCCNEWLOGO.png" class="h-16 w-16 rounded-full object-cover" alt="SMCC Logo" />
          <span class="text-sm">Admission and Scholarship Office</span>
        </div>
        <nav class="flex-1 overflow-y-auto">
          <ul class="text-xs font-semibold">
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer">
              <i class="fas fa-trophy w-5"></i>
              <span>Dashboard</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer">
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li class="bg-[#fcdc2f] bg-opacity-90 text-[#052c6a] flex items-center gap-2 px-4 py-3 cursor-pointer">
              <i class="fas fa-user-graduate w-5"></i>
              <span>Applicants</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer">
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer">
              <i class="fas fa-file-alt w-5"></i>
              <span>Qualifying Exam Result</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer">
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer">
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer">
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer">
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer">
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer">
              <i class="fas fa-chart-line w-5"></i>
              <span>Reports</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer">
              <i class="fas fa-cogs w-5"></i>
              <span>Settings</span>
            </li>
          </ul>
        </nav>
        <div class="flex items-center justify-between px-4 py-3 text-xs border-t border-[#0d8ddb]">
          <div class="flex items-center gap-2">
            <img src="../img/avatar-placeholder.png" class="h-8 w-8 rounded-full object-cover border border-white/40" alt="Profile" />
            <div>
              <p class="font-semibold">Admin</p>
              <p class="text-[10px] text-white/70">Scholarship Officer</p>
            </div>
          </div>
          <button class="text-white/70 text-lg">
            <i class="fas fa-sign-out-alt"></i>
          </button>
        </div>
      </aside>

      <div class="admin-content min-h-screen bg-[#edf2fb] transition-all duration-200 ease-in-out md:ml-56 flex flex-col">
        <header class="fixed top-0 left-0 md:left-56 right-0 z-30 flex items-center justify-between bg-[#052c6a] px-4 py-3 text-white text-xs shadow">
          <div class="flex items-center gap-2">
            <button id="sidebarToggle" class="md:hidden inline-flex h-8 w-8 items-center justify-center rounded bg-[#0d8ddb] focus:outline-none" type="button">
              <i class="fas fa-bars text-white"></i>
            </button>
          </div>
          <div class="flex gap-2 text-xs">
            <button class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 flex items-center gap-1 font-semibold" type="button">
              <i class="fas fa-user"></i>
              Admin Panel
            </button>
            <button class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 font-semibold" type="button">
              Account
            </button>
          </div>
        </header>

        <main class="px-4 pt-20 pb-6 md:px-8 flex-1">

          <div class="tabs-wrapper mb-4">
            <div class="tabs-scroll">
              <?php foreach ($categories as $category): ?>
                <button
                  type="button"
                  class="tab-button"
                  data-filter-button="<?= htmlspecialchars($category["slug"]) ?>"
                  aria-pressed="false"
                >
                  <span><?= htmlspecialchars($category["label"]) ?></span>
                  <span class="tab-count"><?= htmlspecialchars($category["count"]) ?></span>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="mt-6 rounded-3xl bg-white p-4 shadow-lg lg:flex lg:items-center lg:justify-between lg:p-6">
            <div class="flex items-center gap-3">
              <button class="flex h-10 w-10 items-center justify-center rounded-full border border-[#d0d5dd] text-[#052c6a]">
                <i class="fas fa-bars text-lg"></i>
              </button>
              <div>
                <p class="text-xs text-[#7c8191]">Search Applicants</p>
                <h3 class="text-lg font-semibold text-[#052c6a]">Filter and review applications</h3>
              </div>
            </div>
            <div class="mt-4 flex flex-1 items-center gap-3 rounded-full border border-[#d0d5dd] bg-[#f8f9ff] px-4 py-2 lg:mt-0">
              <i class="fas fa-search text-[#7c8191]"></i>
              <input
                id="applicantSearch"
                type="text"
                class="flex-1 bg-transparent text-sm outline-none"
                placeholder="Search applicants..."
                aria-label="Search applicants"
              />
              <button class="text-[#0d8ddb]">
                <i class="fas fa-microphone"></i>
              </button>
            </div>
          </div>

          <div class="mt-6 table-wrapper overflow-hidden bg-white">
            <div class="table-desktop overflow-x-auto">
              <table class="w-full border-collapse text-sm">
                <thead>
                  <tr class="bg-[#f8f9ff] text-left text-xs uppercase tracking-wide text-[#7c8191]">
                    <th class="border border-[#dde3f0] px-4 py-3">Application ID</th>
                    <th class="border border-[#dde3f0] px-4 py-3">Name</th>
                    <th class="border border-[#dde3f0] px-4 py-3">Course</th>
                    <th class="border border-[#dde3f0] px-4 py-3">ISG Grant</th>
                    <th class="border border-[#dde3f0] px-4 py-3 text-center">Status</th>
                    <th class="border border-[#dde3f0] px-4 py-3 text-center">Action</th>
                  </tr>
                </thead>
                <tbody class="bg-white text-[#052c6a]">
                  <?php foreach ($applicants as $applicant): ?>
                    <?php
                      $searchText = strtolower(
                        $applicant["application_id"] . " " .
                        $applicant["name"] . " " .
                        $applicant["course"] . " " .
                        $applicant["grant"] . " " .
                        $applicant["status"]
                      );
                    ?>
                    <tr
                      class="border-t border-[#f2f4f7] hover:bg-[#f8fbff]"
                      data-filter-row="<?= htmlspecialchars($applicant["category_slug"]) ?>"
                      data-search-text="<?= htmlspecialchars($searchText) ?>"
                    >
                      <td class="px-4 py-3 font-semibold"><?= htmlspecialchars($applicant["application_id"]) ?></td>
                      <td class="px-4 py-3"><?= htmlspecialchars($applicant["name"]) ?></td>
                      <td class="px-4 py-3"><?= htmlspecialchars($applicant["course"]) ?></td>
                      <td class="px-4 py-3"><?= htmlspecialchars($applicant["grant"]) ?></td>
                      <td class="px-4 py-3 text-center">
                        <span class="rounded-full bg-[#e5f1ff] px-3 py-1 text-xs font-semibold text-[#0d8ddb]">
                          <?= htmlspecialchars($applicant["status"]) ?>
                        </span>
                      </td>
                      <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center justify-center gap-2">
                          <button class="rounded-full bg-[#0f8cff] px-3 py-1 text-xs font-semibold text-white hover:bg-[#0066cc]">
                            <i class="fas fa-eye mr-1"></i>View
                          </button>
                          <button class="rounded-full bg-[#19b15a] px-3 py-1 text-xs font-semibold text-white hover:bg-[#109142]">
                            <i class="fas fa-check mr-1"></i>Accept
                          </button>
                          <button class="rounded-full bg-[#f44336] px-3 py-1 text-xs font-semibold text-white hover:bg-[#d7372b]">
                            <i class="fas fa-times mr-1"></i>Decline
                          </button>
                          <button class="rounded-full border border-[#f44336] px-3 py-1 text-xs font-semibold text-[#f44336] hover:bg-[#f44336] hover:text-white">
                            <i class="fas fa-trash mr-1"></i>Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="mobile-card space-y-4 p-4">
              <?php foreach ($applicants as $applicant): ?>
                <?php
                  $cardSearchText = strtolower(
                    $applicant["application_id"] . " " .
                    $applicant["name"] . " " .
                    $applicant["course"] . " " .
                    $applicant["grant"] . " " .
                    $applicant["status"]
                  );
                ?>
                <div
                  class="rounded-2xl border border-[#e9edf7] bg-[#f8f9ff] p-4 text-sm text-[#052c6a] shadow-sm"
                  data-filter-card="<?= htmlspecialchars($applicant["category_slug"]) ?>"
                  data-search-text="<?= htmlspecialchars($cardSearchText) ?>"
                >
                  <div class="flex items-center justify-between">
                    <p class="font-semibold text-base"><?= htmlspecialchars($applicant["name"]) ?></p>
                    <span class="rounded-full bg-[#e5f1ff] px-3 py-1 text-xs font-semibold text-[#0d8ddb]">
                      <?= htmlspecialchars($applicant["status"]) ?>
                    </span>
                  </div>
                  <div class="mt-2 text-xs text-[#7c8191]">
                    <p class="font-semibold text-[#052c6a]">Application ID: <?= htmlspecialchars($applicant["application_id"]) ?></p>
                    <p class="mt-1"><?= htmlspecialchars($applicant["course"]) ?></p>
                    <p class="mt-1">ISG Grant: <?= htmlspecialchars($applicant["grant"]) ?></p>
                  </div>
                  <div class="mt-3 grid grid-cols-2 gap-2 text-xs font-semibold">
                    <button class="rounded-full bg-[#0f8cff] px-3 py-2 text-white">View</button>
                    <button class="rounded-full bg-[#19b15a] px-3 py-2 text-white">Accept</button>
                    <button class="rounded-full bg-[#f44336] px-3 py-2 text-white">Decline</button>
                    <button class="rounded-full border border-[#f44336] px-3 py-2 text-[#f44336]">Delete</button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </main>
      </div>
    </div>
    <script>
      const sidebar = document.getElementById("sidebar");
      const sidebarToggle = document.getElementById("sidebarToggle");

      if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener("click", () => {
          sidebar.classList.toggle("active");
        });
      }

      const filterButtons = document.querySelectorAll("[data-filter-button]");
      const tableRows = document.querySelectorAll("[data-filter-row]");
      const mobileCards = document.querySelectorAll("[data-filter-card]");
      const searchInput = document.getElementById("applicantSearch");

      let activeFilterSlug = "";
      let searchQuery = "";

      const itemMatchesFilters = (datasetSlug, datasetSearch) => {
        const matchesCategory =
          activeFilterSlug === "" || datasetSlug === activeFilterSlug;
        const matchesSearch =
          searchQuery === "" ||
          (datasetSearch || "").includes(searchQuery.toLowerCase());
        return matchesCategory && matchesSearch;
      };

      const applyFilter = () => {
        tableRows.forEach((row) => {
          const shouldShow = itemMatchesFilters(
            row.dataset.filterRow,
            row.dataset.searchText
          );
          row.style.display = shouldShow ? "table-row" : "none";
        });

        mobileCards.forEach((card) => {
          const shouldShow = itemMatchesFilters(
            card.dataset.filterCard,
            card.dataset.searchText
          );
          card.style.display = shouldShow ? "block" : "none";
        });
      };

      const updateActiveButtons = () => {
        filterButtons.forEach((button) => {
          const isActive = button.dataset.filterButton === activeFilterSlug;
          button.classList.toggle("active", isActive);
          button.setAttribute("aria-pressed", isActive ? "true" : "false");
        });
      };

      filterButtons.forEach((button) => {
        button.addEventListener("click", () => {
          const slug = button.dataset.filterButton;
          activeFilterSlug = activeFilterSlug === slug ? "" : slug;
          updateActiveButtons();
          applyFilter();
        });
      });

      if (searchInput) {
        searchInput.addEventListener("input", (event) => {
          searchQuery = event.target.value.trim().toLowerCase();
          applyFilter();
        });
      }

      applyFilter();
    </script>
  </body>
</html>
