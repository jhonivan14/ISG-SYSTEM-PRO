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
  </head>
  <body class="bg-white font-sans">
    <div class="min-h-screen bg-gradient-to-br from-white via-blue-50 to-slate-100">
      <header class="bg-[#052c6a] text-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
          <div class="text-xs">
            <p class="font-semibold">Admission and Scholarship Office</p>
            <p class="text-[11px] text-blue-100">Panelist Change Password</p>
          </div>
          <div class="flex items-center gap-2 text-xs">
            <span class="text-[11px] text-blue-100"><?= htmlspecialchars($panelistName) ?></span>
            <button
              class="rounded-full border border-white/40 px-3 py-1 text-[11px] hover:bg-white/10"
              type="button"
              onclick="window.location.href='panelistDashboard.php'"
            >
              Back
            </button>
          </div>
        </div>
      </header>

      <main class="mx-auto max-w-3xl px-4 py-10">
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
      </main>
    </div>
  </body>
</html>
