<?php
session_start();
require_once "../db.php";

$applicationId = (int)($_GET["id"] ?? 0);
$application = [];
$uploadedRequirements = [];
$loadError = "";
$actionMessage = "";
$batchOptions = ["Batch 1", "Batch 2", "Batch 3", "Batch 4", "Batch 5"];
$batchColumnExists = false;
$isStudentAssistantApplicant = false;
$grantRequirements = [
  1 => [
    "2x2 ID Picture",
    "Application Letter",
    "Resume",
    "Form 138 / Report of Grades",
    "Certificate of Indigency",
    "Certificate of Good Moral Character"
  ],
  2 => [
    "Certification (Top 1 or Top 2)",
    "2x2 ID Picture",
    "Form 138 / Grades",
    "Certificate of Indigency",
    "Certificate of Good Moral Character"
  ],
  3 => [
    "2x2 ID Picture",
    "Endorsement Letter from OSAS"
  ],
  4 => [
    "2x2 ID Picture",
    "Endorsement Letter"
  ],
  5 => [
    "2x2 ID Picture",
    "Certification from External Linkages Coordinator",
    "Proof of Relationship",
    "Endorsement Letter from Retiree"
  ],
  6 => [
    "2x2 ID Picture",
    "PWD ID (2 photocopies)"
  ],
  7 => [
    "2x2 ID Picture",
    "Certification from HRMDO"
  ],
  8 => [
    "2x2 ID Picture",
    "Certification from HRMDO"
  ],
  9 => [
    "2x2 ID Picture"
  ],
  10 => [
    "2x2 ID Picture",
    "Endorsement Letter from Station Manager"
  ],
  11 => [
    "2x2 ID Picture",
    "Endorsement Letter from Publication In-Charge"
  ],
  12 => [
    "2x2 ID Picture",
    "Board Resolution / Certification"
  ],
  13 => [
    "2x2 ID Picture",
    "Board Resolution / Certification",
    "Endorsement Letter"
  ],
  14 => [
    "2x2 ID Picture",
    "Certification from Alumni Association"
  ],
];

function app_value(array $application, string $key): string {
  return isset($application[$key]) ? (string)$application[$key] : "";
}

$batchColumnResult = $conn->query("SHOW COLUMNS FROM applications LIKE 'batch'");
if ($batchColumnResult instanceof mysqli_result) {
  $batchColumnExists = $batchColumnResult->num_rows > 0;
  $batchColumnResult->free();
}

if ($applicationId <= 0) {
  $loadError = "Missing application id.";
} else {
  $stmt = $conn->prepare("SELECT * FROM applications WHERE id = ?");
  if ($stmt) {
    $stmt->bind_param("i", $applicationId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($row) {
      $application = $row;
      $isStudentAssistantApplicant = (int)($application["grant_id"] ?? 0) === 1;
    } else {
      $loadError = "Application not found.";
    }
    $stmt->close();
  } else {
    $loadError = "Failed to prepare application lookup.";
  }
}

$postAction = isset($_POST["application_action"]) ? (string)$_POST["application_action"] : "";
$postId = (int)($_POST["application_id"] ?? 0);
$postBatch = trim((string)($_POST["application_batch"] ?? ""));
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if ($postId <= 0 || ($postAction !== "approve" && $postAction !== "decline")) {
    $actionMessage = "Invalid action request.";
  } elseif ($postAction === "approve" && $isStudentAssistantApplicant && !$batchColumnExists) {
    $actionMessage = "Batch assignment is not yet configured. Please add a 'batch' column to the applications table first.";
  } elseif ($postAction === "approve" && $isStudentAssistantApplicant && ($postBatch === "" || !in_array($postBatch, $batchOptions, true))) {
    $actionMessage = "Please select a valid batch before approving.";
  } else {
    $newStatus = $postAction === "approve" ? "Approved" : "Rejected";
    $shouldUpdateBatch = $postAction === "approve" && $isStudentAssistantApplicant;
    $updateSql = $shouldUpdateBatch
      ? "UPDATE applications SET status = ?, batch = ? WHERE id = ?"
      : "UPDATE applications SET status = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    if ($updateStmt) {
      if ($shouldUpdateBatch) {
        $updateStmt->bind_param("ssi", $newStatus, $postBatch, $postId);
      } else {
        $updateStmt->bind_param("si", $newStatus, $postId);
      }
      $updateStmt->execute();
      $updateStmt->close();
      header("Location: applicant.php");
      exit;
    } else {
      $actionMessage = "Failed to update application status.";
    }
  }
}

$uploadsError = "";
if ($applicationId > 0) {
  $uploadStmt = $conn->prepare(
    "SELECT requirement_label, original_file_name, stored_path
     FROM application_uploads
     WHERE application_id = ?
     ORDER BY uploaded_at ASC"
  );
  if ($uploadStmt) {
    $uploadStmt->bind_param("i", $applicationId);
    $uploadStmt->execute();
    $uploadsResult = $uploadStmt->get_result();
    if ($uploadsResult) {
      while ($uploadRow = $uploadsResult->fetch_assoc()) {
        $uploadedRequirements[] = $uploadRow;
      }
    }
    $uploadStmt->close();
  } else {
    $uploadsError = "Failed to prepare uploads lookup.";
  }
}

$scholarshipType = strtolower(app_value($application, "scholarship_type"));
$grantId = (int)app_value($application, "grant_id");
$selectedRequirements = $grantRequirements[$grantId] ?? [];
$uploadsByLabel = [];
foreach ($uploadedRequirements as $upload) {
  $label = isset($upload["requirement_label"]) ? (string)$upload["requirement_label"] : "";
  if ($label === "") {
    $label = "Document";
  }
  if (!isset($uploadsByLabel[$label])) {
    $uploadsByLabel[$label] = [];
  }
  $uploadsByLabel[$label][] = $upload;
}
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Application Form</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap" rel="stylesheet" />
    <style>
      @page {
        size: 8.5in 13in;
        margin: 6mm 6mm 0mm 0mm;
      }
      ::-webkit-scrollbar { width: 6px; }
      ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%); border-radius: 999px; }
      body { font-family: "Times New Roman", serif; }
      header { margin-bottom: 1rem; text-align: center; }
      .header-top { display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem; }
      .header-left { display: flex; align-items: center; gap: 0.5rem; }
      .header-left img { width: 80px; height: 80px; object-fit: contain; }
      .header-left-text { line-height: 1.1; text-align: left; }
      .header-left-text h1 { font-weight: 700; font-size: 16pt; margin: 0; }
      .header-left-text p { margin: 0; font-size: 10pt; }
      .header-right { display: flex; flex-direction: column; gap: 0.2rem; align-items: center; }
      .header-right img { width: 100px; height: 80px; object-fit: contain; }
      .data-value { font-family: Arial, sans-serif; }
      @media print {
        body { background: #ffffff; margin: 0 !important; }
        #sidebar { display: none !important; }
        main { margin-left: 0 !important; }
        .no-print { display: none !important; }
        .print-area { margin: 0; padding: 0; }
        .print-area .header-top {
          margin-top: 0 !important;
        }
        .print-area .border,
        .print-area .shadow-sm {
          border: 0 !important;
          box-shadow: none !important;
        }
        .print-area .max-w-5xl {
          max-width: none !important;
        }
        .print-area .max-w-5xl {
          min-height: 12.7in;
          display: flex;
          flex-direction: column;
        }
        .print-area .max-w-3xl {
          max-width: 100% !important;
        }
        .print-area { margin-top: 0 !important; }
        .print-area .mb-6 { margin-bottom: 0rem !important; }
        .print-area .mt-6 { margin-top: 0.20rem !important; }
        .print-area .mt-8 { margin-top: 0.5rem !important; }
        .print-area .gap-y-8 { row-gap: 0.5rem !important; }
        .print-area {
          transform: scale(1);
          transform-origin: top center;
        }
        .print-area { line-height: 1.45; }
        .print-area .text-xs { line-height: 1.45 !important; }
        .print-area .text-sm { line-height: 1.5 !important; }
        .print-area .header-left img { width: 64px !important; height: 64px !important; }
        .print-area .header-right img { width: 84px !important; height: 68px !important; }
        .print-area .header-left-text h1 { font-size: 14pt !important; }
        .print-area .header-left-text p { font-size: 9pt !important; }
        .print-area .specify-line {
          border-bottom: 1px solid #000 !important;
          display: inline-block !important;
          min-height: 12px !important;
        }
        .print-area .specify-line input {
          width: 100% !important;
          border: 0 !important;
          outline: 0 !important;
          background: transparent !important;
        }
        .print-area .certify-text { margin-bottom: 0.35rem !important; }
        .print-area .footer-print {
          margin-top: 0.2rem !important;
          margin-bottom: 0 !important;
        }
        .print-area .footer-print img {
          display: block !important;
          width: 100% !important;
          height: auto !important;
        }
        .print-area .signature-group { row-gap: 0.9rem !important; }
        .print-area .signature-block { line-height: 1.8 !important; }
        .print-area .box-print { width: 160px !important; }
        .print-area .print-spacer { flex: 1 1 auto; }
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
  <body class="bg-white font-sans">
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
<li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="adminDashboard.php" onclick="window.location.href='adminDashboard.php'">
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="applicant.php" onclick="window.location.href='applicant.php'">
              <i class="fas fa-user-graduate w-5"></i>
              <span>Applicants</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="approved.php" onclick="window.location.href='approved.php'">
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="interviewEvaluation.php" onclick="window.location.href='interviewEvaluation.php'">
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="ranks.php" onclick="window.location.href='ranks.php'">
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="list-of-qualified.php" onclick="window.location.href='list-of-qualified.php'">
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="department-evaluation-list.php" onclick="window.location.href='department-evaluation-list.php'">
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="summary-report.php" onclick="window.location.href='summary-report.php'">
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="institutional-scholars.php" onclick="window.location.href='institutional-scholars.php'">
              <i class="fas fa-chart-line w-5"></i>
              <span>Institutional Scholars</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer" data-nav="accounts.php" onclick="window.location.href='accounts.php'">
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

      <!-- Main content -->
      <main class="ml-0 md:ml-64 flex flex-col min-h-screen">
        <!-- Top bar -->
        <header class="no-print fixed top-0 left-0 md:left-64 right-0 z-20 flex items-center justify-between bg-white text-slate-700 text-xs px-4 py-2 border-b border-slate-200 shadow-sm">
          <div class="flex items-center gap-2">
            <button id="sidebarToggle" class="md:hidden inline-flex items-center justify-center p-2 rounded bg-[#0d8ddb] focus:outline-none" type="button">
              <i class="fas fa-bars"></i>
            </button>
            <span class="text-[11px] font-semibold md:hidden">Admission &amp; Scholarship</span>
          </div>
          <div class="flex gap-2 text-xs">
            <button class=" md:hidden bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 flex items-center gap-1 font-normal" type="button">
              <i class="fas fa-user"></i>
              Admin panel
            </button>
            <button class=" md:hidden bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 font-normal" type="button">
              Account
            </button>
          </div>
        </header>

        <section class="mt-12 px-4 sm:px-6 py-4 bg-[#eef2f7] print-area">
          <div class="bg-white border border-[#0d8ddb] rounded shadow-sm p-4 md:p-6">
            <div class="max-w-5xl mx-auto">
              <div class="mb-4 flex items-center justify-end no-print">
                <button
                  class="inline-flex items-center gap-2 rounded border border-[#0d8ddb] px-3 py-2 text-xs font-semibold text-[#0d8ddb] hover:bg-[#0d8ddb] hover:text-white transition"
                  type="button"
                  onclick="window.print()"
                >
                  <i class="fas fa-print"></i>
                  Print Paper Form
                </button>
              </div>
              <?php if ($loadError !== ""): ?>
                <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                  <?= htmlspecialchars($loadError) ?>
                </div>
              <?php endif; ?>
              <?php if ($actionMessage !== ""): ?>
                <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                  <?= htmlspecialchars($actionMessage) ?>
                </div>
              <?php endif; ?>
              <header>
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
              </header>

              <section class="text-center mb-6">
                <h1 class="font-bold text-base">APPLICATION FORM</h1>
                <p class="font-semibold text-sm">For Institutional Scholars/Grantees</p>
              </section>

              <form>
                <fieldset class="max-w-3xl mx-auto">
                  <legend class="font-semibold text-sm mb-2">Type of Scholarship/Grant:</legend>
                  <div class="flex flex-wrap justify-between max-w-3xl mx-auto">
                    <div class="flex flex-col space-y-1 w-1/2 min-w-[180px]">
                      <label class="inline-flex items-center space-x-2">
                        <input class="w-4 h-4 border border-black" type="checkbox" <?= $scholarshipType === "academic" ? "checked" : "" ?> />
                        <span class="text-sm">Academic</span>
                      </label>
                      <label class="inline-flex items-center space-x-2">
                        <input class="w-4 h-4 border border-black" type="checkbox" <?= $scholarshipType === "kabayani" ? "checked" : "" ?> />
                        <span class="text-sm">Kabayani</span>
                      </label>
                      <label class="text-xs pl-6 pt-0.5">
                        Please specify:
                        <span class="specify-line inline-block border-b border-black w-36">
                          <input type="text" value="<?= htmlspecialchars(app_value($application, "kabayani_specify")) ?>" />
                        </span>
                      </label>
                    </div>
                    <div class="flex flex-col space-y-1 w-1/2 min-w-[180px]">
                      <label class="inline-flex items-center space-x-2">
                        <input class="w-4 h-4 border border-black" type="checkbox" <?= $scholarshipType === "student assistance" ? "checked" : "" ?> />
                        <span class="text-sm">Student Assistance</span>
                      </label>
                      <label class="inline-flex items-center space-x-2">
                        <input class="w-4 h-4 border border-black" type="checkbox" <?= $scholarshipType === "others" ? "checked" : "" ?> />
                        <span class="text-sm">Others</span>
                      </label>
                      <label class="text-xs pl-6 pt-0.5">
                        Please specify:
                        <span class="specify-line inline-block border-b border-black w-44">
                          <input type="text" value="<?= htmlspecialchars(app_value($application, "others_specify")) ?>" />
                        </span>
                      </label>
                    </div>
                  </div>
                </fieldset>

                <br />

                <div class="max-w-3xl mx-auto">
                  <p class="font-serif font-semibold text-sm mb-2">Scholar/Grantee's Profile:</p>
                  <div class="space-y-1 text-xs font-serif">
                    <div class="flex items-center">
                      <label class="w-44">Name of Applicant</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "applicant_name")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Program/Course Enrolled</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "program_course")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Year Level</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "year_level")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">School Year</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "school_year")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Semester</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "semester")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Permanent Address</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "permanent_address")) ?>
                      </span>
                      <label class="ml-4 w-14">Gender</label>
                      <span>:</span>
                      <span class="data-value border-b border-black w-20 ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "gender")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Date of Birth</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "date_of_birth")) ?>
                      </span>
                      <label class="ml-4 w-14">Age</label>
                      <span>:</span>
                      <span class="data-value border-b border-black w-20 ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "age")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Contact Number</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "contact_number")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Email Address</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "email_address")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-72">Estimated Gross Income of the Family/Month</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "estimated_income")) ?>
                      </span>
                    </div>
                  </div>

                  <div class="mt-4 space-y-1 text-xs font-serif">
                    <div class="flex items-center">
                      <label class="w-44">Mother's Name</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "mother_name")) ?>
                      </span>
                      <label class="ml-4 w-14">Age:</label>
                      <span class="data-value border-b border-black w-20 ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "mother_age")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Contact Number</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "mother_contact")) ?>
                      </span>
                      <label class="ml-4 w-20">Occupation:</label>
                      <span class="data-value border-b border-black w-44 ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "mother_occupation")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Company's Name</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "mother_company_name")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Company's Address</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "mother_company_address")) ?>
                      </span>
                    </div>
                  </div>

                  <div class="mt-4 space-y-1 text-xs font-serif">
                    <div class="flex items-center">
                      <label class="w-44">Father's Name</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "father_name")) ?>
                      </span>
                      <label class="ml-4 w-14">Age:</label>
                      <span class="data-value border-b border-black w-20 ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "father_age")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Contact Number</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "father_contact")) ?>
                      </span>
                      <label class="ml-4 w-20">Occupation:</label>
                      <span class="data-value border-b border-black w-44 ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "father_occupation")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Company's Name</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "father_company_name")) ?>
                      </span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Company's Address</label>
                      <span>:</span>
                      <span class="data-value border-b border-black flex-grow ml-2 h-4">
                        <?= htmlspecialchars(app_value($application, "father_company_address")) ?>
                      </span>
                    </div>
                  </div>
                </div>

                <p class="certify-text text-xs font-serif mt-6 mb-6 max-w-3xl mx-auto">I certify that the above information is true and correct.</p>

                <div class="max-w-3xl mx-auto flex flex-wrap justify-between gap-y-8 signature-group">
                  <div class="w-full sm:w-[45%] signature-block">
                    <div class="border-b border-black w-full h-4 mb-1"></div>
                    <p class="text-xs font-serif">Name and signature of applicant</p>
                  </div>
                  <div class="w-full sm:w-[45%] signature-block">
                    <div class="border-b border-black w-full h-4 mb-1"></div>
                    <p class="text-xs font-serif">
                      Name and signature of personnel<br />
                      <span class="text-[10px]">(If the scholar/grantee is personnel dependent)</span>
                    </p>
                  </div>
                </div>

                <div class="max-w-3xl mx-auto flex flex-wrap justify-between gap-y-8 mt-8 signature-group">
                  <div class="w-full sm:w-[45%] signature-block">
                    <p class="text-xs font-serif mb-6">Recommending approval:</p>
                    <div class="border-b border-black w-full h-4 mb-1"></div>
                    <p class="text-xs font-serif">Head, Admission &amp; Scholarship</p>
                  </div>
                  <div class="w-full sm:w-[45%] signature-block">
                    <p class="text-xs font-serif mb-6">Approved by:</p>
                    <div class="border-b border-black w-full h-4 mb-1"></div>
                    <p class="text-xs font-serif">Head, HRMDO</p>
                  </div>
                </div>

                <div class="max-w-3xl mx-auto mt-6">
                  <div class="flex items-center">
                    <img src="../img/box.png" alt="Box" class="w-48 h-auto box-print" />
                  </div>
                </div>
              </form>

              <footer class="max-w-3xl mx-auto mt-6 footer-print">
                <div class="flex items-center justify-between text-[10px] font-semibold text-black">
                  <img src="../img/footer.png" alt="Footer" />
                </div>
              </footer>
            </div>
          </div>
        </section>

        <!-- Uploaded requirements display -->
        <section class="px-4 sm:px-6 pb-8 no-print bg-[#eef2f7]">
          <div class="bg-white border border-[#0d8ddb] rounded shadow-sm p-4 md:p-6">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-sm font-semibold text-[#052c6a]">Uploaded Requirements</h2>
              <?php if ($uploadsError !== ""): ?>
                <span class="text-[11px] text-red-600"><?= htmlspecialchars($uploadsError) ?></span>
              <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full border text-xs">
                <thead>
                  <tr class="bg-[#f1f5f9] text-[#052c6a]">
                    <th class="border px-3 py-2 text-left font-semibold">Requirement</th>
                    <th class="border px-3 py-2 text-left font-semibold">File</th>
                    <th class="border px-3 py-2 text-center font-semibold w-32">Action</th>
                  </tr>
                </thead>
                <tbody class="text-[#052c6a]">
                  <?php if (!empty($selectedRequirements)): ?>
                    <?php foreach ($selectedRequirements as $requirementLabel): ?>
                      <?php
                        $uploadsForRequirement = $uploadsByLabel[$requirementLabel] ?? [];
                        $primaryUpload = $uploadsForRequirement[0] ?? null;
                        $storedPath = $primaryUpload["stored_path"] ?? "";
                        $downloadPath = $storedPath !== "" ? "../" . ltrim((string)$storedPath, "/") : "";
                        $extraCount = count($uploadsForRequirement) - 1;
                      ?>
                      <tr>
                        <td class="border px-3 py-2">
                          <?= htmlspecialchars($requirementLabel) ?>
                        </td>
                        <td class="border px-3 py-2 truncate">
                          <?php if ($primaryUpload): ?>
                            <?= htmlspecialchars((string)($primaryUpload["original_file_name"] ?? "")) ?>
                            <?php if ($extraCount > 0): ?>
                              <span class="text-[10px] text-slate-600">(<?= $extraCount ?> more)</span>
                            <?php endif; ?>
                          <?php else: ?>
                            <span class="text-slate-500">Not uploaded</span>
                          <?php endif; ?>
                        </td>
                        <td class="border px-3 py-2 text-center">
                          <?php if ($downloadPath !== ""): ?>
                            <div class="flex items-center justify-center gap-3">
                              <a href="<?= htmlspecialchars($downloadPath) ?>" class="text-[#0d8ddb] hover:underline" target="_blank" rel="noopener">
                                View
                              </a>
                              <a href="<?= htmlspecialchars($downloadPath) ?>" class="text-[#0d8ddb] hover:underline" download>
                                Download
                              </a>
                            </div>
                          <?php else: ?>
                            <span class="text-slate-500">Unavailable</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php elseif (empty($uploadedRequirements)): ?>
                    <tr>
                      <td colspan="3" class="border px-3 py-3 text-center text-[#052c6a]">
                        No uploaded requirements found.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($uploadedRequirements as $upload): ?>
                      <?php
                        $storedPath = isset($upload["stored_path"]) ? (string)$upload["stored_path"] : "";
                        $downloadPath = "../" . ltrim($storedPath, "/");
                      ?>
                      <tr>
                        <td class="border px-3 py-2">
                          <?= htmlspecialchars((string)($upload["requirement_label"] ?? "Document")) ?>
                        </td>
                        <td class="border px-3 py-2 truncate">
                          <?= htmlspecialchars((string)($upload["original_file_name"] ?? "")) ?>
                        </td>
                        <td class="border px-3 py-2 text-center">
                          <?php if ($storedPath !== ""): ?>
                            <div class="flex items-center justify-center gap-3">
                              <a href="<?= htmlspecialchars($downloadPath) ?>" class="text-[#0d8ddb] hover:underline" target="_blank" rel="noopener">
                                View
                              </a>
                              <a href="<?= htmlspecialchars($downloadPath) ?>" class="text-[#0d8ddb] hover:underline" download>
                                Download
                              </a>
                            </div>
                          <?php else: ?>
                            <span class="text-slate-500">Unavailable</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="px-4 sm:px-6 pb-10 no-print bg-[#eef2f7]">
          <div class="bg-white border border-[#0d8ddb] rounded shadow-sm p-4 md:p-6">
            <form
              id="applicationActionForm"
              class="flex flex-wrap items-center justify-end gap-3"
              method="post"
            >
              <input type="hidden" name="application_id" value="<?= htmlspecialchars((string)$applicationId) ?>" />
              <input type="hidden" name="application_action" id="applicationActionInput" value="" />
              <?php if ($isStudentAssistantApplicant && $batchColumnExists): ?>
                <label for="applicationBatchInput" class="text-xs font-semibold text-[#052c6a]">
                  Assign Batch
                </label>
                <select
                  id="applicationBatchInput"
                  name="application_batch"
                  class="rounded border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] focus:outline-none"
                  aria-label="Assign applicant batch"
                >
                  <option value="">Select batch...</option>
                  <?php foreach ($batchOptions as $batchOption): ?>
                    <option
                      value="<?= htmlspecialchars($batchOption) ?>"
                      <?= app_value($application, "batch") === $batchOption ? "selected" : "" ?>
                    >
                      <?= htmlspecialchars($batchOption) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($isStudentAssistantApplicant): ?>
                <p class="text-[11px] font-semibold text-red-600">
                  Batch column not found in applications table.
                </p>
              <?php endif; ?>
              <button
                class="rounded bg-[#16a34a] px-4 py-2 text-xs font-semibold text-white hover:bg-[#15803d] transition"
                type="button"
                data-action="approve"
              >
                Approve Application
              </button>
              <button
                class="rounded border border-[#f44336] px-4 py-2 text-xs font-semibold text-[#f44336] hover:bg-[#f44336] hover:text-white transition"
                type="button"
                data-action="decline"
              >
                Decline Application
              </button>
            </form>
          </div>
        </section>
      </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      document.addEventListener("DOMContentLoaded", () => {
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
      });

      document.addEventListener("DOMContentLoaded", () => {
        const form = document.getElementById("applicationActionForm");
        const actionInput = document.getElementById("applicationActionInput");
        const batchInput = document.getElementById("applicationBatchInput");
        const actionButtons = document.querySelectorAll("[data-action]");

        const labels = {
          approve: {
            title: "Approve application?",
            text: "This applicant will move to approved list.",
            confirm: "Yes, approve",
          },
          decline: {
            title: "Decline application?",
            text: "This applicant will move to declined list.",
            confirm: "Yes, decline",
          },
        };

        if (!form || !actionInput || actionButtons.length === 0) return;

        actionButtons.forEach((button) => {
          button.addEventListener("click", () => {
            const action = button.dataset.action || "";
            const label = labels[action];
            if (!label) return;

            if (action === "approve" && batchInput && batchInput.value.trim() === "") {
              Swal.fire({
                title: "Batch is required",
                text: "Please select a batch before approving this application.",
                icon: "info",
                confirmButtonColor: "#0d8ddb",
              });
              return;
            }

            const confirmText = action === "approve" && batchInput
              ? `This applicant will move to approved list under ${batchInput.value}.`
              : label.text;

            Swal.fire({
              title: label.title,
              text: confirmText,
              icon: "warning",
              showCancelButton: true,
              confirmButtonText: label.confirm,
              cancelButtonText: "Cancel",
              confirmButtonColor: "#0d8ddb",
              cancelButtonColor: "#9ca3af",
            }).then((result) => {
              if (result.isConfirmed) {
                actionInput.value = action;
                form.submit();
              }
            });
          });
        });
      });
    </script>
  <script>
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("sidebar");
  if (!sidebar) {
    return;
  }

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
});
</script>
</body>
</html>







