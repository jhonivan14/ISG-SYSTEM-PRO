<?php
use PHPMailer\PHPMailer\PHPMailer;

session_start();
require_once "../db.php";

$applicationId = (int)($_POST["application_id"] ?? 0);
$messageBody = isset($_POST["message_body"]) ? trim((string)$_POST["message_body"]) : "";

if ($applicantId <= 0 || $messageBody === "") {
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

  // Manual PHPMailer includes (downloaded ZIP)
  $phpMailerBase = dirname(__DIR__) . "/lib/PHPMailer/PHPMailer-7.0.2/src";
  require_once $phpMailerBase . "/Exception.php";
  require_once $phpMailerBase . "/PHPMailer.php";
  require_once $phpMailerBase . "/SMTP.php";

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
  $mail->Username   = $smtp["jhontabz14@gmail.com"] ?? "";
  $mail->Password   = $smtp["uljizrjkyzyslzvr"] ?? "";
  $mail->Port       = (int)($smtp["port"] ?? 587);

  $secure = $smtp["secure"] ?? "tls";
  $mail->SMTPSecure = ($secure === "ssl")
    ? PHPMailer::ENCRYPTION_SMTPS
    : PHPMailer::ENCRYPTION_STARTTLS;

  if (!filter_var($mail->Username, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException("Invalid SMTP username email. Update smtp_config.php (username).");
  }
  if ($mail->Password === "") {
    throw new RuntimeException("SMTP password is missing. Update smtp_config.php (password).");
  }

  $fromEmail = $smtp["jhontabz14@gmail.com"] ?? $mail->Username;
  $fromName  = $smtp["SMCC"] ?? "ISG Admin";
  if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException("Invalid From email. Update smtp_config.php (from_email).");
  }

  $mail->setFrom($fromEmail, $fromName);
  $mail->addAddress($recipientEmail);
  $mail->Subject = "Scholarship Application Update";
  $mail->Body    = $messageBody;
  $mail->isHTML(false);

  // TEMP DEBUG (remove after testing)
  // $mail->SMTPDebug = 2;
  // $mail->Debugoutput = 'error_log';

  $mail->send();

  header("Location: approved.php?message_status=sent");
  exit;

} catch (Throwable $error) {
  $_SESSION["message_error"] = $error->getMessage();
  header("Location: approved.php?message_status=error");
  exit;
}
