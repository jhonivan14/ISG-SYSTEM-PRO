<?php
// Guide: Mail dispatch endpoint for approved applicant status messages.
// Trace: validate POST payload -> verify recipient -> send via shared mailer -> redirect with status.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once "../db.php";
require_once __DIR__ . "/includes/mailer.php";

$applicantId = (int)($_POST["applicant_id"] ?? 0);
$messageBody = isset($_POST["message_body"]) ? trim((string)$_POST["message_body"]) : "";
$postedRecipientEmail = isset($_POST["recipient_email"]) ? trim((string)$_POST["recipient_email"]) : "";
$returnPage = strtolower(trim((string)($_POST["return_page"] ?? "")));
$returnId = (int)($_POST["return_id"] ?? 0);

$buildRedirectUrl = static function (string $status) use ($returnPage, $returnId): string {
  if (($returnPage === "view-application" || $returnPage === "view-application.php") && $returnId > 0) {
    return "view-application.php?id=" . urlencode((string)$returnId) . "&message_status=" . urlencode($status);
  }

  return "approved.php?message_status=" . urlencode($status);
};

if ($applicantId <= 0 || $messageBody === "") {
  $_SESSION["message_error"] = "Invalid applicant or empty message.";
  header("Location: " . $buildRedirectUrl("error"));
  exit;
}

try {
  $recipientEmail = "";
  $lookupStmt = $conn->prepare("SELECT email_address FROM applications WHERE id = ? LIMIT 1");
  if ($lookupStmt) {
    $lookupStmt->bind_param("i", $applicantId);
    $lookupStmt->execute();
    $lookupStmt->bind_result($recipientEmail);
    $lookupStmt->fetch();
    $lookupStmt->close();
  }

  $recipientEmail = trim((string)$recipientEmail);
  if ($recipientEmail === "" || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException("Recipient email not found for this applicant.");
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

  isgSendPlainTextMail($recipientEmail, "Scholarship Application Update", $messageBody);

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
