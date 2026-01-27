<?php
session_start();
require_once "../db.php";

$grantId = 1;
$grantLabel = "Student Assistant";

$applicants = [];
$totalCount = 0;
$pendingCount = 0;
$approvedCount = 0;
$declinedCount = 0;

$query = "SELECT created_at, applicant_name, program_course, status
  FROM applications
  WHERE grant_id = ?
  ORDER BY created_at DESC";

if ($stmt = $conn->prepare($query)) {
  $stmt->bind_param("i", $grantId);
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $submittedAtRaw = $row["created_at"] ?? "";
      $submittedAt = $submittedAtRaw ? date("Y-m-d h:i A", strtotime($submittedAtRaw)) : "";
      $status = isset($row["status"]) ? trim((string)$row["status"]) : "";
      if ($status === "") {
        $status = "Pending";
      }

      $normalizedStatus = strtolower($status);
      if ($normalizedStatus === "approved") {
        $approvedCount++;
      } elseif ($normalizedStatus === "declined" || $normalizedStatus === "rejected") {
        $declinedCount++;
      } else {
        $pendingCount++;
      }

      $applicants[] = [
        "submitted_at" => $submittedAt,
        "name" => $row["applicant_name"] ?? "",
        "program_course" => $row["program_course"] ?? "",
        "status" => $status,
      ];
    }
    $result->free();
  }
  $stmt->close();
}

$totalCount = count($applicants);
$panelistName = trim((string)($_SESSION["panelist_name"] ?? ""));
if ($panelistName === "") {
  $panelistName = "Panelist";
}
$panelistUsername = trim((string)($_SESSION["panelist_username"] ?? ""));
$showLoginSuccess = !empty($_SESSION["panelist_login_success"]);
unset($_SESSION["panelist_login_success"]);

// Change password is handled in a separate page.
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Panelist Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
      rel="stylesheet"
    />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  </head>
  <body class="bg-white font-sans">
    <div class="min-h-screen bg-gradient-to-br from-white via-blue-50 to-slate-100">
      <header class="bg-[#052c6a] text-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
          <div class="flex items-center gap-3">
            <img
              src="../img/SMCCNEWLOGO.png"
              class="h-12 w-12 rounded-full object-cover"
              alt="SMCC Logo"
            />
            <div class="text-xs">
              <p class="font-semibold">Admission and Scholarship Office</p>
              <p class="text-[11px] text-blue-100">Panelist Dashboard</p>
            </div>
          </div>
          <div class="flex items-center gap-2 text-xs">
            <div class="text-right">
              <p class="text-[11px] text-blue-100">Signed in as</p>
              <p class="text-sm font-semibold">
                <?= htmlspecialchars($panelistName) ?>
              </p>
            </div>
            <span class="rounded-full bg-[#fcdc2f] px-3 py-1 text-[#052c6a]">
              Panelist View
            </span>
            <button
              class="rounded-full border border-white/40 px-3 py-1 text-[11px] hover:bg-white/10"
              type="button"
              onclick="window.location.href='change-password.php'"
            >
              Change Password
            </button>
            <button
              class="rounded-full border border-white/40 px-3 py-1 text-[11px] hover:bg-white/10"
              type="button"
              onclick="window.location.href='../logout.php'"
            >
              Logout
            </button>
          </div>
        </div>
      </header>

      <main class="mx-auto max-w-6xl px-4 pb-10">
        <section class="flex flex-col gap-4 border-b border-[#0d8ddb] py-6 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.25em] text-[#0d8ddb]">
              Student Assistant Grant
            </p>
            <h1 class="text-2xl font-semibold text-[#052c6a]">
              Panelist Dashboard
            </h1>
            <p class="text-xs text-[#052c6a]">
              Review applications submitted for the student assistant grant.
            </p>
            <p class="mt-2 text-sm font-semibold text-[#052c6a]">
              Welcome, <?= htmlspecialchars($panelistName) ?>.
            </p>
          </div>
          <div class="flex items-center gap-4 text-right text-xs text-[#052c6a]">
            <div>
              <p class="text-[#0d8ddb]">Total Applicants</p>
              <p class="text-lg font-semibold text-[#052c6a]">
                <?= htmlspecialchars((string)$totalCount) ?>
              </p>
            </div>
            <div class="h-10 w-px bg-[#0d8ddb]/40"></div>
            <div>
              <p class="text-[#0d8ddb]">Pending</p>
              <p class="text-lg font-semibold text-[#fcdc2f]">
                <?= htmlspecialchars((string)$pendingCount) ?>
              </p>
            </div>
          </div>
        </section>

        <section class="grid gap-4 pt-6 md:grid-cols-3">
          <div class="rounded-xl bg-[#052c6a] p-4 text-white shadow">
            <p class="text-xs uppercase tracking-wide text-[#fcdc2f]">
              Total Applicants
            </p>
            <p class="mt-2 text-2xl font-bold">
              <?= htmlspecialchars((string)$totalCount) ?>
            </p>
            <p class="mt-1 text-[11px] text-blue-100">
              Overall student assistant submissions.
            </p>
          </div>
          <div class="rounded-xl border border-[#0d8ddb] bg-white p-4 text-[#052c6a] shadow-sm">
            <p class="text-xs uppercase tracking-wide text-[#0d8ddb]">
              Pending Reviews
            </p>
            <p class="mt-2 text-2xl font-bold">
              <?= htmlspecialchars((string)$pendingCount) ?>
            </p>
            <p class="mt-1 text-[11px] text-gray-500">
              Applications waiting for panelist review.
            </p>
          </div>
          <div class="rounded-xl bg-[#fcdc2f] p-4 text-[#052c6a] shadow">
            <p class="text-xs uppercase tracking-wide">Approved</p>
            <p class="mt-2 text-2xl font-bold">
              <?= htmlspecialchars((string)$approvedCount) ?>
            </p>
            <p class="mt-1 text-[11px] text-[#052c6a]">
              Marked as approved by evaluators.
            </p>
          </div>
        </section>

        <section class="mt-6 rounded-2xl border border-[#0d8ddb] bg-white p-4 shadow-sm">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-sm font-semibold text-[#0d8ddb]">
                Student Assistant Applicants
              </p>
              <p class="text-xs text-[#052c6a]">
                Showing <?= htmlspecialchars((string)$totalCount) ?> applicants for
                <?= htmlspecialchars($grantLabel) ?>.
              </p>
            </div>
            <div class="flex items-center gap-2 text-xs">
              <span class="rounded-full bg-[#052c6a] px-3 py-1 text-white">
                Approved: <?= htmlspecialchars((string)$approvedCount) ?>
              </span>
              <span class="rounded-full bg-[#fcdc2f] px-3 py-1 text-[#052c6a]">
                Pending: <?= htmlspecialchars((string)$pendingCount) ?>
              </span>
              <span class="rounded-full bg-red-100 px-3 py-1 text-red-700">
                Declined: <?= htmlspecialchars((string)$declinedCount) ?>
              </span>
            </div>
          </div>

          <div class="mt-4 overflow-x-auto">
            <table class="min-w-full border border-[#0d8ddb] text-xs text-left">
              <thead>
                <tr class="bg-[#052c6a] text-white">
                  <th class="border-r border-white/10 px-3 py-3">Timestamp</th>
                  <th class="border-r border-white/10 px-3 py-3">Applicant Name</th>
                  <th class="border-r border-white/10 px-3 py-3">Program / Course</th>
                  <th class="border-r border-white/10 px-3 py-3">Grant</th>
                  <th class="border-r border-white/10 px-3 py-3">Status</th>
                  <th class="px-3 py-3">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($applicants)): ?>
                  <tr>
                    <td colspan="6" class="px-3 py-4 text-center text-[#052c6a]">
                      No student assistant applicants found.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($applicants as $applicant): ?>
                    <tr class="border-b border-[#0d8ddb]">
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
                        <span class="rounded-full bg-[#fcdc2f] px-2 py-1 text-[#052c6a]">
                          <?= htmlspecialchars($applicant["status"]) ?>
                        </span>
                      </td>
                      <td class="px-3 py-2">
                        <div class="flex flex-wrap gap-2">
                          <button
                            class="rounded-full bg-[#0d8ddb] px-3 py-1 text-[11px] text-white"
                            type="button"
                            onclick="window.location.href='Panelist_eval.php'"
                          >
                            Review
                          </button>
                          <button
                            class="rounded-full border border-[#052c6a] px-3 py-1 text-[11px] text-[#052c6a] hover:bg-[#052c6a] hover:text-white"
                            type="button"
                          >
                            View Profile
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

      </main>
    </div>
    <?php if ($showLoginSuccess): ?>
      <script>
        document.addEventListener("DOMContentLoaded", () => {
          Swal.fire({
            icon: "success",
            title: "Login successful",
            text: "Welcome, <?= htmlspecialchars($panelistName) ?>.",
            confirmButtonColor: "#0d8ddb",
          });
        });
      </script>
    <?php endif; ?>
  </body>
</html>
