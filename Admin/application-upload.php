<?php
require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once "../db.php";
require_once "../upload-storage.php";

$applicationId = (int)($_GET["application_id"] ?? 0);
$storedPath = trim((string)($_GET["stored_path"] ?? ""));
$mode = trim((string)($_GET["mode"] ?? "view"));

if ($applicationId <= 0 || $storedPath === "") {
  http_response_code(400);
  exit("Invalid upload request.");
}

$stmt = $conn->prepare(
  "SELECT original_file_name, stored_path, mime_type
   FROM application_uploads
   WHERE application_id = ? AND stored_path = ?
   LIMIT 1"
);

if (!$stmt) {
  http_response_code(500);
  exit("Failed to prepare upload lookup.");
}

$stmt->bind_param("is", $applicationId, $storedPath);
$stmt->execute();
$result = $stmt->get_result();
$upload = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$upload) {
  http_response_code(404);
  exit("Upload not found.");
}

$absolutePath = resolveStoredUploadPath((string)$upload["stored_path"]);
if ($absolutePath === "" || !is_file($absolutePath)) {
  http_response_code(404);
  exit("Stored file not found.");
}

$mimeType = trim((string)($upload["mime_type"] ?? ""));
if ($mimeType === "" && function_exists("mime_content_type")) {
  $detectedMimeType = mime_content_type($absolutePath);
  if (is_string($detectedMimeType) && $detectedMimeType !== "") {
    $mimeType = $detectedMimeType;
  }
}

if ($mimeType === "") {
  $mimeType = "application/octet-stream";
}

$originalFileName = trim((string)($upload["original_file_name"] ?? ""));
if ($originalFileName === "") {
  $originalFileName = basename($absolutePath);
}

$safeFileName = str_replace(["\\", "\"", "\r", "\n"], "_", basename($originalFileName));
$disposition = $mode === "download" ? "attachment" : "inline";
$fileSize = filesize($absolutePath);

header("Cache-Control: private, max-age=0, must-revalidate");
header("Content-Type: " . $mimeType);
if ($fileSize !== false) {
  header("Content-Length: " . (string)$fileSize);
}
header("Content-Disposition: " . $disposition . "; filename=\"" . $safeFileName . "\"");
header("X-Content-Type-Options: nosniff");

readfile($absolutePath);
exit;
