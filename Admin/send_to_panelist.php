<?php
require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once "../db.php";

$applicantId = (int)($_POST["applicant_id"] ?? 0);
$panelistUsernames = isset($_POST["panelist_usernames"]) && is_array($_POST["panelist_usernames"])
  ? array_values(array_unique(array_filter(array_map("trim", $_POST["panelist_usernames"]))))
  : [];

if ($applicantId <= 0) {
  $_SESSION["panelist_error"] = "Invalid applicant id.";
  header("Location: approved.php?panelist_status=error");
  exit;
}
if (empty($panelistUsernames)) {
  $_SESSION["panelist_error"] = "Please select at least one panelist.";
  header("Location: approved.php?panelist_status=error");
  exit;
}

$tableResult = $conn->query("SHOW TABLES LIKE 'panelist_queue'");
if (!$tableResult || $tableResult->num_rows === 0) {
  $_SESSION["panelist_error"] = "Panelist queue table is missing. Create panelist_queue table first.";
  header("Location: approved.php?panelist_status=error");
  exit;
}

$columnResult = $conn->query("SHOW COLUMNS FROM panelist_queue LIKE 'panelist_username'");
if (!$columnResult || $columnResult->num_rows === 0) {
  $_SESSION["panelist_error"] = "panelist_queue is missing panelist_username column. Please run the ALTER TABLE update.";
  header("Location: approved.php?panelist_status=error");
  exit;
}

$panelistCheck = $conn->prepare("SELECT username FROM panelists WHERE username = ? AND (status IS NULL OR status <> 'inactive') LIMIT 1");
if (!$panelistCheck) {
  $_SESSION["panelist_error"] = "Failed to validate panelist accounts.";
  header("Location: approved.php?panelist_status=error");
  exit;
}

$validPanelists = [];
foreach ($panelistUsernames as $username) {
  if ($username === "") {
    continue;
  }
  $panelistCheck->bind_param("s", $username);
  $panelistCheck->execute();
  $panelistCheck->store_result();
  if ($panelistCheck->num_rows > 0) {
    $validPanelists[] = $username;
  }
}
$panelistCheck->close();

if (empty($validPanelists)) {
  $_SESSION["panelist_error"] = "Selected panelists are not available.";
  header("Location: approved.php?panelist_status=error");
  exit;
}

$checkStmt = $conn->prepare("SELECT grant_id FROM applications WHERE id = ? LIMIT 1");
if (!$checkStmt) {
  $_SESSION["panelist_error"] = "Failed to verify applicant.";
  header("Location: approved.php?panelist_status=error");
  exit;
}

$checkStmt->bind_param("i", $applicantId);
$checkStmt->execute();
$checkStmt->bind_result($grantId);
$checkStmt->fetch();
$checkStmt->close();

if ((int)$grantId !== 1) {
  $_SESSION["panelist_error"] = "Only Student Assistant applicants can be sent to panelists.";
  header("Location: approved.php?panelist_status=error");
  exit;
}

$sentBy = isset($_SESSION["admin_name"]) && trim((string)$_SESSION["admin_name"]) !== ""
  ? trim((string)$_SESSION["admin_name"])
  : "Admin";

$insertSql = "INSERT INTO panelist_queue (application_id, panelist_username, sent_by, sent_at)
              VALUES (?, ?, ?, NOW())
              ON DUPLICATE KEY UPDATE sent_by = VALUES(sent_by), sent_at = VALUES(sent_at)";
$insertStmt = $conn->prepare($insertSql);
if (!$insertStmt) {
  $_SESSION["panelist_error"] = "Failed to queue applicant for panelist review.";
  header("Location: approved.php?panelist_status=error");
  exit;
}

$insertStmt->bind_param("iss", $applicantId, $panelistUsername, $sentBy);
$sentCount = 0;
foreach ($validPanelists as $panelistUsername) {
  $insertStmt->execute();
  if ($insertStmt->affected_rows > 0) {
    $sentCount++;
  }
}
$insertStmt->close();

$_SESSION["panelist_sent_count"] = $sentCount;
header("Location: approved.php?panelist_status=sent");
exit;
