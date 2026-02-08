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

$grantId = 1;
$grantLabel = "Student Assistant";

$panelistName = trim((string)($_SESSION["panelist_name"] ?? ""));
if ($panelistName === "") {
  $panelistName = "Panelist";
}
$panelistUsername = trim((string)($_SESSION["panelist_username"] ?? ""));

$activeApplicants = [];
$archivedApplicants = [];
$panelistError = "";
$totalCount = 0;
$pendingCount = 0;
$evaluatedCount = 0;

$queueExists = false;
$queueResult = $conn->query("SHOW TABLES LIKE 'panelist_queue'");
if ($queueResult && $queueResult->num_rows > 0) {
  $queueExists = true;
} else {
  $panelistError = "Panelist queue is not set up yet. Ask the admin to create panelist_queue table.";
}

$hasEvaluationTimestamp = false;
$columnResult = $conn->query("SHOW COLUMNS FROM interview_evaluations LIKE 'created_at'");
if ($columnResult && $columnResult->num_rows > 0) {
  $hasEvaluationTimestamp = true;
  $columnResult->free();
}

$timestampSelect = $hasEvaluationTimestamp ? "MAX(created_at) AS evaluation_created_at" : "MAX(interview_date) AS evaluation_created_at";

$query = "SELECT a.id, a.created_at, a.applicant_name, a.program_course, a.status, pq.sent_by, pq.sent_at,
    ie.evaluation_id, ie.evaluation_created_at
  FROM applications a
  INNER JOIN panelist_queue pq ON pq.application_id = a.id
  LEFT JOIN (
    SELECT applicant_id, interviewer_name, MAX(id) AS evaluation_id, {$timestampSelect}
    FROM interview_evaluations
    GROUP BY applicant_id, interviewer_name
  ) ie ON ie.applicant_id = a.id AND ie.interviewer_name = ?
  WHERE a.grant_id = ? AND pq.panelist_username = ?
  ORDER BY pq.sent_at DESC";

if ($queueExists && $stmt = $conn->prepare($query)) {
  if ($panelistUsername === "") {
    $panelistError = "Panelist account not found in session.";
  }
  $stmt->bind_param("sis", $panelistName, $grantId, $panelistUsername);
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $submittedAtRaw = $row["created_at"] ?? "";
      $submittedAt = $submittedAtRaw ? date("Y-m-d h:i A", strtotime($submittedAtRaw)) : "";
      $sentAtRaw = $row["sent_at"] ?? "";
      $sentAt = $sentAtRaw ? date("Y-m-d h:i A", strtotime($sentAtRaw)) : "";
      $status = isset($row["status"]) ? trim((string)$row["status"]) : "";
      if ($status === "") {
        $status = "Pending";
      }

      $isEvaluated = !empty($row["evaluation_id"]);
      $evaluationTimestampRaw = (string)($row["evaluation_created_at"] ?? "");
      $evaluationTimestamp = $evaluationTimestampRaw !== "" ? strtotime($evaluationTimestampRaw) : false;
      $isArchived = false;
      if ($isEvaluated && $evaluationTimestamp !== false) {
        $isArchived = $evaluationTimestamp <= (time() - 86400);
      }
      if ($isEvaluated) {
        $evaluatedCount++;
      } else {
        $pendingCount++;
      }

      $evaluatedAtDisplay = "";
      if ($evaluationTimestamp !== false) {
        $evaluatedAtDisplay = date("Y-m-d h:i A", $evaluationTimestamp);
      } elseif ($evaluationTimestampRaw !== "") {
        $evaluatedAtDisplay = $evaluationTimestampRaw;
      }

      $applicantData = [
        "id" => $row["id"] ?? null,
        "submitted_at" => $submittedAt,
        "name" => $row["applicant_name"] ?? "",
        "program_course" => $row["program_course"] ?? "",
        "status" => $status,
        "sent_by" => $row["sent_by"] ?? "Admin",
        "sent_at" => $sentAt,
        "evaluated" => $isEvaluated,
        "evaluated_at" => $evaluatedAtDisplay,
      ];

      if ($isArchived) {
        $archivedApplicants[] = $applicantData;
      } else {
        $activeApplicants[] = $applicantData;
      }
    }
    $result->free();
  }
  $stmt->close();
}

$totalCount = count($activeApplicants) + count($archivedApplicants);
$showLoginSuccess = !empty($_SESSION["panelist_login_success"]);
unset($_SESSION["panelist_login_success"]);

$requestedTab = trim((string)($_GET["tab"] ?? ""));
$initialSection = "homeSection";
if ($requestedTab === "pending") {
  $initialSection = "pendingSection";
} elseif ($requestedTab === "evaluated") {
  $initialSection = "evaluatedSection";
}

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
    <link
      href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
  <body class="bg-white text-[#0b1b3a] font-sans">
    <div class="min-h-screen page-shell">
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
              class="panel-nav-item active flex items-center gap-2 px-4 py-3 cursor-pointer hover:bg-[#0d8ddb]"
              data-target-section="homeSection"
            >
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li
              class="panel-nav-item flex items-center gap-2 px-4 py-3 cursor-pointer hover:bg-[#0d8ddb]"
              data-target-section="pendingSection"
            >
              <i class="fas fa-user-clock w-5"></i>
              <span>Pending Applicants</span>
            </li>
            <li
              class="panel-nav-item flex items-center gap-2 px-4 py-3 cursor-pointer hover:bg-[#0d8ddb]"
              data-target-section="evaluatedSection"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Show Evaluated (<?= htmlspecialchars((string)count($archivedApplicants)) ?>)</span>
            </li>
            <li
              class="panel-nav-item flex items-center gap-2 px-4 py-3 cursor-pointer hover:bg-[#0d8ddb]"
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
              <p class="text-[10px] text-blue-200/80 truncate">
                <?= htmlspecialchars($panelistUsername !== "" ? $panelistUsername : "panelist") ?>
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
      </aside>

      <main class="ml-0 md:ml-56 flex flex-col min-h-screen pb-8">
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
              Panelist View
            </button>
            <button
              class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 font-normal"
              type="button"
            >
              <?= htmlspecialchars($panelistName) ?>
            </button>
          </div>
        </header>

        <section
          class="mt-12 border-b border-[#0d8ddb] px-4 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between"
        >
          <h2 class="text-[#0d8ddb] text-lg font-semibold flex items-center gap-2 mb-2 sm:mb-0">
            <i class="fas fa-columns"></i>
            Panelist Dashboard
          </h2>
          <div class="flex text-xs text-[#052c6a] space-x-4 sm:space-x-6">
            <div class="text-right">
              <div class="text-[#0d8ddb]">Total Assigned</div>
              <div class="text-[#052c6a] font-semibold"><?= htmlspecialchars((string)$totalCount) ?></div>
            </div>
            <div class="text-right">
              <div class="text-[#0d8ddb]">Pending</div>
              <div class="text-[#fcdc2f] font-semibold"><?= htmlspecialchars((string)$pendingCount) ?></div>
            </div>
            <div class="text-right">
              <div class="text-[#0d8ddb]">Evaluated</div>
              <div class="text-[#052c6a] font-semibold"><?= htmlspecialchars((string)$evaluatedCount) ?></div>
            </div>
          </div>
        </section>

        <section id="homeSection" data-panel-section class="px-4 sm:px-6">
          <section class="mt-6 glass-card rounded-3xl p-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-2">
              <span class="badge">Student Assistant Grant</span>
              <h1 class="heading-font text-3xl text-[#052c6a]">Panelist Dashboard</h1>
              <p class="text-xs text-[#42506a]">
                Review applications assigned to you for evaluation.
              </p>
              <p class="text-sm font-semibold text-[#052c6a]">
                Welcome, <?= htmlspecialchars($panelistName) ?>.
              </p>
            </div>
            <div></div>
          </section>

          <section class="stagger grid gap-4 pt-6 md:grid-cols-3">
            <div class="stat-card rounded-2xl bg-gradient-to-br from-[#052c6a] to-[#0b3f8f] p-5 text-white">
              <p class="text-xs uppercase tracking-wide text-[#fcdc2f]">Total Assigned</p>
              <p class="mt-2 text-3xl font-bold"><?= htmlspecialchars((string)$totalCount) ?></p>
              <p class="mt-1 text-[11px] text-blue-100">
                Applicants sent to you for evaluation.
              </p>
            </div>
            <div class="stat-card rounded-2xl bg-white p-5 text-[#052c6a]">
              <p class="text-xs uppercase tracking-wide text-[#0d8ddb]">Pending Reviews</p>
              <p class="mt-2 text-3xl font-bold"><?= htmlspecialchars((string)$pendingCount) ?></p>
              <p class="mt-1 text-[11px] text-slate-500">
                Applications waiting for panelist review.
              </p>
            </div>
            <div class="stat-card rounded-2xl bg-gradient-to-br from-[#fcdc2f] to-[#f7b500] p-5 text-[#052c6a]">
              <p class="text-xs uppercase tracking-wide">Evaluated</p>
              <p class="mt-2 text-3xl font-bold"><?= htmlspecialchars((string)$evaluatedCount) ?></p>
              <p class="mt-1 text-[11px] text-[#052c6a]">
                Already evaluated by you.
              </p>
            </div>
          </section>
        </section>

        <section id="pendingSection" data-panel-section class="hidden mt-6 glass-card rounded-3xl p-5 mx-4 sm:mx-6">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-sm font-semibold text-[#0d8ddb]">
                Student Assistant Applicants
              </p>
              <p class="text-xs text-[#052c6a]">
                Showing <?= htmlspecialchars((string)count($activeApplicants)) ?> active applicants for
                <?= htmlspecialchars($grantLabel) ?>.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
              <div class="flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-white px-3 py-2 shadow-sm">
                <i class="fas fa-search text-[#7c8191] text-xs"></i>
                <input
                  id="panelistSearch"
                  type="text"
                  class="w-44 bg-transparent text-xs font-semibold text-[#052c6a] outline-none placeholder:text-[#7c8191]"
                  placeholder="Search applicants..."
                  aria-label="Search applicants"
                />
              </div>
              <span class="rounded-full bg-[#fcdc2f] px-3 py-1 text-[#052c6a] shadow-sm">
                Pending: <?= htmlspecialchars((string)$pendingCount) ?>
              </span>
              <span class="rounded-full bg-[#052c6a] px-3 py-1 text-white shadow-sm">
                Evaluated: <?= htmlspecialchars((string)$evaluatedCount) ?>
              </span>
              <span class="rounded-full bg-blue-100 px-3 py-1 text-blue-700 shadow-sm">
                Total: <?= htmlspecialchars((string)$totalCount) ?>
              </span>
            </div>
          </div>

          <div class="mt-4 overflow-x-auto">
            <?php if ($panelistError !== ""): ?>
              <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                <?= htmlspecialchars($panelistError) ?>
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
                  <th class="border-r border-white/10 px-3 py-3">Sent By</th>
                  <th class="px-3 py-3">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($activeApplicants)): ?>
                  <tr>
                    <td colspan="7" class="px-3 py-4 text-center text-[#052c6a]">
                      No active applicants found.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($activeApplicants as $applicant): ?>
                    <?php
                      $searchText = strtolower(
                        ($applicant["name"] ?? "") . " " .
                        ($applicant["program_course"] ?? "") . " " .
                        ($applicant["status"] ?? "") . " " .
                        ($applicant["sent_by"] ?? "")
                      );
                    ?>
                    <tr class="border-b border-[#0d8ddb]" data-panelist-row data-search-text="<?= htmlspecialchars($searchText) ?>">
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
                        <span class="rounded-full bg-[#fcdc2f] px-2 py-1 text-[#052c6a] font-semibold shadow-sm">
                          <?= htmlspecialchars($applicant["status"]) ?>
                        </span>
                      </td>
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <div class="text-xs font-semibold">
                          <?= htmlspecialchars($applicant["sent_by"]) ?>
                        </div>
                        <?php if ($applicant["sent_at"] !== ""): ?>
                          <div class="text-[10px] text-slate-500">
                            <?= htmlspecialchars($applicant["sent_at"]) ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td class="px-3 py-2">
                        <div class="flex flex-wrap gap-2">
                          <?php if (!empty($applicant["evaluated"])): ?>
                            <button
                              class="cursor-not-allowed rounded-full bg-slate-200 px-3 py-1 text-[11px] font-semibold text-slate-500 shadow-sm"
                              type="button"
                              disabled
                            >
                              Evaluated
                            </button>
                          <?php else: ?>
                            <button
                              class="rounded-full bg-[#0d8ddb] px-3 py-1 text-[11px] font-semibold text-white shadow-sm hover:bg-[#0b7cc0]"
                              type="button"
                              onclick="window.location.href='panelist_eval.php?applicant_id=<?= urlencode((string) ($applicant['id'] ?? '')) ?>'"
                            >
                              Evaluate
                            </button>
                          <?php endif; ?>
                          <button
                            class="rounded-full border border-[#052c6a] px-3 py-1 text-[11px] font-semibold text-[#052c6a] hover:bg-[#052c6a] hover:text-white"
                            type="button"
                            onclick="window.location.href='panelist_eval_view.php?applicant_id=<?= urlencode((string) ($applicant['id'] ?? '')) ?>'"
                          >
                            View Evaluation
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr data-panelist-empty class="hidden">
                    <td colspan="7" class="px-3 py-4 text-center text-[#052c6a]">
                      No matching applicants.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

        <section id="evaluatedSection" data-panel-section class="hidden mt-6 glass-card rounded-3xl p-5 mx-4 sm:mx-6">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-sm font-semibold text-[#0d8ddb]">
                Evaluated Applicants (Archived after 24 hours)
              </p>
              <p class="text-xs text-[#052c6a]">
                Showing <?= htmlspecialchars((string)count($archivedApplicants)) ?> archived evaluations.
              </p>
            </div>
          </div>

          <div class="mt-4 overflow-x-auto">
            <table class="table-hover min-w-full overflow-hidden rounded-2xl border border-[#0d8ddb] text-xs text-left">
              <thead>
                <tr class="bg-gradient-to-r from-[#052c6a] to-[#0b3f8f] text-white">
                  <th class="border-r border-white/10 px-3 py-3">Evaluated At</th>
                  <th class="border-r border-white/10 px-3 py-3">Applicant Name</th>
                  <th class="border-r border-white/10 px-3 py-3">Program / Course</th>
                  <th class="border-r border-white/10 px-3 py-3">Grant</th>
                  <th class="border-r border-white/10 px-3 py-3">Status</th>
                  <th class="px-3 py-3">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($archivedApplicants)): ?>
                  <tr>
                    <td colspan="6" class="px-3 py-4 text-center text-[#052c6a]">
                      No archived evaluations yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($archivedApplicants as $applicant): ?>
                    <?php
                      $searchText = strtolower(
                        ($applicant["name"] ?? "") . " " .
                        ($applicant["program_course"] ?? "") . " " .
                        ($applicant["status"] ?? "")
                      );
                    ?>
                    <tr class="border-b border-[#0d8ddb]" data-archive-row data-search-text="<?= htmlspecialchars($searchText) ?>">
                      <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                        <?= htmlspecialchars($applicant["evaluated_at"]) ?>
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
                        <span class="rounded-full bg-[#052c6a] px-2 py-1 text-white font-semibold shadow-sm">
                          Evaluated
                        </span>
                      </td>
                      <td class="px-3 py-2">
                        <button
                          class="rounded-full border border-[#052c6a] px-3 py-1 text-[11px] font-semibold text-[#052c6a] hover:bg-[#052c6a] hover:text-white"
                          type="button"
                          onclick="window.location.href='panelist_eval_view.php?applicant_id=<?= urlencode((string) ($applicant['id'] ?? '')) ?>'"
                        >
                          View Evaluation
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr data-archive-empty class="hidden">
                    <td colspan="6" class="px-3 py-4 text-center text-[#052c6a]">
                      No matching archived applicants.
                    </td>
                  </tr>
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
    <script>
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");
        const navItems = Array.from(document.querySelectorAll("[data-target-section]"));
        const sections = Array.from(document.querySelectorAll("[data-panel-section]"));

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

        const searchInput = document.getElementById("panelistSearch");
        const activeRows = Array.from(document.querySelectorAll("[data-panelist-row]"));
        const activeEmpty = document.querySelector("[data-panelist-empty]");
        const archiveRows = Array.from(document.querySelectorAll("[data-archive-row]"));
        const archiveEmpty = document.querySelector("[data-archive-empty]");

        const applySearch = () => {
          const query = (searchInput?.value || "").trim().toLowerCase();
          let activeVisible = 0;
          let archiveVisible = 0;

          activeRows.forEach((row) => {
            const text = row.dataset.searchText || "";
            const matches = query === "" || text.includes(query);
            row.style.display = matches ? "table-row" : "none";
            if (matches) activeVisible++;
          });

          archiveRows.forEach((row) => {
            const text = row.dataset.searchText || "";
            const matches = query === "" || text.includes(query);
            row.style.display = matches ? "table-row" : "none";
            if (matches) archiveVisible++;
          });

          if (activeEmpty) {
            activeEmpty.style.display = activeVisible === 0 ? "table-row" : "none";
          }
          if (archiveEmpty) {
            archiveEmpty.style.display = archiveVisible === 0 ? "table-row" : "none";
          }
        };

        if (searchInput) {
          searchInput.addEventListener("input", applySearch);
        }
        applySearch();
      });
    </script>
  </body>
</html>
