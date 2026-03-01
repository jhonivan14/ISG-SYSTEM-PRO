<?php
session_start();
if (empty($_SESSION["head_username"])) {
  header("Location: headLogin.php");
  exit;
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once "../db.php";

$headName = trim((string)($_SESSION["head_name"] ?? ""));
if ($headName === "") {
  $headName = "Head of Office";
}
$headUsername = trim((string)($_SESSION["head_username"] ?? ""));

$grantId = 1;
$grantLabel = "Student Assistant";
$approvedApplicants = [];
$loadError = "";

$approvedQuery = "SELECT created_at, applicant_name, program_course, status
  FROM applications
  WHERE grant_id = ?
    AND LOWER(TRIM(status)) = 'approved'
  ORDER BY created_at DESC";

if ($stmt = $conn->prepare($approvedQuery)) {
  $stmt->bind_param("i", $grantId);
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $submittedAtRaw = $row["created_at"] ?? "";
      $submittedAt = $submittedAtRaw ? date("Y-m-d h:i A", strtotime($submittedAtRaw)) : "";
      $approvedApplicants[] = [
        "submitted_at" => $submittedAt,
        "name" => $row["applicant_name"] ?? "",
        "program_course" => $row["program_course"] ?? "",
        "status" => $row["status"] ?? "Approved",
      ];
    }
    $result->free();
  } else {
    $loadError = "Unable to load approved applicants.";
  }
  $stmt->close();
} else {
  $loadError = "Unable to prepare approved applicants query.";
}

$approvedCount = count($approvedApplicants);

$requestedTab = trim((string)($_GET["tab"] ?? ""));
$initialSection = "homeSection";
if ($requestedTab === "my-sas") {
  $initialSection = "mySAsSection";
} elseif ($requestedTab === "show-evaluation") {
  $initialSection = "showEvaluationSection";
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Head of Office Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
      rel="stylesheet"
    />
    <link
      href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <style>
      :root {
        --navy: #052c6a;
        --navy-deep: #041c43;
        --blue: #0d8ddb;
        --gold: #fcdc2f;
        --ink: #0b1b3a;
      }

      body {
        font-family: "Space Grotesk", sans-serif;
        color: var(--ink);
        background:
          radial-gradient(1200px 700px at 12% -10%, #e7f3ff 0%, transparent 60%),
          radial-gradient(800px 480px at 92% 8%, #fff2c9 0%, transparent 60%),
          linear-gradient(180deg, #f7fbff 0%, #eef4ff 50%, #f4f7fb 100%);
      }

      body::before {
        content: "";
        position: fixed;
        inset: 0;
        background-image:
          linear-gradient(transparent 23px, rgba(5, 44, 106, 0.045) 24px),
          linear-gradient(90deg, transparent 23px, rgba(5, 44, 106, 0.045) 24px);
        background-size: 24px 24px;
        opacity: 0.7;
        pointer-events: none;
        z-index: 0;
      }

      .page-shell {
        position: relative;
        z-index: 1;
      }

      .heading-font {
        font-family: "Fraunces", serif;
      }

      .glass-card {
        background: rgba(255, 255, 255, 0.88);
        border: 1px solid rgba(13, 141, 219, 0.25);
        box-shadow: 0 18px 45px rgba(5, 44, 106, 0.12);
        backdrop-filter: blur(12px);
      }

      .stat-card {
        border: 1px solid rgba(13, 141, 219, 0.25);
        box-shadow: 0 12px 28px rgba(5, 44, 106, 0.12);
      }

      .badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        background: rgba(13, 141, 219, 0.12);
        color: var(--blue);
      }

      .table-hover tbody tr:hover {
        background-color: #f5f9ff;
      }

      @keyframes rise {
        from {
          opacity: 0;
          transform: translateY(18px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .stagger > * {
        opacity: 0;
        animation: rise 0.6s ease forwards;
      }

      .stagger > *:nth-child(1) { animation-delay: 0.05s; }
      .stagger > *:nth-child(2) { animation-delay: 0.12s; }
      .stagger > *:nth-child(3) { animation-delay: 0.2s; }

      ::-webkit-scrollbar {
        width: 6px;
      }
      ::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.12);
      }
      ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
        border-radius: 999px;
      }
      #sidebar nav ul {
        padding: 0.35rem 0.5rem 5.5rem;
      }
      .panel-nav-item {
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
      .panel-nav-item:hover {
        transform: translateX(2px);
        background-color: rgba(255, 255, 255, 0.15);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.16);
      }
      .panel-nav-item.active {
        background-color: rgba(252, 220, 47, 0.95);
        color: #052c6a;
        box-shadow: 0 8px 20px rgba(252, 220, 47, 0.25);
      }
    </style>
  </head>
  <body class="bg-white text-[#0b1b3a] font-sans">
    <div class="min-h-screen page-shell">
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
            <span class="text-sm font-semibold leading-tight text-white">
              Admission and Scholarship Office
            </span>
          </div>
        </div>

        <nav class="flex-1 mt-2">
          <ul class="text-xs font-semibold">
            <li
              class="panel-nav-item active gap-2 cursor-pointer"
              data-target-section="homeSection"
            >
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li
              class="panel-nav-item gap-2 cursor-pointer"
              data-target-section="mySAsSection"
            >
              <i class="fas fa-user-friends w-5"></i>
              <span>My SA's</span>
            </li>
            <li
              class="panel-nav-item gap-2 cursor-pointer"
              data-target-section="showEvaluationSection"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Show Evaluation</span>
            </li>
            <li
              class="panel-nav-item gap-2 cursor-pointer"
              onclick="window.location.href='head-changePassword.phpz'"
            >
              <i class="fas fa-key w-5"></i>
              <span>Change Password</span>
            </li>
          </ul>
        </nav>

        <div class="absolute bottom-0 left-0 w-full p-2">
          <div class="rounded-xl border border-white/20 bg-white/10 backdrop-blur-sm overflow-hidden">
            <div class="h-px w-full bg-gradient-to-r from-transparent via-[#8bcfff] to-transparent opacity-80"></div>
            <div class="px-4 pt-2 pb-1 flex items-center gap-2 text-[11px] text-blue-100/90">
              <div class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center">
                <i class="fas fa-user-tie text-[12px]"></i>
              </div>
              <div class="leading-tight min-w-0">
                <p class="font-semibold truncate"><?= htmlspecialchars($headName) ?></p>
                <p class="text-[10px] text-blue-200/80 truncate">
                  <?= htmlspecialchars($headUsername !== "" ? $headUsername : "head-office") ?>
                </p>
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

      <main class="ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pb-8 pt-14">
        <header
          class="fixed top-0 left-0 md:left-64 right-0 z-20 h-14 flex items-center bg-white border-b border-slate-200 px-4 sm:px-6 shadow-sm"
        >
          <div class="flex items-center gap-2">
            <button
              id="sidebarToggle"
              class="md:hidden inline-flex items-center justify-center p-2 rounded bg-slate-700 text-white hover:bg-slate-800 focus:outline-none transition-colors"
              type="button"
            >
              <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-[#0d4b84] text-lg font-semibold flex items-center gap-2">
              <i class="fas fa-columns"></i>
              Head of Office Dashboard
            </h2>
          </div>
        </header>

        <section id="homeSection" data-head-section class="px-4 sm:px-6">
          <section class="mt-6 glass-card rounded-3xl p-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-2">
              <span class="badge">Student Assistant Grant</span>
              <h1 class="heading-font text-3xl text-[#052c6a]">Head Dashboard</h1>
              <p class="text-xs text-[#42506a]">
                Monitor approved student assistants and manage office evaluations.
              </p>
              <p class="text-sm font-semibold text-[#052c6a]">
                Welcome, <?= htmlspecialchars($headName) ?>.
              </p>
            </div>
            <div></div>
          </section>

          <section class="stagger grid gap-4 pt-6 md:grid-cols-3">
            <div class="stat-card rounded-2xl bg-gradient-to-br from-[#052c6a] to-[#0b3f8f] p-5 text-white">
              <p class="text-xs uppercase tracking-wide text-[#fcdc2f]">Total My SA's</p>
              <p class="mt-2 text-3xl font-bold"><?= htmlspecialchars((string)$approvedCount) ?></p>
              <p class="mt-1 text-[11px] text-blue-100">
                Approved student assistants under your office.
              </p>
            </div>
            <div class="stat-card rounded-2xl bg-white p-5 text-[#052c6a]">
              <p class="text-xs uppercase tracking-wide text-[#0d8ddb]">Ready to Evaluate</p>
              <p class="mt-2 text-3xl font-bold"><?= htmlspecialchars((string)$approvedCount) ?></p>
              <p class="mt-1 text-[11px] text-slate-500">
                Available records for evaluation form processing.
              </p>
            </div>
            <div class="stat-card rounded-2xl bg-gradient-to-br from-[#fcdc2f] to-[#f7b500] p-5 text-[#052c6a]">
              <p class="text-xs uppercase tracking-wide">Show Evaluation</p>
              <p class="mt-2 text-3xl font-bold"><?= htmlspecialchars((string)$approvedCount) ?></p>
              <p class="mt-1 text-[11px] text-[#052c6a]">
                Entries available in the evaluation view.
              </p>
            </div>
          </section>
        </section>

        <section id="mySAsSection" data-head-section class="hidden mt-6 glass-card rounded-3xl p-5 mx-4 sm:mx-6">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-sm font-semibold text-[#0d8ddb]">
                My SA's
              </p>
              <p class="text-xs text-[#052c6a]">
                Showing <?= htmlspecialchars((string)$approvedCount) ?> approved applicants for
                <?= htmlspecialchars($grantLabel) ?>.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
              <div class="flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-white px-3 py-2 shadow-sm">
                <i class="fas fa-search text-[#7c8191] text-xs"></i>
                <input
                  id="mySASearch"
                  type="text"
                  class="w-44 bg-transparent text-xs font-semibold text-[#052c6a] outline-none placeholder:text-[#7c8191]"
                  placeholder="Search My SA's..."
                  aria-label="Search My SA's"
                />
              </div>
              <span class="rounded-full bg-[#fcdc2f] px-3 py-1 text-[#052c6a] shadow-sm">
                Total: <?= htmlspecialchars((string)$approvedCount) ?>
              </span>
            </div>
          </div>

          <div class="mt-4 overflow-x-auto">
            <?php if ($loadError !== ""): ?>
              <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                <?= htmlspecialchars($loadError) ?>
              </div>
            <?php endif; ?>
            <table class="table-hover min-w-full overflow-hidden rounded-2xl border border-[#0d8ddb] text-xs text-left">
              <thead>
                <tr class="bg-gradient-to-r from-[#052c6a] to-[#0b3f8f] text-white">
                  <th class="border-r border-white/10 px-3 py-3">Timestamp</th>
                  <th class="border-r border-white/10 px-3 py-3">Applicant Name</th>
                  <th class="border-r border-white/10 px-3 py-3">Program / Course</th>
                  <th class="border-r border-white/10 px-3 py-3">Grant</th>
                  <th class="border-r border-white/10 px-3 py-3">Status</th>
                  <th class="px-3 py-3">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($approvedApplicants)): ?>
                  <tr>
                    <td colspan="6" class="px-3 py-4 text-center text-[#052c6a]">
                      No approved applicants yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($approvedApplicants as $applicant): ?>
                    <?php
                      $searchText = strtolower(
                        ($applicant["name"] ?? "") . " " .
                        ($applicant["program_course"] ?? "") . " " .
                        ($applicant["status"] ?? "")
                      );
                    ?>
                    <tr class="border-b border-[#0d8ddb]" data-my-sa-row data-search-text="<?= htmlspecialchars($searchText) ?>">
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($applicant["submitted_at"]) ?>
                      </td>
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($applicant["name"]) ?>
                      </td>
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($applicant["program_course"]) ?>
                      </td>
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($grantLabel) ?>
                      </td>
                      <td class="border-r border-[#0d8ddb] px-3 py-2">
                        <span class="rounded-full bg-green-500 px-2 py-1 text-[10px] text-white shadow-sm">
                          Approved
                        </span>
                      </td>
                      <td class="px-3 py-2">
                        <button
                          class="rounded-full bg-[#0d8ddb] px-3 py-1 text-[11px] font-semibold text-white shadow-sm hover:bg-[#0b7cc0]"
                          type="button"
                          onclick="window.location.href='SaEvaluation.php?applicant=<?= urlencode((string)($applicant['name'] ?? '')) ?>'"
                        >
                          Open Evaluation Form
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr data-my-sa-empty class="hidden">
                    <td colspan="6" class="px-3 py-4 text-center text-[#052c6a]">
                      No matching student assistants.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

        <section id="showEvaluationSection" data-head-section class="hidden mt-6 glass-card rounded-3xl p-5 mx-4 sm:mx-6">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-sm font-semibold text-[#0d8ddb]">
                Show Evaluation
              </p>
              <p class="text-xs text-[#052c6a]">
                View evaluation entries for approved student assistants.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
              <div class="flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-white px-3 py-2 shadow-sm">
                <i class="fas fa-search text-[#7c8191] text-xs"></i>
                <input
                  id="showEvalSearch"
                  type="text"
                  class="w-44 bg-transparent text-xs font-semibold text-[#052c6a] outline-none placeholder:text-[#7c8191]"
                  placeholder="Search evaluations..."
                  aria-label="Search evaluations"
                />
              </div>
              <span class="rounded-full bg-[#052c6a] px-3 py-1 text-white shadow-sm">
                Entries: <?= htmlspecialchars((string)$approvedCount) ?>
              </span>
            </div>
          </div>

          <div class="mt-4 overflow-x-auto">
            <table class="table-hover min-w-full overflow-hidden rounded-2xl border border-[#0d8ddb] text-xs text-left">
              <thead>
                <tr class="bg-gradient-to-r from-[#052c6a] to-[#0b3f8f] text-white">
                  <th class="border-r border-white/10 px-3 py-3">Applicant Name</th>
                  <th class="border-r border-white/10 px-3 py-3">Program / Course</th>
                  <th class="border-r border-white/10 px-3 py-3">Last Updated</th>
                  <th class="border-r border-white/10 px-3 py-3">Grant</th>
                  <th class="px-3 py-3">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($approvedApplicants)): ?>
                  <tr>
                    <td colspan="5" class="px-3 py-4 text-center text-[#052c6a]">
                      No evaluation entries yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($approvedApplicants as $applicant): ?>
                    <?php
                      $searchText = strtolower(
                        ($applicant["name"] ?? "") . " " .
                        ($applicant["program_course"] ?? "") . " " .
                        ($applicant["submitted_at"] ?? "")
                      );
                    ?>
                    <tr class="border-b border-[#0d8ddb]" data-show-eval-row data-search-text="<?= htmlspecialchars($searchText) ?>">
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($applicant["name"]) ?>
                      </td>
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($applicant["program_course"]) ?>
                      </td>
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($applicant["submitted_at"]) ?>
                      </td>
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($grantLabel) ?>
                      </td>
                      <td class="px-3 py-2">
                        <button
                          class="rounded-full border border-[#052c6a] px-3 py-1 text-[11px] font-semibold text-[#052c6a] hover:bg-[#052c6a] hover:text-white"
                          type="button"
                          onclick="window.location.href='SaEvaluation.php?applicant=<?= urlencode((string)($applicant['name'] ?? '')) ?>'"
                        >
                          Show Evaluation
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr data-show-eval-empty class="hidden">
                    <td colspan="5" class="px-3 py-4 text-center text-[#052c6a]">
                      No matching evaluations.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      </main>
    </div>
    <script>
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");
        const navItems = Array.from(document.querySelectorAll("[data-target-section]"));
        const sections = Array.from(document.querySelectorAll("[data-head-section]"));

        const setActiveSection = (sectionId) => {
          sections.forEach((section) => {
            section.classList.toggle("hidden", section.id !== sectionId);
          });
          navItems.forEach((item) => {
            const isActive = item.dataset.targetSection === sectionId;
            item.classList.toggle("active", isActive);
          });
        };

        navItems.forEach((item) => {
          item.addEventListener("click", () => {
            setActiveSection(item.dataset.targetSection);
            if (window.innerWidth < 768 && sidebar) {
              sidebar.classList.add("-translate-x-full");
            }
          });
        });

        if (toggleBtn && sidebar) {
          toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
          });
        }

        setActiveSection(<?= json_encode($initialSection) ?>);

        const mySearchInput = document.getElementById("mySASearch");
        const myRows = Array.from(document.querySelectorAll("[data-my-sa-row]"));
        const myEmpty = document.querySelector("[data-my-sa-empty]");

        const showEvalSearchInput = document.getElementById("showEvalSearch");
        const showEvalRows = Array.from(document.querySelectorAll("[data-show-eval-row]"));
        const showEvalEmpty = document.querySelector("[data-show-eval-empty]");

        const applySearch = (input, rows, emptyRow) => {
          const query = (input?.value || "").trim().toLowerCase();
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
        };

        if (mySearchInput) {
          mySearchInput.addEventListener("input", () => {
            applySearch(mySearchInput, myRows, myEmpty);
          });
        }

        if (showEvalSearchInput) {
          showEvalSearchInput.addEventListener("input", () => {
            applySearch(showEvalSearchInput, showEvalRows, showEvalEmpty);
          });
        }

        applySearch(mySearchInput, myRows, myEmpty);
        applySearch(showEvalSearchInput, showEvalRows, showEvalEmpty);
      });
    </script>
  </body>
</html>
