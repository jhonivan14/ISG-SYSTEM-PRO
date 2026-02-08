<?php
session_start();
if (empty($_SESSION["panelist_username"])) {
  header("Location: panelLogin.php");
  exit;
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once "../db.php";

$panelistName = trim((string)($_SESSION["panelist_name"] ?? ""));
if ($panelistName === "") {
  $panelistName = "Panelist";
}
$panelistUsername = trim((string)($_SESSION["panelist_username"] ?? ""));

$passwordMessage = "";
$passwordError = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $currentPassword = (string)($_POST["current_password"] ?? "");
  $newPassword = (string)($_POST["new_password"] ?? "");
  $confirmPassword = (string)($_POST["confirm_password"] ?? "");

  if ($panelistUsername === "") {
    $passwordError = "Panelist account not found in session.";
  } elseif ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
    $passwordError = "Please complete all password fields.";
  } elseif ($newPassword !== $confirmPassword) {
    $passwordError = "New passwords do not match.";
  } else {
    $stmt = $conn->prepare("SELECT password_hash FROM panelists WHERE username = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param("s", $panelistUsername);
      $stmt->execute();
      $result = $stmt->get_result();
      $row = $result ? $result->fetch_assoc() : null;
      $stmt->close();

      $storedHash = $row ? (string)($row["password_hash"] ?? "") : "";
      $verified = false;
      if ($storedHash !== "") {
        if (strpos($storedHash, "$2y$") === 0 || strpos($storedHash, "$argon2") === 0) {
          $verified = password_verify($currentPassword, $storedHash);
        } else {
          $verified = hash("sha256", $currentPassword) === $storedHash;
        }
      }

      if (!$verified) {
        $passwordError = "Current password is incorrect.";
      } else {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($newHash === false) {
          $passwordError = "Unable to update password.";
        } else {
          $updateStmt = $conn->prepare("UPDATE panelists SET password_hash = ? WHERE username = ? LIMIT 1");
          if ($updateStmt) {
            $updateStmt->bind_param("ss", $newHash, $panelistUsername);
            if ($updateStmt->execute()) {
              $passwordMessage = "Password updated successfully.";
            } else {
              $passwordError = "Unable to update password.";
            }
            $updateStmt->close();
          } else {
            $passwordError = "Unable to update password.";
          }
        }
      }
    } else {
      $passwordError = "Unable to update password.";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Change Password</title>
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
      .panel-nav-item {
        transition: background-color 150ms ease, color 150ms ease;
      }
      .panel-nav-item.active {
        background-color: #fcdc2f;
        color: #052c6a;
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
          <img
            src="../img/SMCCNEWLOGO.png"
            class="rounded-full w-16 h-16 object-cover"
            alt="SMCC Logo"
          />
          <span class="text-sm font-normal">Admission and Scholarship Office</span>
        </div>

        <nav class="flex-1">
          <ul class="text-xs font-semibold">
            <li
              class="panel-nav-item flex items-center gap-2 px-4 py-3 cursor-pointer hover:bg-[#0d8ddb]"
              onclick="window.location.href='panelistDashboard.php'"
            >
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li
              class="panel-nav-item flex items-center gap-2 px-4 py-3 cursor-pointer hover:bg-[#0d8ddb]"
              onclick="window.location.href='panelistDashboard.php?tab=pending'"
            >
              <i class="fas fa-user-clock w-5"></i>
              <span>Pending Applicants</span>
            </li>
            <li
              class="panel-nav-item flex items-center gap-2 px-4 py-3 cursor-pointer hover:bg-[#0d8ddb]"
              onclick="window.location.href='panelistDashboard.php?tab=evaluated'"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Show Evaluated</span>
            </li>
            <li
              class="panel-nav-item active flex items-center gap-2 px-4 py-3 cursor-pointer hover:bg-[#0d8ddb]"
              onclick="window.location.href='change-password.php'"
            >
              <i class="fas fa-key w-5"></i>
              <span>Change Password</span>
            </li>
          </ul>
        </nav>

        <div class="absolute bottom-0 left-0 w-full">
          <div class="h-px w-full bg-gradient-to-r from-transparent via-[#0d8ddb] to-transparent opacity-60"></div>
          <div class="px-4 pt-2 pb-1 flex items-center gap-2 text-[11px] text-blue-100/90">
            <div class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center">
              <i class="fas fa-user-tie text-[12px]"></i>
            </div>
            <div class="leading-tight min-w-0">
              <p class="font-semibold truncate"><?= htmlspecialchars($panelistName) ?></p>
              <p class="text-[10px] text-blue-200/80 truncate"><?= htmlspecialchars($panelistUsername !== "" ? $panelistUsername : "panelist") ?></p>
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
      </aside>

      <main class="ml-0 md:ml-56 flex flex-col min-h-screen bg-gradient-to-br from-white via-blue-50 to-slate-100">
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
              Panelist View
            </button>
            <button class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 font-normal" type="button">
              <?= htmlspecialchars($panelistName) ?>
            </button>
          </div>
        </header>

        <section class="mt-12 border-b border-[#0d8ddb] px-4 sm:px-6 py-3">
          <h2 class="text-[#0d8ddb] text-lg font-semibold flex items-center gap-2">
            <i class="fas fa-key"></i>
            Change Password
          </h2>
        </section>

        <section class="mx-auto w-full max-w-3xl px-4 py-10">
          <div class="rounded-2xl border border-[#0d8ddb] bg-white p-6 shadow-sm">
            <h1 class="text-lg font-semibold text-[#052c6a]">Change Password</h1>
            <p class="text-xs text-[#052c6a]">Update your panelist account password.</p>

            <?php if ($passwordError !== ""): ?>
              <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($passwordError) ?>
              </div>
            <?php endif; ?>

            <?php if ($passwordMessage !== ""): ?>
              <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <?= htmlspecialchars($passwordMessage) ?>
              </div>
            <?php endif; ?>

            <form method="POST" class="mt-6 grid gap-4 md:grid-cols-3">
              <div>
                <label class="text-xs font-semibold text-[#052c6a]" for="current-password">Current Password</label>
                <input
                  id="current-password"
                  name="current_password"
                  type="password"
                  class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                  required
                />
              </div>
              <div>
                <label class="text-xs font-semibold text-[#052c6a]" for="new-password">New Password</label>
                <input
                  id="new-password"
                  name="new_password"
                  type="password"
                  class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                  required
                />
              </div>
              <div>
                <label class="text-xs font-semibold text-[#052c6a]" for="confirm-password">Confirm Password</label>
                <input
                  id="confirm-password"
                  name="confirm_password"
                  type="password"
                  class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                  required
                />
              </div>
              <div class="md:col-span-3">
                <button
                  type="submit"
                  class="rounded-full bg-[#0d8ddb] px-6 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:bg-[#0b7bbf]"
                >
                  Update Password
                </button>
              </div>
            </form>
          </div>
        </section>
      </main>
    </div>
    <script>
      (function () {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");
        if (!sidebar || !toggleBtn) return;
        toggleBtn.addEventListener("click", () => {
          sidebar.classList.toggle("-translate-x-full");
        });
      })();
    </script>
  </body>
</html>
