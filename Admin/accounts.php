<?php
session_start();
require_once "../db.php";

$resetMessage = "";
$resetError = "";
$panelistAccounts = [];
$headOfficeAccounts = [];
$panelistError = "";
$headOfficeError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["reset_account_password"])) {
  $accountType = trim((string)($_POST["account_type"] ?? ""));
  $resetUsername = trim((string)($_POST["reset_username"] ?? ""));
  $resetPassword = (string)($_POST["reset_password"] ?? "");

  $tableMap = [
    "panelist" => "panelists",
    "head_office" => "head_offices",
  ];

  if (!isset($tableMap[$accountType])) {
    $resetError = "Invalid account type.";
  } elseif ($resetUsername === "" || $resetPassword === "") {
    $resetError = "Please complete all fields.";
  } else {
    $tableName = $tableMap[$accountType];
    $checkStmt = $conn->prepare("SELECT id FROM {$tableName} WHERE username = ? LIMIT 1");
    if ($checkStmt) {
      $checkStmt->bind_param("s", $resetUsername);
      $checkStmt->execute();
      $checkStmt->store_result();

      if ($checkStmt->num_rows === 0) {
        $resetError = "Username not found.";
      } else {
        $passwordHash = password_hash($resetPassword, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
          $resetError = "Unable to reset password. Please try again.";
        } else {
          $updateStmt = $conn->prepare("UPDATE {$tableName} SET password_hash = ? WHERE username = ? LIMIT 1");
          if ($updateStmt) {
            $updateStmt->bind_param("ss", $passwordHash, $resetUsername);
            if ($updateStmt->execute()) {
              $resetMessage = "Password reset successful.";
            } else {
              $resetError = "Unable to update the password.";
            }
            $updateStmt->close();
          } else {
            $resetError = "Unable to update the password.";
          }
        }
      }
      $checkStmt->close();
    } else {
      $resetError = "Unable to reset the password.";
    }
  }
}

$panelistResult = $conn->query("SELECT username, full_name, password_hash, status FROM panelists ORDER BY username ASC");
if ($panelistResult) {
  while ($row = $panelistResult->fetch_assoc()) {
    $panelistAccounts[] = $row;
  }
  $panelistResult->free();
} else {
  $panelistError = "Panelist accounts table is not available.";
}

$headOfficeResult = $conn->query("SELECT username, full_name, office, password_hash, status FROM head_offices ORDER BY username ASC");
if ($headOfficeResult) {
  while ($row = $headOfficeResult->fetch_assoc()) {
    $headOfficeAccounts[] = $row;
  }
  $headOfficeResult->free();
} else {
  $headOfficeError = "Head of office accounts table is not available.";
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
          class="page-header mt-12 border-b border-[#0d8ddb] px-4 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between"
        >
          <h2 class="text-[#0d8ddb] text-lg font-semibold flex items-center gap-2 mb-2 sm:mb-0">
            <i class="fas fa-flag"></i>
            ACCOUNTS
          </h2>
        </section>

        <section class="px-4 sm:px-6 pt-6">
          <div class="rounded-xl border border-[#0d8ddb] bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="text-[#0d8ddb] text-sm font-semibold">Account Passwords</p>
                <p class="text-xs text-[#052c6a]">
                  View hashed passwords and reset credentials for panelists and head of offices.
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  class="rounded-full bg-[#052c6a] px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-white hover:bg-[#0b3d86]"
                  onclick="window.location.href='add-panelist.php'"
                >
                  Add Panelist
                </button>
                <button
                  type="button"
                  class="rounded-full bg-[#0d8ddb] px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-white hover:bg-[#0b7bbf]"
                  onclick="window.location.href='add-head-office.php'"
                >
                  Add Head of Office
                </button>
              </div>
            </div>

            <?php if ($resetError !== ""): ?>
              <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($resetError) ?>
              </div>
            <?php endif; ?>

            <?php if ($resetMessage !== ""): ?>
              <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <?= htmlspecialchars($resetMessage) ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <section class="px-4 sm:px-6 pt-6">
          <div class="rounded-xl border border-[#0d8ddb] bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-[#052c6a]">Panelists</p>
            <?php if ($panelistError !== ""): ?>
              <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($panelistError) ?>
              </div>
            <?php else: ?>
              <div class="mt-3 overflow-x-auto">
                <table class="min-w-full border border-[#0d8ddb] text-xs text-left">
                  <thead class="bg-[#052c6a] text-white">
                    <tr>
                      <th class="border-r border-white/10 px-3 py-2">Username</th>
                      <th class="border-r border-white/10 px-3 py-2">Full Name</th>
                      <th class="border-r border-white/10 px-3 py-2">Password Hash</th>
                      <th class="border-r border-white/10 px-3 py-2">Status</th>
                      <th class="px-3 py-2">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($panelistAccounts)): ?>
                      <tr>
                        <td colspan="5" class="px-3 py-3 text-center text-[#052c6a]">
                          No panelist accounts found.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($panelistAccounts as $account): ?>
                        <tr class="border-b border-[#0d8ddb]">
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["username"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["full_name"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 font-mono text-[10px] text-[#052c6a] break-all">
                            <?= htmlspecialchars((string)($account["password_hash"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["status"] ?? "")) ?>
                          </td>
                          <td class="px-3 py-2">
                            <form method="POST" class="flex flex-wrap items-center gap-2">
                              <input type="hidden" name="account_type" value="panelist" />
                              <input
                                type="hidden"
                                name="reset_username"
                                value="<?= htmlspecialchars((string)($account["username"] ?? "")) ?>"
                              />
                              <input
                                type="password"
                                name="reset_password"
                                class="w-36 rounded border border-[#0d8ddb]/40 px-2 py-1 text-[11px]"
                                placeholder="New password"
                                required
                              />
                              <button
                                type="submit"
                                name="reset_account_password"
                                value="1"
                                class="rounded-full bg-[#0d8ddb] px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-white hover:bg-[#0b7bbf]"
                              >
                                Reset
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <section class="px-4 sm:px-6 pt-6 pb-6">
          <div class="rounded-xl border border-[#0d8ddb] bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-[#052c6a]">Head of Offices</p>
            <?php if ($headOfficeError !== ""): ?>
              <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($headOfficeError) ?>
              </div>
            <?php else: ?>
              <div class="mt-3 overflow-x-auto">
                <table class="min-w-full border border-[#0d8ddb] text-xs text-left">
                  <thead class="bg-[#052c6a] text-white">
                    <tr>
                      <th class="border-r border-white/10 px-3 py-2">Username</th>
                      <th class="border-r border-white/10 px-3 py-2">Full Name</th>
                      <th class="border-r border-white/10 px-3 py-2">Office</th>
                      <th class="border-r border-white/10 px-3 py-2">Password Hash</th>
                      <th class="border-r border-white/10 px-3 py-2">Status</th>
                      <th class="px-3 py-2">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($headOfficeAccounts)): ?>
                      <tr>
                        <td colspan="6" class="px-3 py-3 text-center text-[#052c6a]">
                          No head of office accounts found.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($headOfficeAccounts as $account): ?>
                        <tr class="border-b border-[#0d8ddb]">
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["username"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["full_name"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["office"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 font-mono text-[10px] text-[#052c6a] break-all">
                            <?= htmlspecialchars((string)($account["password_hash"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["status"] ?? "")) ?>
                          </td>
                          <td class="px-3 py-2">
                            <form method="POST" class="flex flex-wrap items-center gap-2">
                              <input type="hidden" name="account_type" value="head_office" />
                              <input
                                type="hidden"
                                name="reset_username"
                                value="<?= htmlspecialchars((string)($account["username"] ?? "")) ?>"
                              />
                              <input
                                type="password"
                                name="reset_password"
                                class="w-36 rounded border border-[#0d8ddb]/40 px-2 py-1 text-[11px]"
                                placeholder="New password"
                                required
                              />
                              <button
                                type="submit"
                                name="reset_account_password"
                                value="1"
                                class="rounded-full bg-[#0d8ddb] px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-white hover:bg-[#0b7bbf]"
                              >
                                Reset
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
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

