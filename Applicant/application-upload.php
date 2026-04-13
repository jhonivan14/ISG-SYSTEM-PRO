<?php
require_once "../db.php";
require_once "../application-reference.php";
require_once "../upload-storage.php";

$referenceNumber = normalizeApplicationReference((string)($_GET["reference"] ?? ""));
$storedPath = trim((string)($_GET["stored_path"] ?? ""));
$mode = trim((string)($_GET["mode"] ?? "view"));

if ($referenceNumber === "" || $storedPath === "") {
  http_response_code(400);
  exit("Invalid upload request.");
}

backfillMissingApplicationReferences($conn);

$applicationStmt = $conn->prepare("SELECT id FROM applications WHERE reference_number = ? LIMIT 1");
if (!$applicationStmt) {
  http_response_code(500);
  exit("Failed to prepare application lookup.");
}

$applicationStmt->bind_param("s", $referenceNumber);
$applicationStmt->execute();
$applicationResult = $applicationStmt->get_result();
$application = $applicationResult ? $applicationResult->fetch_assoc() : null;
$applicationStmt->close();

if (!$application) {
  http_response_code(404);
  exit("Application not found.");
}

$applicationId = (int)($application["id"] ?? 0);
if ($applicationId <= 0) {
  http_response_code(404);
  exit("Application not found.");
}

$uploadStmt = $conn->prepare(
  "SELECT original_file_name, stored_path, mime_type
   FROM application_uploads
   WHERE application_id = ? AND stored_path = ?
   LIMIT 1"
);

if (!$uploadStmt) {
  http_response_code(500);
  exit("Failed to prepare upload lookup.");
}

$uploadStmt->bind_param("is", $applicationId, $storedPath);
$uploadStmt->execute();
$uploadResult = $uploadStmt->get_result();
$upload = $uploadResult ? $uploadResult->fetch_assoc() : null;
$uploadStmt->close();

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
