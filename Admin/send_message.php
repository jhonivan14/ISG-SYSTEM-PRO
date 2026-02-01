<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

require_once "../db.php";

$recipientEmail = isset($_POST["recipient_email"]) ? trim((string)$_POST["recipient_email"]) : "";
$messageBody = isset($_POST["message_body"]) ? trim((string)$_POST["message_body"]) : "";

if ($recipientEmail === "" || $messageBody === "") {
  header("Location: approved.php?message_status=error");
  exit;
}

if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
  header("Location: approved.php?message_status=error");
  exit;
}

try {
  // Manual PHPMailer includes (downloaded ZIP)
  $phpMailerBase = dirname(__DIR__) . "/lib/PHPMailer/PHPMailer-7.0.2/src";
  $phpMailerFiles = [
    $phpMailerBase . "/Exception.php",
    $phpMailerBase . "/PHPMailer.php",
    $phpMailerBase . "/SMTP.php",
  ];
  foreach ($phpMailerFiles as $file) {
    if (!file_exists($file)) {
      throw new RuntimeException("PHPMailer file missing: " . $file);
    }
    require_once $file;
  }

  // Optional: keep Composer autoload as fallback if present
  $composerAutoload = dirname(__DIR__) . "/vendor/autoload.php";
  if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
  }

  $mail = new PHPMailer(true);

  // Update these settings with your SMTP credentials
  $mail->isSMTP();
  $mail->SMTPDebug = 2;
  $mail->Debugoutput = "error_log";
  $mail->Host = "smtp.gmail.com";
  $mail->SMTPAuth = true;
  $mail->Username = "jhontabz14@gmail.com";
  $mail->Password = "ivanatics_14";
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = 587;

  $mail->setFrom("jhontabz14@gmail.com", "ISG Admin");
  $mail->addAddress($recipientEmail);
  $mail->Subject = "Scholarship Application Update";
  $mail->Body = $messageBody;

  $mail->send();

  header("Location: approved.php?message_status=sent");
  exit;
} catch (Throwable $error) {
  $_SESSION["message_error"] = $error->getMessage();
  header("Location: approved.php?message_status=error");
  exit;
}
