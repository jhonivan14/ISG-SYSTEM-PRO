<?php
// Guide: Mail dispatch endpoint for approved applicant and institutional scholar messages.
// Trace: validate POST payload -> verify recipient -> send via shared mailer -> redirect with status.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once "../db.php";
require_once __DIR__ . "/includes/mailer.php";

$applicantId = (int)($_POST["applicant_id"] ?? 0);
$scholarRecordId = (int)($_POST["scholar_record_id"] ?? 0);
$messageBody = isset($_POST["message_body"]) ? trim((string)$_POST["message_body"]) : "";
$postedRecipientEmail = isset($_POST["recipient_email"]) ? trim((string)$_POST["recipient_email"]) : "";
$returnPage = strtolower(trim((string)($_POST["return_page"] ?? "")));
$returnId = (int)($_POST["return_id"] ?? 0);

$buildRedirectUrl = static function (string $status) use ($returnPage, $returnId): string {
  if (($returnPage === "view-application" || $returnPage === "view-application.php") && $returnId > 0) {
    return "view-application.php?id=" . urlencode((string)$returnId) . "&message_status=" . urlencode($status);
  }

  if ($returnPage === "institutional-scholars" || $returnPage === "institutional-scholars.php") {
    $params = ["message_status" => $status];
    $activeCategory = strtolower(trim((string)($_POST["return_active_category"] ?? "")));
    $schoolYear = trim((string)($_POST["return_school_year"] ?? ""));
    $semester = trim((string)($_POST["return_semester"] ?? ""));
    if (in_array($activeCategory, ["official", "student_assistant", "kabayani", "academic", "others"], true)) {
      $params["active_category"] = $activeCategory;
    }
    if ($schoolYear !== "") {
      $params["school_year"] = $schoolYear;
    }
    if ($semester !== "") {
      $params["semester"] = $semester;
    }
    return "institutional-scholars.php?" . http_build_query($params);
  }

  return "approved.php?message_status=" . urlencode($status);
};

if (($applicantId <= 0 && $scholarRecordId <= 0) || $messageBody === "") {
  $_SESSION["message_error"] = "Invalid recipient or empty message.";
  header("Location: " . $buildRedirectUrl("error"));
  exit;
}

try {
  $recipientEmail = "";
  $mailSubject = "Scholarship Application Update";

  if ($scholarRecordId > 0) {
    $lookupStmt = $conn->prepare("SELECT email FROM institutional_scholar_records WHERE id = ? LIMIT 1");
    if ($lookupStmt) {
      $lookupStmt->bind_param("i", $scholarRecordId);
      $lookupStmt->execute();
      $lookupStmt->bind_result($recipientEmail);
      $lookupStmt->fetch();
      $lookupStmt->close();
    }
    $mailSubject = "Institutional Scholar Update";
  } else {
    $lookupStmt = $conn->prepare("SELECT email_address FROM applications WHERE id = ? LIMIT 1");
    if ($lookupStmt) {
      $lookupStmt->bind_param("i", $applicantId);
      $lookupStmt->execute();
      $lookupStmt->bind_result($recipientEmail);
      $lookupStmt->fetch();
      $lookupStmt->close();
    }
  }

  $recipientEmail = trim((string)$recipientEmail);
  if ($recipientEmail === "" || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException("Recipient email not found.");
  }
  if ($postedRecipientEmail !== "" && strcasecmp($postedRecipientEmail, $recipientEmail) !== 0) {
    $maskedPosted = isgMaskEmail($postedRecipientEmail);
    $maskedDb = isgMaskEmail($recipientEmail);
    $hint = "";
    if ($maskedPosted !== "" || $maskedDb !== "") {
      $hint = " Posted: " . ($maskedPosted !== "" ? $maskedPosted : "[empty]") .
        ", DB: " . ($maskedDb !== "" ? $maskedDb : "[empty]");
    }
    throw new RuntimeException("Recipient email mismatch. Refresh the page and try again." . $hint);
  }

  isgSendPlainTextMail($recipientEmail, $mailSubject, $messageBody);

  header("Location: " . $buildRedirectUrl("sent"));
  exit;
} catch (Throwable $error) {
  $recipientHint = "";
  if (isset($recipientEmail) && is_string($recipientEmail)) {
    $masked = isgMaskEmail($recipientEmail);
    if ($masked !== "") {
      $recipientHint = " Recipient: " . $masked;
    }
  }
  $_SESSION["message_error"] = $error->getMessage() . $recipientHint;
  header("Location: " . $buildRedirectUrl("error"));
  exit;
}
