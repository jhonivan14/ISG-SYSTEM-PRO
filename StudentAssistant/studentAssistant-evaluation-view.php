<?php
require_once __DIR__ . "/studentAssistant-auth.php";
headRequireLogin();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once "../db.php";
date_default_timezone_set("Asia/Manila");

$headUsername = trim((string)($_SESSION["head_username"] ?? ""));
$headRole = isgNormalizeEvaluatorRole((string)($_SESSION["evaluator_role"] ?? headExpectedRole()));
$headName = trim((string)($_SESSION["head_name"] ?? ""));
if ($headName === "") {
  $headName = "Head of Office";
}
$headOffice = trim((string)($_SESSION["head_office"] ?? ""));
$evaluatorScope = isgLoadEvaluatorScope($conn, $headUsername, $headRole);
$headOffice = (string)($evaluatorScope["office"] ?? $headOffice);
$evaluatorOptions = ["Self", "Head of Office", "Administrator"];
$currentFolderRole = strtolower(basename(__DIR__));
$evaluatorType = "Head of Office";
if ($currentFolderRole === "studentassistant") {
  $evaluatorType = "Self";
} elseif ($currentFolderRole === "administrator") {
  $evaluatorType = "Administrator";
}
$evaluationId = isset($_GET["evaluation_id"]) ? (int)$_GET["evaluation_id"] : 0;
$applicationId = isset($_GET["application_id"]) ? (int)$_GET["application_id"] : 0;
$scholarRecordId = $applicationId !== 0 ? abs($applicationId) : 0;

$loadError = "";
$evaluation = null;
$ratings = [];

$ratingOptions = [
  4 => [
    "label" => "Very Good",
    "short" => "VG",
    "interpretation" => "Consistently exceeds the performance expectations stated in the indicator.",
  ],
  3 => [
    "label" => "Good",
    "short" => "G",
    "interpretation" => "Consistently meets the performance expectations stated in the indicator.",
  ],
  2 => [
    "label" => "Poor",
    "short" => "P",
    "interpretation" => "Frequently falls below the performance expectations stated in the indicator and requires improvement.",
  ],
  1 => [
    "label" => "Needs Improvement",
    "short" => "NI",
    "interpretation" => "Consistently fails to meet the performance expectations stated in the indicator and requires close supervision and additional guidance.",
  ],
];
$evaluationSections = [
  "a" => [
    "title" => "A. Quality and Quantity of Work",
    "criteria" => [
      "score-a1" => "A.1 Completes assigned tasks accurately and with minimal errors.",
      "score-a2" => "A.2 Completes assigned tasks thoroughly and according to instructions.",
      "score-a3" => "A.3 Completes work within the required time.",
      "score-a4" => "A.4 Demonstrates initiative by seeking additional responsibilities after completing assigned works.",
      "score-a5" => "A.5 Willingly accepts new assignments and responsibilities.",
    ],
  ],
  "b" => [
    "title" => "B. Interpersonal Skills",
    "criteria" => [
      "score-b1" => "B.1 Communicates clearly and respectfully with students, employees, and visitors.",
      "score-b2" => "B.2 Demonstrates courtesy and professionalism when assisting students, employees, parents, and visitors.",
      "score-b3" => "B.3 Works cooperatively with office personnel and fellow student assistants.",
      "score-b4" => "B.4 Responds appropriately to questions, concerns, and requests.",
      "score-b5" => "B.5 Contributes positively to teamwork and collaborates effectively with colleagues.",
    ],
  ],
  "c" => [
    "title" => "C. Attendance and Reliability",
    "criteria" => [
      "score-c1" => "C.1 Maintains regular attendance and provides timely notification for any authorized absence.",
      "score-c2" => "C.2 Reports for duty punctually and observes the assigned work schedule.",
      "score-c3" => "C.3 Participates actively in institutional activities, meetings, orientations, and trainings when required.",
      "score-c4" => "C.4 Works responsibly with minimal supervision.",
      "score-c5" => "C.5 Follows instructions and completes assigned responsibilities consistently.",
    ],
  ],
  "d" => [
    "title" => "D. Professionalism and Ethical Conduct",
    "criteria" => [
      "score-d1" => "D.1 Demonstrates honesty and integrity in performing assigned duties.",
      "score-d2" => "D.2 Maintains confidentiality of office records and information.",
      "score-d3" => "D.3 Shows respect for institutional policies and procedures.",
      "score-d4" => "D.4 Maintains a positive attitude and professional demeanor while performing assigned duties.",
      "score-d5" => "D.5 Observes proper dress code and behaves appropriately while on duty.",
    ],
  ],
];

if ($evaluationId <= 0 && $applicationId === 0) {
  $loadError = "No applicant selected.";
} elseif ((string)($evaluatorScope["error"] ?? "") !== "") {
  $loadError = (string)$evaluatorScope["error"];
} else {
  $tableResult = $conn->query("SHOW TABLES LIKE 'department_head_evaluations'");
  $hasTable = $tableResult instanceof mysqli_result && $tableResult->num_rows > 0;
  if ($tableResult instanceof mysqli_result) {
    $tableResult->free();
  }

  if (!$hasTable) {
    $loadError = "No saved evaluation found yet.";
  } else {
    $scholarTableResult = $conn->query("SHOW TABLES LIKE 'institutional_scholar_records'");
    $hasScholarTable = $scholarTableResult instanceof mysqli_result && $scholarTableResult->num_rows > 0;
    if ($scholarTableResult instanceof mysqli_result) {
      $scholarTableResult->free();
    }
    if (!$hasScholarTable) {
      $loadError = "Scholar records table is not available.";
    } else {
      $evaluationRestriction = isgEvaluatorEvaluationRestriction($evaluatorScope, "dhe");
      if ($evaluationId > 0) {
        $stmt = $conn->prepare(
          "SELECT
            dhe.id AS evaluation_id,
            dhe.applicant_name,
            dhe.semester,
            dhe.school_year,
            dhe.assigned_office,
            dhe.head_name,
            dhe.evaluation_date,
            dhe.ratings_json,
            dhe.strengths,
            dhe.areas_improvement,
            dhe.recommendations,
            dhe.signature_data,
            COALESCE(NULLIF(TRIM(isr.program_year), ''), '') AS program_course
          FROM department_head_evaluations dhe
          INNER JOIN institutional_scholar_records isr
            ON isr.id = ABS(dhe.application_id)
          WHERE dhe.id = ?
            AND dhe.application_id <> 0
            AND dhe.head_username = ?
            AND dhe.evaluator_role = ?
            " . $evaluationRestriction["sql"] . "
            AND (
              LOWER(TRIM(COALESCE(isr.category, ''))) = 'student_assistant'
              OR (
                LOWER(TRIM(COALESCE(isr.category, ''))) = 'official'
                AND LOWER(TRIM(COALESCE(isr.grant_applied, ''))) LIKE '%assistant%'
              )
            )
          LIMIT 1"
        );
      } else {
        $stmt = $conn->prepare(
          "SELECT
            dhe.id AS evaluation_id,
            dhe.applicant_name,
            dhe.semester,
            dhe.school_year,
            dhe.assigned_office,
            dhe.head_name,
            dhe.evaluation_date,
            dhe.ratings_json,
            dhe.strengths,
            dhe.areas_improvement,
            dhe.recommendations,
            dhe.signature_data,
            COALESCE(NULLIF(TRIM(isr.program_year), ''), '') AS program_course
          FROM department_head_evaluations dhe
          INNER JOIN institutional_scholar_records isr
            ON isr.id = ABS(dhe.application_id)
          WHERE ABS(dhe.application_id) = ?
            AND dhe.application_id <> 0
            AND dhe.head_username = ?
            AND dhe.evaluator_role = ?
            " . $evaluationRestriction["sql"] . "
            AND (
              LOWER(TRIM(COALESCE(isr.category, ''))) = 'student_assistant'
              OR (
                LOWER(TRIM(COALESCE(isr.category, ''))) = 'official'
                AND LOWER(TRIM(COALESCE(isr.grant_applied, ''))) LIKE '%assistant%'
              )
            )
          ORDER BY dhe.updated_at DESC, dhe.id DESC
          LIMIT 1"
        );
      }
      if ($stmt) {
        if ($evaluationId > 0) {
          $viewTypes = "iss" . $evaluationRestriction["types"];
          $viewParams = array_merge([$evaluationId, $headUsername, $headRole], $evaluationRestriction["params"]);
          $stmt->bind_param($viewTypes, ...$viewParams);
        } else {
          $viewTypes = "iss" . $evaluationRestriction["types"];
          $viewParams = array_merge([$scholarRecordId, $headUsername, $headRole], $evaluationRestriction["params"]);
          $stmt->bind_param($viewTypes, ...$viewParams);
        }
        if ($stmt->execute()) {
          $result = $stmt->get_result();
          $row = $result ? $result->fetch_assoc() : null;
          if (is_array($row)) {
            $evaluation = [
              "evaluation_id" => (int)($row["evaluation_id"] ?? 0),
              "applicant_name" => trim((string)($row["applicant_name"] ?? "")),
              "program_course" => trim((string)($row["program_course"] ?? "")),
              "semester" => trim((string)($row["semester"] ?? "")),
              "school_year" => trim((string)($row["school_year"] ?? "")),
              "assigned_office" => trim((string)($row["assigned_office"] ?? "")),
              "head_name" => trim((string)($row["head_name"] ?? "")),
              "evaluation_date" => trim((string)($row["evaluation_date"] ?? "")),
              "strengths" => trim((string)($row["strengths"] ?? "")),
              "areas_improvement" => trim((string)($row["areas_improvement"] ?? "")),
              "recommendations" => trim((string)($row["recommendations"] ?? "")),
              "signature_data" => trim((string)($row["signature_data"] ?? "")),
            ];
            $decoded = json_decode((string)($row["ratings_json"] ?? ""), true);
            if (is_array($decoded)) {
              foreach ($decoded as $key => $val) {
                $score = (int)$val;
                if ($score >= 1 && $score <= 4) {
                  $ratings[(string)$key] = $score;
                }
              }
            }
          } else {
            $loadError = "No saved evaluation found for this student assistant.";
          }
          if ($result instanceof mysqli_result) {
            $result->free();
          }
        } else {
          $loadError = "Unable to load evaluation details.";
        }
        $stmt->close();
      } else {
        $loadError = "Unable to prepare evaluation query.";
      }
    }
  }
}

$displayTerm = "N/A";
if ($evaluation !== null) {
  if ($evaluation["semester"] !== "" && $evaluation["school_year"] !== "") {
    $displayTerm = $evaluation["semester"] . ", S.Y. " . $evaluation["school_year"];
  } elseif ($evaluation["semester"] !== "") {
    $displayTerm = $evaluation["semester"];
  } elseif ($evaluation["school_year"] !== "") {
    $displayTerm = $evaluation["school_year"];
  }
}
$displayDate = ($evaluation !== null && $evaluation["evaluation_date"] !== "")
  ? date("F j, Y", strtotime($evaluation["evaluation_date"]))
  : "N/A";

$check = static function (int $actual, int $scale): string {
  return $actual === $scale ? "&#10003;" : "";
};
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>View Evaluation</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
      rel="stylesheet"
    />
    <style>
      body { background: #eef2f7; }
      #sidebar nav ul { padding: 0.35rem 0.5rem 5.5rem; }
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
      .paper { font-family: "Times New Roman", serif; background: #fff; color: #111827; }
      .paper h1, .paper p { margin: 0; }
      .header-top {
        margin-top: 1.1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 0.5rem;
      }
      .header-logo img { width: 80px; height: 80px; object-fit: contain; }
      .header-center { text-align: center; line-height: 1.2; }
      .header-center h1 { font-weight: 700; font-size: 16pt; }
      .header-center p { font-size: 10pt; }
      .header-cert img { width: 100px; height: 80px; object-fit: contain; }
      .info-row { display: grid; grid-template-columns: 235px 1fr; gap: 8px; margin-bottom: 4px; font-size: 12px; }
      .info-value { border-bottom: 1px solid #111827; min-height: 18px; padding-left: 6px; }
      .paper table { border-collapse: collapse; width: 100%; }
      .paper th, .paper td { border: 1px solid #111827; padding: 6px 8px; font-size: 12px; }
      .paper th { background: #f7f7f7; text-transform: uppercase; font-size: 11px; }
      .rating-table th,
      .rating-table td { vertical-align: middle; }
      .rating-table td:first-child { text-align: left; }
      .rating-table th:nth-child(n+2),
      .rating-table td:nth-child(n+2) { text-align: center !important; }
      .section-label { background: #f3f4f6; font-weight: 700; text-transform: uppercase; }
      .comment-box { border: 1px solid #111827; min-height: 56px; padding: 8px; font-size: 12px; white-space: pre-wrap; }
      .no-print { display: block; }
      @media print {
        @page { size: 8.5in 11in; margin: 0.2in 0.25in; }
        .no-print, #sidebar { display: none !important; }
        html, body { height: auto !important; min-height: 0 !important; background: none !important; }
        body > div, body > div > * { min-height: 0 !important; height: auto !important; }
        main { margin-left: 0 !important; padding: 0 !important; min-height: 0 !important; height: auto !important; background: none !important; display: block !important; }
        section { padding: 0 !important; margin: 0 !important; }
        .min-h-screen { min-height: 0 !important; height: auto !important; }

        .paper { padding: 0.2rem 0.4rem !important; box-shadow: none !important; border-radius: 0 !important; }

        /* Header */
        .header-top { margin-top: 0 !important; margin-bottom: 0.1rem !important; gap: 0.3rem !important; }
        .header-logo img { width: 48px !important; height: 48px !important; }
        .header-cert img { width: 60px !important; height: 48px !important; }
        .header-center h1 { font-size: 11pt !important; }
        .header-center p { font-size: 6.5pt !important; line-height: 1.1 !important; }

        /* Title */
        .paper h2 { font-size: 9.5px !important; margin-bottom: 0.15rem !important; margin-top: 0 !important; }

        /* Info rows */
        .info-row { font-size: 8.5px !important; margin-bottom: 1px !important; gap: 3px !important; grid-template-columns: 175px 1fr !important; }
        .info-value { min-height: 12px !important; padding-left: 3px !important; }
        .mb-4 { margin-bottom: 0.2rem !important; }
        .mb-3 { margin-bottom: 0.18rem !important; }

        /* Direction text */
        .paper p { font-size: 8px !important; }

        /* All table cells */
        .paper th, .paper td { padding: 1.5px 3px !important; font-size: 8px !important; }
        .paper th { font-size: 7.5px !important; }

        /* Comment boxes */
        .comment-box { min-height: 22px !important; padding: 3px !important; font-size: 8px !important; }
        p.text-\[12px\] { font-size: 8px !important; }

        /* Signature */
        .mt-6 { margin-top: 0.2rem !important; }
        .h-20 { height: 2rem !important; }
        .w-64 { width: 9rem !important; }
        .w-64 p { font-size: 8px !important; }
      }
    </style>
  </head>
  <body class="bg-white text-[#0b1b3a]">
    <div class="min-h-screen">
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
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='studentAssistantDashboard.php'">
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='studentAssistant-my-sas.php'">
              <i class="fas fa-user-friends w-5"></i>
              <span>My SA's</span>
            </li>
            <li class="panel-nav-item active gap-2 cursor-pointer" onclick="window.location.href='studentAssistant-show-evaluation.php'">
              <i class="fas fa-check-circle w-5"></i>
              <span>Show Evaluation</span>
            </li>
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='studentAssistant-changePassword.php'">
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
        <header class="fixed top-0 left-0 md:left-64 right-0 z-20 h-14 flex items-center justify-between bg-white border-b border-slate-200 px-4 sm:px-6 shadow-sm no-print">
          <div class="flex items-center gap-2">
            <button id="sidebarToggle" class="md:hidden inline-flex items-center justify-center p-2 rounded bg-slate-700 text-white hover:bg-slate-800 focus:outline-none transition-colors" type="button">
              <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-[#0d4b84] text-lg font-semibold flex items-center gap-2">
              <i class="fas fa-file-alt"></i>
              View Evaluation
            </h2>
          </div>
          <div></div>
        </header>
        

        <section class="px-4 sm:px-6 pt-6">
          <div class="mx-auto max-w-6xl flex justify-end gap-2 mb-4 no-print">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-white px-4 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition-colors duration-150" onclick="window.location.href='studentAssistant-show-evaluation.php'">
              <i class="fas fa-arrow-left text-[10px]"></i> Back
            </button>
            <?php if ($evaluation !== null): ?>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-full bg-[#052c6a] px-4 py-1.5 text-xs font-semibold text-white hover:bg-[#0a3d8a] transition-colors duration-150" onclick="window.print()">
                <i class="fas fa-print text-[10px]"></i> Print
              </button>
            <?php endif; ?>
          </div>
          <?php if ($loadError !== ""): ?>
            <div class="mx-auto max-w-4xl rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($loadError) ?></div>
          <?php elseif ($evaluation !== null): ?>
            <div class="paper mx-auto max-w-6xl rounded-lg p-5 shadow">
              <header>
                <div class="header-top">
                  <div class="header-logo"><img src="../img/SMCCNEWLOGO.png" alt="Seal of Saint Michael College of Caraga" /></div>
                  <div class="header-center">
                    <h1>Saint Michael College of Caraga</h1>
                    <p>Brgy. 4, Nasipit, Agusan del Norte, Philippines<br />District 8, Brgy. Triangulo, Nasipit, Agusan del Norte, Philippines</p>
                    <p>Tel. Nos. +63 085 343-3251 / +63 085 283-3113</p>
                    <p><a href="http://www.smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">www.smccnasipit.edu.ph</a></p>
                  </div>
                  <div class="header-cert"><img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC ISO 9001 logo" /></div>
                </div>
              </header>

              <h2 class="mb-4 mt-1 text-center text-[15px] font-bold uppercase tracking-wide">Student Assistants' Evaluation Form</h2>

              <div class="mb-4">
                <div class="info-row"><span class="font-semibold">Name of Student Assistant:</span><span class="info-value"><?= htmlspecialchars($evaluation["applicant_name"]) ?></span></div>
                <div class="info-row"><span class="font-semibold">Program / Course:</span><span class="info-value"><?= htmlspecialchars($evaluation["program_course"]) ?></span></div>
                <div class="info-row"><span class="font-semibold">Semester &amp; School Year:</span><span class="info-value"><?= htmlspecialchars($displayTerm) ?></span></div>
                <div class="info-row"><span class="font-semibold">Area of Assignment:</span><span class="info-value"><?= htmlspecialchars($evaluation["assigned_office"]) ?></span></div>
                <div class="info-row"><span class="font-semibold">Head of Office:</span><span class="info-value"><?= htmlspecialchars($evaluation["head_name"]) ?></span></div>
                <div class="info-row"><span class="font-semibold">Date of Evaluation:</span><span class="info-value"><?= htmlspecialchars($displayDate) ?></span></div>
              </div>

              <div class="mb-4 flex flex-wrap items-center gap-5 text-[12px]">
                <span class="font-semibold">Evaluator:</span>
                <?php foreach ($evaluatorOptions as $option): ?>
                  <span class="inline-flex items-center gap-1">
                    <span class="inline-flex h-4 w-4 items-center justify-center border border-black text-[10px] leading-none">
                      <?= $evaluatorType === $option ? "&#10003;" : "" ?>
                    </span>
                    <span><?= htmlspecialchars($option) ?></span>
                  </span>
                <?php endforeach; ?>
              </div>

              <p class="mb-4 text-[12px]"><span class="font-semibold">Direction:</span> Please rate each item below to determine the performance of the assigned student assistant of your respective office/department. Put a check (&#10003;) to rate their performance.</p>

              <table class="mb-4 rating-table">
                <thead>
                  <tr><th style="width: 9%;">Scale</th><th style="width: 18%;">Verbal Description</th><th>Verbal Interpretation</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($ratingOptions as $scale => $option): ?>
                    <tr>
                      <td class="text-center font-semibold"><?= htmlspecialchars((string)$scale) ?></td>
                      <td><?= htmlspecialchars($option["label"] . " (" . $option["short"] . ")") ?></td>
                      <td><?= htmlspecialchars($option["interpretation"]) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>

              <table class="mb-4">
                <thead>
                  <tr><th rowspan="2" style="width: 47%;">Performance Indicators</th><th colspan="<?= count($ratingOptions) ?>">Rating</th></tr>
                  <tr>
                    <?php foreach ($ratingOptions as $scale => $option): ?>
                      <th><?= htmlspecialchars(((int)$scale === 1 ? "NI" : $option["label"]) . " (" . $scale . ")") ?></th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($evaluationSections as $section): ?>
                    <tr>
                      <td class="section-label"><?= htmlspecialchars($section["title"]) ?></td>
                      <?php foreach ($ratingOptions as $unusedScale => $unusedOption): ?>
                        <td class="text-center align-middle"></td>
                      <?php endforeach; ?>
                    </tr>
                    <?php foreach ($section["criteria"] as $fieldName => $label): $v = (int)($ratings[$fieldName] ?? 0); ?>
                      <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <?php foreach ($ratingOptions as $scale => $unusedOption): ?>
                          <td class="text-center align-middle"><?= $check($v, (int)$scale) ?></td>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </tbody>
              </table>

              <div class="mb-3">
                <p class="text-[12px] font-semibold">E. Strength(s):</p>
                <div class="comment-box"><?= htmlspecialchars($evaluation["strengths"] !== "" ? $evaluation["strengths"] : "N/A") ?></div>
              </div>
              <div class="mb-3">
                <p class="text-[12px] font-semibold">F. Area(s) for Improvement:</p>
                <div class="comment-box"><?= htmlspecialchars($evaluation["areas_improvement"] !== "" ? $evaluation["areas_improvement"] : "N/A") ?></div>
              </div>
              <div class="mb-3">
                <p class="text-[12px] font-semibold">G. Evaluator's Comment(s):</p>
                <div class="comment-box"><?= htmlspecialchars($evaluation["recommendations"] !== "" ? $evaluation["recommendations"] : "N/A") ?></div>
              </div>
              <div class="mt-6 mb-3 text-[12px] text-slate-700">
                <p class="font-semibold italic text-slate-900">Note for Retention</p>
                <p class="mt-2 leading-5 indent-8">A Student Assistant must obtain an overall performance rating of at least &quot;Good&quot; and comply with all provisions of the Student Assistant Scholarship Agreement to remain eligible for retention in the Student Assistant Scholarship Program.</p>
              </div>

              <div class="mt-6 flex justify-end">
                <div class="w-64 text-center text-[12px]">
                  <?php if ($evaluation["signature_data"] !== ""): ?>
                    <img src="<?= htmlspecialchars($evaluation["signature_data"]) ?>" alt="Evaluator Signature" class="h-20 w-full object-contain border-b border-slate-900" />
                  <?php else: ?>
                    <div class="h-20 border-b border-slate-900"></div>
                  <?php endif; ?>
                  <p class="mt-1">Evaluator's Signature</p>
                </div>
              </div>
            </div>
          <?php endif; ?>
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

