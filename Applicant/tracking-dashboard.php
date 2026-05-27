<?php
require_once "../db.php";
require_once "../application-reference.php";
require_once "../upload-storage.php";
require_once "../scholarship-grants.php";

backfillMissingApplicationReferences($conn);

$grantNames = [
    1 => "Student Assistant",
    2 => "Academic Scholarship Program",
    3 => "Executive Student Government (ESG) President Scholarship Program",
    4 => "Kabayani Scholarship Program",
    5 => "Kabayani Loyalty Grant",
    6 => "Discount for Persons with Disability (PWD)",
    7 => "Discount for Children of Employees",
    8 => "Discount for Sibling of Employees",
    9 => "Sibling Discount",
    10 => "DXSM-FM Grant",
    11 => "Michaelinian Mirror Grant (Editor-in-Chief)",
    12 => "Grant for the Dependents of a Lot Donor",
    13 => "Grant for the Dependents of a Board of Trustees (BOT) Member",
    14 => "SMCC Alumni Discount",
    15 => "Michaelinian Stakeholders Grant",
];

$grantRequirements = [
    1 => ["2x2 ID Picture", "Application Letter", "Resume", "Form 138 / Report of Grades", "Certificate of Indigency", "Certificate of Good Moral Character"],
    2 => ["Certification (Top 1 or Top 2)", "2x2 ID Picture", "Form 138 / Grades", "Certificate of Indigency", "Certificate of Good Moral Character"],
    3 => ["2x2 ID Picture", "Endorsement Letter from OSAS"],
    4 => ["2x2 ID Picture", "Endorsement Letter"],
    5 => ["2x2 ID Picture", "Certification from External Linkages Coordinator", "Proof of Relationship", "Endorsement Letter from Retiree"],
    6 => ["2x2 ID Picture", "PWD ID (2 photocopies)"],
    7 => ["2x2 ID Picture", "Certification from HRMDO"],
    8 => ["2x2 ID Picture", "Certification from HRMDO"],
    9 => ["2x2 ID Picture"],
    10 => ["2x2 ID Picture", "Endorsement Letter from Station Manager"],
    11 => ["2x2 ID Picture", "Endorsement Letter from Publication In-Charge"],
    12 => ["2x2 ID Picture", "Board Resolution / Certification"],
    13 => ["2x2 ID Picture", "Board Resolution / Certification", "Endorsement Letter"],
    14 => ["2x2 ID Picture", "Certification from Alumni Association"],
    15 => [],
];

$grantNames = isg_load_scholarship_grant_names($conn, true);
$grantRequirements = isg_load_scholarship_grant_requirements($conn, true);

function trackingStatusLabel(string $status): string
{
    $key = strtolower(trim($status));

    if ($key === "" || $key === "pending") {
        return "Pending Review";
    }

    if ($key === "reapplied") {
        return "Reapplied";
    }

    if ($key === "approved") {
        return "Approved";
    }

    if ($key === "rejected" || $key === "declined") {
        return "Rejected";
    }

    return ucwords(str_replace("_", " ", $key));
}

function trackingStatusBadgeClass(string $status): string
{
    $key = strtolower(trim($status));

    if ($key === "" || $key === "pending") {
        return "border-yellow-200 bg-yellow-50 text-yellow-800";
    }

    if ($key === "reapplied") {
        return "border-blue-200 bg-blue-50 text-blue-700";
    }

    if ($key === "approved") {
        return "border-emerald-200 bg-emerald-50 text-emerald-700";
    }

    if ($key === "rejected" || $key === "declined") {
        return "border-rose-200 bg-rose-50 text-rose-700";
    }

    return "border-blue-200 bg-blue-50 text-blue-700";
}

function trackingStatusMessage(string $status): string
{
    $key = strtolower(trim($status));

    if ($key === "" || $key === "pending") {
        return "Your application has been received and is waiting for office review.";
    }

    if ($key === "reapplied") {
        return "Your reapplication has been received and is waiting for admin review.";
    }

    if ($key === "approved") {
        return "Your application has passed the review stage. Please wait for the next instructions from the scholarship office.";
    }

    if ($key === "rejected" || $key === "declined") {
        return "The application review is complete. Please contact the scholarship office if you need clarification.";
    }

    return "Your application is being processed. Please check this page again for updates.";
}

function trackingProgressStep(string $status): int
{
    $key = strtolower(trim($status));

    if ($key === "approved" || $key === "rejected" || $key === "declined") {
        return 3;
    }

    return 2;
}

function trackingCanUpdateSubmission(string $status): bool
{
    $key = strtolower(trim($status));
    return $key === "" || $key === "pending" || $key === "reapplied";
}

function trackingCanReapplySubmission(string $status): bool
{
    $key = strtolower(trim($status));
    return $key === "rejected" || $key === "declined";
}

function trackingUpdateLockMessage(string $status): string
{
    $key = strtolower(trim($status));

    if ($key === "approved") {
        return "Submission updates are no longer available because your application has already moved forward from the initial review stage.";
    }

    if ($key === "rejected" || $key === "declined") {
        return "Your application was rejected. Use the Reapply button to update the same application and send it back for review.";
    }

    return "Submission updates are no longer available because the scholarship office has already updated your application status.";
}

function trackingFormattedDate(?string $value): string
{
    if (!is_string($value) || trim($value) === "") {
        return "N/A";
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date("F j, Y g:i A", $timestamp);
}

$referenceInput = isset($_GET["reference"])
    ? normalizeApplicationReference((string)$_GET["reference"])
    : "";
$application = null;
$uploadedRequirements = [];
$uploadedRequirementsByLabel = [];
$lookupError = "";
$batchColumnExists = false;
$updateStatus = trim((string)($_GET["update_status"] ?? ""));

$batchColumnResult = $conn->query("SHOW COLUMNS FROM applications LIKE 'batch'");
if ($batchColumnResult instanceof mysqli_result) {
    $batchColumnExists = $batchColumnResult->num_rows > 0;
    $batchColumnResult->free();
}

if ($referenceInput !== "") {
    if (preg_match('/^\d+$/', $referenceInput)) {
        $stmt = $conn->prepare("SELECT * FROM applications WHERE id = ? LIMIT 1");
        if ($stmt) {
            $applicationId = (int)$referenceInput;
            $stmt->bind_param("i", $applicationId);
        } else {
            $applicationId = 0;
        }
    } else {
        $stmt = $conn->prepare("SELECT * FROM applications WHERE reference_number = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $referenceInput);
        }
    }

    if (!isset($stmt) || !$stmt) {
        $lookupError = "Unable to prepare the tracking lookup right now.";
    } else {
        $stmt->execute();
        $result = $stmt->get_result();
        $application = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($application && (!isset($application["reference_number"]) || trim((string)$application["reference_number"]) === "")) {
            $application["reference_number"] = assignApplicationReference($conn, (int)($application["id"] ?? 0));
        }

        if (!$application && $lookupError === "") {
            $lookupError = "No application matched that reference number.";
        }
    }
}

if ($application) {
    $uploadStmt = $conn->prepare(
        "SELECT id, requirement_label, original_file_name, stored_path, mime_type, uploaded_at
         FROM application_uploads
         WHERE application_id = ?
         ORDER BY uploaded_at DESC, id DESC"
    );

    if ($uploadStmt) {
        $applicationId = (int)($application["id"] ?? 0);
        $uploadStmt->bind_param("i", $applicationId);
        $uploadStmt->execute();
        $uploadResult = $uploadStmt->get_result();
        if ($uploadResult) {
            while ($uploadRow = $uploadResult->fetch_assoc()) {
                $uploadedRequirements[] = $uploadRow;
            }
        }
        $uploadStmt->close();
    }

    foreach ($uploadedRequirements as $upload) {
        $label = trim((string)($upload["requirement_label"] ?? ""));
        if ($label === "") {
            $label = "Document";
        }

        if (!isset($uploadedRequirementsByLabel[$label])) {
            $uploadedRequirementsByLabel[$label] = [];
        }

        $uploadedRequirementsByLabel[$label][] = $upload;
    }
}

$statusText = $application ? trackingStatusLabel((string)($application["status"] ?? "")) : "";
$statusClass = $application ? trackingStatusBadgeClass((string)($application["status"] ?? "")) : "";
$statusMessage = $application ? trackingStatusMessage((string)($application["status"] ?? "")) : "";
$progressStep = $application ? trackingProgressStep((string)($application["status"] ?? "")) : 0;
$canUpdateSubmission = $application ? trackingCanUpdateSubmission((string)($application["status"] ?? "")) : false;
$canReapplySubmission = $application ? trackingCanReapplySubmission((string)($application["status"] ?? "")) : false;
$canEditSubmission = $canUpdateSubmission || $canReapplySubmission;
$updateLockMessage = $application ? trackingUpdateLockMessage((string)($application["status"] ?? "")) : "";
$submissionActionLabel = $canReapplySubmission ? "Reapply" : "Update Submission";
$grantId = $application ? (int)($application["grant_id"] ?? 0) : 0;
$grantLabel = $application ? ($grantNames[$grantId] ?? (string)($application["scholarship_type"] ?? "N/A")) : "";
$requiredDocumentLabels = $application ? ($grantRequirements[$grantId] ?? []) : [];
$batchLabel = $application && $batchColumnExists
    ? trim((string)($application["batch"] ?? ""))
    : "";
$isStudentAssistantFlow = $application && $grantId === 1;
$studentAssistantProgressCards = [];

if ($isStudentAssistantFlow) {
    $applicationId = (int)($application["id"] ?? 0);
    $statusKey = strtolower(trim((string)($application["status"] ?? "")));
    $interviewAssignedCount = 0;
    $interviewRatedCount = 0;
    $qualificationComplete = false;
    $qualificationOutcome = "";

    $rankInputTableResult = $conn->query("SHOW TABLES LIKE 'applicant_rank_inputs'");
    $hasRankInputTable = $rankInputTableResult instanceof mysqli_result && $rankInputTableResult->num_rows > 0;
    if ($rankInputTableResult instanceof mysqli_result) {
        $rankInputTableResult->free();
    }

    if ($hasRankInputTable) {
        $rankStmt = $conn->prepare(
            "SELECT remarks
             FROM applicant_rank_inputs
             WHERE application_id = ?
             LIMIT 1"
        );
        if ($rankStmt) {
            $rankStmt->bind_param("i", $applicationId);
            $rankStmt->execute();
            $rankResult = $rankStmt->get_result();
            $rankRow = $rankResult ? $rankResult->fetch_assoc() : null;
            $rankStmt->close();

            if ($rankRow) {
                $remarksValue = trim((string)($rankRow["remarks"] ?? ""));
                if ($remarksValue !== "") {
                    $qualificationComplete = true;
                    $qualificationOutcome = $remarksValue;
                }
            }
        }
    }

    $panelistQueueTableResult = $conn->query("SHOW TABLES LIKE 'panelist_queue'");
    $hasPanelistQueueTable = $panelistQueueTableResult instanceof mysqli_result && $panelistQueueTableResult->num_rows > 0;
    if ($panelistQueueTableResult instanceof mysqli_result) {
        $panelistQueueTableResult->free();
    }

    if ($hasPanelistQueueTable) {
        $panelistQueueStmt = $conn->prepare(
            "SELECT COUNT(DISTINCT panelist_username) AS assigned_count
             FROM panelist_queue
             WHERE application_id = ?"
        );
        if ($panelistQueueStmt) {
            $panelistQueueStmt->bind_param("i", $applicationId);
            $panelistQueueStmt->execute();
            $panelistQueueResult = $panelistQueueStmt->get_result();
            $panelistQueueRow = $panelistQueueResult ? $panelistQueueResult->fetch_assoc() : null;
            $panelistQueueStmt->close();
            $interviewAssignedCount = (int)($panelistQueueRow["assigned_count"] ?? 0);
        }
    }

    $evaluationTableResult = $conn->query("SHOW TABLES LIKE 'interview_evaluations'");
    $hasEvaluationTable = $evaluationTableResult instanceof mysqli_result && $evaluationTableResult->num_rows > 0;
    if ($evaluationTableResult instanceof mysqli_result) {
        $evaluationTableResult->free();
    }

    if ($hasEvaluationTable) {
        $evaluationStmt = $conn->prepare(
            "SELECT COUNT(DISTINCT interviewer_name) AS rated_count
             FROM interview_evaluations
             WHERE applicant_id = ?"
        );
        if ($evaluationStmt) {
            $evaluationStmt->bind_param("i", $applicationId);
            $evaluationStmt->execute();
            $evaluationResult = $evaluationStmt->get_result();
            $evaluationRow = $evaluationResult ? $evaluationResult->fetch_assoc() : null;
            $evaluationStmt->close();
            $interviewRatedCount = (int)($evaluationRow["rated_count"] ?? 0);
        }
    }

    $examinationComplete = $interviewAssignedCount > 0 || $interviewRatedCount > 0 || $qualificationComplete;
    $interviewComplete = $interviewRatedCount > 0 && ($interviewAssignedCount === 0 || $interviewRatedCount >= $interviewAssignedCount);
    $applicationComplete = $statusKey === "approved" || $examinationComplete || $interviewComplete || $qualificationComplete;
    $isQualifiedOutcome = strcasecmp($qualificationOutcome, "Hired") === 0;

    $currentStudentAssistantStep = "application";
    if ($applicationComplete) {
        if (!$examinationComplete) {
            $currentStudentAssistantStep = "examination";
        } elseif (!$interviewComplete) {
            $currentStudentAssistantStep = "interview";
        } elseif (!$qualificationComplete) {
            $currentStudentAssistantStep = "qualification";
        } else {
            $currentStudentAssistantStep = "";
        }
    }

    $qualificationDescription = "Waiting for qualification result.";
    if ($qualificationComplete) {
        $qualificationDescription = $isQualifiedOutcome
            ? "Final review completed. Congratulations! You are qualified for the Student Assistant opportunity. Please wait for the orientation announcement through the Gmail address you provided in your application form."
            : "Final review completed. Thank you for your time and effort in applying. After the overall evaluation, your application was not selected for this cycle because it did not fully meet the qualification standards for the grant at this time.";
    }

    $studentAssistantProgressCards = [
        [
            "key" => "application",
            "title" => "Application",
            "description" => $applicationComplete
                ? "Application review has been completed and your submission moved to the next screening stage."
                : (($statusKey === "rejected" || $statusKey === "declined")
                    ? "Application review ended before the next screening stage."
                    : "Application submitted and waiting for initial review."),
        ],
        [
            "key" => "examination",
            "title" => "Examination",
            "description" => $examinationComplete
                ? "Examination stage is complete. Your application has been forwarded to the panelists."
                : ($applicationComplete
                    ? "Your application passed the initial review and is now waiting to be forwarded for interview."
                    : "Waiting for the application review to finish."),
        ],
        [
            "key" => "interview",
            "title" => "Interview",
            "description" => $interviewComplete
                ? "Interview evaluation has been completed."
                : ($examinationComplete
                    ? (($interviewAssignedCount > 0 || $interviewRatedCount > 0)
                        ? "Interview is in progress. Please wait for the panelists' final scores."
                        : "Waiting for the interview schedule.")
                    : "Waiting for the examination stage to be completed."),
        ],
        [
            "key" => "qualification",
            "title" => "Qualification",
            "description" => $qualificationDescription,
            "tone" => $qualificationComplete ? ($isQualifiedOutcome ? "success" : "danger") : "default",
        ],
    ];

    foreach ($studentAssistantProgressCards as &$studentAssistantCard) {
        $cardKey = (string)($studentAssistantCard["key"] ?? "");
        $isComplete = false;

        if ($cardKey === "application") {
            $isComplete = $applicationComplete;
        } elseif ($cardKey === "examination") {
            $isComplete = $examinationComplete;
        } elseif ($cardKey === "interview") {
            $isComplete = $interviewComplete;
        } elseif ($cardKey === "qualification") {
            $isComplete = $qualificationComplete;
        }

        $studentAssistantCard["is_complete"] = $isComplete;
        $studentAssistantCard["is_current"] = !$isComplete && $currentStudentAssistantStep === $cardKey;
    }
    unset($studentAssistantCard);

    $isRejectedFlow = $statusKey === "rejected" || $statusKey === "declined";
    $isReappliedFlow = $statusKey === "reapplied";
    if (!$isRejectedFlow && !$isReappliedFlow) {
        if ($qualificationComplete) {
            if ($isQualifiedOutcome) {
                $statusText = "Qualified";
                $statusClass = "border-emerald-200 bg-emerald-50 text-emerald-700";
                $statusMessage = "You have completed the Student Assistant screening process and qualified for the opportunity.";
            } else {
                $statusText = "Not Selected";
                $statusClass = "border-slate-200 bg-slate-50 text-slate-700";
                $statusMessage = "Thank you for completing the Student Assistant screening process. After the final review, your application was not selected for this cycle.";
            }
        } elseif ($interviewComplete) {
            $statusText = "Qualification Stage";
            $statusClass = "border-blue-200 bg-blue-50 text-blue-700";
            $statusMessage = "Your interview evaluation is complete. Your application is now under final qualification review.";
        } elseif ($examinationComplete) {
            $statusText = "Interview Stage";
            $statusClass = "border-blue-200 bg-blue-50 text-blue-700";
            $statusMessage = ($interviewAssignedCount > 0 || $interviewRatedCount > 0)
                ? "Your application is in the interview stage. Please wait for the panelists' evaluation update."
                : "Your application has been sent to the panelists and is waiting for the interview schedule.";
        } elseif ($applicationComplete) {
            $statusText = "Examination Stage";
            $statusClass = "border-blue-200 bg-blue-50 text-blue-700";
            $statusMessage = "Your application passed the initial review and is now in the examination stage.";
        } else {
            $statusText = "Application Stage";
            $statusClass = "border-yellow-200 bg-yellow-50 text-yellow-800";
            $statusMessage = "Your Student Assistant application is waiting for the initial review.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Application Tracking Dashboard</title>
  <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    :root {
      --ink: #052c6a;
      --accent: #0d8ddb;
      --gold: #fcdc2f;
    }

    body {
      font-family: "IBM Plex Sans", sans-serif;
      background:
        radial-gradient(circle at top left, rgba(13, 141, 219, 0.18), transparent 30%),
        radial-gradient(circle at 85% 12%, rgba(252, 220, 47, 0.18), transparent 18%),
        linear-gradient(180deg, #e8f3ff 0%, #f8fbff 38%, #eef6ff 100%);
    }

    h1,
    h2,
    h3,
    .display-font {
      font-family: "Outfit", sans-serif;
    }

    .top-brand,
    .top-brand * {
      font-family: sans-serif;
    }

    .break-anywhere {
      overflow-wrap: anywhere;
      word-break: break-word;
    }
  </style>
</head>
<body class="min-h-screen overflow-x-hidden text-[#052c6a]">
  <header class="top-brand sticky top-0 z-20 bg-gradient-to-r from-[#052c6a] via-[#0d8ddb] to-[#1d4ed8] shadow-md">
    <div class="w-full flex items-center gap-3 px-4 sm:px-6 lg:px-10 py-3">
      <div class="flex items-center justify-center">
        <img
          src="../img/SMCCNEWLOGO.png"
          alt="SMCC Logo"
          class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover bg-white shadow-md border border-white"
        />
      </div>

      <div class="flex-1 min-w-0">
        <p class="text-[10px] leading-4 sm:text-xs text-blue-100 uppercase tracking-[0.12em] sm:tracking-[0.18em]">
          SMCC Admission and Scholarship Office
        </p>
        <div class="mt-1 flex flex-wrap items-center gap-1.5 sm:gap-2">
          <h1 class="text-white text-sm sm:text-base font-semibold leading-tight">
            Institutional Scholarship Grants
          </h1>
          <span class="inline-flex max-w-full items-center gap-1 rounded-full bg-white/10 px-2 py-[2px] text-[10px] sm:text-[11px] text-blue-50">
            Track Application
          </span>
        </div>
        <p class="mt-1 text-[10px] leading-4 sm:text-xs text-blue-100">
          Check the latest application status using your reference number.
        </p>
      </div>
    </div>
  </header>

  <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
    <?php if ($updateStatus === "updated"): ?>
      <div class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-700">
        Your submission was updated successfully. The latest application details and uploaded requirements are shown below.
      </div>
    <?php elseif ($updateStatus === "reapplied"): ?>
      <div class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-700">
        Your reapplication was submitted successfully. Your application is now marked as Reapplied for admin review.
      </div>
    <?php elseif ($updateStatus === "locked"): ?>
      <div class="rounded-[1.5rem] border border-yellow-200 bg-yellow-50 px-5 py-4 text-sm text-yellow-800">
        Submission updates are no longer available because your application status has already been updated by the scholarship office.
      </div>
    <?php endif; ?>

    <section class="grid gap-6 lg:grid-cols-[0.92fr_1.08fr]">
      <aside class="rounded-[1.5rem] border border-[#cfe2ff] bg-white/92 p-4 shadow-[0_28px_60px_-36px_rgba(5,44,106,0.55)] sm:rounded-[2rem] sm:p-6">
        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-[#0d8ddb]">
          Search Application
        </p>
        <h2 class="mt-2 text-2xl font-extrabold text-[#052c6a]">
          Enter your reference number
        </h2>
        <p class="mt-2 text-sm leading-6 text-[#052c6a]/80">
          Use the reference number shown after submission. The latest status will appear on this page.
        </p>

        <form action="tracking-dashboard.php" method="get" class="mt-5 space-y-3">
          <div>
            <label class="mb-1 block text-sm font-semibold text-[#052c6a]" for="reference">
              Application Reference Number
            </label>
            <input
              id="reference"
              name="reference"
              type="text"
              value="<?php echo htmlspecialchars($referenceInput); ?>"
              placeholder="ISG-20260413-7KQ29X"
              class="break-anywhere w-full rounded-2xl border border-[#c7dcff] bg-[#f8fbff] px-4 py-3 text-sm uppercase tracking-[0.04em] sm:tracking-[0.08em] text-[#052c6a] outline-none transition focus:border-[#0d8ddb] focus:ring-4 focus:ring-[#0d8ddb]/15"
              required
            />
          </div>
          <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-full bg-[#0d8ddb] px-5 py-3 text-sm font-semibold text-white shadow-md transition hover:-translate-y-[1px] hover:bg-[#0b63d1]"
          >
            Search Status
          </button>
        </form>

        <div class="mt-5 rounded-[1.5rem] border border-[#e3ecfb] bg-[#f8fbff] px-4 py-4 text-sm text-[#052c6a]/80">
          <p class="font-semibold text-[#052c6a]">Need to apply instead?</p>
          <p class="mt-1 leading-6">
            Open the applicant portal to start a new scholarship application or return to the tracking options page.
          </p>
          <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <a
              href="applicant-portal.php"
              class="inline-flex items-center justify-center rounded-full border border-[#052c6a] px-4 py-2 text-sm font-semibold text-[#052c6a] transition hover:bg-[#052c6a] hover:text-white"
            >
              Applicant Portal
            </a>
            <a
              href="../index.php"
              class="inline-flex items-center justify-center rounded-full border border-[#d2dff3] px-4 py-2 text-sm font-semibold text-[#052c6a]/80 transition hover:border-[#0d8ddb] hover:text-[#0d8ddb]"
            >
              Back to Homepage
            </a>
          </div>
        </div>
      </aside>

      <section class="rounded-[1.5rem] border border-[#cfe2ff] bg-white/92 p-4 shadow-[0_28px_60px_-36px_rgba(5,44,106,0.55)] sm:rounded-[2rem] sm:p-6">
        <?php if ($referenceInput === ""): ?>
          <div class="flex h-full min-h-[20rem] sm:min-h-[24rem] flex-col justify-center rounded-[1.5rem] border border-dashed border-[#c7dcff] bg-[#f8fbff] px-4 py-8 text-center sm:rounded-[1.75rem] sm:px-6 sm:py-10">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-[#0d8ddb]">
              Tracking Ready
            </p>
            <h2 class="mt-2 text-2xl font-extrabold text-[#052c6a]">
              Waiting for your reference number
            </h2>
            <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-[#052c6a]/80">
              Enter the application reference number on the left to open your applicant tracking dashboard.
            </p>
          </div>
        <?php elseif ($application): ?>
          <div class="flex flex-col gap-5">
            <div class="rounded-[1.5rem] bg-gradient-to-br from-[#052c6a] via-[#0d8ddb] to-[#38bdf8] px-5 py-5 text-white sm:rounded-[1.75rem] sm:px-6 sm:py-6">
              <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-blue-100">
                    Application Found
                  </p>
                  <h2 class="mt-2 text-2xl font-extrabold">
                    <?php echo htmlspecialchars((string)($application["applicant_name"] ?? "Applicant")); ?>
                  </h2>
                  <p class="mt-2 text-sm leading-6 text-blue-50">
                    <?php echo htmlspecialchars($statusMessage); ?>
                  </p>
                </div>
                <span class="inline-flex self-start items-center rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold">
                  <?php echo htmlspecialchars($statusText); ?>
                </span>
              </div>

              <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                  <p class="text-[11px] uppercase tracking-[0.12em] sm:tracking-[0.16em] text-blue-100">Reference Number</p>
                  <p class="break-anywhere mt-2 text-sm font-bold tracking-[0.04em] sm:tracking-[0.08em]">
                    <?php echo htmlspecialchars((string)($application["reference_number"] ?? "")); ?>
                  </p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                  <p class="text-[11px] uppercase tracking-[0.12em] sm:tracking-[0.16em] text-blue-100">Submitted On</p>
                  <p class="mt-2 text-sm font-bold">
                    <?php echo htmlspecialchars(trackingFormattedDate((string)($application["created_at"] ?? ""))); ?>
                  </p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                  <p class="text-[11px] uppercase tracking-[0.12em] sm:tracking-[0.16em] text-blue-100">Current Status</p>
                  <p class="mt-2 text-sm font-bold">
                    <?php echo htmlspecialchars($statusText); ?>
                  </p>
                </div>
              </div>
            </div>

            <div class="rounded-[1.5rem] border border-[#d9e7ff] bg-[#f8fbff] px-4 py-4 sm:rounded-[1.75rem] sm:px-5 sm:py-5">
              <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-[#0d8ddb]">
                <?php echo $isStudentAssistantFlow ? "Student Assistant Process" : "Application Progress"; ?>
              </p>
              <?php if ($isStudentAssistantFlow): ?>
                <div class="mt-2 text-sm text-[#052c6a]/80">
                  Student Assistant applicants move through four stages: application, examination, interview, and qualification.
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                  <?php foreach ($studentAssistantProgressCards as $stepIndex => $studentAssistantCard): ?>
                    <?php
                    $stepClass = "border-[#d7e5fa] bg-white text-[#6b7b95]";
                    if (!empty($studentAssistantCard["is_complete"])) {
                        if (($studentAssistantCard["key"] ?? "") === "qualification" && ($studentAssistantCard["tone"] ?? "") === "success") {
                            $stepClass = "border-emerald-200 bg-emerald-50 text-emerald-700";
                        } elseif (($studentAssistantCard["key"] ?? "") === "qualification" && ($studentAssistantCard["tone"] ?? "") === "danger") {
                            $stepClass = "border-rose-200 bg-rose-50 text-rose-700";
                        } else {
                            $stepClass = "border-[#0d8ddb] bg-[#e9f4ff] text-[#052c6a]";
                        }
                    } elseif (!empty($studentAssistantCard["is_current"])) {
                        $stepClass = "border-[#fcdc2f] bg-[#fff8d8] text-[#7a5800]";
                    }
                    ?>
                    <div class="rounded-2xl border px-4 py-4 <?php echo $stepClass; ?>">
                      <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.16em]">
                        Step <?php echo htmlspecialchars((string)($stepIndex + 1)); ?>
                      </p>
                      <p class="mt-2 text-base font-bold">
                        <?php echo htmlspecialchars((string)($studentAssistantCard["title"] ?? "")); ?>
                      </p>
                      <p class="mt-2 text-xs leading-5">
                        <?php echo htmlspecialchars((string)($studentAssistantCard["description"] ?? "")); ?>
                      </p>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                  <?php
                  $steps = [
                      1 => "Submitted",
                      2 => "Under Review",
                      3 => "Final Decision",
                  ];
                  foreach ($steps as $stepNumber => $stepLabel):
                      $isComplete = $progressStep >= $stepNumber;
                      $stepClass = $isComplete
                          ? "border-[#0d8ddb] bg-[#e9f4ff] text-[#052c6a]"
                          : "border-[#d7e5fa] bg-white text-[#6b7b95]";
                  ?>
                    <div class="rounded-2xl border px-4 py-4 <?php echo $stepClass; ?>">
                      <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.16em]">
                        Step <?php echo htmlspecialchars((string)$stepNumber); ?>
                      </p>
                      <p class="mt-2 text-base font-bold">
                        <?php echo htmlspecialchars($stepLabel); ?>
                      </p>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <div class="rounded-[1.5rem] border border-[#e1eaf8] bg-white px-5 py-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-[#0d8ddb]">Grant / Discount</p>
                <p class="mt-2 text-base font-bold text-[#052c6a]">
                  <?php echo htmlspecialchars($grantLabel !== "" ? $grantLabel : "N/A"); ?>
                </p>
              </div>
              <div class="rounded-[1.5rem] border border-[#e1eaf8] bg-white px-5 py-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-[#0d8ddb]">Program / Course</p>
                <p class="mt-2 text-base font-bold text-[#052c6a]">
                  <?php echo htmlspecialchars((string)($application["program_course"] ?? "N/A")); ?>
                </p>
              </div>
              <div class="rounded-[1.5rem] border border-[#e1eaf8] bg-white px-5 py-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-[#0d8ddb]">School Term</p>
                <p class="mt-2 text-base font-bold text-[#052c6a]">
                  <?php
                  $termLabel = trim((string)($application["semester"] ?? ""));
                  $schoolYear = trim((string)($application["school_year"] ?? ""));
                  if ($termLabel !== "" && $schoolYear !== "") {
                      echo htmlspecialchars($termLabel . ", S.Y. " . $schoolYear);
                  } elseif ($termLabel !== "") {
                      echo htmlspecialchars($termLabel);
                  } elseif ($schoolYear !== "") {
                      echo htmlspecialchars($schoolYear);
                  } else {
                      echo "N/A";
                  }
                  ?>
                </p>
              </div>
              <div class="rounded-[1.5rem] border border-[#e1eaf8] bg-white px-5 py-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-[#0d8ddb]">Status Badge</p>
                <span class="mt-2 inline-flex rounded-full border px-3 py-1 text-sm font-semibold <?php echo htmlspecialchars($statusClass); ?>">
                  <?php echo htmlspecialchars($statusText); ?>
                </span>
              </div>
              <?php if ($batchLabel !== ""): ?>
                <div class="rounded-[1.5rem] border border-[#e1eaf8] bg-white px-5 py-5 shadow-sm">
                  <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-[#0d8ddb]">Assigned Batch</p>
                  <p class="mt-2 text-base font-bold text-[#052c6a]">
                    <?php echo htmlspecialchars($batchLabel); ?>
                  </p>
                </div>
              <?php endif; ?>
            </div>

            <div class="rounded-[1.5rem] border border-[#d9e7ff] bg-white px-4 py-4 sm:rounded-[1.75rem] sm:px-5 sm:py-5">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-[#0d8ddb]">Saved Application Form</p>
                <h3 class="mt-2 text-xl font-extrabold text-[#052c6a]">Submitted application details</h3>
                <p class="mt-2 text-sm leading-6 text-[#052c6a]/80">
                  <?php
                  echo htmlspecialchars(
                      $canReapplySubmission
                          ? "Review the rejected application details on file. Use the Reapply button below to edit the same application and send it back for admin review."
                          : ($canUpdateSubmission
                              ? "Review the exact form details on file. If the admin asks for corrections while your application is still pending, use the single Update Submission button below to revise both the form and the uploaded documents."
                              : "Review the exact form details on file. This submission is now locked because the application status has already been updated.")
                  );
                  ?>
                </p>
              </div>

              <div class="mt-5 grid gap-4 lg:grid-cols-3">
                <div class="rounded-2xl border border-[#e1eaf8] bg-[#f8fbff] px-4 py-4">
                  <p class="text-sm font-semibold text-[#052c6a]">Applicant Profile</p>
                  <div class="break-anywhere mt-3 space-y-2 text-sm text-[#052c6a]/85">
                    <div><span class="font-semibold">Name:</span> <?php echo htmlspecialchars((string)($application["applicant_name"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Program:</span> <?php echo htmlspecialchars((string)($application["program_course"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Year Level:</span> <?php echo htmlspecialchars((string)($application["year_level"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">School Year:</span> <?php echo htmlspecialchars((string)($application["school_year"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Semester:</span> <?php echo htmlspecialchars((string)($application["semester"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Address:</span> <?php echo htmlspecialchars((string)($application["permanent_address"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Gender:</span> <?php echo htmlspecialchars((string)($application["gender"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Age:</span> <?php echo htmlspecialchars((string)($application["age"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Birth Date:</span> <?php echo htmlspecialchars((string)($application["date_of_birth"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Contact:</span> <?php echo htmlspecialchars((string)($application["contact_number"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Email:</span> <?php echo htmlspecialchars((string)($application["email_address"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Estimated Income:</span> <?php echo htmlspecialchars((string)($application["estimated_income"] ?? "N/A")); ?></div>
                  </div>
                </div>

                <div class="rounded-2xl border border-[#e1eaf8] bg-[#f8fbff] px-4 py-4">
                  <p class="text-sm font-semibold text-[#052c6a]">Mother's Information</p>
                  <div class="break-anywhere mt-3 space-y-2 text-sm text-[#052c6a]/85">
                    <div><span class="font-semibold">Name:</span> <?php echo htmlspecialchars((string)($application["mother_name"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Contact:</span> <?php echo htmlspecialchars((string)($application["mother_contact"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Occupation:</span> <?php echo htmlspecialchars((string)($application["mother_occupation"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Company:</span> <?php echo htmlspecialchars((string)($application["mother_company_name"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Company Address:</span> <?php echo htmlspecialchars((string)($application["mother_company_address"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Age:</span> <?php echo htmlspecialchars((string)($application["mother_age"] ?? "N/A")); ?></div>
                  </div>
                </div>

                <div class="rounded-2xl border border-[#e1eaf8] bg-[#f8fbff] px-4 py-4">
                  <p class="text-sm font-semibold text-[#052c6a]">Father's Information</p>
                  <div class="break-anywhere mt-3 space-y-2 text-sm text-[#052c6a]/85">
                    <div><span class="font-semibold">Name:</span> <?php echo htmlspecialchars((string)($application["father_name"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Contact:</span> <?php echo htmlspecialchars((string)($application["father_contact"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Occupation:</span> <?php echo htmlspecialchars((string)($application["father_occupation"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Company:</span> <?php echo htmlspecialchars((string)($application["father_company_name"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Company Address:</span> <?php echo htmlspecialchars((string)($application["father_company_address"] ?? "N/A")); ?></div>
                    <div><span class="font-semibold">Age:</span> <?php echo htmlspecialchars((string)($application["father_age"] ?? "N/A")); ?></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="rounded-[1.5rem] border border-[#d9e7ff] bg-[#f8fbff] px-4 py-4 sm:rounded-[1.75rem] sm:px-5 sm:py-5">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-[#0d8ddb]">Submitted Requirements</p>
                <h3 class="mt-2 text-xl font-extrabold text-[#052c6a]">Uploaded documentary requirements</h3>
                <p class="mt-2 text-sm leading-6 text-[#052c6a]/80">
                  <?php
                  echo htmlspecialchars(
                      $canReapplySubmission
                          ? "These are the files currently on record. Replace only the documents that need a clearer or corrected copy before reapplying."
                          : ($canUpdateSubmission
                              ? "These are the files currently on record. Use the same Update Submission button below if you need to replace any document while your application is still pending."
                              : "These are the files currently on record. Document replacement is locked after the scholarship office updates the application status.")
                  );
                  ?>
                </p>
              </div>

              <?php if (empty($requiredDocumentLabels)): ?>
                <div class="mt-4 rounded-2xl border border-[#e1eaf8] bg-white px-4 py-4 text-sm text-[#052c6a]/80">
                  This grant currently has no documentary requirements.
                </div>
              <?php else: ?>
                <div class="mt-4 space-y-3">
                  <?php foreach ($requiredDocumentLabels as $documentLabel): ?>
                    <?php $documentUpload = $uploadedRequirementsByLabel[$documentLabel][0] ?? null; ?>
                    <div class="rounded-2xl border border-[#e1eaf8] bg-white px-4 py-4">
                      <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                          <p class="text-sm font-semibold text-[#052c6a]"><?php echo htmlspecialchars($documentLabel); ?></p>
                          <?php if ($documentUpload): ?>
                            <p class="break-anywhere mt-1 text-xs text-[#052c6a]/75">
                              Uploaded file:
                              <span class="font-semibold"><?php echo htmlspecialchars((string)($documentUpload["original_file_name"] ?? "Document")); ?></span>
                            </p>
                          <?php else: ?>
                            <p class="mt-1 text-xs font-semibold text-rose-600">No uploaded file found yet for this requirement.</p>
                          <?php endif; ?>
                        </div>
                        <div class="flex flex-wrap gap-2">
                          <?php if ($documentUpload): ?>
                            <a href="application-upload.php?reference=<?php echo urlencode((string)($application["reference_number"] ?? "")); ?>&stored_path=<?php echo urlencode((string)($documentUpload["stored_path"] ?? "")); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded-full border border-[#0d8ddb] px-3 py-1 text-xs font-semibold text-[#0d8ddb] hover:bg-[#eff6ff]">
                              View
                            </a>
                            <a href="application-upload.php?reference=<?php echo urlencode((string)($application["reference_number"] ?? "")); ?>&stored_path=<?php echo urlencode((string)($documentUpload["stored_path"] ?? "")); ?>&mode=download" class="inline-flex items-center justify-center rounded-full border border-[#d6e5ff] px-3 py-1 text-xs font-semibold text-[#052c6a]/80 hover:border-[#0d8ddb] hover:text-[#0d8ddb]">
                              Download
                            </a>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <?php if ($canEditSubmission): ?>
              <div class="flex justify-center">
                <a
                  href="update-submission.php?reference=<?php echo urlencode((string)($application["reference_number"] ?? "")); ?>"
                  class="inline-flex items-center justify-center rounded-full bg-[#052c6a] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#0d8ddb]"
                >
                  <?php echo htmlspecialchars($submissionActionLabel); ?>
                </a>
              </div>
            <?php else: ?>
              <div class="rounded-[1.5rem] border border-[#d9e7ff] bg-white px-5 py-4 text-center text-sm leading-6 text-[#052c6a]/80">
                <?php echo htmlspecialchars($updateLockMessage); ?>
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="flex h-full min-h-[20rem] sm:min-h-[24rem] flex-col justify-center rounded-[1.5rem] border border-rose-200 bg-rose-50 px-4 py-8 text-center sm:rounded-[1.75rem] sm:px-6 sm:py-10">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-rose-700">
              Tracking Result
            </p>
            <h2 class="mt-2 text-2xl font-extrabold text-rose-800">
              Reference number not found
            </h2>
            <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-rose-700/90">
              <?php echo htmlspecialchars($lookupError); ?> Please review the reference number and try again.
            </p>
          </div>
        <?php endif; ?>
      </section>
    </section>
  </main>
</body>
</html>
