<?php
session_start();
if (empty($_SESSION["head_username"])) {
  header("Location: headLogin.php");
  exit;
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once "../db.php";
date_default_timezone_set("Asia/Manila");

$headUsername = trim((string)($_SESSION["head_username"] ?? ""));
$headName = trim((string)($_SESSION["head_name"] ?? ""));
if ($headName === "") {
  $headName = "Head of Office";
}
$headOffice = trim((string)($_SESSION["head_office"] ?? ""));
$applicationId = isset($_GET["application_id"]) ? (int)$_GET["application_id"] : 0;

$loadError = "";
$evaluation = null;
$ratings = [];

$ratingGroups = [
  "a" => [
    "score-a1" => "A.1 Accurate at work assigned",
    "score-a2" => "A.2 Always completes tasks",
    "score-a3" => "A.3 Works in a timely manner",
    "score-a4" => "A.4 Asks for more work when assigned tasks are done",
    "score-a5" => "A.5 Easily accepts new responsibilities",
  ],
  "b" => [
    "score-b1" => "B.1 Answers patrons' questions accurately",
    "score-b2" => "B.2 Deals with patrons well",
    "score-b3" => "B.3 Deals with personnel well",
    "score-b4" => "B.4 Knows how to effectively communicate with others",
    "score-b5" => "B.5 Has a good relationship with other student assistants",
  ],
  "c" => [
    "score-c1" => "C.1 Perfect attendance",
    "score-c2" => "C.2 Reports duty on time; rarely comes late",
    "score-c3" => "C.3 Following assigned schedule",
    "score-c4" => "C.4 Able to work without direct supervision",
    "score-c5" => "C.5 Carries out instructions successfully",
  ],
];

if ($headOffice === "" && $headUsername !== "") {
  $officeStmt = $conn->prepare("SELECT office FROM head_offices WHERE username = ? AND status = 'active' LIMIT 1");
  if ($officeStmt) {
    $officeStmt->bind_param("s", $headUsername);
    if ($officeStmt->execute()) {
      $officeResult = $officeStmt->get_result();
      $officeRow = $officeResult ? $officeResult->fetch_assoc() : null;
      if (is_array($officeRow)) {
        $headOffice = trim((string)($officeRow["office"] ?? ""));
        $_SESSION["head_office"] = $headOffice;
      }
      if ($officeResult instanceof mysqli_result) {
        $officeResult->free();
      }
    }
    $officeStmt->close();
  }
}

if ($applicationId === 0) {
  $loadError = "No applicant selected.";
} elseif ($headOffice === "") {
  $loadError = "No office is assigned to this head account.";
} else {
  $tableResult = $conn->query("SHOW TABLES LIKE 'department_head_evaluations'");
  $hasTable = $tableResult instanceof mysqli_result && $tableResult->num_rows > 0;
  if ($tableResult instanceof mysqli_result) {
    $tableResult->free();
  }

  if (!$hasTable) {
    $loadError = "No saved evaluation found yet.";
  } else {
    $officeKey = strtolower(trim($headOffice));
    if ($applicationId > 0) {
      $stmt = $conn->prepare(
        "SELECT
          dhe.applicant_name,
          dhe.semester,
          dhe.school_year,
          dhe.assigned_office,
          dhe.head_name,
          dhe.evaluation_date,
          dhe.ratings_json,
          dhe.strengths,
          dhe.recommendations,
          dhe.signature_data,
          a.program_course
        FROM department_head_evaluations dhe
        INNER JOIN applications a ON a.id = dhe.application_id
        WHERE dhe.application_id = ?
          AND dhe.head_username = ?
          AND LOWER(TRIM(COALESCE(a.assigned_office, ''))) = ?
        LIMIT 1"
      );
      if ($stmt) {
        $stmt->bind_param("iss", $applicationId, $headUsername, $officeKey);
        if ($stmt->execute()) {
          $result = $stmt->get_result();
          $row = $result ? $result->fetch_assoc() : null;
          if (is_array($row)) {
            $evaluation = [
              "applicant_name" => trim((string)($row["applicant_name"] ?? "")),
              "program_course" => trim((string)($row["program_course"] ?? "")),
              "semester" => trim((string)($row["semester"] ?? "")),
              "school_year" => trim((string)($row["school_year"] ?? "")),
              "assigned_office" => trim((string)($row["assigned_office"] ?? "")),
              "head_name" => trim((string)($row["head_name"] ?? "")),
              "evaluation_date" => trim((string)($row["evaluation_date"] ?? "")),
              "strengths" => trim((string)($row["strengths"] ?? "")),
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
            $loadError = "No saved evaluation found for this applicant.";
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
    } else {
      $scholarTableResult = $conn->query("SHOW TABLES LIKE 'institutional_scholar_records'");
      $hasScholarTable = $scholarTableResult instanceof mysqli_result && $scholarTableResult->num_rows > 0;
      if ($scholarTableResult instanceof mysqli_result) {
        $scholarTableResult->free();
      }
      if (!$hasScholarTable) {
        $loadError = "Scholar records table is not available.";
      } else {
        $stmt = $conn->prepare(
          "SELECT
            dhe.applicant_name,
            dhe.semester,
            dhe.school_year,
            dhe.assigned_office,
            dhe.head_name,
            dhe.evaluation_date,
            dhe.ratings_json,
            dhe.strengths,
            dhe.recommendations,
            dhe.signature_data,
            COALESCE(NULLIF(TRIM(isr.program_year), ''), '') AS program_course
          FROM department_head_evaluations dhe
          INNER JOIN institutional_scholar_records isr ON isr.id = (0 - dhe.application_id)
          WHERE dhe.application_id = ?
            AND dhe.head_username = ?
            AND dhe.application_id < 0
            AND isr.category = 'student_assistant'
            AND isr.contract_ended = 0
            AND LOWER(TRIM(COALESCE(isr.assigned_office, ''))) = ?
          LIMIT 1"
        );
        if ($stmt) {
          $stmt->bind_param("iss", $applicationId, $headUsername, $officeKey);
          if ($stmt->execute()) {
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            if (is_array($row)) {
              $evaluation = [
                "applicant_name" => trim((string)($row["applicant_name"] ?? "")),
                "program_course" => trim((string)($row["program_course"] ?? "")),
                "semester" => trim((string)($row["semester"] ?? "")),
                "school_year" => trim((string)($row["school_year"] ?? "")),
                "assigned_office" => trim((string)($row["assigned_office"] ?? "")),
                "head_name" => trim((string)($row["head_name"] ?? "")),
                "evaluation_date" => trim((string)($row["evaluation_date"] ?? "")),
                "strengths" => trim((string)($row["strengths"] ?? "")),
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
    <script src="https://cdn.tailwindcss.com"></script>
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
        @page { size: 8.5in 13in; margin: 0.28in; }
        .no-print, #sidebar { display: none !important; }
        main { margin-left: 0 !important; padding-top: 0 !important; }
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
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='headDashboard.php'">
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='headDashboard.php?tab=my-sas'">
              <i class="fas fa-user-friends w-5"></i>
              <span>My SA's</span>
            </li>
            <li class="panel-nav-item active gap-2 cursor-pointer" onclick="window.location.href='show-evaluation.php'">
              <i class="fas fa-check-circle w-5"></i>
              <span>Show Evaluation</span>
            </li>
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='head-changePassword.php'">
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
          <div class="flex items-center gap-2">
            <button type="button" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700" onclick="window.location.href='show-evaluation.php'">Back</button>
            <?php if ($evaluation !== null): ?>
              <button type="button" class="rounded-full bg-[#052c6a] px-4 py-2 text-xs font-semibold text-white" onclick="window.print()">Print</button>
            <?php endif; ?>
          </div>
        </header>

        <section class="px-4 sm:px-6 pt-6">
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

              <p class="mb-4 text-[12px]"><span class="font-semibold">Direction:</span> Please rate each item below to determine the performance of the assigned student assistant of your respective office/department. Put a check (&#10003;) to rate their performance.</p>

              <table class="mb-4 rating-table">
                <thead>
                  <tr><th style="width: 9%;">Scale</th><th style="width: 18%;">Verbal Description</th><th>Verbal Interpretation</th></tr>
                </thead>
                <tbody>
                  <tr><td class="text-center font-semibold">4</td><td>Excellent</td><td>This rating is given to student assistants who consistently exceed expectations and demonstrate outstanding performance in their assigned tasks.</td></tr>
                  <tr><td class="text-center font-semibold">3</td><td>Good</td><td>This rating is given to student assistants who consistently meet expectations and perform their assigned tasks satisfactorily.</td></tr>
                  <tr><td class="text-center font-semibold">2</td><td>Fair</td><td>This rating is given to student assistants who occasionally meet expectations but may have areas for improvement.</td></tr>
                  <tr><td class="text-center font-semibold">1</td><td>Poor</td><td>This rating is given to student assistants who consistently fail to meet expectations and demonstrate unsatisfactory performance in their assigned tasks.</td></tr>
                </tbody>
              </table>

              <table class="mb-4">
                <thead>
                  <tr><th rowspan="2" style="width: 47%;">Performance Indicators</th><th colspan="4">Rating</th></tr>
                  <tr><th>Excellent (4)</th><th>Good (3)</th><th>Fair (2)</th><th>Poor (1)</th></tr>
                </thead>
                <tbody>
                  <tr><td class="section-label">A. Quality and Quantity of Work</td><td class="text-center align-middle"></td><td class="text-center align-middle"></td><td class="text-center align-middle"></td><td class="text-center align-middle"></td></tr>
                  <?php foreach ($ratingGroups["a"] as $fieldName => $label): $v = (int)($ratings[$fieldName] ?? 0); ?>
                    <tr><td><?= htmlspecialchars($label) ?></td><td class="text-center align-middle"><?= $check($v, 4) ?></td><td class="text-center align-middle"><?= $check($v, 3) ?></td><td class="text-center align-middle"><?= $check($v, 2) ?></td><td class="text-center align-middle"><?= $check($v, 1) ?></td></tr>
                  <?php endforeach; ?>
                  <tr><td class="section-label">B. Interpersonal Skills</td><td class="text-center align-middle"></td><td class="text-center align-middle"></td><td class="text-center align-middle"></td><td class="text-center align-middle"></td></tr>
                  <?php foreach ($ratingGroups["b"] as $fieldName => $label): $v = (int)($ratings[$fieldName] ?? 0); ?>
                    <tr><td><?= htmlspecialchars($label) ?></td><td class="text-center align-middle"><?= $check($v, 4) ?></td><td class="text-center align-middle"><?= $check($v, 3) ?></td><td class="text-center align-middle"><?= $check($v, 2) ?></td><td class="text-center align-middle"><?= $check($v, 1) ?></td></tr>
                  <?php endforeach; ?>
                  <tr><td class="section-label">C. Attendance and Reliability</td><td class="text-center align-middle"></td><td class="text-center align-middle"></td><td class="text-center align-middle"></td><td class="text-center align-middle"></td></tr>
                  <?php foreach ($ratingGroups["c"] as $fieldName => $label): $v = (int)($ratings[$fieldName] ?? 0); ?>
                    <tr><td><?= htmlspecialchars($label) ?></td><td class="text-center align-middle"><?= $check($v, 4) ?></td><td class="text-center align-middle"><?= $check($v, 3) ?></td><td class="text-center align-middle"><?= $check($v, 2) ?></td><td class="text-center align-middle"><?= $check($v, 1) ?></td></tr>
                  <?php endforeach; ?>
                </tbody>
              </table>

              <div class="mb-3">
                <p class="text-[12px] font-semibold">D. Strength(s)/Areas for Improvement:</p>
                <div class="comment-box"><?= htmlspecialchars($evaluation["strengths"] !== "" ? $evaluation["strengths"] : "N/A") ?></div>
              </div>
              <div class="mb-3">
                <p class="text-[12px] font-semibold">E. Evaluator's Comment(s)/Recommendation:</p>
                <div class="comment-box"><?= htmlspecialchars($evaluation["recommendations"] !== "" ? $evaluation["recommendations"] : "N/A") ?></div>
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
