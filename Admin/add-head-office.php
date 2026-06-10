<?php
// Guide: Standalone head office account registration form.
// Trace: validate submitted fields -> insert account -> show success or error feedback.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once "../db.php";

$errorMessage = "";
$successMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim((string)($_POST["username"] ?? ""));
  $fullName = trim((string)($_POST["full_name"] ?? ""));
  $office = trim((string)($_POST["office"] ?? ""));
  $password = (string)($_POST["password"] ?? "");
  $status = trim((string)($_POST["status"] ?? "active"));

  if ($username === "" || $fullName === "" || $office === "" || $password === "") {
    $errorMessage = "Please complete all fields.";
  } else {
    $checkStmt = $conn->prepare("SELECT id FROM head_offices WHERE username = ? LIMIT 1");
    if ($checkStmt) {
      $checkStmt->bind_param("s", $username);
      $checkStmt->execute();
      $checkStmt->store_result();
      if ($checkStmt->num_rows > 0) {
        $errorMessage = "Username already exists.";
      }
      $checkStmt->close();
    }

    if ($errorMessage === "") {
      $passwordHash = password_hash($password, PASSWORD_DEFAULT);
      if ($passwordHash === false) {
        $errorMessage = "Unable to save the account.";
      } else {
        $insertStmt = $conn->prepare("INSERT INTO head_offices (username, full_name, office, password_hash, status) VALUES (?, ?, ?, ?, ?)");
        if ($insertStmt) {
          $insertStmt->bind_param("sssss", $username, $fullName, $office, $passwordHash, $status);
          if ($insertStmt->execute()) {
            $successMessage = "Head of office account created.";
          } else {
            $errorMessage = "Unable to save the account.";
          }
          $insertStmt->close();
        } else {
          $errorMessage = "Unable to save the account.";
        }
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Add Head of Office</title>
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
      rel="stylesheet"
    />
  </head>
  <body class="bg-white font-sans">
    <div class="min-h-screen bg-gradient-to-br from-white via-blue-50 to-slate-100">
      <header class="bg-[#052c6a] text-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
          <div class="flex items-center gap-3">
            <img
              src="../img/SMCCNEWLOGO.png"
              class="h-12 w-12 rounded-full object-cover"
              alt="SMCC Logo"
            />
            <div class="text-xs">
              <p class="font-semibold">Admission and Scholarship Office</p>
              <p class="text-[11px] text-blue-100">Add Head of Office Account</p>
            </div>
          </div>
          <button
            type="button"
            class="rounded-full border border-white/40 px-3 py-1 text-[11px] hover:bg-white/10"
            onclick="window.location.href='accounts.php'"
          >
            Back to Accounts
          </button>
        </div>
      </header>

      <main class="mx-auto max-w-3xl px-4 py-10">
        <div class="rounded-2xl border border-[#0d8ddb] bg-white p-6 shadow-sm">
          <h1 class="text-lg font-semibold text-[#052c6a]">Create Head of Office Account</h1>
          <p class="text-xs text-[#052c6a]">
            Provide account details to add a new head of office.
          </p>

          <?php if ($errorMessage !== ""): ?>
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
              <?= htmlspecialchars($errorMessage) ?>
            </div>
          <?php endif; ?>

          <?php if ($successMessage !== ""): ?>
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
              <?= htmlspecialchars($successMessage) ?>
            </div>
          <?php endif; ?>

          <form method="POST" class="mt-6 grid gap-4 md:grid-cols-2">
            <div>
              <label class="text-xs font-semibold text-[#052c6a]" for="username">Username</label>
              <input
                id="username"
                name="username"
                type="text"
                class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                required
              />
            </div>
            <div>
              <label class="text-xs font-semibold text-[#052c6a]" for="full-name">Full Name</label>
              <input
                id="full-name"
                name="full_name"
                type="text"
                class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                required
              />
            </div>
            <div>
              <label class="text-xs font-semibold text-[#052c6a]" for="office">Office</label>
              <input
                id="office"
                name="office"
                type="text"
                class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                required
              />
            </div>
            <div>
              <label class="text-xs font-semibold text-[#052c6a]" for="password">Password</label>
              <input
                id="password"
                name="password"
                type="password"
                class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                required
              />
            </div>
            <div>
              <label class="text-xs font-semibold text-[#052c6a]" for="status">Status</label>
              <select
                id="status"
                name="status"
                class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
              >
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div class="md:col-span-2">
              <button
                type="submit"
                class="rounded-full bg-[#0d8ddb] px-6 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:bg-[#0b7bbf]"
              >
                Save Head of Office
              </button>
            </div>
          </form>
        </div>
      </main>
    </div>
  </body>
</html>
