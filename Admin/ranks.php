<?php
require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
$defaultBatchLabel = "All Batches";
require_once __DIR__ . "/includes/school-term-filter.php";
require_once __DIR__ . "/includes/panelist-sent-applicants.php";

$criteriaCount = 12;
$maxInterviewPoints = $criteriaCount * 5;
$rankRemarkOptions = [
  "Hired",
  "Not be hired as agreed by the panelists",
];
$rankSaveStatus = isset($_GET["rank_save"]) ? strtolower(trim((string)$_GET["rank_save"])) : "";
$rankSaveMessage = "";
$rankSaveMessageType = "";
$truncateToTwoDecimals = static function (float $value): float {
  if ($value >= 0) {
    return floor($value * 100) / 100;
  }

  return ceil($value * 100) / 100;
};
$formatScoreDisplay = static function (?float $value) use ($truncateToTwoDecimals): string {
  if ($value === null) {
    return "";
  }

  return number_format($truncateToTwoDecimals($value), 2, ".", "");
};
$buildRanksUrl = static function (string $schoolYear, string $semester, string $batch, string $saveStatus = ""): string {
  $query = [];
  if ($schoolYear !== "") {
    $query["school_year"] = $schoolYear;
  }
  if ($semester !== "") {
    $query["semester"] = $semester;
  }
  if ($batch !== "") {
    $query["batch"] = $batch;
  }
  if ($saveStatus !== "") {
    $query["rank_save"] = $saveStatus;
  }

  $queryString = http_build_query($query);
  return "ranks.php" . ($queryString !== "" ? "?" . $queryString : "");
};
$savedRankInputsByApplicantId = [];
$interviewScoresByApplicantId = [];

if (($conn ?? null) instanceof mysqli) {
  $conn->query(
    "CREATE TABLE IF NOT EXISTS applicant_rank_inputs (
      application_id INT NOT NULL PRIMARY KEY,
      exam_rating DECIMAL(5,2) DEFAULT NULL,
      grades_rating DECIMAL(5,2) DEFAULT NULL,
      remarks VARCHAR(100) DEFAULT NULL,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $remarksColumnResult = $conn->query("SHOW COLUMNS FROM applicant_rank_inputs LIKE 'remarks'");
  if ($remarksColumnResult instanceof mysqli_result) {
    $hasRemarksColumn = $remarksColumnResult->num_rows > 0;
    $remarksColumnResult->free();
    if (!$hasRemarksColumn) {
      $conn->query("ALTER TABLE applicant_rank_inputs ADD COLUMN remarks VARCHAR(100) DEFAULT NULL AFTER grades_rating");
    }
  }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($conn ?? null) instanceof mysqli) {
  $postedSchoolYear = trim((string)($_POST["school_year"] ?? $selectedSchoolYear ?? ""));
  $postedSemester = trim((string)($_POST["semester"] ?? $selectedSemester ?? ""));
  $postedBatch = trim((string)($_POST["batch"] ?? $selectedBatch ?? ""));
  $postedApplicantIds = isset($_POST["applicant_ids"]) && is_array($_POST["applicant_ids"])
    ? array_values(array_unique(array_map("intval", $_POST["applicant_ids"])))
    : [];
  $postedExamRatings = isset($_POST["exam_rating"]) && is_array($_POST["exam_rating"]) ? $_POST["exam_rating"] : [];
  $postedGradesRatings = isset($_POST["grades_rating"]) && is_array($_POST["grades_rating"]) ? $_POST["grades_rating"] : [];
  $postedRemarks = isset($_POST["remarks"]) && is_array($_POST["remarks"]) ? $_POST["remarks"] : [];
  $allowedApplicantIds = [];
  foreach ($panelistSentApplicants as $panelistSentApplicant) {
    $applicantId = (int)($panelistSentApplicant["id"] ?? 0);
    if ($applicantId > 0) {
      $allowedApplicantIds[$applicantId] = true;
    }
  }

  $saveStmt = $conn->prepare(
    "INSERT INTO applicant_rank_inputs (application_id, exam_rating, grades_rating, remarks)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       exam_rating = VALUES(exam_rating),
       grades_rating = VALUES(grades_rating),
       remarks = VALUES(remarks)"
  );

  if ($saveStmt) {
    foreach ($postedApplicantIds as $applicantId) {
      if ($applicantId <= 0 || !isset($allowedApplicantIds[$applicantId])) {
        continue;
      }

      $examValue = array_key_exists((string)$applicantId, $postedExamRatings)
        ? trim((string)$postedExamRatings[(string)$applicantId])
        : "";
      $gradesValue = array_key_exists((string)$applicantId, $postedGradesRatings)
        ? trim((string)$postedGradesRatings[(string)$applicantId])
        : "";
      $remarksValue = array_key_exists((string)$applicantId, $postedRemarks)
        ? trim((string)$postedRemarks[(string)$applicantId])
        : "";

      $examRating = ($examValue !== "" && is_numeric($examValue)) ? (float)$examValue : null;
      $gradesRating = ($gradesValue !== "" && is_numeric($gradesValue)) ? (float)$gradesValue : null;
      $remarks = in_array($remarksValue, $rankRemarkOptions, true) ? $remarksValue : "";

      if ($examRating !== null && ($examRating < 0 || $examRating > 100)) {
        $examRating = null;
      }
      if ($gradesRating !== null && ($gradesRating < 0 || $gradesRating > 100)) {
        $gradesRating = null;
      }

      $saveStmt->bind_param("idds", $applicantId, $examRating, $gradesRating, $remarks);
      $saveStmt->execute();
    }
    $saveStmt->close();

    header("Location: " . $buildRanksUrl($postedSchoolYear, $postedSemester, $postedBatch, "success"));
    exit;
  }

  header("Location: " . $buildRanksUrl($postedSchoolYear, $postedSemester, $postedBatch, "error"));
  exit;
}

if ($rankSaveStatus === "success") {
  $rankSaveMessage = "Ranking inputs saved.";
  $rankSaveMessageType = "success";
} elseif ($rankSaveStatus === "error") {
  $rankSaveMessage = "Unable to save ranking inputs.";
  $rankSaveMessageType = "error";
}

if (($conn ?? null) instanceof mysqli && !empty($panelistSentApplicants)) {
  $applicantIds = [];
  foreach ($panelistSentApplicants as $sentApplicant) {
    $applicantId = (int)($sentApplicant["id"] ?? 0);
    if ($applicantId > 0) {
      $applicantIds[] = $applicantId;
    }
  }

  $applicantIds = array_values(array_unique($applicantIds));
  if (!empty($applicantIds)) {
    $placeholders = implode(", ", array_fill(0, count($applicantIds), "?"));
    $types = str_repeat("i", count($applicantIds));

    $savedRankSql = "
      SELECT application_id, exam_rating, grades_rating, remarks
      FROM applicant_rank_inputs
      WHERE application_id IN ({$placeholders})
    ";
    $savedRankStmt = $conn->prepare($savedRankSql);
    if ($savedRankStmt) {
      $savedRankStmt->bind_param($types, ...$applicantIds);
      if ($savedRankStmt->execute()) {
        $savedRankResult = $savedRankStmt->get_result();
        while ($savedRankRow = $savedRankResult->fetch_assoc()) {
          $applicantId = (int)($savedRankRow["application_id"] ?? 0);
          if ($applicantId <= 0) {
            continue;
          }

          $savedRankInputsByApplicantId[$applicantId] = [
            "exam_rating" => is_numeric($savedRankRow["exam_rating"] ?? null) ? (float)$savedRankRow["exam_rating"] : null,
            "grades_rating" => is_numeric($savedRankRow["grades_rating"] ?? null) ? (float)$savedRankRow["grades_rating"] : null,
            "remarks" => trim((string)($savedRankRow["remarks"] ?? "")),
          ];
        }
        $savedRankResult->free();
      }
      $savedRankStmt->close();
    }

    foreach ($applicantIds as $applicantId) {
      $interviewScoresByApplicantId[$applicantId] = [
        "assigned_panel_count" => 0,
        "rated_panel_count" => 0,
        "total_points_sum" => 0.0,
        "weighted_mean" => null,
        "interview_rating" => null,
        "interview_weighted" => null,
      ];
    }

    $panelAssignmentSql = "
      SELECT application_id, COUNT(DISTINCT panelist_username) AS assigned_panel_count
      FROM panelist_queue
      WHERE application_id IN ({$placeholders})
      GROUP BY application_id
    ";
    $panelAssignmentStmt = $conn->prepare($panelAssignmentSql);
    if ($panelAssignmentStmt) {
      $panelAssignmentStmt->bind_param($types, ...$applicantIds);
      if ($panelAssignmentStmt->execute()) {
        $panelAssignmentResult = $panelAssignmentStmt->get_result();
        while ($panelAssignmentRow = $panelAssignmentResult->fetch_assoc()) {
          $applicantId = (int)($panelAssignmentRow["application_id"] ?? 0);
          if ($applicantId <= 0 || !isset($interviewScoresByApplicantId[$applicantId])) {
            continue;
          }

          $interviewScoresByApplicantId[$applicantId]["assigned_panel_count"] = (int)($panelAssignmentRow["assigned_panel_count"] ?? 0);
        }
        $panelAssignmentResult->free();
      }
      $panelAssignmentStmt->close();
    }

    $evaluationSql = "
      SELECT ie.applicant_id, ie.interviewer_name, ie.total_points
      FROM interview_evaluations ie
      INNER JOIN (
        SELECT applicant_id, interviewer_name, MAX(id) AS latest_id
        FROM interview_evaluations
        WHERE applicant_id IN ({$placeholders})
        GROUP BY applicant_id, interviewer_name
      ) latest ON latest.latest_id = ie.id
      ORDER BY ie.applicant_id ASC, ie.interviewer_name ASC
    ";
    $evaluationStmt = $conn->prepare($evaluationSql);
    if ($evaluationStmt) {
      $evaluationStmt->bind_param($types, ...$applicantIds);
      if ($evaluationStmt->execute()) {
        $evaluationResult = $evaluationStmt->get_result();
        while ($evaluationRow = $evaluationResult->fetch_assoc()) {
          $applicantId = (int)($evaluationRow["applicant_id"] ?? 0);
          if ($applicantId <= 0 || !isset($interviewScoresByApplicantId[$applicantId])) {
            continue;
          }

          $totalPoints = is_numeric($evaluationRow["total_points"] ?? null)
            ? (float)$evaluationRow["total_points"]
            : 0.0;
          if ($totalPoints < 0) {
            $totalPoints = 0.0;
          }

          $interviewScoresByApplicantId[$applicantId]["rated_panel_count"]++;
          $interviewScoresByApplicantId[$applicantId]["total_points_sum"] += $totalPoints;
        }
        $evaluationResult->free();
      }
      $evaluationStmt->close();
    }

    foreach ($interviewScoresByApplicantId as $applicantId => $summary) {
      $assignedPanelCount = (int)($summary["assigned_panel_count"] ?? 0);
      $ratedPanelCount = (int)($summary["rated_panel_count"] ?? 0);
      if ($assignedPanelCount <= 0 || $ratedPanelCount <= 0 || $ratedPanelCount < $assignedPanelCount) {
        continue;
      }

      $averagePanelScore = ((float)($summary["total_points_sum"] ?? 0.0)) / $assignedPanelCount;
      $weightedMean = ((float)($summary["total_points_sum"] ?? 0.0)) / ($criteriaCount * $assignedPanelCount);
      $interviewRating = ($averagePanelScore / $maxInterviewPoints) * 100;
      $interviewWeighted = $interviewRating * 0.40;

      $interviewScoresByApplicantId[$applicantId]["weighted_mean"] = $weightedMean;
      $interviewScoresByApplicantId[$applicantId]["interview_rating"] = $interviewRating;
      $interviewScoresByApplicantId[$applicantId]["interview_weighted"] = $interviewWeighted;
    }
  }
}

$rankRows = array_map(static function (array $item) use ($interviewScoresByApplicantId, $savedRankInputsByApplicantId, $rankRemarkOptions, $formatScoreDisplay): array {
  $applicantId = (int)($item["id"] ?? 0);
  $summary = $interviewScoresByApplicantId[$applicantId] ?? [];
  $savedInputs = $savedRankInputsByApplicantId[$applicantId] ?? [];
  $weightedMean = isset($summary["weighted_mean"]) && is_numeric($summary["weighted_mean"])
    ? (float)$summary["weighted_mean"]
    : null;
  $interviewWeighted = isset($summary["interview_weighted"]) && is_numeric($summary["interview_weighted"])
    ? (float)$summary["interview_weighted"]
    : null;
  $savedExamRating = isset($savedInputs["exam_rating"]) && is_numeric($savedInputs["exam_rating"])
    ? (float)$savedInputs["exam_rating"]
    : null;
  $savedGradesRating = isset($savedInputs["grades_rating"]) && is_numeric($savedInputs["grades_rating"])
    ? (float)$savedInputs["grades_rating"]
    : null;
  $savedRemarks = trim((string)($savedInputs["remarks"] ?? ""));
  if (!in_array($savedRemarks, $rankRemarkOptions, true)) {
    $savedRemarks = "";
  }

  return [
    "id" => $applicantId,
    "name" => (string)($item["name"] ?? ""),
    "exRateInput" => $savedExamRating === null ? "" : $formatScoreDisplay($savedExamRating),
    "ex30" => "",
    "inRate" => $formatScoreDisplay($weightedMean),
    "in40" => $formatScoreDisplay($interviewWeighted),
    "in40Value" => $interviewWeighted,
    "grRateInput" => $savedGradesRating === null ? "" : $formatScoreDisplay($savedGradesRating),
    "gr30" => "",
    "avg" => "",
    "rank" => "",
    "remarks" => $savedRemarks,
  ];
}, $panelistSentApplicants);

if (!empty($rankRows)) {
  usort($rankRows, static function (array $left, array $right): int {
    $leftName = trim((string)($left["name"] ?? ""));
    $rightName = trim((string)($right["name"] ?? ""));
    $nameComparison = strnatcasecmp($leftName, $rightName);
    if ($nameComparison !== 0) {
      return $nameComparison;
    }

    return ((int)($left["id"] ?? 0)) <=> ((int)($right["id"] ?? 0));
  });
}
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">   
    <title>Applicants Rank</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <style>
      ::-webkit-scrollbar {
        width: 6px;
      }
      ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
        border-radius: 999px;
      }
      body {
           background-color: #edf2fb;
      }
      .paper {
        width: 100%;
        max-width: none;
        padding: 18px;
        background-color: #fff;
        font-family: "Times New Roman", serif;
        background-image:
          radial-gradient(rgba(0, 0, 0, 0.035) 0.5px, transparent 0.5px),
          radial-gradient(rgba(0, 0, 0, 0.03) 0.5px, transparent 0.5px);
        background-size: 8px 8px, 8px 8px;
        background-position: 0 0, 4px 4px;
      }
      .document-header {
        margin-bottom: 1rem;
        text-align: center;
      }
      .header-top {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 0.5rem;
      }
      .header-left {
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      .header-left img {
        width: 80px;
        height: 80px;
        object-fit: contain;
      }
      .header-left-text {
        line-height: 1.1;
        text-align: left;
      }
      .header-left-text h1 {
        font-weight: 700;
        font-size: 16pt;
        margin: 0;
      }
      .header-left-text p {
        margin: 0;
        font-size: 10pt;
      }
      .header-right {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        align-items: center;
      }
      .header-right img {
        width: 100px;
        height: 80px;
        object-fit: contain;
      }
      .rating-exam {
        background-color: #bbf7d0;
      }
      .rating-interview {
        background-color: #fed7aa;
      }
      .rating-grades {
        background-color: #fef08a;
      }
      .score-input {
        width: 100%;
        border: 0;
        padding: 0;
        background: transparent;
        text-align: center;
        font: inherit;
        outline: none;
      }
      .score-input::-webkit-outer-spin-button,
      .score-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
      }
      .score-input[type="number"] {
        appearance: textfield;
      }
      .remark-select {
        width: 100%;
        border: 0;
        background: transparent;
        font: inherit;
        outline: none;
      }
      @media (max-width: 767px) {
        .paper {
          padding: 12px;
        }
        .header-left {
          flex-direction: column;
          text-align: center;
        }
        .header-left-text {
          text-align: center;
        }
        .header-left img {
          width: 64px;
          height: 64px;
        }
        .header-right img {
          width: 84px;
          height: 64px;
        }
      }
      @page {
        size: A4;
        margin: 10mm;
      }
      @media print {
        body {
          background: white !important;
        }
        #sidebar {
          display: none !important;
        }
        .paper,
        .paper * {
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }
        .admin-sidebar,
        .admin-topbar,
        .no-print {
          display: none !important;
        }
        .admin-content {
          margin-left: 0 !important;
        }
        .paper {
          width: auto;
          min-height: auto;
          padding: 0;
          box-shadow: none !important;
          border: 0 !important;
          background-image: none;
        }
        table {
          page-break-inside: auto;
        }
        tr {
          page-break-inside: avoid;
          page-break-after: auto;
        }
        .paper table {
          width: 100% !important;
          table-layout: fixed;
          font-size: 9.5px !important;
        }

        .paper th,
        .paper td {
          padding: 2px 3px !important;
          line-height: 1.15 !important;
          min-width: 0 !important;
          white-space: normal !important;
          overflow-wrap: anywhere;
          word-break: break-word;
        }

        .paper .overflow-x-auto {
          overflow: visible !important;
        }
        .remark-select {
          appearance: none !important;
          -webkit-appearance: none !important;
          -moz-appearance: none !important;
          background-image: none !important;
          padding-right: 0 !important;
        }
        .remark-select::-ms-expand {
          display: none !important;
        }
        .rating-exam {
          background-color: #bbf7d0 !important;
        }
        .rating-interview {
          background-color: #fed7aa !important;
        }
        .rating-grades {
          background-color: #fef08a !important;
        }
        .header-top {
          flex-wrap: nowrap !important;
          align-items: flex-start !important;
          justify-content: center !important;
          gap: 0.75rem !important;
        }
        .header-left {
          flex-direction: row !important;
          align-items: flex-start !important;
          gap: 0.5rem !important;
        }
        .header-left-text {
          text-align: center !important;
        }
        .header-right {
          flex-direction: row !important;
          align-items: flex-start !important;
          align-self: flex-start !important;
        }
      }
          #sidebar nav ul {
        padding: 0.35rem 0.5rem 5.5rem;
      }
      #sidebar li[data-nav] {
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
      #sidebar li[data-nav]:hover {
        transform: translateX(2px);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.16);
      }
    </style>
  </head>
  <body class="font-sans">
    <div class="min-h-screen">
      <!-- Sidebar -->
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
            <div class="min-w-0">
              <p class="text-[10px] uppercase tracking-[0.14em] text-blue-100/85">
                SMCC Scholarship
              </p>
              <p class="text-sm font-semibold leading-tight text-white">
                Admission and Scholarship Office
              </p>
              <p class="text-[10px] text-blue-100/80 mt-1">
                Admin Management Portal
              </p>
            </div>
          </div>
        </div>
        <nav class="flex-1 mt-2">
          <ul class="text-xs font-semibold">
<li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
               data-nav="adminDashboard.php" onclick="window.location.href='adminDashboard.php'"
            >
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="applicant.php" onclick="window.location.href='applicant.php'"
            >
              <i class="fas fa-user-graduate w-5"></i>
              <span>Applicants</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="approved.php" onclick="window.location.href='approved.php'"
            >
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="interviewEvaluation.php" onclick="window.location.href='interviewEvaluation.php'"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="ranks.php" onclick="window.location.href='ranks.php'"
            >
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="list-of-qualified.php" onclick="window.location.href='list-of-qualified.php'"
            >
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="department-evaluation-list.php" onclick="window.location.href='department-evaluation-list.php'"
            >
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="summary-report.php" onclick="window.location.href='summary-report.php'"
            >
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="institutional-scholars.php" onclick="window.location.href='institutional-scholars.php'"
            >
              <i class="fas fa-chart-line w-5"></i>
              <span>Institutional Scholars</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="accounts.php" onclick="window.location.href='accounts.php'"
            >
              <i class="fas fa-user-circle w-5"></i>
              <span>Accounts</span>
            </li>
          </ul>
        </nav>
        
                 <div class="absolute bottom-0 left-0 w-full p-2">
          <div class="rounded-xl border border-white/20 bg-white/10 backdrop-blur-sm overflow-hidden">
            <div class="h-px w-full bg-gradient-to-r from-transparent via-[#8bcfff] to-transparent opacity-80"></div>

            <div class="px-4 pt-2 pb-1 flex items-center gap-2 text-[11px] text-blue-100/90">
              <div class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center">
                <i class="fas fa-user-shield text-[12px]"></i>
              </div>
              <div class="leading-tight">
                <p class="font-semibold">Admin Account</p>
                <p class="text-[10px] text-blue-200/80">Institutional Scholarship</p>
              </div>
            </div>

            <!-- Logout button -->
            <div class="px-3 pb-3 pt-1">
              <button
                onclick="window.location.href='../logout.php'"
                class="w-full flex items-center justify-center gap-2 text-[11px] font-semibold
                       bg-gradient-to-r from-red-500 to-red-600
                       hover:from-red-600 hover:to-red-700
                       px-3 py-2 rounded-full shadow-md hover:shadow-lg
                       transition-all duration-150"
                type="button"
              >
                <i class="fas fa-sign-out-alt text-xs"></i>
                <span>Logout</span>
              </button>
            </div>
          </div>
        </div>
      </aside>
      <main class="admin-content ml-0 md:ml-64 flex flex-col min-h-screen pt-14 bg-[#f8fafc]">
        <header class="admin-topbar hidden fixed top-0 left-0 md:left-64 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2">
          <div class="flex items-center gap-2">
            <button
              id="sidebarToggleTop"
              class="md:hidden inline-flex items-center justify-center p-2 rounded bg-[#0d8ddb] focus:outline-none"
              type="button"
            >
              <i class="fas fa-bars"></i>
            </button>
            <span class="text-[11px] font-semibold md:hidden">Admission &amp; Scholarship</span>
          </div>
          <div class="flex gap-2 text-xs">
            <button
              type="button"
              class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 flex items-center gap-1 font-normal"
            >
              <i class="fas fa-user"></i>
              Admin panel
            </button>
            <button type="button" class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 font-normal">
              Account
            </button>
          </div>
        </header>

        <section
          class="page-header no-print fixed top-0 left-0 md:left-64 right-0 z-20 bg-white border-b border-slate-200 px-4 sm:px-6 py-3 shadow-sm"
        >
                    <div class="flex items-center gap-2">
            <button
              id="sidebarToggle"
              class="md:hidden inline-flex items-center justify-center p-2 rounded bg-slate-700 text-white hover:bg-slate-800 focus:outline-none transition-colors"
              type="button"
            >
              <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-slate-800 text-lg font-semibold flex items-center gap-2">
            <i class="fas fa-flag"></i>
           RANKING
          </h2>
          </div>
        </section>

        <section class="flex flex-col px-3 sm:px-4 lg:px-6 pt-6 md:pt-16 pb-6 bg-[#eef2f7] flex-1 min-h-[calc(100vh-3rem)]">
          <div class="no-print w-full mb-4 rounded-lg border border-[#0d8ddb] bg-white p-3 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <form class="flex flex-wrap items-center gap-2 lg:justify-end" method="get" action="ranks.php">
                <select
                  id="academicYear"
                  name="school_year"
                  class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                  aria-label="Select academic year"
                  onchange="this.form.submit()"
                >
                  <option value="" <?php echo $selectedSchoolYear === "" ? "selected" : ""; ?>>All School Years</option>
                  <?php foreach ($schoolYearOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedSchoolYear === $option ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($option); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select
                  id="semesterSelect"
                  name="semester"
                  class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                  aria-label="Select semester"
                  onchange="this.form.submit()"
                >
                  <option value="" <?php echo $selectedSemester === "" ? "selected" : ""; ?>>All Semesters</option>
                  <?php foreach ($semesterOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedSemester === $option ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($option); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select
                  id="batchSelect"
                  name="batch"
                  class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                  aria-label="Select batch"
                  onchange="this.form.submit()"
                >
                  <option value="" <?php echo $selectedBatch === "" ? "selected" : ""; ?>>All Batches</option>
                  <?php foreach ($batchOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedBatch === $option ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($option); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if ($selectedSchoolYear !== "" || $selectedSemester !== "" || $selectedBatch !== ""): ?>
                  <a
                    href="ranks.php"
                    class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm"
                  >
                    Clear
                  </a>
                <?php endif; ?>
              </form>
              <div class="flex flex-wrap items-center gap-2">
                <button
                  type="submit"
                  form="rankInputsForm"
                  class="inline-flex items-center gap-2 rounded-full border border-[#052c6a] bg-[#052c6a] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#041c43]"
                >
                  <i class="fas fa-save"></i>
                  <span>Save</span>
                </button>
                <button
                  type="button"
                  onclick="window.print()"
                  class="inline-flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-[#0d8ddb] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#0a6fac]"
                >
                  <i class="fas fa-print"></i>
                  <span>Print</span>
                </button>
              </div>
            </div>
          </div>

          <?php if ($rankSaveMessage !== ""): ?>
            <div class="no-print mb-4 rounded-lg border px-4 py-3 text-xs font-semibold <?php echo $rankSaveMessageType === "success" ? "border-green-200 bg-green-50 text-green-700" : "border-red-200 bg-red-50 text-red-700"; ?>">
              <?php echo htmlspecialchars($rankSaveMessage); ?>
            </div>
          <?php endif; ?>

          <form id="rankInputsForm" method="post">
            <input type="hidden" name="school_year" value="<?php echo htmlspecialchars($selectedSchoolYear); ?>" />
            <input type="hidden" name="semester" value="<?php echo htmlspecialchars($selectedSemester); ?>" />
            <input type="hidden" name="batch" value="<?php echo htmlspecialchars($selectedBatch); ?>" />
          <div class="paper w-full bg-white border border-slate-300 shadow-xl print:shadow-none print:border-0">
            <div class="document-header">
              <div class="header-top">
                <div class="header-left">
                  <img src="../img/SMCCNEWLOGO.png" alt="Seal of Saint Michael College of Caraga" />
                  <div class="header-left-text">
                    <h1 class="text-center">Saint Michael College of Caraga</h1>
                    <p class="text-center">
                      Brgy. 4, Nasipit, Agusan del Norte, Philippines<br />
                      District 8, Brgy. Triangulo, Nasipit, Agusan del Norte, Philippines
                    </p>
                    <p class="text-center">Tel. Nos. +63 085 343-3251 / +63 085 283-3113</p>
                    <p class="text-center">
                      <a href="http://www.smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">www.smccnasipit.edu.ph</a>
                    </p>
                  </div>
                </div>
                <div class="header-right">
                  <img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC ISO 9001 logo" />
                </div>
              </div>
            </div>
            <div class="text-center mt-3">
              <p class="text-[12px]">Student Assistance Scholarship Program (SASP) Applicants' Rank</p>
              <p class="text-[12px]" id="termText"><?php echo htmlspecialchars($displaySemester); ?>, S.Y. <?php echo htmlspecialchars($displaySchoolYear); ?></p>
              <p class="text-[12px]" id="batchText"><?php echo htmlspecialchars($displayBatch); ?></p>
            </div>
            <div class="overflow-x-auto mt-3">
              <table class="w-full border-collapse border border-black text-[12px]">
                <colgroup>
                  <col style="width: 4%" />
                  <col style="width: 26%" />
                  <col style="width: 6%" />
                  <col style="width: 6%" />
                  <col style="width: 6%" />
                  <col style="width: 6%" />
                  <col style="width: 6%" />
                  <col style="width: 6%" />
                  <col style="width: 8%" />
                  <col style="width: 5%" />
                  <col style="width: 21%" />
                </colgroup>
                <thead>
                  <tr class="text-center font-semibold bg-white">
                    <th rowspan="3" class="border border-black px-2 py-2 w-[56px]">SEQ.</th>
                    <th rowspan="3" class="border border-black px-2 py-2 text-left min-w-[260px]">NAME OF APPLICANT</th>
                    <th colspan="6" class="border border-black px-2 py-2">RATING</th>
                    <th rowspan="3" class="border border-black px-2 py-2 w-[78px]">100%<br />AVERAGE</th>
                    <th rowspan="3" class="border border-black px-2 py-2 w-[64px]">RANK</th>
                    <th rowspan="3" class="border border-black px-2 py-2 min-w-[170px]">REMARKS</th>
                </tr>
                  <tr class="text-center font-semibold bg-white">
                    <th colspan="2" class="border border-black px-2 py-2 bg-green-200 rating-exam">Examination</th>
                    <th colspan="2" class="border border-black px-2 py-2 bg-orange-200 rating-interview">Interview</th>
                    <th colspan="2" class="border border-black px-2 py-2 bg-yellow-200 rating-grades">Grades</th>
                  </tr>
                  <tr class="text-center font-semibold bg-white">
                    <th class="border border-black px-2 py-2">100</th>
                    <th class="border border-black text-red-500 px-2 py-2">30%</th>
                    <th class="border border-black px-2 py-2">5.00</th>
                    <th class="border border-black text-red-500 px-2 py-2">40%</th>
                    <th class="border border-black px-2 py-2">100</th>
                    <th class="border border-black text-red-500 px-2 py-2">30%</th>
                  </tr>
                </thead>
                <tbody id="rankTableBody"></tbody>
              </table>
            </div>
            <div class="mt-8 text-[12px] space-y-8">
              <div>
                <p class="mb-10">Prepared by:</p>
                <p class="font-semibold border-t border-black inline-block pt-1">ARLYN B. TUYOGON, MMBM</p>
                <p class="text-[11px]">Head, Admission & Scholarship</p>
              </div>
              <div>
                <p class="mb-10">Checked by:</p>
                <p class="font-semibold border-t border-black inline-block pt-1">FELMARIE MANLUNAS, MACDDS</p>
                <p class="text-[11px]">Head, Student Affairs & Services</p>
              </div>
              <div>
                <p class="mb-10">Noted by:</p>
                <p class="font-semibold border-t border-black inline-block pt-1">RICKY E. DESTACAMENTO, RGC, MAED</p>
                <p class="text-[11px]">Head, HRMDO</p>
              </div>
            </div>
          </div>
          </form>
        </section>
      </main>
    </div>
    <script>
      const rows = <?php echo json_encode($rankRows, JSON_UNESCAPED_SLASHES); ?>;
      const remarkOptions = <?php echo json_encode(array_values($rankRemarkOptions), JSON_UNESCAPED_SLASHES); ?>;
      const truncateToTwoDecimals = (value) => {
        if (!Number.isFinite(value)) {
          return null;
        }
        return value >= 0 ? Math.floor(value * 100) / 100 : Math.ceil(value * 100) / 100;
      };
      const formatDisplayScore = (value) => {
        if (value === null || value === undefined || !Number.isFinite(value)) {
          return "";
        }
        return truncateToTwoDecimals(value).toFixed(2);
      };
      const parseManualScore = (value) => {
        if (typeof value !== "string" || value.trim() === "") {
          return null;
        }

        const parsedValue = Number(value);
        if (!Number.isFinite(parsedValue) || parsedValue < 0 || parsedValue > 100) {
          return null;
        }

        return parsedValue;
      };

      function updateRankAssignments() {
        const rankedRows = rows
          .map((row, index) => ({ row, index }))
          .filter(({ row }) => row.avgValue !== null && Number.isFinite(row.avgValue))
          .sort((left, right) => {
            if (right.row.avgValue === left.row.avgValue) {
              return left.index - right.index;
            }
            return right.row.avgValue - left.row.avgValue;
          });

        rows.forEach((row) => {
          row.rank = "";
        });

        rankedRows.forEach((item, index) => {
          item.row.rank = String(index + 1);
        });
      }

      function syncRowDisplay(rowIndex) {
        const row = rows[rowIndex];
        const rowElement = document.querySelector(`[data-row-index="${rowIndex}"]`);
        if (!row || !rowElement) {
          return;
        }

        const ex30Cell = rowElement.querySelector("[data-field='ex30']");
        const gr30Cell = rowElement.querySelector("[data-field='gr30']");
        const avgCell = rowElement.querySelector("[data-field='avg']");
        const rankCell = rowElement.querySelector("[data-field='rank']");

        if (ex30Cell) ex30Cell.textContent = row.ex30;
        if (gr30Cell) gr30Cell.textContent = row.gr30;
        if (avgCell) avgCell.textContent = row.avg;
        if (rankCell) rankCell.textContent = row.rank;
      }

      function syncAllRankDisplays() {
        rows.forEach((_, index) => {
          syncRowDisplay(index);
        });
      }

      function recalculateRow(rowIndex) {
        const row = rows[rowIndex];
        if (!row) {
          return;
        }

        const examRating = parseManualScore(row.exRateInput);
        const gradesRating = parseManualScore(row.grRateInput);
        const interviewWeighted = row.in40Value !== null && Number.isFinite(row.in40Value) ? row.in40Value : null;

        const examWeighted = examRating === null ? null : truncateToTwoDecimals(examRating * 0.30);
        const gradesWeighted = gradesRating === null ? null : truncateToTwoDecimals(gradesRating * 0.30);

        row.ex30 = formatDisplayScore(examWeighted);
        row.gr30 = formatDisplayScore(gradesWeighted);

        if (examWeighted === null || gradesWeighted === null || interviewWeighted === null) {
          row.avgValue = null;
          row.avg = "";
        } else {
          row.avgValue = truncateToTwoDecimals(examWeighted + interviewWeighted + gradesWeighted);
          row.avg = formatDisplayScore(row.avgValue);
        }

        updateRankAssignments();
        syncAllRankDisplays();
      }

      function renderRankRows() {
        const tbody = document.getElementById("rankTableBody");
        if (!tbody) return;

        tbody.innerHTML = "";
        rows.forEach((row, index) => {
          const tr = document.createElement("tr");
          tr.className = "border border-black text-[12px]";
          tr.dataset.rowIndex = String(index);
          const remarkOptionsHtml = ['<option value=""></option>']
            .concat(
              remarkOptions.map((option) => {
                const selected = row.remarks === option ? " selected" : "";
                return `<option value="${option}"${selected}>${option}</option>`;
              })
            )
            .join("");
          tr.innerHTML = `
            <td class="border border-black px-2 py-2 text-center">${index + 1}</td>
            <td class="border border-black px-2 py-2">${row.name}</td>
            <td class="border border-black px-2 py-2 text-center">
              <input type="hidden" name="applicant_ids[]" value="${row.id}" />
              <input
                type="number"
                min="0"
                max="100"
                step="0.01"
                value="${row.exRateInput}"
                class="score-input"
                data-field="exRateInput"
                data-row-index="${index}"
                name="exam_rating[${row.id}]"
              />
            </td>
            <td class="border border-black px-2 py-2 text-center" data-field="ex30">${row.ex30}</td>
            <td class="border border-black px-2 py-2 text-center">${row.inRate}</td>
            <td class="border border-black px-2 py-2 text-center">${row.in40}</td>
            <td class="border border-black px-2 py-2 text-center">
              <input
                type="number"
                min="0"
                max="100"
                step="0.01"
                value="${row.grRateInput}"
                class="score-input"
                data-field="grRateInput"
                data-row-index="${index}"
                name="grades_rating[${row.id}]"
              />
            </td>
            <td class="border border-black px-2 py-2 text-center" data-field="gr30">${row.gr30}</td>
            <td class="border border-black px-2 py-2 text-center font-semibold" data-field="avg">${row.avg}</td>
            <td class="border border-black px-2 py-2 text-center font-semibold" data-field="rank">${row.rank}</td>
            <td class="border border-black px-2 py-2">
              <select
                class="remark-select"
                name="remarks[${row.id}]"
                data-field="remarks"
                data-row-index="${index}"
              >
                ${remarkOptionsHtml}
              </select>
            </td>
          `;
          tbody.appendChild(tr);
        });

        tbody.querySelectorAll("input[data-field='exRateInput']").forEach((input) => {
          input.addEventListener("input", (event) => {
            const rowIndex = Number(event.currentTarget.dataset.rowIndex);
            rows[rowIndex].exRateInput = event.currentTarget.value;
            recalculateRow(rowIndex);
          });
        });

        tbody.querySelectorAll("input[data-field='grRateInput']").forEach((input) => {
          input.addEventListener("input", (event) => {
            const rowIndex = Number(event.currentTarget.dataset.rowIndex);
            rows[rowIndex].grRateInput = event.currentTarget.value;
            recalculateRow(rowIndex);
          });
        });

        tbody.querySelectorAll("select[data-field='remarks']").forEach((select) => {
          select.addEventListener("change", (event) => {
            const rowIndex = Number(event.currentTarget.dataset.rowIndex);
            rows[rowIndex].remarks = event.currentTarget.value;
          });
        });

        rows.forEach((_, index) => {
          recalculateRow(index);
        });
      }

      function setupTermText() {
        const academicYearSelect = document.getElementById("academicYear");
        const semesterSelect = document.getElementById("semesterSelect");
        const termText = document.getElementById("termText");
        if (!academicYearSelect || !semesterSelect || !termText) return;

        const updateTermText = () => {
          const semester = semesterSelect.value || "1st Semester";
          const schoolYear = academicYearSelect.value || "<?php echo htmlspecialchars($currentSchoolYear, ENT_QUOTES); ?>";
          termText.textContent = `${semester}, S.Y. ${schoolYear}`;
        };

        academicYearSelect.addEventListener("change", updateTermText);
        semesterSelect.addEventListener("change", updateTermText);
        updateTermText();
      }

      function setupSidebar() {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");

        if (toggleBtn && sidebar) {
          toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
          });

          sidebar.querySelectorAll("li").forEach((item) => {
            item.addEventListener("click", () => {
              if (window.innerWidth < 768) {
                sidebar.classList.add("-translate-x-full");
              }
            });
          });
        }
      }

      function markActiveSidebarItem() {
        const sidebar = document.getElementById("sidebar");
        if (!sidebar) return;

        const currentPage = window.location.pathname.split("/").pop().toLowerCase();
        const sidebarAliases = {
          "view-application.php": "applicant.php",
          "department-evaluation-indi.php": "department-evaluation-list.php",
          "summary-reports.php": "summary-report.php",
          "list-0f-qualified.php": "list-of-qualified.php"
        };
        const activePage = sidebarAliases[currentPage] || currentPage;

        sidebar.querySelectorAll("li[data-nav]").forEach((item) => {
          const target = (item.dataset.nav || "").toLowerCase();
          const isActive = target === activePage;
          item.classList.toggle("bg-[#fcdc2f]", isActive);
          item.classList.toggle("bg-opacity-90", isActive);
          item.classList.toggle("text-[#052c6a]", isActive);
          item.classList.toggle("hover:bg-white/15", !isActive);
        });
      }

      document.addEventListener("DOMContentLoaded", () => {
        renderRankRows();
        setupTermText();
        setupSidebar();
        markActiveSidebarItem();
      });
    </script>
  </body>
</html>









