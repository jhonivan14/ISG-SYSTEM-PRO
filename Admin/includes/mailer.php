<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__, 2) . "/vendor/autoload.php";

function isgMaskEmail(string $email): string
{
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
        $maskedLocal = substr($local, 0, 1)
            . str_repeat("*", max(1, strlen($local) - 2))
            . substr($local, -1);
    }

    return $maskedLocal . "@" . $domain;
}

function isgSendPlainTextMail(string $recipientEmail, string $subject, string $body): void
{
    $recipientEmail = trim($recipientEmail);
    if ($recipientEmail === "" || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("Recipient email is invalid.");
    }

    $smtpConfigPath = dirname(__DIR__, 2) . "/smtp_config.php";
    if (!file_exists($smtpConfigPath)) {
        throw new RuntimeException("SMTP config missing. Create smtp_config.php in project root.");
    }

    $smtp = require $smtpConfigPath;
    if (!is_array($smtp)) {
        throw new RuntimeException("SMTP config is invalid.");
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtp["host"] ?? "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = $smtp["username"] ?? "";
    $mail->Password = $smtp["password"] ?? "";
    $mail->Port = (int)($smtp["port"] ?? 587);

    $secure = $smtp["secure"] ?? "tls";
    $mail->SMTPSecure = ($secure === "ssl")
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;

    if (!filter_var($mail->Username, FILTER_VALIDATE_EMAIL)) {
        $maskedUser = isgMaskEmail($mail->Username);
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
    $fromName = $smtp["from_name"] ?? "ISG Admin";
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $maskedFrom = isgMaskEmail($fromEmail);
        $hint = $maskedFrom !== "" ? " Current: " . $maskedFrom : " Current: [empty]";
        if (stripos($fromEmail, "yourgmail@gmail.com") !== false) {
            $hint .= " (still placeholder)";
        }
        throw new RuntimeException("Invalid From email. Update smtp_config.php (from_email)." . $hint);
    }

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($recipientEmail);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->isHTML(false);
    $mail->send();
}
