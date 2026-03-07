<?php
session_start();
require_once "../db.php";

$resetMessage = "";
$resetError = "";
$accountMessage = "";
$panelistAccounts = [];
$headOfficeAccounts = [];
$panelistError = "";
$headOfficeError = "";
$panelistFormError = "";
$headOfficeFormError = "";
$activeModal = "";
$panelistFormData = [
  "username" => "",
  "full_name" => "",
  "status" => "active",
];
$headOfficeFormData = [
  "username" => "",
  "name" => "",
  "lastname" => "",
  "office" => "",
  "status" => "active",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (isset($_POST["reset_account_password"])) {
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
  } elseif (isset($_POST["create_account"])) {
    $createAccountType = trim((string)($_POST["create_account_type"] ?? ""));
    $status = strtolower(trim((string)($_POST["status"] ?? "active")));
    $status = in_array($status, ["active", "inactive"], true) ? $status : "active";

    if ($createAccountType === "panelist") {
      $activeModal = "panelistModal";
      $panelistFormData = [
        "username" => trim((string)($_POST["username"] ?? "")),
        "full_name" => trim((string)($_POST["full_name"] ?? "")),
        "status" => $status,
      ];
      $password = (string)($_POST["password"] ?? "");

      if ($panelistFormData["username"] === "" || $panelistFormData["full_name"] === "" || $password === "") {
        $panelistFormError = "Please complete all fields.";
      } else {
        $checkStmt = $conn->prepare("SELECT id FROM panelists WHERE username = ? LIMIT 1");
        if ($checkStmt) {
          $checkStmt->bind_param("s", $panelistFormData["username"]);
          $checkStmt->execute();
          $checkStmt->store_result();
          if ($checkStmt->num_rows > 0) {
            $panelistFormError = "Username already exists.";
          }
          $checkStmt->close();
        } else {
          $panelistFormError = "Unable to save the account.";
        }

        if ($panelistFormError === "") {
          $passwordHash = password_hash($password, PASSWORD_DEFAULT);
          if ($passwordHash === false) {
            $panelistFormError = "Unable to save the account.";
          } else {
            $insertStmt = $conn->prepare("INSERT INTO panelists (username, full_name, password_hash, status) VALUES (?, ?, ?, ?)");
            if ($insertStmt) {
              $insertStmt->bind_param("ssss", $panelistFormData["username"], $panelistFormData["full_name"], $passwordHash, $panelistFormData["status"]);
              if ($insertStmt->execute()) {
                $accountMessage = "Panelist account created.";
                $activeModal = "";
                $panelistFormData = [
                  "username" => "",
                  "full_name" => "",
                  "status" => "active",
                ];
              } else {
                $panelistFormError = "Unable to save the account.";
              }
              $insertStmt->close();
            } else {
              $panelistFormError = "Unable to save the account.";
            }
          }
        }
      }
    } elseif ($createAccountType === "head_office") {
      $activeModal = "headOfficeModal";
      $headOfficeFormData = [
        "username" => trim((string)($_POST["username"] ?? "")),
        "name" => trim((string)($_POST["name"] ?? "")),
        "lastname" => trim((string)($_POST["lastname"] ?? "")),
        "office" => trim((string)($_POST["office"] ?? "")),
        "status" => $status,
      ];
      $password = (string)($_POST["password"] ?? "");

      if ($headOfficeFormData["username"] === "" || $headOfficeFormData["name"] === "" || $headOfficeFormData["lastname"] === "" || $headOfficeFormData["office"] === "" || $password === "") {
        $headOfficeFormError = "Please complete all fields.";
      } else {
        $checkStmt = $conn->prepare("SELECT id FROM head_offices WHERE username = ? LIMIT 1");
        if ($checkStmt) {
          $checkStmt->bind_param("s", $headOfficeFormData["username"]);
          $checkStmt->execute();
          $checkStmt->store_result();
          if ($checkStmt->num_rows > 0) {
            $headOfficeFormError = "Username already exists.";
          }
          $checkStmt->close();
        } else {
          $headOfficeFormError = "Unable to save the account.";
        }

        if ($headOfficeFormError === "") {
          $passwordHash = password_hash($password, PASSWORD_DEFAULT);
          if ($passwordHash === false) {
            $headOfficeFormError = "Unable to save the account.";
          } else {
            $insertStmt = $conn->prepare("INSERT INTO head_offices (username, name, lastname, office, password_hash, status) VALUES (?, ?, ?, ?, ?, ?)");
            if ($insertStmt) {
              $insertStmt->bind_param("ssssss", $headOfficeFormData["username"], $headOfficeFormData["name"], $headOfficeFormData["lastname"], $headOfficeFormData["office"], $passwordHash, $headOfficeFormData["status"]);
              if ($insertStmt->execute()) {
                $accountMessage = "Head of office account created.";
                $activeModal = "";
                $headOfficeFormData = [
                  "username" => "",
                  "name" => "",
                  "lastname" => "",
                  "office" => "",
                  "status" => "active",
                ];
              } else {
                $headOfficeFormError = "Unable to save the account.";
              }
              $insertStmt->close();
            } else {
              $headOfficeFormError = "Unable to save the account.";
            }
          }
        }
      }
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

$headOfficeResult = $conn->query("SELECT username, name, lastname, office, password_hash, status FROM head_offices ORDER BY username ASC");
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
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
        border-radius: 999px;
      }
          #sidebar nav ul {
        padding: 0.35rem 0.5rem 5.5rem;
      }
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
    </style>
  </head>
  <body class="bg-white font-sans">
    <div class="min-h-screen">
      <!-- Sidebar -->
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
            <div class="min-w-0">
              <p class="text-[10px] uppercase tracking-[0.14em] text-blue-100/85">
                SMCC Scholarship
              </p>
              <p class="text-sm font-semibold leading-tight text-white">
                Admission and Scholarship Office
              </p>
              <p class="text-[10px] text-blue-100/80 mt-1">
                Admin Management Portal
              </p>
            </div>
          </div>
        </div>

        <nav class="flex-1 mt-2">
          <ul class="text-xs font-semibold">
<li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
               data-nav="adminDashboard.php" onclick="window.location.href='adminDashboard.php'"
            >
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="applicant.php" onclick="window.location.href='applicant.php'"
            >
              <i class="fas fa-user-graduate w-5"></i>
              <span>Applicants</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="approved.php" onclick="window.location.href='approved.php'"
            >
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="interviewEvaluation.php" onclick="window.location.href='interviewEvaluation.php'"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="ranks.php" onclick="window.location.href='ranks.php'"
            >
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="list-of-qualified.php" onclick="window.location.href='list-of-qualified.php'"
            >
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="department-evaluation-list.php" onclick="window.location.href='department-evaluation-list.php'"
            >
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="summary-report.php" onclick="window.location.href='summary-report.php'"
            >
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="institutional-scholars.php" onclick="window.location.href='institutional-scholars.php'"
            >
              <i class="fas fa-chart-line w-5"></i>
              <span>Institutional Scholars</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="accounts.php" onclick="window.location.href='accounts.php'"
            >
              <i class="fas fa-user-circle w-5"></i>
              <span>Accounts</span>
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

            <!-- Logout button -->
            <div class="px-3 pb-3 pt-1">
              <button
                onclick="window.location.href='../logout.php'"
                class="w-full flex items-center justify-center gap-2 text-[11px] font-semibold
                       bg-gradient-to-r from-red-500 to-red-600
                       hover:from-red-600 hover:to-red-700
                       px-3 py-2 rounded-full shadow-md hover:shadow-lg
                       transition-all duration-150"
                type="button"
              >
                <i class="fas fa-sign-out-alt text-xs"></i>
                <span>Logout</span>
              </button>
            </div>
          </div>
        </div>
      </aside>

      <!-- Main content -->
      <main class="ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pt-14">
        <!-- Top bar -->
        <header
          class="hidden fixed top-0 left-0 md:left-64 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
        >
          <div class="flex items-center gap-2">
            <!-- Mobile menu button -->
            <button
              id="sidebarToggleTop"
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
          class="page-header fixed top-0 left-0 md:left-64 right-0 z-20 bg-white border-b border-slate-200 px-4 sm:px-6 py-3 shadow-sm"
        >
                    <div class="flex items-center gap-2">
            <button
              id="sidebarToggle"
              class="md:hidden inline-flex items-center justify-center p-2 rounded bg-slate-700 text-white hover:bg-slate-800 focus:outline-none transition-colors"
              type="button"
            >
              <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-slate-800 text-lg font-semibold flex items-center gap-2">
            <i class="fas fa-flag"></i>
            ACCOUNTS
          </h2>
          </div>
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
                  data-open-modal="panelistModal"
                >
                  Add Panelist
                </button>
                <button
                  type="button"
                  class="rounded-full bg-[#0d8ddb] px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-white hover:bg-[#0b7bbf]"
                  data-open-modal="headOfficeModal"
                >
                  Add Head of Office
                </button>
              </div>
            </div>

            <?php if ($accountMessage !== ""): ?>
              <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <?= htmlspecialchars($accountMessage) ?>
              </div>
            <?php endif; ?>

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
                      <th class="border-r border-white/10 px-3 py-2">Name</th>
                      <th class="border-r border-white/10 px-3 py-2">Last Name</th>
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
                            <?= htmlspecialchars((string)($account["name"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["lastname"] ?? "")) ?>
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
         

        <!-- Panelist Modal -->
        <div
          id="panelistModal"
          class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-950/60 px-4 py-6"
          role="dialog"
          aria-modal="true"
          aria-labelledby="panelist-modal-title"
        >
          <div class="absolute inset-0" data-close-modal="panelistModal"></div>
          <div class="relative z-10 w-full max-w-2xl rounded-2xl border border-[#0d8ddb]/20 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#0d8ddb]">Accounts</p>
                <h3 id="panelist-modal-title" class="text-lg font-semibold text-[#052c6a]">Create Panelist Account</h3>
              </div>
              <button
                type="button"
                class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700"
                data-close-modal="panelistModal"
              >
                Close
              </button>
            </div>
            <div class="px-6 py-5">
              <p class="text-xs text-[#052c6a]">
                Provide the account details below to add a new panelist without leaving this page.
              </p>

              <?php if ($panelistFormError !== ""): ?>
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                  <?= htmlspecialchars($panelistFormError) ?>
                </div>
              <?php endif; ?>

              <form method="POST" class="mt-6 grid gap-4 md:grid-cols-2">
                <input type="hidden" name="create_account" value="1" />
                <input type="hidden" name="create_account_type" value="panelist" />
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="panelist-username">Username</label>
                  <input
                    id="panelist-username"
                    name="username"
                    type="text"
                    value="<?= htmlspecialchars($panelistFormData["username"]) ?>"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="panelist-full-name">Full Name</label>
                  <input
                    id="panelist-full-name"
                    name="full_name"
                    type="text"
                    value="<?= htmlspecialchars($panelistFormData["full_name"]) ?>"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="panelist-password">Password</label>
                  <input
                    id="panelist-password"
                    name="password"
                    type="password"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="panelist-status">Status</label>
                  <select
                    id="panelist-status"
                    name="status"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                  >
                    <option value="active" <?= $panelistFormData["status"] === "active" ? "selected" : "" ?>>Active</option>
                    <option value="inactive" <?= $panelistFormData["status"] === "inactive" ? "selected" : "" ?>>Inactive</option>
                  </select>
                </div>
                <div class="md:col-span-2 flex flex-wrap justify-end gap-2 pt-2">
                  <button
                    type="button"
                    class="rounded-full border border-slate-300 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:border-slate-400 hover:text-slate-700"
                    data-close-modal="panelistModal"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    class="rounded-full bg-[#0d8ddb] px-6 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:bg-[#0b7bbf]"
                  >
                    Save Panelist
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Head of Office Modal -->
        <div
          id="headOfficeModal"
          class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-950/60 px-4 py-6"
          role="dialog"
          aria-modal="true"
          aria-labelledby="head-office-modal-title"
        >
          <div class="absolute inset-0" data-close-modal="headOfficeModal"></div>
          <div class="relative z-10 w-full max-w-2xl rounded-2xl border border-[#0d8ddb]/20 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#0d8ddb]">Accounts</p>
                <h3 id="head-office-modal-title" class="text-lg font-semibold text-[#052c6a]">Create Department Head Account</h3>
              </div>
              <button
                type="button"
                class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700"
                data-close-modal="headOfficeModal"
              >
                Close
              </button>
            </div>
            <div class="px-6 py-5">
              <p class="text-xs text-[#052c6a]">
                Provide the account details below to add a new head of office without leaving this page.
              </p>

              <?php if ($headOfficeFormError !== ""): ?>
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                  <?= htmlspecialchars($headOfficeFormError) ?>
                </div>
              <?php endif; ?>

              <form method="POST" class="mt-6 grid gap-4 md:grid-cols-2">
                <input type="hidden" name="create_account" value="1" />
                <input type="hidden" name="create_account_type" value="head_office" />
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="head-office-username">Username</label>
                  <input
                    id="head-office-username"
                    name="username"
                    type="text"
                    value="<?= htmlspecialchars($headOfficeFormData["username"]) ?>"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="head-office-full-name">Name</label>
                  <input
                    id="head-office-full-name"
                    name="name"
                    type="text"
                    value="<?= htmlspecialchars($headOfficeFormData["name"]) ?>"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="head-office-last-name">Last Name</label>
                  <input
                    id="head-office-last-name"
                    name="lastname"
                    type="text"
                    value="<?= htmlspecialchars($headOfficeFormData["lastname"]) ?>"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="head-office-office">Office</label>
                  <input
                    id="head-office-office"
                    name="office"
                    type="text"
                    value="<?= htmlspecialchars($headOfficeFormData["office"]) ?>"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="head-office-password">Password</label>
                  <input
                    id="head-office-password"
                    name="password"
                    type="password"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="head-office-status">Status</label>
                  <select
                    id="head-office-status"
                    name="status"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                  >
                    <option value="active" <?= $headOfficeFormData["status"] === "active" ? "selected" : "" ?>>Active</option>
                    <option value="inactive" <?= $headOfficeFormData["status"] === "inactive" ? "selected" : "" ?>>Inactive</option>
                  </select>
                </div>
                <div class="md:col-span-2 flex flex-wrap justify-end gap-2 pt-2">
                  <button
                    type="button"
                    class="rounded-full border border-slate-300 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:border-slate-400 hover:text-slate-700"
                    data-close-modal="headOfficeModal"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    class="rounded-full bg-[#0d8ddb] px-6 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:bg-[#0b7bbf]"
                  >
                    Save Head of Office
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </main>
    </div>

    <script>
      // Sidebar toggle for mobile
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");
        const initialModal = <?= json_encode($activeModal) ?>;

        const setModalState = (modalId, isOpen) => {
          const modal = document.getElementById(modalId);
          if (!modal) {
            return;
          }

          modal.classList.toggle("hidden", !isOpen);
          modal.classList.toggle("flex", isOpen);
          document.body.classList.toggle("overflow-hidden", isOpen);
        };

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

        document.querySelectorAll("[data-open-modal]").forEach((button) => {
          button.addEventListener("click", () => {
            const modalId = button.getAttribute("data-open-modal");
            if (modalId) {
              setModalState(modalId, true);
            }
          });
        });

        document.querySelectorAll("[data-close-modal]").forEach((button) => {
          button.addEventListener("click", () => {
            const modalId = button.getAttribute("data-close-modal");
            if (modalId) {
              setModalState(modalId, false);
            }
          });
        });

        document.addEventListener("keydown", (event) => {
          if (event.key !== "Escape") {
            return;
          }

          document.querySelectorAll("[id$='Modal']").forEach((modal) => {
            if (!modal.classList.contains("hidden")) {
              setModalState(modal.id, false);
            }
          });
        });

        if (initialModal) {
          setModalState(initialModal, true);
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
    item.classList.toggle("hover:bg-white/15", !isActive);
  });
});
</script>
</body>
</html>










