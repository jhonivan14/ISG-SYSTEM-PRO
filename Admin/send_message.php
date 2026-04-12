<?php
// Guide: Mail dispatch endpoint for approved applicant status messages.
// Trace: validate POST payload -> verify recipient -> load SMTP config -> send mail -> redirect with status.

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once "../db.php";
require_once __DIR__ . "/../vendor/autoload.php";

// Helper: partially mask addresses in errors so admins can troubleshoot without exposing full emails.

$maskEmail = function (string $email): string {
  $email = trim($email);
  if ($email === "" || strpos($email, "@") === false) {
    return "";
  }
  [$local, $domain] = explode("@", $email, 2);
  $local = trim($local);
  if ($local === "") {
    return "";
  }
  if (strlen($local) <= 2) {
    $maskedLocal = substr($local, 0, 1) . "***";
  } else {
    $maskedLocal = substr($local, 0, 1) . str_repeat("*", max(1, strlen($local) - 2)) . substr($local, -1);
  }
  return $maskedLocal . "@" . $domain;
};

$applicantId = (int)($_POST["applicant_id"] ?? 0);
$messageBody = isset($_POST["message_body"]) ? trim((string)$_POST["message_body"]) : "";
$postedRecipientEmail = isset($_POST["recipient_email"]) ? trim((string)$_POST["recipient_email"]) : "";

if ($applicantId <= 0 || $messageBody === "") {
  $_SESSION["message_error"] = "Invalid applicant or empty message.";
  header("Location: approved.php?message_status=error");
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
    $maskedPosted = $maskEmail($postedRecipientEmail);
    $maskedDb = $maskEmail($recipientEmail);
    $hint = "";
    if ($maskedPosted !== "" || $maskedDb !== "") {
      $hint = " Posted: " . ($maskedPosted !== "" ? $maskedPosted : "[empty]") .
        ", DB: " . ($maskedDb !== "" ? $maskedDb : "[empty]");
    }
    throw new RuntimeException("Recipient email mismatch. Refresh the page and try again." . $hint);
  }

  $smtpConfigPath = dirname(__DIR__) . "/smtp_config.php";
  if (!file_exists($smtpConfigPath)) {
    throw new RuntimeException("SMTP config missing. Create smtp_config.php in project root.");
  }

  $smtp = require $smtpConfigPath;
  if (!is_array($smtp)) {
    throw new RuntimeException("SMTP config is invalid.");
  }

  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host       = $smtp["host"] ?? "smtp.gmail.com";
  $mail->SMTPAuth   = true;
  $mail->Username   = $smtp["username"] ?? "";
  $mail->Password   = $smtp["password"] ?? "";
  $mail->Port       = (int)($smtp["port"] ?? 587);

  $secure = $smtp["secure"] ?? "tls";
  $mail->SMTPSecure = ($secure === "ssl")
    ? PHPMailer::ENCRYPTION_SMTPS
    : PHPMailer::ENCRYPTION_STARTTLS;

  if (!filter_var($mail->Username, FILTER_VALIDATE_EMAIL)) {
    $maskedUser = $maskEmail($mail->Username);
    $hint = $maskedUser !== "" ? " Current: " . $maskedUser : " Current: [empty]";
    if (stripos($mail->Username, "yourgmail@gmail.com") !== false) {
      $hint .= " (still placeholder)";
    }
    throw new RuntimeException("Invalid SMTP username email. Update smtp_config.php (username)." . $hint);
  }
  if ($mail->Password === "") {
    throw new RuntimeException("SMTP password is missing. Update smtp_config.php (password).");
  }

  $fromEmail = $smtp["from_email"] ?? $mail->Username;
  $fromName  = $smtp["from_name"] ?? "ISG Admin";
  if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    $maskedFrom = $maskEmail($fromEmail);
    $hint = $maskedFrom !== "" ? " Current: " . $maskedFrom : " Current: [empty]";
    if (stripos($fromEmail, "yourgmail@gmail.com") !== false) {
      $hint .= " (still placeholder)";
    }
    throw new RuntimeException("Invalid From email. Update smtp_config.php (from_email)." . $hint);
  }

  $mail->setFrom($fromEmail, $fromName);
  $mail->addAddress($recipientEmail);
  $mail->Subject = "Scholarship Application Update";
  $mail->Body    = $messageBody;
  $mail->isHTML(false);

  $mail->send();

  header("Location: approved.php?message_status=sent");
  exit;
} catch (Throwable $error) {
  $recipientHint = "";
  if (isset($recipientEmail) && is_string($recipientEmail)) {
    $masked = $maskEmail($recipientEmail);
    if ($masked !== "") {
      $recipientHint = " Recipient: " . $masked;
    }
  }
  $_SESSION["message_error"] = $error->getMessage() . $recipientHint;
  header("Location: approved.php?message_status=error");
  exit;
}
