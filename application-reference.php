<?php

function ensureApplicationReferenceColumn(mysqli $conn): bool
{
    static $checked = false;
    static $available = false;

    if ($checked) {
        return $available;
    }

    $checked = true;
    $columnExists = false;
    $columnResult = $conn->query("SHOW COLUMNS FROM applications LIKE 'reference_number'");

    if ($columnResult instanceof mysqli_result) {
        $columnExists = $columnResult->num_rows > 0;
        $columnResult->free();
    }

    if (!$columnExists) {
        $available = (bool)$conn->query(
            "ALTER TABLE applications ADD COLUMN reference_number VARCHAR(32) DEFAULT NULL AFTER email_address"
        );

        return $available;
    }

    $available = true;
    return true;
}

function normalizeApplicationReference(string $value): string
{
    $value = strtoupper(trim($value));
    return preg_replace('/\s+/', '', $value);
}

function buildApplicationReferenceToken(int $length = 6): string
{
    $alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    $token = "";

    for ($index = 0; $index < $length; $index++) {
        $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return $token;
}

function generateApplicationReference(mysqli $conn): ?string
{
    if (!ensureApplicationReferenceColumn($conn)) {
        return null;
    }

    $lookupStmt = $conn->prepare("SELECT id FROM applications WHERE reference_number = ? LIMIT 1");
    if (!$lookupStmt) {
        return null;
    }

    for ($attempt = 0; $attempt < 12; $attempt++) {
        $referenceNumber = "ISG-" . date("Ymd") . "-" . buildApplicationReferenceToken();
        $lookupStmt->bind_param("s", $referenceNumber);
        $lookupStmt->execute();
        $result = $lookupStmt->get_result();
        $exists = $result instanceof mysqli_result && $result->fetch_assoc();
        if ($result instanceof mysqli_result) {
            $result->free();
        }

        if (!$exists) {
            $lookupStmt->close();
            return $referenceNumber;
        }
    }

    $lookupStmt->close();
    return null;
}

function assignApplicationReference(mysqli $conn, int $applicationId): ?string
{
    if ($applicationId <= 0 || !ensureApplicationReferenceColumn($conn)) {
        return null;
    }

    $existingStmt = $conn->prepare("SELECT reference_number FROM applications WHERE id = ? LIMIT 1");
    if (!$existingStmt) {
        return null;
    }

    $existingStmt->bind_param("i", $applicationId);
    $existingStmt->execute();
    $existingResult = $existingStmt->get_result();
    $existingRow = $existingResult ? $existingResult->fetch_assoc() : null;
    $existingStmt->close();

    if ($existingRow && trim((string)($existingRow["reference_number"] ?? "")) !== "") {
        return normalizeApplicationReference((string)$existingRow["reference_number"]);
    }

    $referenceNumber = generateApplicationReference($conn);
    if ($referenceNumber === null) {
        return null;
    }

    $stmt = $conn->prepare(
        "UPDATE applications
         SET reference_number = ?
         WHERE id = ? AND (reference_number IS NULL OR TRIM(reference_number) = '')"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("si", $referenceNumber, $applicationId);
    $success = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($success && $affectedRows > 0) {
        return $referenceNumber;
    }

    $reloadStmt = $conn->prepare("SELECT reference_number FROM applications WHERE id = ? LIMIT 1");
    if (!$reloadStmt) {
        return null;
    }

    $reloadStmt->bind_param("i", $applicationId);
    $reloadStmt->execute();
    $reloadResult = $reloadStmt->get_result();
    $reloadRow = $reloadResult ? $reloadResult->fetch_assoc() : null;
    $reloadStmt->close();

    if ($reloadRow && trim((string)($reloadRow["reference_number"] ?? "")) !== "") {
        return normalizeApplicationReference((string)$reloadRow["reference_number"]);
    }

    return null;
}

function backfillMissingApplicationReferences(mysqli $conn): void
{
    static $completed = false;

    if ($completed || !ensureApplicationReferenceColumn($conn)) {
        return;
    }

    $completed = true;
    $result = $conn->query(
        "SELECT id
         FROM applications
         WHERE reference_number IS NULL OR TRIM(reference_number) = ''
         ORDER BY id ASC"
    );

    if (!($result instanceof mysqli_result)) {
        return;
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();

    if (empty($rows)) {
        return;
    }

    $stmt = $conn->prepare("UPDATE applications SET reference_number = ? WHERE id = ?");
    if (!$stmt) {
        return;
    }

    foreach ($rows as $row) {
        $applicationId = (int)($row["id"] ?? 0);
        if ($applicationId <= 0) {
            continue;
        }

        $referenceNumber = generateApplicationReference($conn);
        if ($referenceNumber === null) {
            continue;
        }

        $stmt->bind_param("si", $referenceNumber, $applicationId);
        $stmt->execute();
    }

    $stmt->close();
}
