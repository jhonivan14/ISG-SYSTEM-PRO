<?php
session_start();
if (empty($_SESSION["panelist_username"])) {
    header("Location: panelLogin.php");
    exit;
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once __DIR__ . '/../db.php';

$panelistUsername = trim((string)($_SESSION["panelist_username"] ?? ""));
$panelistName = trim((string)($_SESSION["panelist_name"] ?? ""));
if ($panelistName === "") {
    $panelistName = $panelistUsername !== "" ? $panelistUsername : "Panelist";
}

$applicantId = (int)($_GET["applicant_id"] ?? 0);
$loadError = "";
$evaluation = null;
$itemsByCriterion = [];

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

if ($panelistUsername === "") {
    $loadError = "Panelist account not found in session.";
} elseif ($applicantId <= 0) {
    $loadError = "Applicant not found.";
} else {
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

    if ($loadError === "") {
        $stmt = $conn->prepare(
            "SELECT id, applicant_name, interview_date, interviewer_name, total_points, overall_assessment,
                    strengths, areas_for_improvement, signature_data
             FROM interview_evaluations
             WHERE applicant_id = ? AND interviewer_name = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param("is", $applicantId, $panelistName);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $evaluation = $row;
                }
                $result->free();
            }
            $stmt->close();
        }

        if (!$evaluation) {
            $loadError = "No evaluation found yet.";
        } else {
            $itemsStmt = $conn->prepare(
                "SELECT criterion_id, rating, comment
                 FROM interview_evaluation_items
                 WHERE evaluation_id = ?
                 ORDER BY criterion_id ASC"
            );
            if ($itemsStmt) {
                $itemsStmt->bind_param("i", $evaluation["id"]);
                if ($itemsStmt->execute()) {
                    $itemsResult = $itemsStmt->get_result();
                    while ($item = $itemsResult->fetch_assoc()) {
                        $criterionId = (int)($item["criterion_id"] ?? 0);
                        if ($criterionId > 0) {
                            $itemsByCriterion[$criterionId] = [
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
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
        rel="stylesheet"
    />
    <style>
        .app-shell {
            min-height: 100vh;
        }
        .sidebar {
            width: 224px;
            height: 100vh;
            background: #052c6a;
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 30;
            overflow-y: auto;
        }
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 20;
            background: #052c6a;
            color: #fff;
            padding: 8px 12px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            font-size: 12px;
            gap: 8px;
        }
        .topbar-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .topbar-pill {
            background: #fcdc2f;
            color: #052c6a;
            border: 0;
            border-radius: 4px;
            padding: 4px 10px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            font-family: "Segoe UI", sans-serif;
        }
        .main-content {
            margin-left: 224px;
            padding-top: 44px;
        }
        .panel-nav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 150ms ease, color 150ms ease;
        }
        .panel-nav-item:hover {
            background: #0d8ddb;
        }
        .panel-nav-item.active {
            background: #fcdc2f;
            color: #052c6a;
        }
        @media (min-width: 768px) {
            .topbar {
                left: 224px;
            }
        }
        @page {
            size: 8.5in 13in portrait;
            margin: 0.25in 0.49in 0in 0.49in;
        }
        body {
            margin: 0;
            background: #e9eef5;
            font-family: "Segoe UI", sans-serif;
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
        }
        .no-print {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 8px;
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
            margin-top: 10px;
            width: 100%;
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
            margin: 0 0 0 calc(-1 * var(--footer-left-offset));
        }
        .error {
            max-width: 720px;
            margin: 60px auto;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #9f1239;
            padding: 16px 18px;
            border-radius: 12px;
            font-family: "Segoe UI", sans-serif;
            text-align: center;
        }
        @media print {
            html,
            body {
                width: 8.5in;
                height: 13in;
            }
            .sidebar,
            .topbar {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding-top: 0 !important;
            }
            body {
                background: #fff;
            }
            .page {
                padding: 0;
            }
            .paper {
                border: none;
                box-shadow: none;
                padding: 0;
                margin: 0 auto;
                width: 100%;
                max-width: none;
                transform: none;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <aside id="sidebar" class="sidebar">
            <div style="display:flex;align-items:center;gap:12px;padding:16px;border-bottom:1px solid #0d8ddb;">
                <img src="../img/SMCCNEWLOGO.png" alt="SMCC Logo" style="width:64px;height:64px;border-radius:999px;object-fit:cover;" />
                <span style="font-size:14px;">Admission and Scholarship Office</span>
            </div>
            <nav style="padding-bottom:112px;">
                <div class="panel-nav-item" onclick="window.location.href='panelistDashboard.php'">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </div>
                <div class="panel-nav-item" onclick="window.location.href='panelistDashboard.php?tab=pending'">
                    <i class="fas fa-user-clock"></i>
                    <span>Pending Applicants</span>
                </div>
                <div class="panel-nav-item active" onclick="window.location.href='panelistDashboard.php?tab=evaluated'">
                    <i class="fas fa-check-circle"></i>
                    <span>Show Evaluated</span>
                </div>
                <div class="panel-nav-item" onclick="window.location.href='change-password.php'">
                    <i class="fas fa-key"></i>
                    <span>Change Password</span>
                </div>
            </nav>
            <div style="position:absolute;left:0;bottom:0;width:100%;">
                <div style="height:1px;width:100%;background:linear-gradient(to right, transparent, #0d8ddb, transparent);opacity:0.6;"></div>
                <div style="padding:8px 16px 4px;display:flex;align-items:center;gap:8px;font-size:11px;color:#dbeafe;">
                    <div style="width:28px;height:28px;border-radius:999px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-user-tie" style="font-size:12px;"></i>
                    </div>
                    <div style="line-height:1.2;min-width:0;">
                        <p style="margin:0;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($panelistName) ?></p>
                        <p style="margin:0;font-size:10px;color:#bfdbfe;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($panelistUsername !== "" ? $panelistUsername : "panelist") ?></p>
                    </div>
                </div>
                <div style="padding:4px 12px 12px;">
                    <button
                        type="button"
                        onclick="window.location.href='../logout.php'"
                        style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;font-size:11px;font-weight:600;background:linear-gradient(to right,#ef4444,#dc2626);color:#fff;padding:8px 12px;border-radius:999px;border:0;cursor:pointer;"
                    >
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </button>
                </div>
            </div>
        </aside>

        <header class="topbar">
            <div class="topbar-actions">
                <button type="button" class="topbar-pill">
                    <i class="fas fa-user"></i>
                    Panelist View
                </button>
                <button type="button" class="topbar-pill">
                    <?= htmlspecialchars($panelistName) ?>
                </button>
            </div>
        </header>

        <main class="main-content">
<?php if ($loadError !== ""): ?>
    <div class="error"><?php echo htmlspecialchars($loadError); ?></div>
<?php else: ?>
    <div class="page">
        <div class="paper">
            <div class="no-print">
                <button class="btn" type="button" onclick="window.print()">Print</button>
            </div>

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
    </div>
<?php endif; ?>
        </main>
    </div>
</body>
</html>
