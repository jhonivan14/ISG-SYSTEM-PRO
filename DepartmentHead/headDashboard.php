<?php
session_start();
require_once "../db.php";

$headName = trim((string)($_SESSION["head_name"] ?? ""));
if ($headName === "") {
  $headName = "Head of Office";
}
$headUsername = trim((string)($_SESSION["head_username"] ?? ""));

$grantId = 1;
$grantLabel = "Student Assistant";
$approvedApplicants = [];

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
  }
  $stmt->close();
}

$approvedCount = count($approvedApplicants);

// Change password is handled in a separate page.
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
  </head>
  <body class="bg-white font-sans">
    <div class="min-h-screen bg-gradient-to-br from-white via-blue-50 to-slate-100">
      <header class="bg-[#052c6a] text-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
          <div class="flex items-center gap-3">
            <img
              src="../img/SMCCNEWLOGO.png"
              class="h-12 w-12 rounded-full object-cover"
              alt="SMCC Logo"
            />
            <div class="text-xs">
              <p class="font-semibold">Admission and Scholarship Office</p>
              <p class="text-[11px] text-blue-100">Head of Office Dashboard</p>
            </div>
          </div>
          <div class="flex items-center gap-2 text-xs">
            <div class="text-right">
              <p class="text-[11px] text-blue-100">Signed in as</p>
              <p class="text-sm font-semibold"><?= htmlspecialchars($headName) ?></p>
            </div>
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

      <main class="mx-auto max-w-5xl px-4 py-8">
        <section class="rounded-2xl border border-[#0d8ddb] bg-white p-5 shadow-sm">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-sm font-semibold text-[#0d8ddb]">Approved Applicants</p>
              <p class="text-xs text-[#052c6a]">
                Showing <?= htmlspecialchars((string)$approvedCount) ?> approved applicants for
                <?= htmlspecialchars($grantLabel) ?>.
              </p>
            </div>
            <span class="rounded-full bg-[#fcdc2f] px-3 py-1 text-xs font-semibold text-[#052c6a]">
              Approved: <?= htmlspecialchars((string)$approvedCount) ?>
            </span>
          </div>

          <div class="mt-4 overflow-x-auto">
            <table class="min-w-full border border-[#0d8ddb] text-xs text-left">
              <thead class="bg-[#052c6a] text-white">
                <tr>
                  <th class="border-r border-white/10 px-3 py-2">Timestamp</th>
                  <th class="border-r border-white/10 px-3 py-2">Applicant Name</th>
                  <th class="border-r border-white/10 px-3 py-2">Program / Course</th>
                  <th class="border-r border-white/10 px-3 py-2">Grant</th>
                  <th class="px-3 py-2">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($approvedApplicants)): ?>
                  <tr>
                    <td colspan="5" class="px-3 py-3 text-center text-[#052c6a]">
                      No approved applicants yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($approvedApplicants as $applicant): ?>
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
                      <td class="px-3 py-2">
                        <span class="rounded-full bg-green-500 px-2 py-1 text-[10px] text-white">
                          Approved
                        </span>
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
  </body>
</html>
