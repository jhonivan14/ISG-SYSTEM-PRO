<?php
require_once __DIR__ . "/panelist-auth.php";
require_once __DIR__ . "/../Admin/includes/admin-auth.php";
$isAdminPreviewRequested = trim((string)($_GET["admin_preview"] ?? "")) === "1";
$isAdminPreview = $isAdminPreviewRequested && adminIsAuthenticated();
if (!$isAdminPreview) {
    panelistRequireLogin();
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once __DIR__ . '/../db.php';

$panelistUsername = $isAdminPreview ? "" : trim((string)($_SESSION["panelist_username"] ?? ""));
$panelistName = $isAdminPreview ? "Admin Preview" : trim((string)($_SESSION["panelist_name"] ?? ""));
if ($panelistName === "") {
    $panelistName = $panelistUsername !== "" ? $panelistUsername : ($isAdminPreview ? "Admin Preview" : "Panelist");
}

$applicantId = (int)($_GET["applicant_id"] ?? 0);
$loadError = "";
$evaluations = [];
$itemsByEvaluationId = [];

$criteria = [
    1 => "Academic Performance",
    2 => "Related Work Experience",
    3 => "Computer Skills",
    4 => "Communication Skills",
    5 => "Multitasking Abilities",
    6 => "Time Management",
    7 => "Interpersonal Skills",
    8 => "Stress Tolerance",
    9 => "Work Ethics and Reliability",
    10 => "Initiative",
    11 => "Integrity and Cooperation",
    12 => "Attitude Towards the Position",
];

$assessmentLabels = [
    "highly_recommended" => "Highly Recommended",
    "recommended" => "Recommended",
    "recommended_with_reservations" => "Recommended with Reservations",
    "not_recommended" => "Not Recommended",
];

$sections = [
    "A. Academic Qualifications" => [1],
    "B. Skills and Competencies" => [2, 3, 4, 5, 6, 7, 8],
    "C. Work Behavior and Personal Qualities" => [9, 10, 11, 12],
];

if (!$isAdminPreview && $panelistUsername === "") {
    $loadError = "Panelist account not found in session.";
} elseif ($applicantId <= 0) {
    $loadError = "Applicant not found.";
} else {
    if (!$isAdminPreview) {
        $assignStmt = $conn->prepare(
            "SELECT 1 FROM panelist_queue WHERE application_id = ? AND panelist_username = ? LIMIT 1"
        );
        if ($assignStmt) {
            $assignStmt->bind_param("is", $applicantId, $panelistUsername);
            if ($assignStmt->execute()) {
                $assignStmt->store_result();
                if ($assignStmt->num_rows === 0) {
                    $loadError = "Applicant is not assigned to this panelist.";
                }
            }
            $assignStmt->close();
        }
    }

    if ($loadError === "") {
        $evaluationSql = $isAdminPreview
            ? "SELECT ie.id, ie.applicant_name, ie.interview_date, ie.interviewer_name, ie.total_points, ie.overall_assessment,
                    ie.strengths, ie.areas_for_improvement, ie.signature_data
               FROM interview_evaluations ie
               INNER JOIN (
                   SELECT interviewer_name, MAX(id) AS latest_id
                   FROM interview_evaluations
                   WHERE applicant_id = ?
                   GROUP BY interviewer_name
               ) latest ON latest.latest_id = ie.id
               WHERE ie.applicant_id = ?
               ORDER BY ie.interviewer_name ASC, ie.id DESC"
            : "SELECT id, applicant_name, interview_date, interviewer_name, total_points, overall_assessment,
                    strengths, areas_for_improvement, signature_data
               FROM interview_evaluations
               WHERE applicant_id = ? AND interviewer_name = ?
               ORDER BY id DESC
               LIMIT 1";
        $stmt = $conn->prepare($evaluationSql);
        if ($stmt) {
            if ($isAdminPreview) {
                $stmt->bind_param("ii", $applicantId, $applicantId);
            } else {
                $stmt->bind_param("is", $applicantId, $panelistName);
            }
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $evaluations[] = $row;
                }
                $result->free();
            }
            $stmt->close();
        }

        if (empty($evaluations)) {
            $loadError = "No evaluation found yet.";
        } else {
            $evaluationIds = [];
            foreach ($evaluations as $evaluationRow) {
                $evaluationId = (int)($evaluationRow["id"] ?? 0);
                if ($evaluationId <= 0) {
                    continue;
                }

                $evaluationIds[] = $evaluationId;
                $itemsByEvaluationId[$evaluationId] = [];
            }

            if (!empty($evaluationIds)) {
                $itemPlaceholders = implode(", ", array_fill(0, count($evaluationIds), "?"));
                $itemTypes = str_repeat("i", count($evaluationIds));
                $itemsStmt = $conn->prepare(
                    "SELECT evaluation_id, criterion_id, rating, comment
                     FROM interview_evaluation_items
                     WHERE evaluation_id IN ({$itemPlaceholders})
                     ORDER BY evaluation_id ASC, criterion_id ASC"
                );
            } else {
                $itemsStmt = false;
            }

            if ($itemsStmt) {
                $itemsStmt->bind_param($itemTypes, ...$evaluationIds);
                if ($itemsStmt->execute()) {
                    $itemsResult = $itemsStmt->get_result();
                    while ($item = $itemsResult->fetch_assoc()) {
                        $evaluationId = (int)($item["evaluation_id"] ?? 0);
                        $criterionId = (int)($item["criterion_id"] ?? 0);
                        if ($evaluationId > 0 && $criterionId > 0) {
                            $itemsByEvaluationId[$evaluationId][$criterionId] = [
                                "rating" => (int)($item["rating"] ?? 0),
                                "comment" => (string)($item["comment"] ?? ""),
                            ];
                        }
                    }
                    $itemsResult->free();
                }
                $itemsStmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Interview Evaluation Sheet</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    />
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
        rel="stylesheet"
    />
    <style>
        .app-shell {
            min-height: 100vh;
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
            display: flex;
            align-items: center;
            gap: 8px;
            padding-left: 0.75rem;
            padding-right: 0.75rem;
            min-height: 2.5rem;
            cursor: pointer;
            border-radius: 0.85rem;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            transition: background-color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
        }
        .panel-nav-item:hover {
            transform: translateX(2px);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.16);
        }
        .panel-nav-item.active {
            background: rgba(252, 220, 47, 0.95);
            color: #052c6a;
            box-shadow: 0 8px 20px rgba(252, 220, 47, 0.25);
        }
        .sidebar,
        .topbar {
            font: sans-serif;
        }
        @page {
            size: 8.5in 13in portrait;
            margin: 0.18in 0.32in 0.1in 0.32in;
        }
        body {
            margin: 0;
            background: #e9eef5;
            font: sans-serif;
            color: #0f172a;
        }
        *, *::before, *::after {
            box-sizing: border-box;
        }
        .page {
            min-height: 100vh;
            padding: 14px 12px 24px;
        }
        .paper {
            max-width: 860px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #0f172a;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
            padding: 12px 16px 14px;
            break-inside: avoid-page;
            page-break-inside: avoid;
        }
        .paper + .paper {
            margin-top: 18px;
        }
        .no-print {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 8px;
        }
        .compiled-meta {
            margin-bottom: 10px;
            font-size: 11px;
            font-weight: 600;
            color: #334155;
        }
        .compiled-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #0f172a;
        }
        body.admin-preview .sidebar,
        body.admin-preview .topbar {
            display: none !important;
        }
        body.admin-preview .main-content {
            margin-left: 0 !important;
            padding-top: 0 !important;
            min-height: auto !important;
        }
        body.admin-preview .page {
            padding: 12px;
            min-height: auto;
        }
        body.admin-preview .paper {
            max-width: none;
            margin: 0;
        }
        .btn {
            border: 1px solid #0f172a;
            background: #fff;
            color: #0f172a;
            font-size: 11px;
            padding: 6px 12px;
            border-radius: 999px;
            cursor: pointer;
        }
        .btn:hover {
            background: #f1f5f9;
        }
        .btn-back {
            background: #052c6a;
            color: #fff;
            border-color: #052c6a;
        }
        .btn-back:hover {
            background: #0d8ddb;
            border-color: #0d8ddb;
        }
        .header {
            display: grid;
            grid-template-columns: 118px 1fr 118px;
            align-items: center;
            column-gap: 4px;
            border-bottom: 2px solid #0f172a;
            min-height: 72px;
            padding: 4px 0 6px;
        }
        .logo-group {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .logo-left {
            justify-content: flex-end;
        }
        .logo-right {
            justify-content: flex-start;
        }
        .logo-group img {
            width: 56px;
            height: 56px;
            object-fit: contain;
        }
        .header-text {
            text-align: center;
            line-height: 1.2;
            width: 100%;
        }
        .school-name {
            font-weight: 700;
            font-size: 12pt;
        }
        .school-sub {
            font-size: 8.5pt;
        }
        .office {
            font-size: 9pt;
            margin-top: 4px;
            font-weight: 700;
        }
        .sheet-title {
            text-align: center;
            margin: 8px 0 6px;
        }
        .sheet-name {
            font-weight: 700;
            font-size: 12pt;
        }
        .sheet-sub {
            font-size: 9pt;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-bottom: 4px;
        }
        .info-table td {
            padding: 2px 4px;
            vertical-align: bottom;
        }
        .info-table .label {
            width: 140px;
            font-weight: 700;
        }
        .info-table .line {
            border-bottom: 1px solid #0f172a;
            height: 14px;
        }
        .direction {
            font-size: 8.8pt;
            margin: 4px 0 4px;
        }
        .scale-table,
        .criteria-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.7pt;
        }
        .scale-table th,
        .scale-table td,
        .criteria-table th,
        .criteria-table td {
            border: 1px solid #0f172a;
            padding: 3px 4px;
            text-align: center;
        }
        .scale-table th {
            font-weight: 700;
        }
        .scale-table td:nth-child(2),
        .scale-table td:last-child {
            text-align: left;
            padding-left: 8px;
        }
        .criteria-table th:first-child,
        .criteria-table td:first-child {
            text-align: left;
        }
        .criteria-table .section-row td {
            font-weight: 700;
        }
        .criteria-table .comment-cell {
            text-align: left;
        }
        .total-row td {
            font-weight: 700;
            text-align: right;
        }
        .total-row td:last-child {
            text-align: left;
        }
        .assessment {
            margin-top: 6px;
            font-size: 9pt;
        }
        .assessment-title {
            font-weight: 700;
            margin-bottom: 2px;
        }
        .assessment-list {
            display: grid;
            gap: 1px;
            margin-top: 1px;
        }
        .assessment-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .check-box {
            width: 10px;
            height: 10px;
            border: 1px solid #0f172a;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 9pt;
            line-height: 1;
        }
        .note-line {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            margin-top: 4px;
            font-size: 9pt;
        }
        .note-line .label {
            font-weight: 700;
            min-width: 120px;
        }
        .note-line .line {
            flex: 1;
            border-bottom: 1px solid #0f172a;
            min-height: 14px;
        }
        .signature-block {
            margin-top: 8px;
            display: flex;
            justify-content: flex-end;
        }
        .signature-box {
            width: 240px;
            text-align: center;
            font-size: 9pt;
        }
        .signature-line {
            border-top: 1px solid #0f172a;
            margin-top: 6px;
            padding-top: 4px;
        }
        .signature-img {
            max-height: 44px;
            display: block;
            margin: 0 auto;
        }
        .signature-block,
        .assessment,
        .note-line {
            page-break-inside: avoid;
        }
        .sheet-footer {
            margin-top: 4px;
            width: 100%;
            display: flex;
            align-items: flex-start;
            text-align: left;
            line-height: 0;
            overflow: hidden;
            --footer-left-offset: 34px;
            page-break-inside: avoid;
        }
        .sheet-footer img {
            width: calc(100% + var(--footer-left-offset));
            height: auto;
            display: block;
            object-fit: contain;
            margin: -1px 0 0 calc(-1 * var(--footer-left-offset));
        }
        .error {
            max-width: 720px;
            margin: 60px auto;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #9f1239;
            padding: 16px 18px;
            border-radius: 12px;
            font-family: "Space Grotesk", sans-serif;
            text-align: center;
        }
        @media print {
            html,
            body {
                width: auto !important;
                height: auto !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
            }
            body {
                background: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .app-shell,
            .main-content,
            .page {
                background: #fff !important;
                min-height: 0 !important;
                height: auto !important;
                overflow: visible !important;
            }
            .app-shell,
            .main-content {
                display: block !important;
            }
            .sidebar,
            .topbar {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding-top: 0 !important;
            }
            .page {
                padding: 0;
            }
            .paper {
                background: #fff !important;
                border: none;
                box-shadow: none;
                padding: 0 0.03in;
                margin: 0 auto;
                width: 100%;
                max-width: none;
                transform: none;
                break-inside: avoid-page;
                page-break-inside: avoid;
                break-after: page;
                page-break-after: always;
            }
            .paper + .paper {
                margin-top: 0;
                break-before: page;
                page-break-before: always;
            }
            .paper:last-child {
                break-after: auto;
                page-break-after: auto;
            }
            .no-print {
                display: none !important;
            }
            .header {
                grid-template-columns: 100px 1fr 100px;
                min-height: 62px;
                padding: 2px 0 4px;
            }
            .logo-group img {
                width: 48px;
                height: 48px;
            }
            .school-name,
            .sheet-name {
                font-size: 10.5pt;
            }
            .school-sub,
            .sheet-sub,
            .info-table,
            .assessment,
            .note-line {
                font-size: 8pt;
            }
            .office,
            .direction,
            .scale-table,
            .criteria-table,
            .signature-box {
                font-size: 7.6pt;
            }
            .sheet-title {
                margin: 6px 0 4px;
            }
            .info-table {
                margin-bottom: 2px;
            }
            .info-table td {
                padding: 1px 3px;
            }
            .info-table .line {
                height: 12px;
            }
            .direction {
                margin: 2px 0 3px;
            }
            .scale-table th,
            .scale-table td,
            .criteria-table th,
            .criteria-table td {
                padding: 2px 3px;
            }
            .assessment {
                margin-top: 4px;
            }
            .note-line {
                margin-top: 3px;
            }
            .signature-block {
                margin-top: 6px;
            }
            .signature-img {
                max-height: 36px;
            }
            .sheet-footer {
                margin-top: 2px;
                --footer-left-offset: 22px;
            }
        }
    </style>
</head>
<body class="<?php echo $isAdminPreview ? 'admin-preview' : ''; ?>">
    <div class="app-shell">
        <aside
            id="sidebar"
            class="sidebar flex flex-col bg-gradient-to-b from-[#031f4f] via-[#0a4b86] to-[#0f9ad8] text-white w-64 h-screen fixed left-0 top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out overflow-y-auto shadow-[12px_0_28px_-12px_rgba(4,31,79,0.65)]"
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
                    <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='panelistDashboard.php?tab=pending'">
                        <i class="fas fa-user-clock w-5"></i>
                        <span>Pending Applicants</span>
                    </li>
                    <li class="panel-nav-item active gap-2 cursor-pointer" onclick="window.location.href='panelistDashboard.php?tab=evaluated'">
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
                            type="button"
                            onclick="window.location.href='../logout.php'"
                            class="w-full flex items-center justify-center gap-2 text-[11px] font-semibold bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 px-3 py-2 rounded-full shadow-md hover:shadow-lg transition-all duration-150"
                        >
                            <i class="fas fa-sign-out-alt text-xs"></i>
                            <span>Logout</span>
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <header
            class="topbar fixed top-0 left-0 md:left-64 right-0 z-20 h-14 flex items-center bg-white border-b border-slate-200 px-4 sm:px-6 shadow-sm"
        >
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    id="sidebarToggle"
                    class="md:hidden inline-flex items-center justify-center p-2 rounded bg-slate-700 text-white hover:bg-slate-800 focus:outline-none transition-colors"
                    aria-label="Toggle sidebar"
                >
                    <i class="fas fa-bars"></i>
                </button>
                <h2 class="text-[#0d4b84] text-lg font-semibold flex items-center gap-2">
                    <i class="fas fa-file-alt"></i>
                    Evaluation View
                </h2>
            </div>
        </header>

        <main class="main-content ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pt-14">
<?php if ($loadError !== ""): ?>
    <div class="error"><?php echo htmlspecialchars($loadError); ?></div>
<?php else: ?>
    <div class="page">
        <?php foreach ($evaluations as $evaluationIndex => $evaluation): ?>
            <?php
                $currentEvaluationId = (int)($evaluation["id"] ?? 0);
                $itemsByCriterion = $itemsByEvaluationId[$currentEvaluationId] ?? [];
                $totalEvaluations = count($evaluations);
            ?>
            <div class="paper">
                <?php if (!$isAdminPreview): ?>
                    <div class="no-print">
                        <button class="btn" type="button" onclick="window.print()">Print</button>
                    </div>
                <?php elseif ($totalEvaluations > 1): ?>
                    <div class="compiled-meta no-print">
                        <span>
                            <i class="fas fa-layer-group"></i>
                            Evaluation <?php echo htmlspecialchars((string)($evaluationIndex + 1)); ?> of <?php echo htmlspecialchars((string)$totalEvaluations); ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="header">
                    <div class="logo-group logo-left">
                        <img src="../img/SMCCNEWLOGO.png" alt="SMCC Logo" />
                        <img src="../img/admission-logo.jpg" alt="Admission Logo" />
                    </div>
                    <div class="header-text">
                        <div class="school-name">SAINT MICHAEL COLLEGE OF CARAGA</div>
                        <div class="school-sub">Atupan St., Brgy. 4, Nasipit, Agusan del Norte 8602, Philippines</div>
                        <div class="school-sub">Website: www.smccnasipit.edu.ph | Tel. Nos. 085 300-2932</div>
                        <div class="office">Office of the Admission &amp; Scholarship</div>
                    </div>
                    <div class="logo-group logo-right">
                        <img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC Logo" />
                    </div>
                </div>

                <div class="sheet-title">
                    <div class="sheet-name">INTERVIEW EVALUATION SHEET</div>
                    <div class="sheet-sub">Student Assistant Scholarship Program</div>
                </div>

                <table class="info-table">
                    <tr>
                        <td class="label">Applicant&apos;s Name</td>
                        <td class="line"><?php echo htmlspecialchars((string)($evaluation["applicant_name"] ?? "")); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Date of Interview</td>
                        <td class="line"><?php echo htmlspecialchars((string)($evaluation["interview_date"] ?? "")); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Interviewer&apos;s Name</td>
                        <td class="line"><?php echo htmlspecialchars((string)($evaluation["interviewer_name"] ?? "")); ?></td>
                    </tr>
                </table>

                <div class="direction">
                    <strong>Direction:</strong> Please evaluate the applicant based on the criteria below. Use the rating scale provided.
                </div>

                <table class="scale-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Rating</th>
                            <th style="width: 160px;">Description</th>
                            <th>Interpretation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>5</td>
                            <td>Excellent</td>
                            <td>Outstanding performance; far exceeds expectations.</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td>Very Good</td>
                            <td>Above average; exceeds expectations.</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td>Good</td>
                            <td>Meets expectations satisfactorily.</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>Fair</td>
                            <td>Below expectations; needs improvement.</td>
                        </tr>
                        <tr>
                            <td>1</td>
                            <td>Poor</td>
                            <td>Far below expectations.</td>
                        </tr>
                    </tbody>
                </table>

                <table class="criteria-table" style="margin-top: 8px;">
                    <thead>
                        <tr>
                            <th style="width: 36%;">Criteria</th>
                            <th style="width: 6%;">5</th>
                            <th style="width: 6%;">4</th>
                            <th style="width: 6%;">3</th>
                            <th style="width: 6%;">2</th>
                            <th style="width: 6%;">1</th>
                            <th>Interviewer&apos;s Comment(s)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sections as $sectionTitle => $sectionCriteria): ?>
                            <tr class="section-row">
                                <td colspan="7"><?php echo htmlspecialchars($sectionTitle); ?></td>
                            </tr>
                            <?php foreach ($sectionCriteria as $criterionId): ?>
                                <?php
                                    $item = $itemsByCriterion[$criterionId] ?? ["rating" => 0, "comment" => ""];
                                    $rating = (int)$item["rating"];
                                    $criterionLabel = $criterionId . ". " . ($criteria[$criterionId] ?? ("Criterion " . $criterionId));
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($criterionLabel); ?></td>
                                    <td><?php echo $rating === 5 ? "&#10003;" : ""; ?></td>
                                    <td><?php echo $rating === 4 ? "&#10003;" : ""; ?></td>
                                    <td><?php echo $rating === 3 ? "&#10003;" : ""; ?></td>
                                    <td><?php echo $rating === 2 ? "&#10003;" : ""; ?></td>
                                    <td><?php echo $rating === 1 ? "&#10003;" : ""; ?></td>
                                    <td class="comment-cell"><?php echo htmlspecialchars((string)$item["comment"]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="6">Total Points Earned</td>
                            <td><?php echo htmlspecialchars((string)($evaluation["total_points"] ?? "")); ?></td>
                        </tr>
                    </tbody>
                </table>

                <div class="assessment">
                    <div class="assessment-title">Overall Assessment</div>
                    <div>General Impression of the Applicant:</div>
                    <div class="assessment-list">
                        <?php
                            $assessmentKey = (string)($evaluation["overall_assessment"] ?? "");
                            $assessmentOptions = [
                                "highly_recommended" => "Highly Recommended",
                                "recommended" => "Recommended",
                                "recommended_with_reservations" => "Recommended with Reservations",
                                "not_recommended" => "Not Recommended",
                            ];
                        ?>
                        <?php foreach ($assessmentOptions as $key => $label): ?>
                            <div class="assessment-item">
                                <span class="check-box"><?php echo $assessmentKey === $key ? "&#10003;" : ""; ?></span>
                                <span><?php echo htmlspecialchars($label); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="note-line">
                    <div class="label">Strengths:</div>
                    <div class="line"><?php echo htmlspecialchars((string)($evaluation["strengths"] ?? "")); ?></div>
                </div>
                <div class="note-line">
                    <div class="label">Areas for Improvement:</div>
                    <div class="line"><?php echo htmlspecialchars((string)($evaluation["areas_for_improvement"] ?? "")); ?></div>
                </div>

                <div class="signature-block">
                    <div class="signature-box">
                        <?php if (!empty($evaluation["signature_data"])): ?>
                            <img class="signature-img" src="<?php echo htmlspecialchars((string)$evaluation["signature_data"]); ?>" alt="Signature" />
                        <?php endif; ?>
                        <div class="signature-line">Interviewer&apos;s Signature</div>
                    </div>
                </div>

                <div class="sheet-footer">
                    <img src="../img/admissionFooter.png" alt="Admission Footer" />
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
        </main>
    </div>
    <script>
        (function () {
            const sidebar = document.getElementById("sidebar");
            const toggleBtn = document.getElementById("sidebarToggle");
            if (!sidebar || !toggleBtn) {
                return;
            }

            toggleBtn.addEventListener("click", () => {
                sidebar.classList.toggle("-translate-x-full");
            });
        })();
    </script>
</body>
</html>
