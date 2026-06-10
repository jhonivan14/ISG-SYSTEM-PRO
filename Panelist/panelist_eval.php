<?php
require_once __DIR__ . "/panelist-auth.php";
panelistRequireLogin();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once __DIR__ . '/../db.php';

$panelistName = trim((string)($_SESSION["panelist_name"] ?? ""));
$panelistUsername = trim((string)($_SESSION["panelist_username"] ?? ""));
if ($panelistName === "") {
    $panelistName = $panelistUsername !== "" ? $panelistUsername : "Panelist";
}

$applicantId = (int)($_GET["applicant_id"] ?? ($_POST["applicant_id"] ?? 0));
$prefillApplicantName = "";
$loadError = "";

if ($applicantId > 0 && $panelistUsername !== "") {
    $stmt = $conn->prepare(
        "SELECT a.applicant_name
         FROM applications a
         INNER JOIN panelist_queue pq ON pq.application_id = a.id
         WHERE a.id = ? AND pq.panelist_username = ?
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param("is", $applicantId, $panelistUsername);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $prefillApplicantName = (string)($row["applicant_name"] ?? "");
            }
            $result->free();
        }
        $stmt->close();
    }
}

if ($panelistUsername === "") {
    $loadError = "Panelist account not found in session.";
} elseif ($applicantId <= 0) {
    $loadError = "Applicant not found.";
} elseif ($prefillApplicantName === "") {
    $loadError = "Applicant is not assigned to this panelist.";
}

$save_success = null;
$save_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicant_name = $prefillApplicantName;
    $interview_date = date('Y-m-d');
    $interviewer_name = $panelistName;
    $overall_assessment = $_POST['overall_assessment'] ?? null;
    $strengths = trim($_POST['strengths'] ?? '');
    $areas_for_improvement = trim($_POST['areas_for_improvement'] ?? '');
    $signature_data = $_POST['signature_data'] ?? null;

    $ratings = $_POST['rating'] ?? [];
    $comments = $_POST['comment'] ?? [];
    $computed_total = 0;
    foreach (range(1, 12) as $criterion_id) {
        if (!isset($ratings[$criterion_id])) {
            continue;
        }
        $rating_value = (int) $ratings[$criterion_id];
        if ($rating_value < 1 || $rating_value > 5) {
            continue;
        }
        $computed_total += $rating_value;
    }
    $total_points = (string) $computed_total;
    $overall_assessment = ($overall_assessment === '' || $overall_assessment === null) ? null : $overall_assessment;
    $strengths = $strengths === '' ? null : $strengths;
    $areas_for_improvement = $areas_for_improvement === '' ? null : $areas_for_improvement;
    $signature_data = $signature_data === '' ? null : $signature_data;

    if ($loadError !== "" || $applicant_name === '' || $interviewer_name === '') {
        $save_error = $loadError !== "" ? $loadError : 'Applicant or panelist details are missing.';
    } else {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare(
                'INSERT INTO interview_evaluations (applicant_id, applicant_name, interview_date, interviewer_name, total_points, overall_assessment, strengths, areas_for_improvement, signature_data)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'issssssss',
                $applicantId,
                $applicant_name,
                $interview_date,
                $interviewer_name,
                $total_points,
                $overall_assessment,
                $strengths,
                $areas_for_improvement,
                $signature_data
            );
            $stmt->execute();
            $evaluation_id = $stmt->insert_id;
            $stmt->close();

            $item_stmt = $conn->prepare(
                'INSERT INTO interview_evaluation_items (evaluation_id, criterion_id, rating, comment)
                 VALUES (?, ?, ?, ?)'
            );

            foreach (range(1, 12) as $criterion_id) {
                if (!isset($ratings[$criterion_id])) {
                    continue;
                }
                $rating_value = (int) $ratings[$criterion_id];
                if ($rating_value < 1 || $rating_value > 5) {
                    continue;
                }
                $comment_value = trim($comments[$criterion_id] ?? '');
                $comment_value = $comment_value === '' ? null : $comment_value;

                $item_stmt->bind_param('iiis', $evaluation_id, $criterion_id, $rating_value, $comment_value);
                $item_stmt->execute();
            }
            $item_stmt->close();

            $conn->commit();
            $save_success = 'Evaluation saved successfully.';
        } catch (Throwable $e) {
            $conn->rollback();
            $save_error = 'Failed to save evaluation. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Panelist Evaluation Sheet</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
        rel="stylesheet"
    />
    <style>
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
<body class="bg-slate-100 font-sans text-[#0b1b3a]">
    <div class="min-h-screen">
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
                    <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='panelistDashboard.php'">
                        <i class="fas fa-home w-5"></i>
                        <span>Home</span>
                    </li>
                    <li class="panel-nav-item active gap-2 cursor-pointer" onclick="window.location.href='panelistDashboard.php?tab=pending'">
                        <i class="fas fa-user-clock w-5"></i>
                        <span>Pending Applicants</span>
                    </li>
                    <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='panelistDashboard.php?tab=evaluated'">
                        <i class="fas fa-check-circle w-5"></i>
                        <span>Show Evaluated</span>
                    </li>
                    <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='change-password.php'">
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
            </div>
        </aside>

        <main class="ml-0 md:ml-64 min-h-screen pt-14">
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
                        <i class="fas fa-clipboard-check"></i>
                        Interview Evaluation Sheet
                    </h2>
                </div>
            </header>
            <div class="px-4 py-6">
                <div class="max-w-5xl mx-auto px-4">
        <div class="bg-white shadow-2xl shadow-blue-100/60 rounded-2xl overflow-hidden border border-slate-200">
            <div class="bg-gradient-to-r from-blue-900 via-blue-800 to-blue-600 px-8 py-10 text-white">
                <div class="mb-4">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-xs font-bold uppercase tracking-wide text-blue-900 shadow-lg shadow-blue-900/20 hover:bg-blue-50 focus:outline-none focus:ring focus:ring-white/40"
                        onclick="window.location.href='panelistDashboard.php'"
                    >
                        <span aria-hidden="true">&larr;</span>
                        Back to Dashboard
                    </button>
                </div>
                <p class="text-sm uppercase tracking-[0.35em] text-blue-100">Student Assistance Scholarship Program</p>
                <h1 class="mt-3 text-3xl font-bold tracking-tight">Interview Evaluation Sheet</h1>
                <p class="mt-4 max-w-3xl text-blue-100 text-sm leading-relaxed">
                    Evaluate applicants using the performance scale below. Document constructive feedback to support the final decision.
                </p>
            </div>

            <form method="post" class="px-8 py-10 space-y-10">
                <?php if ($loadError !== ""): ?>
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <?php echo htmlspecialchars($loadError); ?>
                    </div>
                <?php endif; ?>
                <section class="grid gap-6 md:grid-cols-3">
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-slate-700" for="applicant-name">Applicant&apos;s Name</label>
                        <input id="applicant-name" name="applicant_name" type="text" value="<?php echo htmlspecialchars($prefillApplicantName); ?>" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring focus:ring-blue-500/20" readonly />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-slate-700" for="interview-date">Date of Interview</label>
                        <input id="interview-date" name="interview_date" type="date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring focus:ring-blue-500/20" readonly />
                    </div>
                    <div class="space-y-2 md:col-span-1">
                        <label class="text-sm font-semibold text-slate-700" for="interviewer-name">Interviewer&apos;s Name</label>
                        <input id="interviewer-name" name="interviewer_name" type="text" value="<?php echo htmlspecialchars($panelistName); ?>" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring focus:ring-blue-500/20" readonly />
                    </div>
                </section>
                <input type="hidden" name="applicant_id" value="<?php echo htmlspecialchars((string)$applicantId); ?>" />

                <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-6">
                    <p class="text-sm font-semibold uppercase text-slate-600">Direction</p>
                    <p class="mt-2 text-sm text-slate-600">
                        Please evaluate the applicant based on the criteria below. Use the rating scale provided.
                    </p>
                    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">Rating</th>
                                    <th class="px-4 py-3 font-semibold">Description</th>
                                    <th class="px-4 py-3 font-semibold">Interpretation</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-700">5</td>
                                    <td class="px-4 py-3 text-slate-700">Excellent</td>
                                    <td class="px-4 py-3 text-slate-600">Outstanding performance; far exceeds expectations.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-700">4</td>
                                    <td class="px-4 py-3 text-slate-700">Very Good</td>
                                    <td class="px-4 py-3 text-slate-600">Above average; exceeds expectations.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-700">3</td>
                                    <td class="px-4 py-3 text-slate-700">Good</td>
                                    <td class="px-4 py-3 text-slate-600">Meets expectations satisfactorily.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-700">2</td>
                                    <td class="px-4 py-3 text-slate-700">Fair</td>
                                    <td class="px-4 py-3 text-slate-600">Below expectations; needs improvement.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-700">1</td>
                                    <td class="px-4 py-3 text-slate-700">Poor</td>
                                    <td class="px-4 py-3 text-slate-600">Far below expectations.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="space-y-6">
                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-6 py-4 font-semibold">Criteria</th>
                                    <th class="px-4 py-4 font-semibold text-center">5</th>
                                    <th class="px-4 py-4 font-semibold text-center">4</th>
                                    <th class="px-4 py-4 font-semibold text-center">3</th>
                                    <th class="px-4 py-4 font-semibold text-center">2</th>
                                    <th class="px-4 py-4 font-semibold text-center">1</th>
                                    <th class="px-6 py-4 font-semibold">Interviewer&apos;s Comment(s)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr class="bg-slate-50/70">
                                    <td class="px-6 py-3 font-semibold text-slate-700">A. Academic Qualifications</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-6 py-3"></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">1. Academic Performance</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[1]" value="5" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[1]" value="4" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[1]" value="3" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[1]" value="2" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[1]" value="1" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[1]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr class="bg-slate-50/70">
                                    <td class="px-6 py-3 font-semibold text-slate-700">B. Skills and Competencies</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-6 py-3"></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">2. Related Work Experience</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[2]" value="5" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[2]" value="4" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[2]" value="3" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[2]" value="2" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[2]" value="1" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[2]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">3. Computer Skills</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[3]" value="5" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[3]" value="4" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[3]" value="3" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[3]" value="2" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[3]" value="1" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[3]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">4. Communication Skills</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[4]" value="5" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[4]" value="4" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[4]" value="3" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[4]" value="2" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[4]" value="1" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[4]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">5. Multitasking Abilities</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[5]" value="5" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[5]" value="4" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[5]" value="3" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[5]" value="2" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[5]" value="1" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[5]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">6. Time Management</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[6]" value="5" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[6]" value="4" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[6]" value="3" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[6]" value="2" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[6]" value="1" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[6]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">7. Interpersonal Skills</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[7]" value="5" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[7]" value="4" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[7]" value="3" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[7]" value="2" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[7]" value="1" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[7]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">8. Stress Tolerance</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[8]" value="5" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[8]" value="4" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[8]" value="3" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[8]" value="2" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[8]" value="1" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[8]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr class="bg-slate-50/70">
                                    <td class="px-6 py-3 font-semibold text-slate-700">C. Work Behavior and Personal Qualities</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-6 py-3"></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">9. Work Ethics and Reliability</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[9]" value="5" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[9]" value="4" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[9]" value="3" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[9]" value="2" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[9]" value="1" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[9]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">10. Initiative</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[10]" value="5" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[10]" value="4" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[10]" value="3" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[10]" value="2" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[10]" value="1" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[10]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">11. Integrity and Cooperation</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[11]" value="5" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[11]" value="4" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[11]" value="3" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[11]" value="2" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[11]" value="1" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[11]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">12. Attitude Towards the Position</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[12]" value="5" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[12]" value="4" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[12]" value="3" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[12]" value="2" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[12]" value="1" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[12]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr class="bg-slate-50/70">
                                    <td class="px-6 py-3 font-semibold text-slate-700">Total Points Earned</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-6 py-3">
                                        <input id="total-points" type="number" name="total_points" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-blue-900" placeholder="0" value="0" readonly />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="space-y-6">
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Overall Assessment</p>
                        <p class="mt-3 text-sm font-semibold text-slate-700">General Impression of the Applicant:</p>
                        <div class="mt-3 grid gap-2 text-sm text-slate-700 md:grid-cols-2">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="overall_assessment" value="highly_recommended" class="accent-blue-600" />
                                Highly Recommended
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="overall_assessment" value="recommended" class="accent-blue-600" />
                                Recommended
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="overall_assessment" value="recommended_with_reservations" class="accent-blue-600" />
                                Recommended with Reservations
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="overall_assessment" value="not_recommended" class="accent-blue-600" />
                                Not Recommended
                            </label>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700" for="strengths">Strengths</label>
                            <input id="strengths" name="strengths" type="text" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 focus:border-blue-500 focus:outline-none focus:ring focus:ring-blue-500/20" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700" for="improvements">Areas for Improvement</label>
                            <input id="improvements" name="areas_for_improvement" type="text" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 focus:border-blue-500 focus:outline-none focus:ring focus:ring-blue-500/20" />
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div></div>
                        <div class="space-y-2 md:justify-self-end">
                            <label class="text-sm font-semibold text-slate-700" for="signature-pad">Interviewer&apos;s Signature</label>
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                <canvas id="signature-pad" class="h-36 w-full rounded-lg border border-dashed border-slate-300"></canvas>
                                <input type="hidden" id="signature-data" name="signature_data" />
                                <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                                    <span>Draw signature above</span>
                                    <button type="button" id="signature-clear" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:border-blue-400 hover:text-blue-600">
                                        Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <div class="flex flex-col gap-3 border-t border-slate-200 pt-8 md:flex-row md:items-center md:justify-between">
                    <p class="text-xs text-slate-500">Finalize the evaluation by verifying that all sections are complete and ratings align with the applicant&apos;s demonstrated competencies.</p>
                    <button type="submit" class="rounded-full bg-gradient-to-r from-blue-700 to-blue-500 px-6 py-3 text-sm font-semibold uppercase tracking-wide text-white shadow-lg shadow-blue-500/30 transition hover:from-blue-800 hover:to-blue-600 focus:outline-none focus:ring focus:ring-blue-400/40 disabled:cursor-not-allowed disabled:opacity-60" <?php echo $loadError !== "" ? "disabled" : ""; ?>>
                        Submit Evaluation
                    </button>
                </div>
            </form>
        </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        const evaluationToastMessage = <?php echo json_encode($save_success ?: $save_error, JSON_UNESCAPED_SLASHES); ?>;
        const evaluationToastType = <?php echo json_encode($save_success ? "success" : ($save_error ? "error" : ""), JSON_UNESCAPED_SLASHES); ?>;

        function showEvaluationToast() {
            if (!evaluationToastMessage) {
                return;
            }

            if (typeof Swal === "undefined") {
                window.alert(evaluationToastMessage);
                return;
            }

            Swal.fire({
                toast: true,
                position: "top-end",
                showConfirmButton: false,
                icon: evaluationToastType === "error" ? "error" : "success",
                title: evaluationToastMessage,
                timer: evaluationToastType === "error" ? 4200 : 3200,
                timerProgressBar: true,
                background: evaluationToastType === "error" ? "#fef2f2" : "#f0fdf4",
                color: evaluationToastType === "error" ? "#991b1b" : "#166534",
            });
        }

        (function () {
            showEvaluationToast();

            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggle');
            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('-translate-x-full');
                });
            }

            const totalInput = document.getElementById('total-points');
            const ratingInputs = Array.from(
                document.querySelectorAll('input[type="radio"][name^="rating["]')
            );

            const updateTotal = () => {
                if (!totalInput) return;
                let total = 0;
                ratingInputs.forEach((input) => {
                    if (!input.checked) return;
                    const value = parseInt(input.value, 10);
                    if (!Number.isNaN(value)) {
                        total += value;
                    }
                });
                totalInput.value = total;
            };

            ratingInputs.forEach((input) => {
                input.addEventListener('change', updateTotal);
            });
            updateTotal();

            const canvas = document.getElementById('signature-pad');
            const clearBtn = document.getElementById('signature-clear');
            const signatureInput = document.getElementById('signature-data');
            if (!canvas || !clearBtn || !signatureInput) return;

            const ctx = canvas.getContext('2d');
            const scaleCanvas = () => {
                const ratio = window.devicePixelRatio || 1;
                const rect = canvas.getBoundingClientRect();
                canvas.width = rect.width * ratio;
                canvas.height = rect.height * ratio;
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.scale(ratio, ratio);
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#0f172a';
            };

            let drawing = false;

            const getPoint = (event) => {
                const rect = canvas.getBoundingClientRect();
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                return { x: clientX - rect.left, y: clientY - rect.top };
            };

            const startDraw = (event) => {
                drawing = true;
                const { x, y } = getPoint(event);
                ctx.beginPath();
                ctx.moveTo(x, y);
                event.preventDefault();
            };

            const draw = (event) => {
                if (!drawing) return;
                const { x, y } = getPoint(event);
                ctx.lineTo(x, y);
                ctx.stroke();
                event.preventDefault();
            };

            const endDraw = () => {
                if (!drawing) return;
                drawing = false;
                signatureInput.value = canvas.toDataURL('image/png');
            };

            scaleCanvas();
            window.addEventListener('resize', scaleCanvas);

            canvas.addEventListener('mousedown', startDraw);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', endDraw);
            canvas.addEventListener('mouseleave', endDraw);

            canvas.addEventListener('touchstart', startDraw, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            canvas.addEventListener('touchend', endDraw);

            clearBtn.addEventListener('click', () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                signatureInput.value = '';
            });
        })();
    </script>
</body>
</html>
