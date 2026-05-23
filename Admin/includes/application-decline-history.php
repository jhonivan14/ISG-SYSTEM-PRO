<?php
// Guide: Persistence helpers for declined application history snapshots.
// Trace: ensure table -> snapshot current application row -> backfill legacy declined rows.

if (!function_exists("applicationDeclineHistoryEnsureTable")) {
  function applicationDeclineHistoryEnsureTable(mysqli $conn): bool
  {
    return (bool)$conn->query("
      CREATE TABLE IF NOT EXISTS application_decline_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        reference_number VARCHAR(80) DEFAULT NULL,
        applicant_name VARCHAR(255) DEFAULT NULL,
        program_course VARCHAR(255) DEFAULT NULL,
        grant_id INT DEFAULT NULL,
        school_year VARCHAR(20) DEFAULT NULL,
        semester VARCHAR(50) DEFAULT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Rejected',
        declined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        source VARCHAR(50) DEFAULT NULL,
        snapshot_hash CHAR(40) NOT NULL,
        application_snapshot LONGTEXT DEFAULT NULL,
        KEY idx_decline_history_application_id (application_id),
        KEY idx_decline_history_reference_number (reference_number),
        KEY idx_decline_history_term (school_year, semester),
        UNIQUE KEY uniq_decline_history_snapshot_hash (snapshot_hash)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
  }
}

if (!function_exists("applicationDeclineHistorySnapshot")) {
  function applicationDeclineHistorySnapshot(mysqli $conn, int $applicationId, string $source = ""): bool
  {
    if ($applicationId <= 0 || !applicationDeclineHistoryEnsureTable($conn)) {
      return false;
    }

    $stmt = $conn->prepare("SELECT * FROM applications WHERE id = ? LIMIT 1");
    if (!$stmt) {
      return false;
    }

    $stmt->bind_param("i", $applicationId);
    $stmt->execute();
    $result = $stmt->get_result();
    $application = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($application)) {
      return false;
    }

    $snapshot = json_encode($application, JSON_UNESCAPED_SLASHES);
    if (!is_string($snapshot) || $snapshot === "") {
      $snapshot = serialize($application);
    }

    $snapshotHash = sha1($snapshot);
    $existsStmt = $conn->prepare("SELECT id FROM application_decline_history WHERE snapshot_hash = ? LIMIT 1");
    if ($existsStmt) {
      $existsStmt->bind_param("s", $snapshotHash);
      $existsStmt->execute();
      $existsResult = $existsStmt->get_result();
      $exists = $existsResult instanceof mysqli_result && $existsResult->num_rows > 0;
      $existsStmt->close();
      if ($exists) {
        return true;
      }
    }

    $referenceNumber = trim((string)($application["reference_number"] ?? ""));
    $applicantName = trim((string)($application["applicant_name"] ?? ""));
    $programCourse = trim((string)($application["program_course"] ?? ""));
    $grantId = (int)($application["grant_id"] ?? 0);
    $schoolYear = trim((string)($application["school_year"] ?? ""));
    $semester = trim((string)($application["semester"] ?? ""));
    $status = trim((string)($application["status"] ?? "Rejected"));
    if ($status === "") {
      $status = "Rejected";
    }
    $declinedAt = date("Y-m-d H:i:s");

    $insertStmt = $conn->prepare(
      "INSERT INTO application_decline_history (
        application_id, reference_number, applicant_name, program_course, grant_id,
        school_year, semester, status, declined_at, source, snapshot_hash, application_snapshot
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$insertStmt) {
      return false;
    }

    $insertStmt->bind_param(
      "isssisssssss",
      $applicationId,
      $referenceNumber,
      $applicantName,
      $programCourse,
      $grantId,
      $schoolYear,
      $semester,
      $status,
      $declinedAt,
      $source,
      $snapshotHash,
      $snapshot
    );
    $ok = $insertStmt->execute();
    $insertStmt->close();

    return $ok;
  }
}

if (!function_exists("applicationDeclineHistoryBackfillCurrent")) {
  function applicationDeclineHistoryBackfillCurrent(mysqli $conn): void
  {
    if (!applicationDeclineHistoryEnsureTable($conn)) {
      return;
    }

    $result = $conn->query("SELECT id FROM applications WHERE LOWER(TRIM(status)) IN ('declined', 'rejected')");
    if (!$result instanceof mysqli_result) {
      return;
    }

    while ($row = $result->fetch_assoc()) {
      applicationDeclineHistorySnapshot($conn, (int)($row["id"] ?? 0), "legacy_backfill");
    }
    $result->free();
  }
}
