<?php
session_start();
require_once "../db.php";

$headName = trim((string)($_SESSION["head_name"] ?? ""));
if ($headName === "") {
  $headName = "Head of Office";
}
$headUsername = trim((string)($_SESSION["head_username"] ?? ""));

$passwordMessage = "";
$passwordError = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $currentPassword = (string)($_POST["current_password"] ?? "");
  $newPassword = (string)($_POST["new_password"] ?? "");
  $confirmPassword = (string)($_POST["confirm_password"] ?? "");

  if ($headUsername === "") {
    $passwordError = "Head of office account not found in session.";
  } elseif ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
    $passwordError = "Please complete all password fields.";
  } elseif ($newPassword !== $confirmPassword) {
    $passwordError = "New passwords do not match.";
  } else {
    $stmt = $conn->prepare("SELECT password_hash FROM head_offices WHERE username = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param("s", $headUsername);
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
          $updateStmt = $conn->prepare("UPDATE head_offices SET password_hash = ? WHERE username = ? LIMIT 1");
          if ($updateStmt) {
            $updateStmt->bind_param("ss", $newHash, $headUsername);
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
    <link
      href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <style>
      :root {
        --navy: #052c6a;
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

      .glass-card {
        background: rgba(255, 255, 255, 0.88);
        border: 1px solid rgba(13, 141, 219, 0.25);
        box-shadow: 0 18px 45px rgba(5, 44, 106, 0.12);
        backdrop-filter: blur(12px);
      }

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
        <div class="mx-3 mt-3 rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
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
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='headDashboard.php'">
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='headDashboard.php?tab=my-sas'">
              <i class="fas fa-user-friends w-5"></i>
              <span>My SA's</span>
            </li>
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='show-evaluation.php'">
              <i class="fas fa-check-circle w-5"></i>
              <span>Show Evaluation</span>
            </li>
            <li class="panel-nav-item active gap-2 cursor-pointer" onclick="window.location.href='head-changePassword.php'">
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
              <i class="fas fa-key"></i>
              Change Password
            </h2>
          </div>
        </header>

        <section class="px-4 sm:px-6 pt-6">
          <div class="glass-card mx-auto max-w-4xl rounded-3xl p-6">
            <h1 class="text-lg font-semibold text-[#052c6a]">Change Password</h1>
            <p class="text-xs text-[#052c6a]">Update your head of office account password.</p>

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
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");

        if (toggleBtn && sidebar) {
          toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
          });
        }
      });
    </script>
  </body>
</html>
