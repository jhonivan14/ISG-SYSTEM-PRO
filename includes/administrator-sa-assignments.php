<?php
// Shared administrator SA assignment helpers.
// Trace: create assignment lock table -> claim SA by term -> enforce owner before evaluation.

require_once __DIR__ . "/evaluator-users.php";

if (!function_exists("isgEnsureAdministratorSaAssignmentsTable")) {
  function isgEnsureAdministratorSaAssignmentsTable(mysqli $conn): bool
  {
    static $ensured = false;
    if ($ensured) {
      return true;
    }

    $createSql = "CREATE TABLE IF NOT EXISTS administrator_sa_assignments (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      scholar_record_id INT NOT NULL,
      school_year VARCHAR(50) NOT NULL DEFAULT '',
      semester VARCHAR(50) NOT NULL DEFAULT '',
      administrator_username VARCHAR(100) NOT NULL,
      administrator_role VARCHAR(50) NOT NULL DEFAULT 'administrator',
      administrator_name VARCHAR(150) DEFAULT NULL,
      assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_admin_sa_term (scholar_record_id, school_year, semester),
      KEY idx_admin_assignment_owner (administrator_username, administrator_role),
      KEY idx_admin_assignment_term (school_year, semester)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createSql) !== true) {
      return false;
    }

    $columns = isgTableColumns($conn, "administrator_sa_assignments");
    $columnDefinitions = [
      "scholar_record_id" => "INT NOT NULL AFTER id",
      "school_year" => "VARCHAR(50) NOT NULL DEFAULT '' AFTER scholar_record_id",
      "semester" => "VARCHAR(50) NOT NULL DEFAULT '' AFTER school_year",
      "administrator_username" => "VARCHAR(100) NOT NULL AFTER semester",
      "administrator_role" => "VARCHAR(50) NOT NULL DEFAULT 'administrator' AFTER administrator_username",
      "administrator_name" => "VARCHAR(150) DEFAULT NULL AFTER administrator_role",
      "assigned_at" => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER administrator_name",
      "updated_at" => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER assigned_at",
    ];

    foreach ($columnDefinitions as $column => $definition) {
      if (!isset($columns[$column])) {
        if ($conn->query("ALTER TABLE administrator_sa_assignments ADD COLUMN {$column} {$definition}") !== true) {
          return false;
        }
      }
    }

    $indexColumns = [];
    $indexResult = $conn->query("SHOW INDEX FROM administrator_sa_assignments");
    if ($indexResult instanceof mysqli_result) {
      while ($indexRow = $indexResult->fetch_assoc()) {
        $keyName = trim((string)($indexRow["Key_name"] ?? ""));
        $sequence = (int)($indexRow["Seq_in_index"] ?? 0);
        $columnName = trim((string)($indexRow["Column_name"] ?? ""));
        if ($keyName !== "" && $sequence > 0 && $columnName !== "") {
          if (!isset($indexColumns[$keyName])) {
            $indexColumns[$keyName] = [];
          }
          $indexColumns[$keyName][$sequence] = $columnName;
        }
      }
      $indexResult->free();
    }

    if (!isset($indexColumns["uniq_admin_sa_term"])) {
      if ($conn->query("CREATE UNIQUE INDEX uniq_admin_sa_term ON administrator_sa_assignments (scholar_record_id, school_year, semester)") !== true) {
        return false;
      }
    }
    if (!isset($indexColumns["idx_admin_assignment_owner"])) {
      $conn->query("CREATE INDEX idx_admin_assignment_owner ON administrator_sa_assignments (administrator_username, administrator_role)");
    }
    if (!isset($indexColumns["idx_admin_assignment_term"])) {
      $conn->query("CREATE INDEX idx_admin_assignment_term ON administrator_sa_assignments (school_year, semester)");
    }

    isgBackfillAdministratorSaAssignmentsFromEvaluations($conn);

    $ensured = true;
    return true;
  }
}

if (!function_exists("isgBackfillAdministratorSaAssignmentsFromEvaluations")) {
  function isgBackfillAdministratorSaAssignmentsFromEvaluations(mysqli $conn): void
  {
    static $backfilled = false;
    if ($backfilled) {
      return;
    }

    $tableResult = $conn->query("SHOW TABLES LIKE 'department_head_evaluations'");
    $hasEvaluationTable = $tableResult instanceof mysqli_result && $tableResult->num_rows > 0;
    if ($tableResult instanceof mysqli_result) {
      $tableResult->free();
    }
    if (!$hasEvaluationTable) {
      $backfilled = true;
      return;
    }

    $evaluationColumns = isgTableColumns($conn, "department_head_evaluations");
    if (!isset($evaluationColumns["application_id"]) || !isset($evaluationColumns["head_username"]) || !isset($evaluationColumns["evaluator_role"])) {
      $backfilled = true;
      return;
    }

    $schoolYearExpr = isset($evaluationColumns["school_year"]) ? "TRIM(COALESCE(school_year, ''))" : "''";
    $semesterExpr = isset($evaluationColumns["semester"]) ? "TRIM(COALESCE(semester, ''))" : "''";
    $headNameExpr = isset($evaluationColumns["head_name"]) ? "NULLIF(TRIM(COALESCE(head_name, '')), '')" : "NULL";
    $assignedAtExpr = isset($evaluationColumns["created_at"]) ? "COALESCE(created_at, CURRENT_TIMESTAMP)" : "CURRENT_TIMESTAMP";
    if (isset($evaluationColumns["created_at"]) && isset($evaluationColumns["id"])) {
      $orderSql = "created_at ASC, id ASC";
    } elseif (isset($evaluationColumns["created_at"])) {
      $orderSql = "created_at ASC";
    } elseif (isset($evaluationColumns["id"])) {
      $orderSql = "id ASC";
    } else {
      $orderSql = "application_id ASC";
    }

    $conn->query("
      INSERT IGNORE INTO administrator_sa_assignments (
        scholar_record_id,
        school_year,
        semester,
        administrator_username,
        administrator_role,
        administrator_name,
        assigned_at
      )
      SELECT
        ABS(application_id) AS scholar_record_id,
        {$schoolYearExpr} AS school_year,
        {$semesterExpr} AS semester,
        TRIM(COALESCE(head_username, '')) AS administrator_username,
        'administrator' AS administrator_role,
        {$headNameExpr} AS administrator_name,
        {$assignedAtExpr} AS assigned_at
      FROM department_head_evaluations
      WHERE evaluator_role = 'administrator'
        AND application_id <> 0
        AND ABS(application_id) > 0
        AND TRIM(COALESCE(head_username, '')) <> ''
      ORDER BY {$orderSql}
    ");

    $backfilled = true;
  }
}

if (!function_exists("isgAdministratorSaAssignmentKey")) {
  function isgAdministratorSaAssignmentKey(int $scholarRecordId, string $schoolYear, string $semester): string
  {
    return $scholarRecordId . "|" . strtolower(trim($schoolYear)) . "|" . strtolower(trim($semester));
  }
}

if (!function_exists("isgLoadAdministratorSaAssignmentsForRecords")) {
  function isgLoadAdministratorSaAssignmentsForRecords(mysqli $conn, array $scholarRecordIds): array
  {
    if (!isgEnsureAdministratorSaAssignmentsTable($conn)) {
      return [];
    }

    $ids = [];
    foreach ($scholarRecordIds as $recordId) {
      $recordId = (int)$recordId;
      if ($recordId > 0) {
        $ids[$recordId] = $recordId;
      }
    }
    $ids = array_values($ids);
    if (empty($ids)) {
      return [];
    }

    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $stmt = $conn->prepare("
      SELECT
        scholar_record_id,
        school_year,
        semester,
        administrator_username,
        administrator_role,
        administrator_name,
        assigned_at
      FROM administrator_sa_assignments
      WHERE scholar_record_id IN ({$placeholders})
    ");
    if (!$stmt) {
      return [];
    }

    $types = str_repeat("i", count($ids));
    $stmt->bind_param($types, ...$ids);
    $assignments = [];
    if ($stmt->execute()) {
      $result = $stmt->get_result();
      while ($row = $result->fetch_assoc()) {
        $recordId = (int)($row["scholar_record_id"] ?? 0);
        if ($recordId <= 0) {
          continue;
        }
        $key = isgAdministratorSaAssignmentKey(
          $recordId,
          (string)($row["school_year"] ?? ""),
          (string)($row["semester"] ?? "")
        );
        $assignments[$key] = $row;
      }
      if ($result instanceof mysqli_result) {
        $result->free();
      }
    }
    $stmt->close();

    return $assignments;
  }
}

if (!function_exists("isgLoadAdministratorSaAssignment")) {
  function isgLoadAdministratorSaAssignment(mysqli $conn, int $scholarRecordId, string $schoolYear, string $semester): ?array
  {
    if ($scholarRecordId <= 0 || !isgEnsureAdministratorSaAssignmentsTable($conn)) {
      return null;
    }

    $stmt = $conn->prepare("
      SELECT
        scholar_record_id,
        school_year,
        semester,
        administrator_username,
        administrator_role,
        administrator_name,
        assigned_at
      FROM administrator_sa_assignments
      WHERE scholar_record_id = ?
        AND school_year = ?
        AND semester = ?
      LIMIT 1
    ");
    if (!$stmt) {
      return null;
    }

    $schoolYear = trim($schoolYear);
    $semester = trim($semester);
    $stmt->bind_param("iss", $scholarRecordId, $schoolYear, $semester);
    $assignment = null;
    if ($stmt->execute()) {
      $result = $stmt->get_result();
      $row = $result ? $result->fetch_assoc() : null;
      if (is_array($row)) {
        $assignment = $row;
      }
      if ($result instanceof mysqli_result) {
        $result->free();
      }
    }
    $stmt->close();

    return $assignment;
  }
}

if (!function_exists("isgAdministratorOwnsSaAssignment")) {
  function isgAdministratorOwnsSaAssignment(mysqli $conn, int $scholarRecordId, string $schoolYear, string $semester, string $administratorUsername): bool
  {
    $assignment = isgLoadAdministratorSaAssignment($conn, $scholarRecordId, $schoolYear, $semester);
    if (!is_array($assignment)) {
      return false;
    }

    return strtolower(trim((string)($assignment["administrator_username"] ?? ""))) === strtolower(trim($administratorUsername));
  }
}

if (!function_exists("isgClaimAdministratorSaAssignment")) {
  function isgClaimAdministratorSaAssignment(
    mysqli $conn,
    int $scholarRecordId,
    string $schoolYear,
    string $semester,
    string $administratorUsername,
    string $administratorName
  ): array {
    $schoolYear = trim($schoolYear);
    $semester = trim($semester);
    $administratorUsername = trim($administratorUsername);
    $administratorName = trim($administratorName);

    if ($scholarRecordId <= 0 || $schoolYear === "" || $semester === "" || $administratorUsername === "") {
      return ["ok" => false, "message" => "Unable to pick this student assistant because the record is incomplete."];
    }
    if (!isgEnsureAdministratorSaAssignmentsTable($conn)) {
      return ["ok" => false, "message" => "Unable to prepare administrator assignment storage."];
    }

    $existingAssignment = isgLoadAdministratorSaAssignment($conn, $scholarRecordId, $schoolYear, $semester);
    if (is_array($existingAssignment)) {
      $assignedUsername = trim((string)($existingAssignment["administrator_username"] ?? ""));
      if (strtolower($assignedUsername) === strtolower($administratorUsername)) {
        return ["ok" => true, "message" => "This student assistant is already picked by you."];
      }

      $assignedName = trim((string)($existingAssignment["administrator_name"] ?? ""));
      $ownerLabel = $assignedName !== "" ? $assignedName : $assignedUsername;
      return ["ok" => false, "message" => "This student assistant was already picked by " . ($ownerLabel !== "" ? $ownerLabel : "another administrator") . "."];
    }

    $stmt = $conn->prepare("
      INSERT INTO administrator_sa_assignments (
        scholar_record_id,
        school_year,
        semester,
        administrator_username,
        administrator_role,
        administrator_name
      ) VALUES (?, ?, ?, ?, 'administrator', NULLIF(?, ''))
    ");
    if (!$stmt) {
      return ["ok" => false, "message" => "Unable to prepare the assignment request."];
    }

    $stmt->bind_param("issss", $scholarRecordId, $schoolYear, $semester, $administratorUsername, $administratorName);
    $ok = $stmt->execute();
    $errorCode = (int)$stmt->errno;
    $stmt->close();

    if ($ok) {
      return ["ok" => true, "message" => "Student assistant picked successfully."];
    }

    if ($errorCode === 1062) {
      $latestAssignment = isgLoadAdministratorSaAssignment($conn, $scholarRecordId, $schoolYear, $semester);
      $assignedUsername = trim((string)($latestAssignment["administrator_username"] ?? ""));
      $assignedName = trim((string)($latestAssignment["administrator_name"] ?? ""));
      $ownerLabel = $assignedName !== "" ? $assignedName : $assignedUsername;
      return ["ok" => false, "message" => "This student assistant was just picked by " . ($ownerLabel !== "" ? $ownerLabel : "another administrator") . "."];
    }

    return ["ok" => false, "message" => "Unable to pick this student assistant."];
  }
}

if (!function_exists("isgLoadEvaluatorStudentAssistantRecord")) {
  function isgLoadEvaluatorStudentAssistantRecord(mysqli $conn, int $recordId, array $evaluatorScope): ?array
  {
    if ($recordId <= 0) {
      return null;
    }

    $restriction = isgEvaluatorScholarRestriction($evaluatorScope);
    $sql = "
      SELECT
        id,
        scholar_id,
        full_name,
        program_year,
        semester,
        academic_year,
        assigned_office,
        category,
        grant_applied,
        status,
        created_at
      FROM institutional_scholar_records
      WHERE id = ?
        AND (
          LOWER(TRIM(COALESCE(category, ''))) = 'student_assistant'
          OR (
            LOWER(TRIM(COALESCE(category, ''))) = 'official'
            AND LOWER(TRIM(COALESCE(grant_applied, ''))) LIKE '%assistant%'
          )
        )
        AND COALESCE(contract_ended, 0) = 0
        " . $restriction["sql"] . "
      LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      return null;
    }

    $types = "i" . $restriction["types"];
    $params = array_merge([$recordId], $restriction["params"]);
    $stmt->bind_param($types, ...$params);
    $row = null;
    if ($stmt->execute()) {
      $result = $stmt->get_result();
      $row = $result ? $result->fetch_assoc() : null;
      if ($result instanceof mysqli_result) {
        $result->free();
      }
    }
    $stmt->close();

    return is_array($row) ? $row : null;
  }
}
?>
