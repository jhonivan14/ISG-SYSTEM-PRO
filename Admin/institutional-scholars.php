<?php
// Guide: Institutional scholar registry with imports, renewals, status changes, and category tabs.
// Trace: normalize filters/actions -> ensure table exists -> load scholar records -> render UI -> client-side state handlers.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once __DIR__ . "/includes/school-term-filter.php";
require_once __DIR__ . "/includes/applicant-sidebar-badge.php";
require_once "../scholarship-grants.php";

$autoImportType = "";
$autoImportMessage = "";
$actionNoticeType = "";
$actionNoticeMessage = "";
$messageNoticeType = "";
$messageNoticeMessage = "";

$validScholarCategories = ["official", "student_assistant", "kabayani", "academic", "others"];
$activeCategoryParam = strtolower(trim((string)($_GET["active_category"] ?? "official")));
if (!in_array($activeCategoryParam, $validScholarCategories, true)) {
  $activeCategoryParam = "official";
}

$grantToCategoryMap = [
  1 => "student_assistant",
  2 => "academic",
  4 => "kabayani",
  5 => "kabayani",
];

$grantLabels = [
  1 => "Student Assistant",
  2 => "Academic Scholarship Program",
  3 => "Executive Student Government (ESG) President Scholarship Program",
  4 => "Kabayani Scholarship Program",
  5 => "Kabayani Loyalty Grant",
  6 => "Discount for Persons with Disability (PWD)",
  7 => "Discount for Children of Employees",
  8 => "Discount for Sibling of Employees",
  9 => "Sibling Discount",
  10 => "DXSM-FM Grant",
  11 => "Michaelinian Mirror Grant (Editor-in-Chief)",
  12 => "Grant for the Dependents of a Lot Donor",
  13 => "Grant for the Dependents of a Board of Trustees (BOT) Member",
  14 => "SMCC Alumni Discount",
];
$activeGrantLabels = isg_load_scholarship_grant_names($conn);
$grantLabels = isg_load_scholarship_grant_names($conn, true);

$scholarCategoryLabels = [
  "official" => "Official Scholars",
  "student_assistant" => "Student Assistant",
  "kabayani" => "Kabayani",
  "academic" => "Academic",
  "others" => "Others",
];

$manualGrantOptions = array_values($activeGrantLabels);
if (!in_array("Others", $manualGrantOptions, true)) {
  $manualGrantOptions[] = "Others";
}
$manualGrantDefaultsByCategory = [
  "student_assistant" => "Student Assistant",
  "academic" => "Academic Scholarship Program",
  "kabayani" => "Kabayani Scholarship Program",
  "others" => "Others",
];
$manualDefaultGrant = $manualGrantDefaultsByCategory[$activeCategoryParam] ?? "";

$serverScholarRecords = array_fill_keys($validScholarCategories, []);
$terminatedScholarRecords = [];
$assignedOfficeHistoryRecords = [];
$assignedOfficeOptions = [];
$noticeTypeParam = strtolower(trim((string)($_GET["scholar_notice"] ?? "")));
if (($noticeTypeParam === "success" || $noticeTypeParam === "error") && isset($_GET["scholar_notice_message"])) {
  $actionNoticeType = $noticeTypeParam;
  $actionNoticeMessage = trim((string)$_GET["scholar_notice_message"]);
}
$messageStatusParam = strtolower(trim((string)($_GET["message_status"] ?? "")));
if ($messageStatusParam === "sent") {
  $messageNoticeType = "success";
  $messageNoticeMessage = "Message sent successfully.";
} elseif ($messageStatusParam === "error") {
  $messageNoticeType = "error";
  $messageErrorText = isset($_SESSION["message_error"]) ? trim((string)$_SESSION["message_error"]) : "";
  $messageNoticeMessage = $messageErrorText !== "" ? $messageErrorText : "Failed to send message. Please try again.";
}
unset($_SESSION["message_error"]);

// Helpers below normalize scholar data, renewal rules, imports, and table persistence logic.

function isgBuildProgramYear(string $program, string $yearLevel): string
{
  $programYear = trim($program);
  $yearLevel = trim($yearLevel);
  if ($yearLevel !== "") {
    $programYear .= ($programYear !== "" ? " / " : "") . $yearLevel;
  }
  return $programYear;
}

function isgSplitProgramYear(string $programYear): array
{
  $value = trim($programYear);
  if ($value === "") {
    return ["", ""];
  }

  $parts = preg_split('/\s*\/\s*/', $value, 2);
  $program = trim((string)($parts[0] ?? ""));
  $yearLevel = trim((string)($parts[1] ?? ""));
  if ($program === "") {
    $program = $value;
  }

  return [$program, $yearLevel];
}

function isgCanonicalStatus(string $status): string
{
  $value = strtolower(trim($status));
  if ($value === "contract ended" || $value === "contract_ended") return "contract_ended";
  if ($value === "renewed") return "renewed";
  if ($value === "expired") return "expired";
  if ($value === "for renewal" || $value === "for_renewal") return "for_renewal";
  return "official_scholar";
}

function isgIncrementSchoolYear(string $schoolYear, string $fallback): string
{
  $value = trim($schoolYear);
  if (!preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $value, $matches)) {
    $value = trim($fallback);
    if (!preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $value, $matches)) {
      $year = (int)date("Y");
      return $year . "-" . ($year + 1);
    }
  }
  $nextStart = ((int)$matches[1]) + 1;
  return $nextStart . "-" . ($nextStart + 1);
}

function isgSchoolYearAvailableForRenewal(array $schoolYearOptions, string $targetSchoolYear): bool
{
  $targetValue = trim($targetSchoolYear);
  if ($targetValue === "") {
    return false;
  }

  foreach ($schoolYearOptions as $option) {
    if (trim((string)$option) === $targetValue) {
      return true;
    }
  }

  return false;
}

function isgHasConfiguredInstitutionalActiveTerm(string $schoolYear, string $semester): bool
{
  $schoolYear = trim($schoolYear);
  $semester = trim($semester);
  return $schoolYear !== "" && ($semester === "1st Semester" || $semester === "2nd Semester");
}

function isgNormalizeYearLevelLabel(int $yearLevel): string
{
  if ($yearLevel <= 1) {
    return "1st Year";
  }
  if ($yearLevel === 2) {
    return "2nd Year";
  }
  if ($yearLevel === 3) {
    return "3rd Year";
  }
  return "4th Year";
}

function isgIncrementYearLevelLabel(string $yearLabel): string
{
  $value = trim($yearLabel);
  if ($value === "") {
    return $value;
  }

  if (preg_match('/\b([1-4])(?:st|nd|rd|th)\s*year\b/i', $value, $matches)) {
    $currentYearLevel = (int)$matches[1];
    $nextYearLevel = min($currentYearLevel + 1, 4);
    return preg_replace(
      '/\b([1-4])(?:st|nd|rd|th)\s*year\b/i',
      isgNormalizeYearLevelLabel($nextYearLevel),
      $value,
      1
    ) ?? $value;
  }

  $wordMap = [
    "first" => 1,
    "second" => 2,
    "third" => 3,
    "fourth" => 4,
  ];
  if (preg_match('/\b(first|second|third|fourth)\s*year\b/i', $value, $matches)) {
    $matchedWord = strtolower((string)($matches[1] ?? ""));
    $currentYearLevel = $wordMap[$matchedWord] ?? 0;
    if ($currentYearLevel > 0) {
      $nextYearLevel = min($currentYearLevel + 1, 4);
      return preg_replace(
        '/\b(first|second|third|fourth)\s*year\b/i',
        isgNormalizeYearLevelLabel($nextYearLevel),
        $value,
        1
      ) ?? $value;
    }
  }

  return $value;
}

function isgIncrementProgramYearLevel(string $programYear): string
{
  $value = trim($programYear);
  if ($value === "") {
    return $value;
  }

  $parts = preg_split('/\s*\/\s*/', $value, 2);
  if (is_array($parts) && count($parts) === 2) {
    $program = trim((string)($parts[0] ?? ""));
    $yearLabel = trim((string)($parts[1] ?? ""));
    $nextYearLabel = isgIncrementYearLevelLabel($yearLabel);
    if ($program !== "" && $nextYearLabel !== "") {
      return $program . " / " . $nextYearLabel;
    }
    return $value;
  }

  return isgIncrementYearLevelLabel($value);
}

function isgSchoolYearStart(string $schoolYear): int
{
  $value = trim($schoolYear);
  if (!preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $value, $matches)) {
    return 0;
  }
  return (int)$matches[1];
}

function isgEnsureInstitutionalScholarTable(mysqli $conn): bool
{
  $createSql = "
    CREATE TABLE IF NOT EXISTS institutional_scholar_records (
      id INT AUTO_INCREMENT PRIMARY KEY,
      source_application_id INT DEFAULT NULL,
      category VARCHAR(40) NOT NULL,
      scholar_id VARCHAR(60) NOT NULL,
      grant_applied VARCHAR(255) NOT NULL,
      full_name VARCHAR(255) NOT NULL,
      email VARCHAR(255) DEFAULT NULL,
      program_year VARCHAR(255) DEFAULT NULL,
      assigned_office VARCHAR(255) DEFAULT NULL,
      semester VARCHAR(50) DEFAULT NULL,
      academic_year VARCHAR(20) DEFAULT NULL,
      status VARCHAR(40) NOT NULL DEFAULT 'official_scholar',
      renewal_status VARCHAR(40) NOT NULL DEFAULT '',
      renewal_scope VARCHAR(40) NOT NULL DEFAULT '',
      second_semester_renewed TINYINT(1) NOT NULL DEFAULT 0,
      contract_ended TINYINT(1) NOT NULL DEFAULT 0,
      termination_reason TEXT NULL,
      terminated_at DATETIME DEFAULT NULL,
      terminated_by VARCHAR(100) DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_isr_category_source_term (category, source_application_id, semester, academic_year),
      UNIQUE KEY uniq_isr_category_scholar_term (category, scholar_id, semester, academic_year),
      KEY idx_isr_source (source_application_id),
      KEY idx_isr_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ";
  if (!$conn->query($createSql)) {
    return false;
  }

  $columnDefinitions = [
    "source_application_id" => "INT DEFAULT NULL AFTER id",
    "category" => "VARCHAR(40) NOT NULL AFTER source_application_id",
    "scholar_id" => "VARCHAR(60) NOT NULL AFTER category",
    "grant_applied" => "VARCHAR(255) NOT NULL AFTER scholar_id",
    "full_name" => "VARCHAR(255) NOT NULL AFTER grant_applied",
    "email" => "VARCHAR(255) DEFAULT NULL AFTER full_name",
    "program_year" => "VARCHAR(255) DEFAULT NULL AFTER email",
    "assigned_office" => "VARCHAR(255) DEFAULT NULL AFTER program_year",
    "semester" => "VARCHAR(50) DEFAULT NULL AFTER assigned_office",
    "academic_year" => "VARCHAR(20) DEFAULT NULL AFTER semester",
    "status" => "VARCHAR(40) NOT NULL DEFAULT 'official_scholar' AFTER academic_year",
    "renewal_status" => "VARCHAR(40) NOT NULL DEFAULT '' AFTER status",
    "renewal_scope" => "VARCHAR(40) NOT NULL DEFAULT '' AFTER renewal_status",
    "second_semester_renewed" => "TINYINT(1) NOT NULL DEFAULT 0 AFTER renewal_scope",
    "contract_ended" => "TINYINT(1) NOT NULL DEFAULT 0 AFTER second_semester_renewed",
    "termination_reason" => "TEXT NULL AFTER contract_ended",
    "terminated_at" => "DATETIME DEFAULT NULL AFTER termination_reason",
    "terminated_by" => "VARCHAR(100) DEFAULT NULL AFTER terminated_at",
    "created_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER terminated_by",
    "updated_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
  ];
  foreach ($columnDefinitions as $column => $definition) {
    $columnResult = $conn->query("SHOW COLUMNS FROM institutional_scholar_records LIKE '" . $conn->real_escape_string($column) . "'");
    $exists = $columnResult instanceof mysqli_result && $columnResult->num_rows > 0;
    if ($columnResult instanceof mysqli_result) {
      $columnResult->free();
    }
    if (!$exists) {
      $conn->query("ALTER TABLE institutional_scholar_records ADD COLUMN $column $definition");
    }
  }

  foreach (["uniq_isr_category_source", "uniq_isr_category_scholar"] as $legacyIndexName) {
    $legacyIndexResult = $conn->query("SHOW INDEX FROM institutional_scholar_records WHERE Key_name = '" . $conn->real_escape_string($legacyIndexName) . "'");
    $legacyExists = $legacyIndexResult instanceof mysqli_result && $legacyIndexResult->num_rows > 0;
    if ($legacyIndexResult instanceof mysqli_result) {
      $legacyIndexResult->free();
    }
    if ($legacyExists) {
      $conn->query("ALTER TABLE institutional_scholar_records DROP INDEX $legacyIndexName");
    }
  }

  $indexChecks = [
    "uniq_isr_category_source_term" => "CREATE UNIQUE INDEX uniq_isr_category_source_term ON institutional_scholar_records (category, source_application_id, semester, academic_year)",
    "uniq_isr_category_scholar_term" => "CREATE UNIQUE INDEX uniq_isr_category_scholar_term ON institutional_scholar_records (category, scholar_id, semester, academic_year)",
    "idx_isr_source" => "CREATE INDEX idx_isr_source ON institutional_scholar_records (source_application_id)",
    "idx_isr_category" => "CREATE INDEX idx_isr_category ON institutional_scholar_records (category)",
  ];
  foreach ($indexChecks as $indexName => $indexSql) {
    $indexResult = $conn->query("SHOW INDEX FROM institutional_scholar_records WHERE Key_name = '" . $conn->real_escape_string($indexName) . "'");
    $exists = $indexResult instanceof mysqli_result && $indexResult->num_rows > 0;
    if ($indexResult instanceof mysqli_result) {
      $indexResult->free();
    }
    if (!$exists) {
      $conn->query($indexSql);
    }
  }

  $conn->query("
    UPDATE institutional_scholar_records target
    INNER JOIN institutional_scholar_records source
      ON LOWER(TRIM(COALESCE(source.category, ''))) = LOWER(TRIM(COALESCE(target.category, '')))
      AND LOWER(TRIM(COALESCE(source.scholar_id, ''))) = LOWER(TRIM(COALESCE(target.scholar_id, '')))
      AND TRIM(COALESCE(source.academic_year, '')) = TRIM(COALESCE(target.academic_year, ''))
      AND (
        LOWER(TRIM(COALESCE(source.semester, ''))) LIKE '%1st%'
        OR LOWER(TRIM(COALESCE(source.semester, ''))) LIKE '%first%'
      )
      AND LOWER(TRIM(COALESCE(source.status, ''))) = 'renewed'
      AND LOWER(TRIM(COALESCE(source.renewal_scope, ''))) = '2nd_semester'
    SET
      target.status = 'renewed',
      target.renewal_status = 'renew',
      target.renewal_scope = '',
      target.second_semester_renewed = 0
    WHERE COALESCE(target.contract_ended, 0) = 0
      AND (
        LOWER(TRIM(COALESCE(target.semester, ''))) LIKE '%2nd%'
        OR LOWER(TRIM(COALESCE(target.semester, ''))) LIKE '%second%'
      )
      AND LOWER(TRIM(COALESCE(target.status, ''))) <> 'terminated'
  ");

  $conn->query("
    UPDATE institutional_scholar_records target
    INNER JOIN institutional_scholar_records source
      ON LOWER(TRIM(COALESCE(source.category, ''))) = LOWER(TRIM(COALESCE(target.category, '')))
      AND LOWER(TRIM(COALESCE(source.scholar_id, ''))) = LOWER(TRIM(COALESCE(target.scholar_id, '')))
      AND TRIM(COALESCE(target.academic_year, '')) = CONCAT(
        CAST(SUBSTRING(TRIM(COALESCE(source.academic_year, '')), 1, 4) AS UNSIGNED) + 1,
        '-',
        CAST(SUBSTRING(TRIM(COALESCE(source.academic_year, '')), 6, 4) AS UNSIGNED) + 1
      )
      AND LOWER(TRIM(COALESCE(source.status, ''))) = 'renewed'
      AND LOWER(TRIM(COALESCE(source.renewal_scope, ''))) = 'school_year'
    SET
      target.status = 'renewed',
      target.renewal_status = 'renew',
      target.renewal_scope = '',
      target.second_semester_renewed = 0
    WHERE COALESCE(target.contract_ended, 0) = 0
      AND (
        LOWER(TRIM(COALESCE(target.semester, ''))) LIKE '%1st%'
        OR LOWER(TRIM(COALESCE(target.semester, ''))) LIKE '%first%'
      )
      AND LOWER(TRIM(COALESCE(target.status, ''))) <> 'terminated'
      AND TRIM(COALESCE(source.academic_year, '')) REGEXP '^[0-9]{4}-[0-9]{4}$'
  ");

  return true;
}

function isgEnsureAssignedOfficeHistoryTable(mysqli $conn): bool
{
  $createSql = "
    CREATE TABLE IF NOT EXISTS institutional_scholar_office_history (
      id INT AUTO_INCREMENT PRIMARY KEY,
      scholar_record_id INT DEFAULT NULL,
      source_application_id INT DEFAULT NULL,
      scholar_id VARCHAR(60) NOT NULL,
      full_name VARCHAR(255) NOT NULL,
      academic_year VARCHAR(20) DEFAULT NULL,
      semester VARCHAR(50) DEFAULT NULL,
      from_office VARCHAR(255) DEFAULT NULL,
      to_office VARCHAR(255) NOT NULL,
      changed_by VARCHAR(100) DEFAULT NULL,
      changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      KEY idx_isoh_scholar_term (scholar_id, academic_year, semester),
      KEY idx_isoh_record (scholar_record_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ";
  if (!$conn->query($createSql)) {
    return false;
  }

  $columnDefinitions = [
    "scholar_record_id" => "INT DEFAULT NULL AFTER id",
    "source_application_id" => "INT DEFAULT NULL AFTER scholar_record_id",
    "scholar_id" => "VARCHAR(60) NOT NULL AFTER source_application_id",
    "full_name" => "VARCHAR(255) NOT NULL AFTER scholar_id",
    "academic_year" => "VARCHAR(20) DEFAULT NULL AFTER full_name",
    "semester" => "VARCHAR(50) DEFAULT NULL AFTER academic_year",
    "from_office" => "VARCHAR(255) DEFAULT NULL AFTER semester",
    "to_office" => "VARCHAR(255) NOT NULL AFTER from_office",
    "changed_by" => "VARCHAR(100) DEFAULT NULL AFTER to_office",
    "changed_at" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER changed_by",
  ];
  foreach ($columnDefinitions as $column => $definition) {
    $columnResult = $conn->query("SHOW COLUMNS FROM institutional_scholar_office_history LIKE '" . $conn->real_escape_string($column) . "'");
    $exists = $columnResult instanceof mysqli_result && $columnResult->num_rows > 0;
    if ($columnResult instanceof mysqli_result) {
      $columnResult->free();
    }
    if (!$exists) {
      $conn->query("ALTER TABLE institutional_scholar_office_history ADD COLUMN $column $definition");
    }
  }

  return true;
}

function isgUpsertScholarRecord(mysqli $conn, string $category, array $record): bool
{
  $sourceApplicationId = isset($record["source_application_id"]) ? (int)$record["source_application_id"] : 0;
  $sourceParam = $sourceApplicationId > 0 ? $sourceApplicationId : null;
  $scholarId = trim((string)($record["scholar_id"] ?? ""));
  if ($scholarId === "") {
    return false;
  }

  $grantApplied = trim((string)($record["grant_applied"] ?? ""));
  $fullName = trim((string)($record["full_name"] ?? ""));
  if ($fullName === "") {
    return false;
  }

  $programYear = trim((string)($record["program_year"] ?? ""));
  $email = trim((string)($record["email"] ?? ""));
  $assignedOffice = trim((string)($record["assigned_office"] ?? ""));
  $semester = trim((string)($record["semester"] ?? ""));
  $academicYear = trim((string)($record["academic_year"] ?? ""));
  $status = isgCanonicalStatus((string)($record["status"] ?? ""));
  $recordCanBeUpdatedSql = "
    COALESCE(contract_ended, 0) = 0
    AND LOWER(TRIM(COALESCE(status, ''))) <> 'terminated'
    AND NOT (
      LOWER(TRIM(COALESCE(status, ''))) = 'renewed'
      AND LOWER(TRIM(COALESCE(renewal_scope, ''))) IN ('2nd_semester', 'school_year')
    )
  ";

  if ($sourceParam !== null) {
    $insertSql = "
      INSERT INTO institutional_scholar_records
        (source_application_id, category, scholar_id, grant_applied, full_name, email, program_year, assigned_office, semester, academic_year, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        scholar_id = IF({$recordCanBeUpdatedSql}, VALUES(scholar_id), scholar_id),
        grant_applied = IF({$recordCanBeUpdatedSql}, VALUES(grant_applied), grant_applied),
        full_name = IF({$recordCanBeUpdatedSql}, VALUES(full_name), full_name),
        email = IF({$recordCanBeUpdatedSql} AND TRIM(COALESCE(VALUES(email), '')) <> '', VALUES(email), email),
        program_year = IF({$recordCanBeUpdatedSql}, VALUES(program_year), program_year),
        assigned_office = IF({$recordCanBeUpdatedSql}, VALUES(assigned_office), assigned_office),
        updated_at = IF({$recordCanBeUpdatedSql}, CURRENT_TIMESTAMP, updated_at)
    ";
    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param(
      "issssssssss",
      $sourceParam,
      $category,
      $scholarId,
      $grantApplied,
      $fullName,
      $email,
      $programYear,
      $assignedOffice,
      $semester,
      $academicYear,
      $status
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  }

  $insertSql = "
    INSERT INTO institutional_scholar_records
      (source_application_id, category, scholar_id, grant_applied, full_name, email, program_year, assigned_office, semester, academic_year, status)
    VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      grant_applied = IF({$recordCanBeUpdatedSql}, VALUES(grant_applied), grant_applied),
      full_name = IF({$recordCanBeUpdatedSql}, VALUES(full_name), full_name),
      email = IF({$recordCanBeUpdatedSql} AND TRIM(COALESCE(VALUES(email), '')) <> '', VALUES(email), email),
      program_year = IF({$recordCanBeUpdatedSql}, VALUES(program_year), program_year),
      assigned_office = IF({$recordCanBeUpdatedSql}, VALUES(assigned_office), assigned_office),
      updated_at = IF({$recordCanBeUpdatedSql}, CURRENT_TIMESTAMP, updated_at)
  ";
  $stmt = $conn->prepare($insertSql);
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param(
    "ssssssssss",
    $category,
    $scholarId,
    $grantApplied,
    $fullName,
    $email,
    $programYear,
    $assignedOffice,
    $semester,
    $academicYear,
    $status
  );
  $ok = $stmt->execute();
  $stmt->close();
  return $ok;
}

function isgUpsertScholarTermRecord(mysqli $conn, array $record): bool
{
  $sourceApplicationId = isset($record["source_application_id"]) && (int)$record["source_application_id"] > 0
    ? (int)$record["source_application_id"]
    : null;
  $category = trim((string)($record["category"] ?? ""));
  $scholarId = trim((string)($record["scholar_id"] ?? ""));
  $grantApplied = trim((string)($record["grant_applied"] ?? ""));
  $fullName = trim((string)($record["full_name"] ?? ""));
  if ($category === "" || $scholarId === "" || $fullName === "") {
    return false;
  }

  $programYear = trim((string)($record["program_year"] ?? ""));
  $email = trim((string)($record["email"] ?? ""));
  $assignedOffice = trim((string)($record["assigned_office"] ?? ""));
  $semester = trim((string)($record["semester"] ?? ""));
  $academicYear = trim((string)($record["academic_year"] ?? ""));
  $status = isgCanonicalStatus((string)($record["status"] ?? "official_scholar"));
  $renewalStatus = trim((string)($record["renewal_status"] ?? ""));
  $renewalScope = trim((string)($record["renewal_scope"] ?? ""));
  $secondSemesterRenewed = (int)($record["second_semester_renewed"] ?? 0) === 1 ? 1 : 0;

  $sql = "
    INSERT INTO institutional_scholar_records
      (
        source_application_id,
        category,
        scholar_id,
        grant_applied,
        full_name,
        email,
        program_year,
        assigned_office,
        semester,
        academic_year,
        status,
        renewal_status,
        renewal_scope,
        second_semester_renewed
      )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      grant_applied = VALUES(grant_applied),
      full_name = VALUES(full_name),
      email = IF(TRIM(COALESCE(VALUES(email), '')) <> '', VALUES(email), email),
      program_year = VALUES(program_year),
      assigned_office = VALUES(assigned_office),
      status = VALUES(status),
      renewal_status = VALUES(renewal_status),
      renewal_scope = VALUES(renewal_scope),
      second_semester_renewed = VALUES(second_semester_renewed),
      contract_ended = 0,
      termination_reason = NULL,
      terminated_at = NULL,
      terminated_by = NULL,
      updated_at = CURRENT_TIMESTAMP
  ";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return false;
  }

  $stmt->bind_param(
    "issssssssssssi",
    $sourceApplicationId,
    $category,
    $scholarId,
    $grantApplied,
    $fullName,
    $email,
    $programYear,
    $assignedOffice,
    $semester,
    $academicYear,
    $status,
    $renewalStatus,
    $renewalScope,
    $secondSemesterRenewed
  );
  $ok = $stmt->execute();
  $stmt->close();

  return $ok;
}

function isgGenerateManualScholarId(mysqli $conn): string
{
  for ($i = 0; $i < 5; $i++) {
    try {
      $suffix = strtoupper(bin2hex(random_bytes(3)));
    } catch (Throwable $e) {
      $suffix = strtoupper(dechex(mt_rand(100000, 999999)));
    }
    $candidate = "MAN-" . date("Ymd") . "-" . $suffix;

    $checkStmt = $conn->prepare("SELECT id FROM institutional_scholar_records WHERE scholar_id = ? LIMIT 1");
    if (!$checkStmt) {
      return $candidate;
    }
    $checkStmt->bind_param("s", $candidate);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;
    if ($result instanceof mysqli_result) {
      $result->free();
    }
    $checkStmt->close();
    if (!$exists) {
      return $candidate;
    }
  }

  return "MAN-" . date("YmdHis");
}

function isgNormalizeHeader(string $header): string
{
  $value = strtolower(trim($header));
  $value = preg_replace('/\s+/', '_', $value);
  $value = preg_replace('/[^a-z0-9_]+/', '', $value);
  return trim((string)$value, "_");
}

function isgFirstNonEmptyValue(array $row, array $keys): string
{
  foreach ($keys as $key) {
    $normalizedKey = isgNormalizeHeader((string)$key);
    if ($normalizedKey === "") {
      continue;
    }
    if (!array_key_exists($normalizedKey, $row)) {
      continue;
    }
    $value = trim((string)$row[$normalizedKey]);
    if ($value !== "") {
      return $value;
    }
  }
  return "";
}

function isgNormalizeCategoryValue(string $value): string
{
  $normalized = strtolower(trim($value));
  if ($normalized === "") return "";
  if (in_array($normalized, ["student assistant", "student_assistant", "studentassistant", "assistant"], true)) return "student_assistant";
  if (in_array($normalized, ["kabayani"], true)) return "kabayani";
  if (in_array($normalized, ["academic", "acad"], true)) return "academic";
  if (in_array($normalized, ["others", "other"], true)) return "others";
  if (in_array($normalized, ["official", "official scholar", "official_scholar"], true)) return "official";
  return "";
}

function isgCategoryFromGrantValue(string $grant): string
{
  $normalized = strtolower(trim($grant));
  if ($normalized === "") return "others";
  if (strpos($normalized, "assistant") !== false) return "student_assistant";
  if (strpos($normalized, "kabayani") !== false) return "kabayani";
  if (strpos($normalized, "academic") !== false) return "academic";
  if (strpos($normalized, "official") !== false) return "official";
  return "others";
}

function isgNormalizeSemesterValue(string $value, string $fallback): string
{
  $normalized = strtolower(trim($value));
  if ($normalized === "") return $fallback;
  if (strpos($normalized, "1st") !== false || strpos($normalized, "first") !== false || $normalized === "1") return "1st Semester";
  if (strpos($normalized, "2nd") !== false || strpos($normalized, "second") !== false || $normalized === "2") return "2nd Semester";
  return $fallback;
}

function isgNormalizeEmailAddress(string $value): string
{
  $email = trim($value);
  if ($email === "") {
    return "";
  }

  return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : "";
}

function isgColumnLettersToIndex(string $letters): int
{
  $letters = strtoupper(trim($letters));
  $index = 0;
  $length = strlen($letters);
  for ($i = 0; $i < $length; $i++) {
    $char = ord($letters[$i]);
    if ($char < 65 || $char > 90) {
      continue;
    }
    $index = ($index * 26) + ($char - 64);
  }
  return max(0, $index - 1);
}

function isgExtractXlsxRows(string $filePath, string &$error): array
{
  if (!class_exists("ZipArchive")) {
    $error = "ZipArchive extension is not enabled in PHP.";
    return [];
  }

  $zip = new ZipArchive();
  if ($zip->open($filePath) !== true) {
    $error = "Unable to open Excel file.";
    return [];
  }

  $sharedStrings = [];
  $sharedStringsXml = $zip->getFromName("xl/sharedStrings.xml");
  if (is_string($sharedStringsXml) && $sharedStringsXml !== "") {
    $sharedDoc = new DOMDocument();
    if (@$sharedDoc->loadXML($sharedStringsXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
      $sharedXPath = new DOMXPath($sharedDoc);
      $sharedXPath->registerNamespace("main", "http://schemas.openxmlformats.org/spreadsheetml/2006/main");
      $siNodes = $sharedXPath->query("//main:sst/main:si");
      if ($siNodes instanceof DOMNodeList) {
        foreach ($siNodes as $siNode) {
          $parts = [];
          $textNodes = $sharedXPath->query(".//main:t", $siNode);
          if ($textNodes instanceof DOMNodeList) {
            foreach ($textNodes as $textNode) {
              $parts[] = (string)$textNode->textContent;
            }
          }
          $sharedStrings[] = trim(implode("", $parts));
        }
      }
    }
  }

  $sheetPath = "";
  for ($i = 0; $i < $zip->numFiles; $i++) {
    $entryName = (string)$zip->getNameIndex($i);
    if (preg_match('/^xl\/worksheets\/sheet\d+\.xml$/i', $entryName)) {
      $sheetPath = $entryName;
      break;
    }
  }
  if ($sheetPath === "") {
    $zip->close();
    $error = "No worksheet found in Excel file.";
    return [];
  }

  $sheetXml = $zip->getFromName($sheetPath);
  $zip->close();
  if (!is_string($sheetXml) || $sheetXml === "") {
    $error = "Unable to read worksheet data.";
    return [];
  }

  $sheetDoc = new DOMDocument();
  if (!@$sheetDoc->loadXML($sheetXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
    $error = "Invalid worksheet XML format.";
    return [];
  }

  $sheetXPath = new DOMXPath($sheetDoc);
  $sheetXPath->registerNamespace("main", "http://schemas.openxmlformats.org/spreadsheetml/2006/main");
  $rowNodes = $sheetXPath->query("//main:sheetData/main:row");
  if (!($rowNodes instanceof DOMNodeList)) {
    $error = "No rows found in worksheet.";
    return [];
  }

  $rawRows = [];
  foreach ($rowNodes as $rowNode) {
    $rowValues = [];
    $cellNodes = $sheetXPath->query("./main:c", $rowNode);
    if (!($cellNodes instanceof DOMNodeList)) {
      continue;
    }
    foreach ($cellNodes as $cellNode) {
      if (!($cellNode instanceof DOMElement)) {
        continue;
      }
      $reference = strtoupper((string)$cellNode->getAttribute("r"));
      $matches = [];
      if (!preg_match('/([A-Z]+)/', $reference, $matches)) {
        continue;
      }
      $columnIndex = isgColumnLettersToIndex((string)$matches[1]);
      $cellType = strtolower((string)$cellNode->getAttribute("t"));
      $value = "";

      if ($cellType === "inlineStr") {
        $textNodes = $sheetXPath->query(".//main:t", $cellNode);
        if ($textNodes instanceof DOMNodeList) {
          $parts = [];
          foreach ($textNodes as $textNode) {
            $parts[] = (string)$textNode->textContent;
          }
          $value = implode("", $parts);
        }
      } else {
        $valueNodes = $sheetXPath->query("./main:v", $cellNode);
        $rawValue = "";
        if ($valueNodes instanceof DOMNodeList && $valueNodes->length > 0) {
          $rawValue = trim((string)$valueNodes->item(0)->textContent);
        }
        if ($cellType === "s") {
          $sharedIndex = (int)$rawValue;
          $value = $sharedStrings[$sharedIndex] ?? "";
        } else {
          $value = $rawValue;
        }
      }

      $rowValues[$columnIndex] = trim((string)$value);
    }

    if (empty($rowValues)) {
      continue;
    }
    $hasContent = false;
    foreach ($rowValues as $cellValue) {
      if (trim((string)$cellValue) !== "") {
        $hasContent = true;
        break;
      }
    }
    if ($hasContent) {
      $rawRows[] = $rowValues;
    }
  }

  if (empty($rawRows)) {
    $error = "Excel file has no readable rows.";
    return [];
  }

  $headerRow = $rawRows[0];
  ksort($headerRow);
  $headerMap = [];
  foreach ($headerRow as $colIndex => $headerValue) {
    $normalizedHeader = isgNormalizeHeader((string)$headerValue);
    if ($normalizedHeader !== "") {
      $headerMap[(int)$colIndex] = $normalizedHeader;
    }
  }
  if (empty($headerMap)) {
    $error = "Excel header row is empty.";
    return [];
  }

  $rows = [];
  for ($i = 1; $i < count($rawRows); $i++) {
    $rawRow = $rawRows[$i];
    $assoc = [];
    $hasValue = false;
    foreach ($headerMap as $colIndex => $fieldName) {
      $cellValue = trim((string)($rawRow[$colIndex] ?? ""));
      $assoc[$fieldName] = $cellValue;
      if ($cellValue !== "") {
        $hasValue = true;
      }
    }
    if ($hasValue) {
      $rows[] = $assoc;
    }
  }

  return $rows;
}

function isgExtractCsvRows(string $filePath, string &$error): array
{
  $handle = @fopen($filePath, "r");
  if ($handle === false) {
    $error = "Unable to open CSV file.";
    return [];
  }

  $header = fgetcsv($handle);
  if (!is_array($header)) {
    fclose($handle);
    $error = "CSV file is empty.";
    return [];
  }

  $headerMap = [];
  foreach ($header as $index => $headerValue) {
    $normalizedHeader = isgNormalizeHeader((string)$headerValue);
    if ($normalizedHeader !== "") {
      $headerMap[(int)$index] = $normalizedHeader;
    }
  }
  if (empty($headerMap)) {
    fclose($handle);
    $error = "CSV header row is empty.";
    return [];
  }

  $rows = [];
  while (($values = fgetcsv($handle)) !== false) {
    $assoc = [];
    $hasValue = false;
    foreach ($headerMap as $index => $fieldName) {
      $cellValue = trim((string)($values[$index] ?? ""));
      $assoc[$fieldName] = $cellValue;
      if ($cellValue !== "") {
        $hasValue = true;
      }
    }
    if ($hasValue) {
      $rows[] = $assoc;
    }
  }
  fclose($handle);

  return $rows;
}

function isgExtractSpreadsheetRows(string $filePath, string $originalName, string &$error): array
{
  $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
  if ($extension === "csv") {
    return isgExtractCsvRows($filePath, $error);
  }
  $error = "Unsupported file type. Use .csv only.";
  return [];
}

function isgBindParams(mysqli_stmt $stmt, string $types, array $values): bool
{
  if ($types === "" || empty($values)) {
    return true;
  }

  $bindArgs = [$types];
  foreach ($values as $index => $value) {
    $bindArgs[] = &$values[$index];
  }

  return call_user_func_array([$stmt, "bind_param"], $bindArgs);
}

function isgScholarRowKey(array $row): string
{
  $scholarId = strtolower(trim((string)($row["scholar_id"] ?? "")));
  $semester = strtolower(trim((string)($row["semester"] ?? "")));
  $academicYear = strtolower(trim((string)($row["academic_year"] ?? "")));
  if ($scholarId !== "") {
    return "sid-" . sha1($scholarId . "|" . $semester . "|" . $academicYear);
  }

  $fullName = strtolower(trim((string)($row["full_name"] ?? "")));
  if ($fullName === "" && $semester === "" && $academicYear === "") {
    return "";
  }

  return "name-" . sha1($fullName . "|" . $semester . "|" . $academicYear);
}

function isgLoadScholarRecords(mysqli $conn, array $validCategories): array
{
  $records = array_fill_keys($validCategories, []);
  $dedupedRecords = [];
  $result = $conn->query("
    SELECT
      id,
      source_application_id,
      category,
      scholar_id,
      grant_applied,
      full_name,
      email,
      program_year,
      assigned_office,
      semester,
      academic_year,
      status,
      renewal_status,
      renewal_scope,
      second_semester_renewed,
      contract_ended,
      termination_reason,
      terminated_at,
      terminated_by,
      created_at
    FROM institutional_scholar_records
    ORDER BY id DESC
  ");
  if (!($result instanceof mysqli_result)) {
    return $records;
  }

  while ($row = $result->fetch_assoc()) {
    $category = strtolower(trim((string)($row["category"] ?? "")));
    if (!in_array($category, $validCategories, true)) {
      continue;
    }
    $record = [
      "id" => (int)($row["id"] ?? 0),
      "source_application_id" => (int)($row["source_application_id"] ?? 0),
      "scholar_id" => trim((string)($row["scholar_id"] ?? "")),
      "grant_applied" => trim((string)($row["grant_applied"] ?? "")),
      "full_name" => trim((string)($row["full_name"] ?? "")),
      "email" => trim((string)($row["email"] ?? "")),
      "program_year" => trim((string)($row["program_year"] ?? "")),
      "assigned_office" => trim((string)($row["assigned_office"] ?? "")),
      "semester" => trim((string)($row["semester"] ?? "")),
      "academic_year" => trim((string)($row["academic_year"] ?? "")),
      "status" => trim((string)($row["status"] ?? "official_scholar")),
      "renewal_status" => trim((string)($row["renewal_status"] ?? "")),
      "renewal_scope" => trim((string)($row["renewal_scope"] ?? "")),
      "second_semester_renewed" => (int)($row["second_semester_renewed"] ?? 0) === 1,
      "contract_ended" => (int)($row["contract_ended"] ?? 0) === 1,
      "termination_reason" => trim((string)($row["termination_reason"] ?? "")),
      "terminated_at" => trim((string)($row["terminated_at"] ?? "")),
      "terminated_by" => trim((string)($row["terminated_by"] ?? "")),
      "created_at" => trim((string)($row["created_at"] ?? "")),
      "__category" => $category,
    ];

    $rowKey = isgScholarRowKey($record);
    if ($rowKey === "") {
      continue;
    }

    if (!isset($dedupedRecords[$rowKey])) {
      $dedupedRecords[$rowKey] = $record;
      continue;
    }

    $existingCategory = strtolower(trim((string)($dedupedRecords[$rowKey]["__category"] ?? "")));
    if ($existingCategory !== "official" && $category === "official") {
      $dedupedRecords[$rowKey] = $record;
    }
  }
  $result->free();

  foreach ($dedupedRecords as $record) {
    $baseRecord = $record;
    unset($baseRecord["__category"]);
    $records["official"][] = $baseRecord;

    $targetCategory = strtolower(trim((string)($record["__category"] ?? "")));
    if ($targetCategory === "" || $targetCategory === "official") {
      $targetCategory = isgCategoryFromGrantValue((string)($record["grant_applied"] ?? ""));
    }
    if ($targetCategory !== "official" && in_array($targetCategory, $validCategories, true)) {
      $records[$targetCategory][] = $baseRecord;
    }
  }

  foreach ($records as $categoryKey => $categoryRecords) {
    if (!is_array($categoryRecords)) {
      continue;
    }
    usort($categoryRecords, static function (array $left, array $right): int {
      $leftName = strtolower(trim((string)($left["full_name"] ?? "")));
      $rightName = strtolower(trim((string)($right["full_name"] ?? "")));
      if ($leftName !== $rightName) {
        return $leftName <=> $rightName;
      }
      $leftId = strtolower(trim((string)($left["scholar_id"] ?? "")));
      $rightId = strtolower(trim((string)($right["scholar_id"] ?? "")));
      return $leftId <=> $rightId;
    });
    $records[$categoryKey] = $categoryRecords;
  }

  return $records;
}

function isgLoadTerminatedScholarRecords(mysqli $conn): array
{
  $records = [];

  $result = $conn->query("
    SELECT
      id,
      scholar_id,
      grant_applied,
      full_name,
      email,
      program_year,
      assigned_office,
      semester,
      academic_year,
      status,
      termination_reason,
      terminated_by,
      terminated_at,
      updated_at
    FROM institutional_scholar_records
    WHERE COALESCE(contract_ended, 0) = 1
      OR LOWER(TRIM(COALESCE(status, ''))) IN ('contract_ended', 'terminated')
    ORDER BY COALESCE(terminated_at, updated_at) DESC, id DESC
  ");
  if (!($result instanceof mysqli_result)) {
    return $records;
  }

  while ($row = $result->fetch_assoc()) {
    $statusValue = strtolower(trim((string)($row["status"] ?? "")));
    $records[] = [
      "id" => 0,
      "scholar_record_id" => (int)($row["id"] ?? 0),
      "scholar_id" => trim((string)($row["scholar_id"] ?? "")),
      "grant_applied" => trim((string)($row["grant_applied"] ?? "")),
      "full_name" => trim((string)($row["full_name"] ?? "")),
      "email" => trim((string)($row["email"] ?? "")),
      "program_year" => trim((string)($row["program_year"] ?? "")),
      "assigned_office" => trim((string)($row["assigned_office"] ?? "")),
      "semester" => trim((string)($row["semester"] ?? "")),
      "academic_year" => trim((string)($row["academic_year"] ?? "")),
      "action_type" => $statusValue === "terminated" ? "terminated" : "end_contract",
      "reason" => trim((string)($row["termination_reason"] ?? "")),
      "created_by" => trim((string)($row["terminated_by"] ?? "")),
      "created_at" => trim((string)(($row["terminated_at"] ?? "") !== "" ? $row["terminated_at"] : ($row["updated_at"] ?? ""))),
    ];
  }
  $result->free();

  usort($records, static function (array $left, array $right): int {
    $leftTime = strtotime((string)($left["created_at"] ?? "")) ?: 0;
    $rightTime = strtotime((string)($right["created_at"] ?? "")) ?: 0;
    if ($leftTime === $rightTime) {
      return ((int)($right["scholar_record_id"] ?? 0)) <=> ((int)($left["scholar_record_id"] ?? 0));
    }
    return $rightTime <=> $leftTime;
  });

  return $records;
}

function isgLoadAssignedOfficeHistoryRecords(mysqli $conn): array
{
  $records = [];
  if (!isgEnsureAssignedOfficeHistoryTable($conn)) {
    return $records;
  }

  $result = $conn->query("
    SELECT
      id,
      scholar_record_id,
      source_application_id,
      scholar_id,
      full_name,
      academic_year,
      semester,
      from_office,
      to_office,
      changed_by,
      changed_at
    FROM institutional_scholar_office_history
    ORDER BY changed_at DESC, id DESC
  ");
  if (!($result instanceof mysqli_result)) {
    return $records;
  }

  while ($row = $result->fetch_assoc()) {
    $records[] = [
      "id" => (int)($row["id"] ?? 0),
      "scholar_record_id" => (int)($row["scholar_record_id"] ?? 0),
      "source_application_id" => (int)($row["source_application_id"] ?? 0),
      "scholar_id" => trim((string)($row["scholar_id"] ?? "")),
      "full_name" => trim((string)($row["full_name"] ?? "")),
      "academic_year" => trim((string)($row["academic_year"] ?? "")),
      "semester" => trim((string)($row["semester"] ?? "")),
      "from_office" => trim((string)($row["from_office"] ?? "")),
      "to_office" => trim((string)($row["to_office"] ?? "")),
      "changed_by" => trim((string)($row["changed_by"] ?? "")),
      "changed_at" => trim((string)($row["changed_at"] ?? "")),
    ];
  }
  $result->free();

  return $records;
}

$rawInstitutionalSchoolYearParam = array_key_exists("school_year", $_GET)
  ? trim((string)$_GET["school_year"])
  : null;
$showAllInstitutionalSchoolYears = $rawInstitutionalSchoolYearParam !== null
  && strtolower($rawInstitutionalSchoolYearParam) === "all";
$activeInstitutionalSchoolYear = $showAllInstitutionalSchoolYears
  ? ""
  : ($selectedSchoolYear !== "" ? $selectedSchoolYear : $displaySchoolYear);
$activeInstitutionalSemester = $rawSelectedSemester === null
  ? $displaySemester
  : $selectedSemester;
$configuredInstitutionalSchoolYear = trim((string)($configuredSchoolYear ?? ""));
$configuredInstitutionalSemester = trim((string)($configuredSemester ?? ""));
$hasConfiguredInstitutionalActiveTerm = isgHasConfiguredInstitutionalActiveTerm(
  $configuredInstitutionalSchoolYear,
  $configuredInstitutionalSemester
);

if (($conn ?? null) instanceof mysqli) {
  $assignedOfficeMap = [];
  $addAssignedOfficeOption = static function (array &$map, string $value): void {
    $office = trim($value);
    if ($office === "") {
      return;
    }
    $officeKey = strtolower($office);
    if (!isset($map[$officeKey])) {
      $map[$officeKey] = $office;
    }
  };

  $headOfficeTableResult = $conn->query("SHOW TABLES LIKE 'head_offices'");
  $hasHeadOfficeTable = $headOfficeTableResult instanceof mysqli_result && $headOfficeTableResult->num_rows > 0;
  if ($headOfficeTableResult instanceof mysqli_result) {
    $headOfficeTableResult->free();
  }
  if ($hasHeadOfficeTable) {
    $headOfficeOptionsResult = $conn->query("
      SELECT DISTINCT TRIM(COALESCE(office, '')) AS office_name
      FROM head_offices
      WHERE TRIM(COALESCE(office, '')) <> ''
        AND LOWER(TRIM(COALESCE(status, ''))) = 'active'
      ORDER BY office_name ASC
    ");
    if ($headOfficeOptionsResult instanceof mysqli_result) {
      while ($headOfficeRow = $headOfficeOptionsResult->fetch_assoc()) {
        $addAssignedOfficeOption($assignedOfficeMap, (string)($headOfficeRow["office_name"] ?? ""));
      }
      $headOfficeOptionsResult->free();
    }
  }

  $assignedOfficeColumnResult = $conn->query("SHOW COLUMNS FROM applications LIKE 'assigned_office'");
  if ($assignedOfficeColumnResult instanceof mysqli_result) {
    $hasAssignedOfficeColumn = $assignedOfficeColumnResult->num_rows > 0;
    $assignedOfficeColumnResult->free();
    if (!$hasAssignedOfficeColumn) {
      $conn->query("ALTER TABLE applications ADD COLUMN assigned_office VARCHAR(100) DEFAULT NULL AFTER year_level");
      $hasAssignedOfficeColumn = true;
    }
  } else {
    $hasAssignedOfficeColumn = false;
  }

  $hasScholarStorage = isgEnsureInstitutionalScholarTable($conn);
  if ($hasScholarStorage) {
    $existingOfficeResult = $conn->query("
      SELECT DISTINCT TRIM(COALESCE(assigned_office, '')) AS office_name
      FROM institutional_scholar_records
      WHERE TRIM(COALESCE(assigned_office, '')) <> ''
      ORDER BY office_name ASC
    ");
    if ($existingOfficeResult instanceof mysqli_result) {
      while ($existingOfficeRow = $existingOfficeResult->fetch_assoc()) {
        $addAssignedOfficeOption($assignedOfficeMap, (string)($existingOfficeRow["office_name"] ?? ""));
      }
      $existingOfficeResult->free();
    }
  }
  if (!empty($assignedOfficeMap)) {
    natcasesort($assignedOfficeMap);
    $assignedOfficeOptions = array_values($assignedOfficeMap);
  }

  $rankInputTableResult = $conn->query("SHOW TABLES LIKE 'applicant_rank_inputs'");
  if ($hasAssignedOfficeColumn && $hasScholarStorage && $rankInputTableResult instanceof mysqli_result && $rankInputTableResult->num_rows > 0) {
    $rankInputTableResult->free();

    $sql = "
      SELECT
        a.id,
        a.applicant_name,
        a.email_address,
        a.program_course,
        a.year_level,
        a.assigned_office,
        a.school_year,
        a.semester
      FROM applicant_rank_inputs ari
      INNER JOIN applications a ON a.id = ari.application_id
      WHERE
        a.grant_id = 1
        AND LOWER(TRIM(a.status)) = 'approved'
        AND LOWER(TRIM(COALESCE(ari.remarks, ''))) = 'hired'
        AND TRIM(COALESCE(a.assigned_office, '')) <> ''
      ORDER BY a.applicant_name ASC
    ";
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
      while ($row = $result->fetch_assoc()) {
        $record = [
          "source_application_id" => (int)($row["id"] ?? 0),
          "scholar_id" => "APP-" . str_pad((string)((int)($row["id"] ?? 0)), 5, "0", STR_PAD_LEFT),
          "grant_applied" => "Student Assistant",
          "full_name" => trim((string)($row["applicant_name"] ?? "")),
          "email" => isgNormalizeEmailAddress((string)($row["email_address"] ?? "")),
          "program_year" => isgBuildProgramYear((string)($row["program_course"] ?? ""), (string)($row["year_level"] ?? "")),
          "assigned_office" => trim((string)($row["assigned_office"] ?? "")),
          "semester" => trim((string)($row["semester"] ?? "")),
          "academic_year" => trim((string)($row["school_year"] ?? "")),
          "status" => "official_scholar",
        ];
        isgUpsertScholarRecord($conn, "official", $record);
      }
      $result->free();
    }
  } elseif ($rankInputTableResult instanceof mysqli_result) {
    $rankInputTableResult->free();
  }

  if (
    $hasScholarStorage &&
    isset($_GET["source"], $_GET["applicant_id"]) &&
    strtolower(trim((string)$_GET["source"])) === "approved"
  ) {
    $confirmedApplicantId = (int)$_GET["applicant_id"];
    if ($confirmedApplicantId <= 0) {
      $autoImportType = "error";
      $autoImportMessage = "Invalid applicant selected for import.";
    } else {
      $stmt = $conn->prepare(
        "SELECT id, applicant_name, email_address, program_course, year_level, school_year, semester, grant_id, status
         FROM applications
         WHERE id = ?
         LIMIT 1"
      );
      if ($stmt) {
        $stmt->bind_param("i", $confirmedApplicantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
          $autoImportType = "error";
          $autoImportMessage = "Applicant not found.";
        } else {
          $status = strtolower(trim((string)($row["status"] ?? "")));
          if ($status !== "approved") {
            $autoImportType = "error";
            $autoImportMessage = "Only approved applicants can be added to Institutional Scholars.";
          } else {
            $grantId = (int)($row["grant_id"] ?? 0);
            $grantLabel = $grantLabels[$grantId] ?? "Others";
            $record = [
              "source_application_id" => (int)($row["id"] ?? 0),
              "scholar_id" => "APP-" . str_pad((string)((int)($row["id"] ?? 0)), 5, "0", STR_PAD_LEFT),
              "grant_applied" => $grantLabel,
              "full_name" => trim((string)($row["applicant_name"] ?? "")),
              "email" => isgNormalizeEmailAddress((string)($row["email_address"] ?? "")),
              "program_year" => isgBuildProgramYear((string)($row["program_course"] ?? ""), (string)($row["year_level"] ?? "")),
              "assigned_office" => "",
              "semester" => trim((string)($row["semester"] ?? "")) !== "" ? trim((string)$row["semester"]) : $displaySemester,
              "academic_year" => trim((string)($row["school_year"] ?? "")) !== "" ? trim((string)$row["school_year"]) : $displaySchoolYear,
              "status" => "official_scholar",
            ];

            $okOfficial = isgUpsertScholarRecord($conn, "official", $record);
            if ($okOfficial) {
              $autoImportType = "success";
              $autoImportMessage = ($record["full_name"] !== "")
                ? ($record["full_name"] . " was added to Institutional Scholars.")
                : "Applicant was added to Institutional Scholars.";
            } else {
              $autoImportType = "error";
              $autoImportMessage = "Unable to save scholar record right now.";
            }
          }
        }
      } else {
        $autoImportType = "error";
        $autoImportMessage = "Unable to load applicant details right now.";
      }
    }
  }

  if ($hasScholarStorage && ($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && (string)($_POST["form_action"] ?? "") === "add_manual_scholar") {
    $manualFullName = trim((string)($_POST["manual_full_name"] ?? ""));
    $manualEmail = isgNormalizeEmailAddress((string)($_POST["manual_email"] ?? ""));
    $manualGrantApplied = trim((string)($_POST["manual_grant_applied"] ?? ""));
    if (!in_array($manualGrantApplied, $manualGrantOptions, true)) {
      $manualGrantApplied = "";
    }
    $manualProgramYear = trim((string)($_POST["manual_program_year"] ?? ""));
    $manualAssignedOffice = trim((string)($_POST["manual_assigned_office"] ?? ""));
    if (strcasecmp($manualGrantApplied, "Student Assistant") !== 0) {
      $manualAssignedOffice = "";
    }
    if ($manualAssignedOffice !== "" && !in_array($manualAssignedOffice, $assignedOfficeOptions, true)) {
      $manualAssignedOffice = "";
    }
    $manualSemester = trim((string)($_POST["manual_semester"] ?? ""));
    if (!in_array($manualSemester, $semesterOptions, true)) {
      $manualSemester = $displaySemester;
    }
    $manualAcademicYear = trim((string)($_POST["manual_academic_year"] ?? ""));
    if ($manualAcademicYear === "") {
      $manualAcademicYear = $displaySchoolYear;
    }
    $manualScholarId = isgGenerateManualScholarId($conn);

    $manualSuccess = false;
    $manualMessage = "Unable to add scholar. Please provide required fields and a valid email address.";
    if ($manualFullName !== "" && $manualEmail !== "" && $manualGrantApplied !== "") {
      $record = [
        "source_application_id" => 0,
        "scholar_id" => $manualScholarId,
        "grant_applied" => $manualGrantApplied,
        "full_name" => $manualFullName,
        "email" => $manualEmail,
        "program_year" => $manualProgramYear,
        "assigned_office" => $manualAssignedOffice,
        "semester" => $manualSemester,
        "academic_year" => $manualAcademicYear,
        "status" => "official_scholar",
      ];

      $manualSuccess = isgUpsertScholarRecord($conn, "official", $record);
      $manualMessage = $manualSuccess
        ? "Scholar record has been added."
        : "Failed to save scholar record in database.";
    }

    $redirectParams = [];
    $returnSchoolYear = trim((string)($_POST["return_school_year"] ?? ""));
    $returnSemester = trim((string)($_POST["return_semester"] ?? ""));
    $returnActiveCategory = strtolower(trim((string)($_POST["return_active_category"] ?? $activeCategoryParam)));
    if ($returnSchoolYear !== "") {
      $redirectParams["school_year"] = $returnSchoolYear;
    }
    if ($returnSemester !== "") {
      $redirectParams["semester"] = $returnSemester;
    }
    if (!in_array($returnActiveCategory, $validScholarCategories, true)) {
      $returnActiveCategory = "official";
    }
    $redirectParams["active_category"] = $returnActiveCategory;
    $redirectParams["scholar_notice"] = $manualSuccess ? "success" : "error";
    $redirectParams["scholar_notice_message"] = $manualMessage;

    header("Location: institutional-scholars.php" . (!empty($redirectParams) ? ("?" . http_build_query($redirectParams)) : ""));
    exit;
  }

  if ($hasScholarStorage && ($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && (string)($_POST["form_action"] ?? "") === "import_scholars_file") {
    $importSuccess = false;
    $importMessage = "No file uploaded.";
    $uploadedFile = $_FILES["scholar_import_file"] ?? null;
    if (is_array($uploadedFile) && (int)($uploadedFile["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
      $tmpPath = (string)($uploadedFile["tmp_name"] ?? "");
      $originalName = (string)($uploadedFile["name"] ?? "");
      $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
      if ($extension !== "csv") {
        $importMessage = "Only .csv file is allowed for import.";
      } else {
        $parseError = "";
        $rows = isgExtractSpreadsheetRows($tmpPath, $originalName, $parseError);
        if ($parseError !== "") {
          $importMessage = $parseError;
        } elseif (empty($rows)) {
          $importMessage = "File has no data rows to import.";
        } else {
          $firstRow = is_array($rows[0] ?? null) ? $rows[0] : [];
          $requiredHeaderGroups = [
            "scholarship_grant" => ["scholarship_grant", "grant_applied", "grant", "scholarshipgrant"],
            "full_name" => ["full_name", "fullname", "name", "beneficiary_name", "scholar_name"],
            "email" => ["email", "email_address", "student_email", "studentemail"],
            "program_year" => ["program_year", "program__year", "program", "course_year", "program_year_level"],
            "assigned_office" => ["assigned_office", "office", "assigned_department"],
            "semester" => ["semester", "term"],
            "academic_year" => ["academic_year", "school_year", "ay"],
          ];
          $missingHeaders = [];
          foreach ($requiredHeaderGroups as $requiredLabel => $headerAliases) {
            $found = false;
            foreach ($headerAliases as $alias) {
              if (array_key_exists($alias, $firstRow)) {
                $found = true;
                break;
              }
            }
            if (!$found) {
              $missingHeaders[] = $requiredLabel;
            }
          }

          if (!empty($missingHeaders)) {
            $importMessage = "CSV missing required column(s): " . implode(", ", $missingHeaders) . ".";
          } else {
            $importedCount = 0;
            $skippedCount = 0;

            foreach ($rows as $row) {
              $rowGrant = isgFirstNonEmptyValue($row, $requiredHeaderGroups["scholarship_grant"]);
              $rowFullName = isgFirstNonEmptyValue($row, $requiredHeaderGroups["full_name"]);
              $rowEmail = isgNormalizeEmailAddress(isgFirstNonEmptyValue($row, $requiredHeaderGroups["email"]));
              $rowProgramYear = isgFirstNonEmptyValue($row, $requiredHeaderGroups["program_year"]);
              $rowAssignedOffice = isgFirstNonEmptyValue($row, $requiredHeaderGroups["assigned_office"]);
              $rowSemesterRaw = isgFirstNonEmptyValue($row, $requiredHeaderGroups["semester"]);
              $rowAcademicYear = isgFirstNonEmptyValue($row, $requiredHeaderGroups["academic_year"]);

              if (
                $rowGrant === "" ||
                $rowFullName === "" ||
                $rowEmail === "" ||
                $rowProgramYear === "" ||
                $rowAssignedOffice === "" ||
                $rowSemesterRaw === "" ||
                $rowAcademicYear === ""
              ) {
                $skippedCount++;
                continue;
              }

              $rowSemester = isgNormalizeSemesterValue($rowSemesterRaw, $displaySemester);
              $rowScholarIdSeed = strtolower($rowGrant . "|" . $rowFullName . "|" . $rowSemester . "|" . $rowAcademicYear);
              $rowScholarId = "CSV-" . strtoupper(substr(sha1($rowScholarIdSeed), 0, 16));

              $record = [
                "source_application_id" => 0,
                "scholar_id" => $rowScholarId,
                "grant_applied" => $rowGrant,
                "full_name" => $rowFullName,
                "email" => $rowEmail,
                "program_year" => $rowProgramYear,
                "assigned_office" => $rowAssignedOffice,
                "semester" => $rowSemester,
                "academic_year" => $rowAcademicYear,
                "status" => "official_scholar",
              ];

              if (isgUpsertScholarRecord($conn, "official", $record)) {
                $importedCount++;
              } else {
                $skippedCount++;
              }
            }

            if ($importedCount > 0) {
              $importSuccess = true;
              $importMessage = "Imported {$importedCount} record(s)." . ($skippedCount > 0 ? " Skipped {$skippedCount} row(s)." : "");
            } else {
              $importMessage = "No records imported." . ($skippedCount > 0 ? " Skipped {$skippedCount} row(s)." : "");
            }
          }
        }
      }
    } elseif (is_array($uploadedFile) && (int)($uploadedFile["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
      $importMessage = "File upload failed. Please try again.";
    }

    $redirectParams = [];
    $returnSchoolYear = trim((string)($_POST["return_school_year"] ?? ""));
    $returnSemester = trim((string)($_POST["return_semester"] ?? ""));
    $returnActiveCategory = strtolower(trim((string)($_POST["return_active_category"] ?? $activeCategoryParam)));
    if ($returnSchoolYear !== "") {
      $redirectParams["school_year"] = $returnSchoolYear;
    }
    if ($returnSemester !== "") {
      $redirectParams["semester"] = $returnSemester;
    }
    if (!in_array($returnActiveCategory, $validScholarCategories, true)) {
      $returnActiveCategory = "official";
    }
    $redirectParams["active_category"] = $returnActiveCategory;
    $redirectParams["scholar_notice"] = $importSuccess ? "success" : "error";
    $redirectParams["scholar_notice_message"] = $importMessage;

    header("Location: institutional-scholars.php" . (!empty($redirectParams) ? ("?" . http_build_query($redirectParams)) : ""));
    exit;
  }

  $requestedAction = strtolower(trim((string)($_GET["scholar_action"] ?? "")));
  if ($hasScholarStorage && ($requestedAction === "renew" || $requestedAction === "end_contract" || $requestedAction === "terminate" || $requestedAction === "change_office" || $requestedAction === "edit_scholar")) {
    $targetScholarRecordId = (int)($_GET["id"] ?? ($_GET["scholar_record_id"] ?? 0));
    $renewalScope = trim((string)($_GET["renewal_scope"] ?? ""));
    $targetAssignedOffice = trim((string)($_GET["new_assigned_office"] ?? ""));
    $targetFullName = trim((string)($_GET["new_full_name"] ?? ""));
    $targetProgramYearProvided = array_key_exists("new_program_year", $_GET);
    $targetProgramYear = trim((string)($_GET["new_program_year"] ?? ""));
    $targetEmailProvided = array_key_exists("new_email", $_GET);
    $targetEmailRaw = trim((string)($_GET["new_email"] ?? ""));
    $terminationReason = trim((string)($_GET["termination_reason"] ?? ""));

    $actionSuccess = false;
    $actionMessage = "Unable to process scholar action.";
    $redirectSchoolYear = "";
    $redirectSemester = "";

    if ($targetScholarRecordId <= 0) {
      $actionMessage = "Missing scholar reference.";
    } else {
      $whereSql = "id = ?";
      $whereTypes = "i";
      $whereParams = [$targetScholarRecordId];
      $studentAssistantWhereSql = "(
        LOWER(TRIM(COALESCE(category, ''))) = 'student_assistant'
        OR LOWER(TRIM(COALESCE(grant_applied, ''))) LIKE '%assistant%'
      )";
      $targetRecordSql = "
        SELECT
          id,
          scholar_id,
          grant_applied,
          full_name,
          email,
          program_year,
          assigned_office,
          semester,
          academic_year,
          status,
          renewal_scope,
          second_semester_renewed
        FROM institutional_scholar_records
        WHERE id = ?
        LIMIT 1
      ";
      $targetRecordStmt = $conn->prepare($targetRecordSql);
      $targetRecord = null;
      if ($targetRecordStmt) {
        $targetRecordStmt->bind_param("i", $targetScholarRecordId);
        if ($targetRecordStmt->execute()) {
          $targetRecordResult = $targetRecordStmt->get_result();
          $targetRecord = $targetRecordResult ? $targetRecordResult->fetch_assoc() : null;
          if ($targetRecordResult instanceof mysqli_result) {
            $targetRecordResult->free();
          }
        }
        $targetRecordStmt->close();
      }

      if (!is_array($targetRecord)) {
        $actionMessage = "Scholar reference was not found.";
      } else {
        $targetScholarId = strtolower(trim((string)($targetRecord["scholar_id"] ?? "")));
        $targetSemester = trim((string)($targetRecord["semester"] ?? ""));
        $targetAcademicYear = trim((string)($targetRecord["academic_year"] ?? ""));
        $targetStatus = isgCanonicalStatus((string)($targetRecord["status"] ?? ""));
        $targetRenewalScope = strtolower(trim((string)($targetRecord["renewal_scope"] ?? "")));
        $targetSemesterNormalized = isgNormalizeSemesterValue($targetSemester, "");
        $targetIsHistoricalRenewed = $targetStatus === "renewed" && (
          $targetRenewalScope === "school_year" ||
          ($targetRenewalScope === "2nd_semester" && $targetSemesterNormalized === "1st Semester")
        );
        if ($targetScholarId !== "") {
          $whereSql = "
            LOWER(TRIM(COALESCE(scholar_id, ''))) = ?
            AND TRIM(COALESCE(semester, '')) = ?
            AND TRIM(COALESCE(academic_year, '')) = ?
          ";
          $whereTypes = "sss";
          $whereParams = [$targetScholarId, $targetSemester, $targetAcademicYear];
        }

        if ($requestedAction === "renew") {
          if ($renewalScope !== "2nd_semester" && $renewalScope !== "school_year") {
            $actionMessage = "Invalid renewal scope.";
          } elseif ($targetIsHistoricalRenewed) {
            $actionMessage = "This previous term is already renewed and cannot be renewed again.";
          } elseif (!$hasConfiguredInstitutionalActiveTerm) {
            $actionMessage = "Set the active school year and semester in Settings before renewing scholars.";
          } elseif (
            $renewalScope === "2nd_semester" &&
            ($configuredInstitutionalSchoolYear !== $targetAcademicYear || $configuredInstitutionalSemester !== "2nd Semester")
          ) {
            $termLabel = $targetAcademicYear !== "" ? ("2nd Semester, S.Y. " . $targetAcademicYear) : "2nd Semester";
            $actionMessage = "Cannot renew for " . $termLabel . " until it is set as the active term in Settings.";
          } else {
            $sourceRecords = [];
            $sourceRecordSql = "
              SELECT
                source_application_id,
                category,
                scholar_id,
                grant_applied,
                full_name,
                email,
                program_year,
                assigned_office,
                semester,
                academic_year
              FROM institutional_scholar_records
              WHERE $whereSql AND COALESCE(contract_ended, 0) = 0
              ORDER BY
                CASE
                  WHEN LOWER(TRIM(COALESCE(category, ''))) = 'official'
                    AND LOWER(TRIM(COALESCE(grant_applied, ''))) LIKE '%assistant%'
                  THEN 0
                  ELSE 1
                END,
                id DESC
            ";
            $sourceRecordStmt = $conn->prepare($sourceRecordSql);
            if ($sourceRecordStmt) {
              isgBindParams($sourceRecordStmt, $whereTypes, $whereParams);
              if ($sourceRecordStmt->execute()) {
                $sourceRecordResult = $sourceRecordStmt->get_result();
                if ($sourceRecordResult instanceof mysqli_result) {
                  while ($sourceRow = $sourceRecordResult->fetch_assoc()) {
                    $sourceRecords[] = $sourceRow;
                  }
                  $sourceRecordResult->free();
                }
              }
              $sourceRecordStmt->close();
            }

            if (empty($sourceRecords)) {
              $actionMessage = "No active scholar record found to renew.";
            } elseif ($renewalScope === "2nd_semester") {
              $targetRenewSemester = "2nd Semester";
              $targetRenewAcademicYear = trim((string)($targetRecord["academic_year"] ?? ""));
              $renewInsertOk = true;
              foreach ($sourceRecords as $sourceRecord) {
                $renewInsertOk = isgUpsertScholarTermRecord($conn, [
                  "source_application_id" => (int)($sourceRecord["source_application_id"] ?? 0),
                  "category" => trim((string)($sourceRecord["category"] ?? "official")),
                  "scholar_id" => trim((string)($sourceRecord["scholar_id"] ?? "")),
                  "grant_applied" => trim((string)($sourceRecord["grant_applied"] ?? "")),
                  "full_name" => trim((string)($sourceRecord["full_name"] ?? "")),
                  "email" => trim((string)($sourceRecord["email"] ?? "")),
                  "program_year" => trim((string)($sourceRecord["program_year"] ?? "")),
                  "assigned_office" => trim((string)($sourceRecord["assigned_office"] ?? "")),
                  "semester" => $targetRenewSemester,
                  "academic_year" => trim((string)($sourceRecord["academic_year"] ?? $targetRenewAcademicYear)),
                  "status" => "renewed",
                  "renewal_status" => "renew",
                  "renewal_scope" => "",
                  "second_semester_renewed" => 0,
                ]);
                if (!$renewInsertOk) {
                  break;
                }
              }

              if ($renewInsertOk) {
                $markRenewedSql = "
                  UPDATE institutional_scholar_records
                  SET
                    renewal_status = 'renew',
                    renewal_scope = '2nd_semester',
                    second_semester_renewed = 1,
                    status = 'renewed',
                    contract_ended = 0,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE $whereSql AND COALESCE(contract_ended, 0) = 0
                ";
                $markRenewedStmt = $conn->prepare($markRenewedSql);
                if ($markRenewedStmt) {
                  isgBindParams($markRenewedStmt, $whereTypes, $whereParams);
                  $renewInsertOk = $markRenewedStmt->execute();
                  $markRenewedStmt->close();
                } else {
                  $renewInsertOk = false;
                }
              }

              if ($renewInsertOk) {
                $actionSuccess = true;
                $actionMessage = "Scholar renewed for 2nd Semester.";
                $redirectSchoolYear = $targetRenewAcademicYear;
                $redirectSemester = $targetRenewSemester;
              } else {
                $actionMessage = "Unable to save the renewed 2nd Semester record.";
              }
            } else {
              $currentYearSql = "
                SELECT academic_year, program_year
                FROM institutional_scholar_records
                WHERE $whereSql
                ORDER BY
                  CASE
                    WHEN LOWER(TRIM(COALESCE(category, ''))) = 'official'
                      AND LOWER(TRIM(COALESCE(grant_applied, ''))) LIKE '%assistant%'
                    THEN 0
                    ELSE 1
                  END,
                  id DESC
                LIMIT 1
              ";
              $currentYearStmt = $conn->prepare($currentYearSql);
              $baseAcademicYear = "";
              $baseProgramYear = "";
              if ($currentYearStmt) {
                isgBindParams($currentYearStmt, $whereTypes, $whereParams);
                if ($currentYearStmt->execute()) {
                  $result = $currentYearStmt->get_result();
                  $row = $result ? $result->fetch_assoc() : null;
                  $baseAcademicYear = trim((string)($row["academic_year"] ?? ""));
                  $baseProgramYear = trim((string)($row["program_year"] ?? ""));
                  if ($result instanceof mysqli_result) {
                    $result->free();
                  }
                }
                $currentYearStmt->close();
              }
              $renewalBaseYear = $baseAcademicYear !== "" ? $baseAcademicYear : trim((string)$displaySchoolYear);
              if ($renewalBaseYear === "") {
                $renewalBaseYear = trim((string)($currentSchoolYear ?? ""));
              }
              $nextAcademicYear = isgIncrementSchoolYear($renewalBaseYear, $displaySchoolYear);

              if (!isgSchoolYearAvailableForRenewal($schoolYearOptions, $nextAcademicYear)) {
                $actionMessage = $nextAcademicYear !== ""
                  ? "Cannot renew for next School Year until " . $nextAcademicYear . " is added to the system school year list."
                  : "Cannot renew for next School Year until the next school year is added to the system school year list.";
              } elseif ($configuredInstitutionalSchoolYear !== $nextAcademicYear) {
                $actionMessage = $nextAcademicYear !== ""
                  ? "Cannot renew for next School Year until " . $nextAcademicYear . " is set as the active school year in Settings."
                  : "Cannot renew for next School Year until the next school year is active in Settings.";
              } else {
                $renewInsertOk = true;
                foreach ($sourceRecords as $sourceRecord) {
                  $renewInsertOk = isgUpsertScholarTermRecord($conn, [
                    "source_application_id" => (int)($sourceRecord["source_application_id"] ?? 0),
                    "category" => trim((string)($sourceRecord["category"] ?? "official")),
                    "scholar_id" => trim((string)($sourceRecord["scholar_id"] ?? "")),
                    "grant_applied" => trim((string)($sourceRecord["grant_applied"] ?? "")),
                    "full_name" => trim((string)($sourceRecord["full_name"] ?? "")),
                    "email" => trim((string)($sourceRecord["email"] ?? "")),
                    "program_year" => isgIncrementProgramYearLevel((string)($sourceRecord["program_year"] ?? $baseProgramYear)),
                    "assigned_office" => trim((string)($sourceRecord["assigned_office"] ?? "")),
                    "semester" => "1st Semester",
                    "academic_year" => $nextAcademicYear,
                    "status" => "renewed",
                    "renewal_status" => "renew",
                    "renewal_scope" => "",
                    "second_semester_renewed" => 0,
                  ]);
                  if (!$renewInsertOk) {
                    break;
                  }
                }

                if ($renewInsertOk) {
                  $markRenewedSql = "
                    UPDATE institutional_scholar_records
                    SET
                      renewal_status = 'renew',
                      renewal_scope = 'school_year',
                      second_semester_renewed = 1,
                      status = 'renewed',
                      contract_ended = 0,
                      updated_at = CURRENT_TIMESTAMP
                    WHERE $whereSql AND COALESCE(contract_ended, 0) = 0
                ";
                  $markRenewedStmt = $conn->prepare($markRenewedSql);
                  if ($markRenewedStmt) {
                    isgBindParams($markRenewedStmt, $whereTypes, $whereParams);
                    $renewInsertOk = $markRenewedStmt->execute();
                    $markRenewedStmt->close();
                  } else {
                    $renewInsertOk = false;
                  }
                }

                if ($renewInsertOk) {
                  $actionSuccess = true;
                  $actionMessage = "Scholar renewed for next School Year.";
                  $redirectSchoolYear = $nextAcademicYear;
                  $redirectSemester = "1st Semester";
                } else {
                  $actionMessage = "Unable to save the renewed next School Year record.";
                }
              }
            }
          }
        } elseif ($requestedAction === "end_contract") {
          if ($targetIsHistoricalRenewed) {
            $actionMessage = "This previous term is already renewed and cannot be changed.";
          } else {
          $endSql = "
            UPDATE institutional_scholar_records
            SET
              contract_ended = 1,
              status = 'contract_ended',
              termination_reason = NULL,
              terminated_at = NOW(),
              terminated_by = ?,
              updated_at = CURRENT_TIMESTAMP
            WHERE $whereSql
          ";
          $endStmt = $conn->prepare($endSql);
          if ($endStmt) {
            $actionBy = trim((string)($_SESSION["admin_username"] ?? $_SESSION["admin_name"] ?? "Admin"));
            isgBindParams($endStmt, "s" . $whereTypes, array_merge([$actionBy], $whereParams));
            $actionSuccess = $endStmt->execute();
            $endStmt->close();
            if ($actionSuccess) {
              $actionMessage = "Scholar contract ended.";
            }
          }
          }
        } elseif ($requestedAction === "terminate") {
          if ($targetIsHistoricalRenewed) {
            $actionMessage = "This previous term is already renewed and cannot be changed.";
          } elseif ($terminationReason === "") {
            $actionMessage = "Termination reason is required.";
          } else {
            $terminateSql = "
              UPDATE institutional_scholar_records
              SET
                contract_ended = 1,
                status = 'terminated',
                termination_reason = ?,
                terminated_at = NOW(),
                terminated_by = ?,
                updated_at = CURRENT_TIMESTAMP
              WHERE $whereSql
            ";
            $terminateStmt = $conn->prepare($terminateSql);
            if ($terminateStmt) {
              $actionBy = trim((string)($_SESSION["admin_username"] ?? $_SESSION["admin_name"] ?? "Admin"));
              isgBindParams($terminateStmt, "ss" . $whereTypes, array_merge([$terminationReason, $actionBy], $whereParams));
              $actionSuccess = $terminateStmt->execute();
              $terminateStmt->close();
              if ($actionSuccess) {
                $actionMessage = "Scholar terminated.";
              }
            }
          }
        } elseif ($requestedAction === "change_office" || $requestedAction === "edit_scholar") {
          if ($targetIsHistoricalRenewed) {
            $actionMessage = "This previous term is already renewed and cannot be changed.";
          } else {
          $nextFullName = $targetFullName !== "" ? $targetFullName : trim((string)($targetRecord["full_name"] ?? ""));
          $nextProgramYear = $targetProgramYearProvided ? $targetProgramYear : trim((string)($targetRecord["program_year"] ?? ""));
          $nextEmailRaw = $targetEmailProvided ? $targetEmailRaw : trim((string)($targetRecord["email"] ?? ""));
          $nextEmail = isgNormalizeEmailAddress($nextEmailRaw);

          if ($nextFullName === "") {
            $actionMessage = "Full name is required.";
          } elseif ($requestedAction === "edit_scholar" && ($nextEmailRaw === "" || $nextEmail === "")) {
            $actionMessage = "Valid email address is required.";
          } elseif ($nextEmailRaw !== "" && $nextEmail === "") {
            $actionMessage = "Valid email address is required.";
          } elseif ($requestedAction === "change_office" && $targetAssignedOffice === "") {
            $actionMessage = "Assigned office is required.";
          } else {
            $sourceApplicationIds = [];
            $officeHistoryRows = [];
            $sourceLookupSql = "
              SELECT DISTINCT source_application_id
              FROM institutional_scholar_records
              WHERE $whereSql
                AND COALESCE(contract_ended, 0) = 0
            ";
            $sourceLookupStmt = $conn->prepare($sourceLookupSql);
            if ($sourceLookupStmt) {
              isgBindParams($sourceLookupStmt, $whereTypes, $whereParams);
              if ($sourceLookupStmt->execute()) {
                $sourceLookupResult = $sourceLookupStmt->get_result();
                while ($sourceLookupRow = $sourceLookupResult ? $sourceLookupResult->fetch_assoc() : null) {
                  if (!is_array($sourceLookupRow)) {
                    break;
                  }
                  $sourceId = (int)($sourceLookupRow["source_application_id"] ?? 0);
                  if ($sourceId > 0) {
                    $sourceApplicationIds[$sourceId] = true;
                  }
                }
                if ($sourceLookupResult instanceof mysqli_result) {
                  $sourceLookupResult->free();
                }
              }
              $sourceLookupStmt->close();
            }

            $checkSql = "
              SELECT COUNT(*) AS total
              FROM institutional_scholar_records
              WHERE $whereSql
                AND COALESCE(contract_ended, 0) = 0
                AND $studentAssistantWhereSql
            ";
            $checkStmt = $conn->prepare($checkSql);
            $targetCount = 0;
            if ($checkStmt) {
              isgBindParams($checkStmt, $whereTypes, $whereParams);
              if ($checkStmt->execute()) {
                $checkResult = $checkStmt->get_result();
                $checkRow = $checkResult ? $checkResult->fetch_assoc() : null;
                if (is_array($checkRow)) {
                  $targetCount = (int)($checkRow["total"] ?? 0);
                }
                if ($checkResult instanceof mysqli_result) {
                  $checkResult->free();
                }
              }
              $checkStmt->close();
            }

            $isStudentAssistantRecord = $targetCount > 0;
            $shouldUpdateOffice = $isStudentAssistantRecord && $targetAssignedOffice !== "";

            if ($requestedAction === "change_office" && !$isStudentAssistantRecord) {
              $actionMessage = "Only active Student Assistant records can change assigned office.";
            } else {
              if ($shouldUpdateOffice) {
                $historyLookupSql = "
                  SELECT
                    id AS scholar_record_id,
                    source_application_id,
                    scholar_id,
                    full_name,
                    academic_year,
                    semester,
                    assigned_office
                  FROM institutional_scholar_records
                  WHERE $whereSql
                    AND COALESCE(contract_ended, 0) = 0
                    AND $studentAssistantWhereSql
                ";
                $historyLookupStmt = $conn->prepare($historyLookupSql);
                if ($historyLookupStmt) {
                  isgBindParams($historyLookupStmt, $whereTypes, $whereParams);
                  if ($historyLookupStmt->execute()) {
                    $historyLookupResult = $historyLookupStmt->get_result();
                    if ($historyLookupResult instanceof mysqli_result) {
                      $seenHistoryKeys = [];
                      while ($historyRow = $historyLookupResult->fetch_assoc()) {
                        $fromOffice = trim((string)($historyRow["assigned_office"] ?? ""));
                        $historyKey = strtolower(trim((string)($historyRow["scholar_id"] ?? "")))
                          . "|" . strtolower(trim((string)($historyRow["full_name"] ?? "")))
                          . "|" . strtolower(trim((string)($historyRow["academic_year"] ?? "")))
                          . "|" . strtolower(trim((string)($historyRow["semester"] ?? "")))
                          . "|" . strtolower($fromOffice);
                        if (strcasecmp($fromOffice, $targetAssignedOffice) !== 0) {
                          if (isset($seenHistoryKeys[$historyKey])) {
                            continue;
                          }
                          $seenHistoryKeys[$historyKey] = true;
                          $officeHistoryRows[] = $historyRow;
                        }
                      }
                      $historyLookupResult->free();
                    }
                  }
                  $historyLookupStmt->close();
                }
              }

              $updateSql = $shouldUpdateOffice
                ? "
                  UPDATE institutional_scholar_records
                  SET
                    full_name = ?,
                    program_year = ?,
                    email = ?,
                    assigned_office = ?,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE $whereSql
                    AND COALESCE(contract_ended, 0) = 0
                "
                : "
                  UPDATE institutional_scholar_records
                  SET
                    full_name = ?,
                    program_year = ?,
                    email = ?,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE $whereSql
                    AND COALESCE(contract_ended, 0) = 0
                ";
              $updateStmt = $conn->prepare($updateSql);
              if ($updateStmt) {
                $updateTypes = ($shouldUpdateOffice ? "ssss" : "sss") . $whereTypes;
                $updateValues = $shouldUpdateOffice
                  ? [$nextFullName, $nextProgramYear, $nextEmail, $targetAssignedOffice]
                  : [$nextFullName, $nextProgramYear, $nextEmail];
                isgBindParams($updateStmt, $updateTypes, array_merge($updateValues, $whereParams));
                $actionSuccess = $updateStmt->execute();
                if ($actionSuccess) {
                  $affectedRows = $updateStmt->affected_rows;
                  $applicationSyncOk = true;
                  if (!empty($sourceApplicationIds)) {
                    [$syncProgramCourse, $syncYearLevel] = isgSplitProgramYear($nextProgramYear);
                    foreach (array_keys($sourceApplicationIds) as $sourceId) {
                      if ($requestedAction === "edit_scholar" && $shouldUpdateOffice && ($hasAssignedOfficeColumn ?? false)) {
                        $appUpdateStmt = $conn->prepare("
                          UPDATE applications
                          SET applicant_name = ?, email_address = ?, program_course = ?, year_level = ?, assigned_office = ?
                          WHERE id = ?
                        ");
                        if (!$appUpdateStmt) {
                          $applicationSyncOk = false;
                          break;
                        }
                        $appUpdateStmt->bind_param("sssssi", $nextFullName, $nextEmail, $syncProgramCourse, $syncYearLevel, $targetAssignedOffice, $sourceId);
                      } elseif ($requestedAction === "edit_scholar") {
                        $appUpdateStmt = $conn->prepare("
                          UPDATE applications
                          SET applicant_name = ?, email_address = ?, program_course = ?, year_level = ?
                          WHERE id = ?
                        ");
                        if (!$appUpdateStmt) {
                          $applicationSyncOk = false;
                          break;
                        }
                        $appUpdateStmt->bind_param("ssssi", $nextFullName, $nextEmail, $syncProgramCourse, $syncYearLevel, $sourceId);
                      } elseif ($shouldUpdateOffice && ($hasAssignedOfficeColumn ?? false)) {
                        $appUpdateStmt = $conn->prepare("UPDATE applications SET assigned_office = ? WHERE id = ?");
                        if (!$appUpdateStmt) {
                          $applicationSyncOk = false;
                          break;
                        }
                        $appUpdateStmt->bind_param("si", $targetAssignedOffice, $sourceId);
                      } else {
                        continue;
                      }

                      if (!$appUpdateStmt->execute()) {
                        $applicationSyncOk = false;
                        $appUpdateStmt->close();
                        break;
                      }
                      $appUpdateStmt->close();
                    }
                  }

                  if (!$applicationSyncOk) {
                    $actionSuccess = false;
                    $actionMessage = "Scholar record updated but failed to sync source application.";
                  } else {
                    if (!empty($officeHistoryRows) && isgEnsureAssignedOfficeHistoryTable($conn)) {
                      $historyStmt = $conn->prepare("
                        INSERT INTO institutional_scholar_office_history
                          (
                            scholar_record_id,
                            source_application_id,
                            scholar_id,
                            full_name,
                            academic_year,
                            semester,
                            from_office,
                            to_office,
                            changed_by
                          )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                      ");
                      if ($historyStmt) {
                        $changedBy = trim((string)($_SESSION["admin_username"] ?? $_SESSION["admin_name"] ?? "Admin"));
                        foreach ($officeHistoryRows as $historyRow) {
                          $historyScholarRecordId = (int)($historyRow["scholar_record_id"] ?? 0);
                          $historySourceApplicationId = (int)($historyRow["source_application_id"] ?? 0);
                          $historyScholarId = trim((string)($historyRow["scholar_id"] ?? ""));
                          $historyFullName = trim((string)($historyRow["full_name"] ?? ""));
                          $historyAcademicYear = trim((string)($historyRow["academic_year"] ?? ""));
                          $historySemester = trim((string)($historyRow["semester"] ?? ""));
                          $historyFromOffice = trim((string)($historyRow["assigned_office"] ?? ""));
                          $historyStmt->bind_param(
                            "iisssssss",
                            $historyScholarRecordId,
                            $historySourceApplicationId,
                            $historyScholarId,
                            $historyFullName,
                            $historyAcademicYear,
                            $historySemester,
                            $historyFromOffice,
                            $targetAssignedOffice,
                            $changedBy
                          );
                          if (!$historyStmt->execute()) {
                            $actionSuccess = false;
                            $actionMessage = "Scholar record updated but failed to record office history.";
                            break;
                          }
                        }
                        $historyStmt->close();
                      }
                    }

                    if ($actionSuccess) {
                      $actionMessage = $affectedRows > 0
                        ? "Scholar record updated successfully."
                        : "Scholar record already has those details.";
                    }
                  }
                }
                $updateStmt->close();
              }
            }
          }
          }
        }
      }
    }

    $redirectParams = $_GET;
    unset(
      $redirectParams["scholar_action"],
      $redirectParams["id"],
      $redirectParams["scholar_record_id"],
      $redirectParams["renewal_scope"],
      $redirectParams["new_assigned_office"],
      $redirectParams["new_full_name"],
      $redirectParams["new_program_year"],
      $redirectParams["new_email"],
      $redirectParams["termination_reason"],
      $redirectParams["scholar_notice"],
      $redirectParams["scholar_notice_message"]
    );
    if ($actionSuccess && $redirectSchoolYear !== "") {
      $redirectParams["school_year"] = $redirectSchoolYear;
    }
    if ($actionSuccess && $redirectSemester !== "") {
      $redirectParams["semester"] = $redirectSemester;
    }
    $redirectParams["scholar_notice"] = $actionSuccess ? "success" : "error";
    $redirectParams["scholar_notice_message"] = $actionMessage;
    header("Location: institutional-scholars.php" . (!empty($redirectParams) ? ("?" . http_build_query($redirectParams)) : ""));
    exit;
  }

  if ($hasScholarStorage) {
    $serverScholarRecords = isgLoadScholarRecords($conn, $validScholarCategories);
    $terminatedScholarRecords = isgLoadTerminatedScholarRecords($conn);
    $assignedOfficeHistoryRecords = isgLoadAssignedOfficeHistoryRecords($conn);
  }
}
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Institutional Scholars</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
      rel="stylesheet"
    />
    <style>
      ::-webkit-scrollbar {
        width: 6px;
      }

      ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
        border-radius: 999px;
      }

      .table-zebra tbody tr:nth-child(even) {
        background: #f8fafc;
      }
          #sidebar > nav > ul {
        padding: 0.35rem 0.5rem 5.5rem;
      }
      #sidebar li[data-nav] {
        border-radius: 0.85rem;
        margin-bottom: 0.25rem;
        padding-left: 0.75rem;
        padding-right: 0.75rem;
        min-height: 2.5rem;
        display: flex;
        align-items: center;
        white-space: nowrap;
        transition: background-color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
      }
      #sidebar li[data-nav]:hover {
        transform: translateX(2px);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.16);
      }
    </style>
  </head>
  <body class="bg-white font-sans">
    <div class="min-h-screen">
      <!-- Sidebar -->
      <aside
        id="sidebar"
        class="flex flex-col bg-gradient-to-b from-[#031f4f] via-[#0a4b86] to-[#0f9ad8] text-white w-64 h-screen fixed left-0 top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out overflow-y-auto shadow-[12px_0_28px_-12px_rgba(4,31,79,0.65)]"
      >
        <div
          class="mx-3 mt-3 rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm"
        >
          <div class="flex items-center gap-3">
            <div class="relative shrink-0">
              <span class="absolute -inset-1 rounded-full bg-white/15 blur-sm"></span>
              <img
                src="../img/SMCCNEWLOGO.png"
                class="relative rounded-full w-14 h-14 object-cover ring-2 ring-white/45"
                alt="SMCC Logo"
              />
            </div>
            <div class="min-w-0">
              <p class="text-[10px] uppercase tracking-[0.14em] text-blue-100/85">
                SMCC Scholarship
              </p>
              <p class="text-sm font-semibold leading-tight text-white">
                Admission and Scholarship Office
              </p>
              <p class="text-[10px] text-blue-100/80 mt-1">
                Admin Management Portal
              </p>
            </div>
          </div>
        </div>

        <nav class="flex-1 mt-2">
          <ul class="text-xs font-semibold">
<li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="adminDashboard.php" onclick="window.location.href='adminDashboard.php'"
            >
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li class="mb-1">
              <details class="group">
                <summary
                  class="flex cursor-pointer list-none items-center gap-2 rounded-[0.85rem] px-4 py-3 text-left hover:bg-white/15"
                  style="list-style: none;"
                  data-nav="applicant.php"
                >
                  <i class="fas fa-user-graduate w-5"></i>
                  <span class="flex-1">Applicants</span>
                  <i class="fas fa-chevron-down text-[10px] transition group-open:rotate-180"></i>
                </summary>
                <ul class="ml-8 mt-1 space-y-1 border-l border-white/20 pl-3 text-[11px] font-semibold">
                  <li>
                    <a href="applicant.php" class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-blue-50 hover:bg-white/15">
                      <span>Pending Applicants</span>
                      <span class="inline-flex min-w-[1.65rem] items-center justify-center rounded-full bg-gradient-to-r from-[#fcdc2f] to-[#ffe889] px-2 py-0.5 text-[10px] font-extrabold leading-none text-[#052c6a] shadow-[0_0_0_1px_rgba(255,255,255,0.35),0_6px_14px_rgba(252,220,47,0.28)]">
                        <?= htmlspecialchars($sidebarPendingApplicantBadge ?? '0') ?>
                      </span>
                    </a>
                  </li>
                  <li>
                    <a href="declined-applicants.php" class="block rounded-lg px-3 py-2 text-blue-50 hover:bg-white/15">
                      Declined Applicants
                    </a>
                  </li>
                  <li>
                    <a href="reserved-applicants.php" class="block rounded-lg px-3 py-2 text-blue-50 hover:bg-white/15">
                      Reserved Applicants
                    </a>
                  </li>
                  <li>
                    <a href="summary-of-applicants.php" class="block rounded-lg px-3 py-2 text-blue-50 hover:bg-white/15">
                      Summary of Applicants
                    </a>
                  </li>
                </ul>
              </details>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="approved.php" onclick="window.location.href='approved.php'"
            >
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="interviewEvaluation.php" onclick="window.location.href='interviewEvaluation.php'"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="ranks.php" onclick="window.location.href='ranks.php'"
            >
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="list-of-qualified.php" onclick="window.location.href='list-of-qualified.php'"
            >
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="department-evaluation-list.php" onclick="window.location.href='department-evaluation-list.php'"
            >
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="summary-report.php" onclick="window.location.href='summary-report.php'"
            >
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="isg-scholars.php" onclick="window.location.href='institutional-scholars.php'"
            >
              <i class="fas fa-chart-line w-5"></i>
              <span>Institutional Scholars</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="accounts.php" onclick="window.location.href='accounts.php'"
            >
              <i class="fas fa-user-circle w-5"></i>
              <span>Settings</span>
            </li>
          </ul>
        </nav>

                <div class="absolute bottom-0 left-0 w-full p-2">
          <div class="rounded-xl border border-white/20 bg-white/10 backdrop-blur-sm overflow-hidden">
            <div class="h-px w-full bg-gradient-to-r from-transparent via-[#8bcfff] to-transparent opacity-80"></div>

            <div class="px-4 pt-2 pb-1 flex items-center gap-2 text-[11px] text-blue-100/90">
              <div class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center">
                <i class="fas fa-user-shield text-[12px]"></i>
              </div>
              <div class="leading-tight">
                <p class="font-semibold">Admin Account</p>
                <p class="text-[10px] text-blue-200/80">Institutional Scholarship</p>
              </div>
            </div>

            <!-- Logout button -->
            <div class="px-3 pb-3 pt-1">
              <button
                onclick="window.location.href='../logout.php'"
                class="w-full flex items-center justify-center gap-2 text-[11px] font-semibold
                       bg-gradient-to-r from-red-500 to-red-600
                       hover:from-red-600 hover:to-red-700
                       px-3 py-2 rounded-full shadow-md hover:shadow-lg
                       transition-all duration-150"
                type="button"
              >
                <i class="fas fa-sign-out-alt text-xs"></i>
                <span>Logout</span>
              </button>
            </div>
          </div>
        </div>
      </aside>

      <main class="ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pt-14">
        <header
          class="hidden fixed top-0 left-0 md:left-64 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
        >
          <div class="flex items-center gap-2">
            <button
              id="sidebarToggleTop"
              class="md:hidden inline-flex items-center justify-center p-2 rounded bg-[#0d8ddb] focus:outline-none"
              type="button"
            >
              <i class="fas fa-bars"></i>
            </button>
            <span class="text-[11px] font-semibold md:hidden">Admission &amp; Scholarship</span>
          </div>
          <div class="flex gap-2 text-xs">
            <button class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 flex items-center gap-1 font-normal" type="button">
              <i class="fas fa-user"></i>
              Admin panel
            </button>
            <button class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 font-normal" type="button">Account</button>
          </div>
        </header>

        <section
          class="page-header fixed top-0 left-0 md:left-64 right-0 z-20 bg-white border-b border-slate-200 px-4 sm:px-6 py-3 shadow-sm"
        >
                    <div class="flex items-center gap-2">
            <button
              id="sidebarToggle"
              class="md:hidden inline-flex items-center justify-center p-2 rounded bg-slate-700 text-white hover:bg-slate-800 focus:outline-none transition-colors"
              type="button"
            >
              <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-slate-800 text-lg font-semibold flex items-center gap-2">
            <i class="fas fa-flag"></i>
            OFFICIAL INSTITUTIONAL SCHOLARS
          </h2>
          </div>
        </section>

        <section class="mt-12 px-3 sm:px-4 lg:px-6 py-4 bg-gray-100 flex-1 min-h-[calc(100vh-3rem)]">
          <div class="w-full space-y-4 h-full flex flex-col">
            <div class="bg-white rounded-xl shadow-sm border border-[#e5e7eb] px-4 sm:px-6 py-5">
              <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div>
                  <h1 class="text-xl font-bold text-[#052c6a]">Institutional Scholars Storage</h1>
                </div>
              </div>

              <form class="mt-4 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between" method="get" action="institutional-scholars.php">
                <div class="flex flex-wrap gap-2">
                  <input type="hidden" name="active_category" value="<?php echo htmlspecialchars($activeCategoryParam); ?>" />
                  <select
                    class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                    name="school_year"
                    aria-label="Select academic year"
                    onchange="this.form.submit()"
                  >
                    <option value="all" <?php echo $showAllInstitutionalSchoolYears ? "selected" : ""; ?>>All School Years</option>
                    <?php foreach ($schoolYearOptions as $option): ?>
                      <option value="<?php echo htmlspecialchars($option); ?>" <?php echo !$showAllInstitutionalSchoolYears && $activeInstitutionalSchoolYear === $option ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($option); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <select
                    class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                    name="semester"
                    aria-label="Select semester"
                    onchange="this.form.submit()"
                  >
                    <option value="">All Semesters</option>
                    <?php foreach ($semesterOptions as $option): ?>
                      <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $activeInstitutionalSemester === $option ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($option); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if ($showAllInstitutionalSchoolYears || $selectedSchoolYear !== "" || $rawSelectedSemester !== null): ?>
                    <a
                      href="institutional-scholars.php?active_category=<?php echo urlencode($activeCategoryParam); ?>"
                      class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm"
                    >
                      Clear
                    </a>
                  <?php endif; ?>
                </div>
                <div class="flex flex-wrap gap-2 lg:ml-auto">
                  <button
                    type="button"
                    id="openManualAddModal"
                    class="inline-flex items-center justify-center rounded-full bg-[#0d8ddb] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#0a7fc8]"
                  >
                    Add Record
                  </button>
                  <button
                    type="button"
                    id="printScholarsBtn"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-[#0d8ddb] bg-white px-4 py-2 text-xs font-semibold text-[#052c6a] shadow-sm transition-colors hover:bg-[#eff6ff]"
                  >
                    <i class="fas fa-print text-[11px]"></i>
                    <span>Print</span>
                  </button>
                </div>
              </form>

              <div id="manualAddModal" class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-900/50 px-3 py-6">
                <div class="w-full max-w-5xl max-h-[90vh] overflow-y-auto rounded-xl border border-[#dbe7ff] bg-white p-4 sm:p-5">
                  <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-bold text-[#052c6a]">Add Record</h3>
                    <button
                      type="button"
                      id="closeManualAddModal"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 text-slate-600 hover:bg-slate-100"
                      aria-label="Close add record modal"
                    >
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>

                  <div class="mt-4 grid grid-cols-1 xl:grid-cols-2 gap-4">
                    <form id="manualAddForm" class="space-y-3 rounded-xl border border-[#dbe7ff] bg-[#f8fbff] p-3" method="post" action="institutional-scholars.php">
                      <input type="hidden" name="form_action" value="add_manual_scholar" />
                      <input type="hidden" name="return_school_year" value="<?php echo htmlspecialchars($selectedSchoolYear); ?>" />
                      <input type="hidden" name="return_semester" value="<?php echo htmlspecialchars($selectedSemester); ?>" />
                      <input type="hidden" name="return_active_category" value="<?php echo htmlspecialchars($activeCategoryParam); ?>" />

                      <p class="text-xs font-semibold text-[#052c6a]">Manual Entry</p>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                          <label class="mb-1 block text-[11px] font-semibold text-slate-700">Scholarship Grant</label>
                          <select
                            id="manualGrantApplied"
                            name="manual_grant_applied"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                            required
                          >
                            <option value="" <?php echo $manualDefaultGrant === "" ? "selected" : ""; ?> disabled>Select scholarship grant</option>
                            <?php foreach ($manualGrantOptions as $grantOption): ?>
                              <option value="<?php echo htmlspecialchars($grantOption); ?>" <?php echo $manualDefaultGrant === $grantOption ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($grantOption); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div>
                          <label class="mb-1 block text-[11px] font-semibold text-slate-700">Full Name</label>
                          <input
                            type="text"
                            name="manual_full_name"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                            required
                          />
                        </div>
                        <div>
                          <label class="mb-1 block text-[11px] font-semibold text-slate-700">Email</label>
                          <input
                            type="email"
                            name="manual_email"
                            placeholder="student@example.com"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                            required
                          />
                        </div>
                        <div>
                          <label class="mb-1 block text-[11px] font-semibold text-slate-700">Program / Year</label>
                          <input
                            type="text"
                            name="manual_program_year"
                            placeholder="Ex: BSIT / 2nd Year"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                          />
                        </div>
                        <div>
                          <label class="mb-1 block text-[11px] font-semibold text-slate-700">Assigned Office</label>
                          <select
                            id="manualAssignedOffice"
                            name="manual_assigned_office"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                            <?php echo empty($assignedOfficeOptions) ? "disabled" : ""; ?>
                          >
                            <option value="">Select assigned office</option>
                            <?php foreach ($assignedOfficeOptions as $officeOption): ?>
                              <option value="<?php echo htmlspecialchars($officeOption); ?>">
                                <?php echo htmlspecialchars($officeOption); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                          <?php if (empty($assignedOfficeOptions)): ?>
                            <p class="mt-1 text-[11px] text-slate-500">No active head office available yet.</p>
                          <?php endif; ?>
                          
                        </div>
                        <div>
                          <label class="mb-1 block text-[11px] font-semibold text-slate-700">Semester</label>
                          <select
                            name="manual_semester"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                          >
                            <?php foreach ($semesterOptions as $option): ?>
                              <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $option === $displaySemester ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($option); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div>
                          <label class="mb-1 block text-[11px] font-semibold text-slate-700">Academic Year</label>
                          <input
                            type="text"
                            name="manual_academic_year"
                            value="<?php echo htmlspecialchars($displaySchoolYear); ?>"
                            placeholder="YYYY-YYYY"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700"
                          />
                        </div>
                      </div>

                      <div class="flex flex-wrap items-center gap-2">
                        <button
                          type="submit"
                          class="inline-flex items-center rounded-full bg-[#0d8ddb] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#0a7fc8]"
                        >
                          Save Scholar
                        </button>
                        <button
                          type="button"
                          id="cancelManualAddModal"
                          class="inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                          Cancel
                        </button>
                      </div>
                    </form>

                    <form
                      id="importScholarForm"
                      class="space-y-3 rounded-xl border border-[#dbe7ff] bg-[#f8fbff] p-3"
                      method="post"
                      action="institutional-scholars.php"
                      enctype="multipart/form-data"
                    >
                      <input type="hidden" name="form_action" value="import_scholars_file" />
                      <input type="hidden" name="return_school_year" value="<?php echo htmlspecialchars($selectedSchoolYear); ?>" />
                      <input type="hidden" name="return_semester" value="<?php echo htmlspecialchars($selectedSemester); ?>" />
                      <input type="hidden" name="return_active_category" value="<?php echo htmlspecialchars($activeCategoryParam); ?>" />

                      <p class="text-xs font-semibold text-[#052c6a]">Import CSV</p>

                      <div>
                        <label class="mb-1 block text-[11px] font-semibold text-slate-700">Upload File (.csv)</label>
                        <input
                          type="file"
                          name="scholar_import_file"
                          accept=".csv,text/csv"
                          class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-[#0d8ddb] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-[#0a7fc8]"
                          required
                        />
                        <p class="mt-1 text-[11px] text-slate-600">
                          Required CSV columns: <strong>scholarship_grant, full_name, email, program_year, assigned_office, semester, academic_year</strong>.
                        </p>
                        <a
                          href="institutional-scholars-import-template.csv"
                          download
                          class="mt-2 inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-1.5 text-[11px] font-semibold text-[#0d8ddb] hover:bg-[#eff6ff]"
                        >
                          Download CSV Template
                        </a>
                      </div>

                      <div class="flex flex-wrap items-center gap-2">
                        <button
                          type="submit"
                          class="inline-flex items-center rounded-full bg-[#0d8ddb] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#0a7fc8]"
                        >
                          Import this file
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

              <div id="scholarMessageModal" class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-900/50 px-3 py-6">
                <div class="w-full max-w-md rounded-xl border border-[#dbe7ff] bg-white shadow-lg">
                  <div class="flex items-center justify-between gap-3 border-b border-[#dbe7ff] px-4 py-3">
                    <h3 class="text-sm font-bold text-[#052c6a]">Send Message</h3>
                    <button
                      type="button"
                      id="scholarMessageClose"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 text-slate-600 hover:bg-slate-100"
                      aria-label="Close message modal"
                    >
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <form id="scholarMessageForm" action="send_message.php" method="post" class="space-y-3 px-4 py-4">
                    <input type="hidden" name="scholar_record_id" id="scholarMessageRecordId" value="" />
                    <input type="hidden" name="return_page" value="institutional-scholars.php" />
                    <input type="hidden" name="return_school_year" id="scholarMessageReturnSchoolYear" value="<?php echo htmlspecialchars($showAllInstitutionalSchoolYears ? "all" : $selectedSchoolYear); ?>" />
                    <input type="hidden" name="return_semester" id="scholarMessageReturnSemester" value="<?php echo htmlspecialchars($rawSelectedSemester === null ? "" : $selectedSemester); ?>" />
                    <input type="hidden" name="return_active_category" id="scholarMessageReturnCategory" value="<?php echo htmlspecialchars($activeCategoryParam); ?>" />
                    <div>
                      <label class="mb-1 block text-[11px] font-semibold text-slate-700">Recipient</label>
                      <input
                        type="text"
                        id="scholarMessageRecipientName"
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-700"
                        readonly
                      />
                    </div>
                    <div>
                      <label class="mb-1 block text-[11px] font-semibold text-slate-700">Recipient Email</label>
                      <input
                        type="text"
                        id="scholarMessageRecipientEmail"
                        name="recipient_email"
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-700"
                        readonly
                      />
                    </div>
                    <div>
                      <label class="mb-1 block text-[11px] font-semibold text-slate-700" for="scholarMessageBody">Message</label>
                      <textarea
                        id="scholarMessageBody"
                        name="message_body"
                        rows="6"
                        required
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700 focus:border-[#0d8ddb] focus:outline-none"
                        placeholder="Type your message here..."
                      ></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                      <button
                        type="button"
                        id="scholarMessageCancel"
                        class="inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                      >
                        Cancel
                      </button>
                      <button
                        type="submit"
                        id="scholarMessageSubmit"
                        class="inline-flex items-center rounded-full bg-[#0d8ddb] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#0a7fc8]"
                      >
                        Send
                      </button>
                    </div>
                  </form>
                </div>
              </div>

              <div
                id="renewalTermNotice"
                class="mt-3 hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-800"
              ></div>

            </div>

            <div class="bg-white rounded-xl shadow-sm border border-[#e5e7eb] overflow-hidden flex-1 flex flex-col min-h-[420px]">
              <div class="px-4 sm:px-6 py-4 border-b border-[#e5e7eb] bg-[#f8fafc]">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div>
                    <h2 class="text-base font-bold text-[#052c6a]">Scholar Category Tables</h2>
                  </div>
                  <span class="inline-flex items-center gap-2 text-[11px] font-semibold text-[#0f172a] bg-white border border-[#e2e8f0] px-3 py-1 rounded-full">
                    Active: <span id="activeCategoryLabel" class="text-[#052c6a]">Official Scholars</span>
                  </span>
                </div>

                <div class="mt-4 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                  <div class="flex flex-wrap gap-2">
                    <button type="button" data-category="official" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-transparent bg-[#052c6a] text-white shadow-sm">Official Scholars</button>
                    <button type="button" data-category="student_assistant" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-[#e2e8f0] bg-white text-[#334155]">Student Assistant</button>
                    <button type="button" data-category="kabayani" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-[#e2e8f0] bg-white text-[#334155]">Kabayani</button>
                    <button type="button" data-category="academic" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-[#e2e8f0] bg-white text-[#334155]">Academic</button>
                    <button type="button" data-category="others" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-[#e2e8f0] bg-white text-[#334155]">Others</button>
                  </div>
                  <div class="flex flex-wrap items-center gap-2">
                    <div id="scholarSearchWrap">
                      <label class="sr-only" for="scholarSearchInput">Search scholar records</label>
                      <input
                        id="scholarSearchInput"
                        type="search"
                        class="w-72 rounded-lg border border-[#e2e8f0] bg-white px-3 py-2 text-xs font-semibold text-[#334155] shadow-sm focus:border-[#0d8ddb] focus:outline-none"
                        placeholder="Search records..."
                      />
                    </div>
                    <div id="terminatedSearchWrap" class="hidden">
                      <label class="sr-only" for="terminatedSearchInput">Search terminated records</label>
                      <input
                        id="terminatedSearchInput"
                        type="search"
                        class="w-56 rounded-lg border border-[#e2e8f0] bg-white px-3 py-2 text-xs font-semibold text-[#334155] shadow-sm focus:border-[#0d8ddb] focus:outline-none"
                        placeholder="Search records..."
                      />
                    </div>
                    <button type="button" data-category="terminated_records" id="terminatedRecordsToggle" class="category-btn inline-flex items-center gap-2 rounded-lg border border-[#dc2626] bg-white px-4 py-2 text-xs font-semibold text-[#dc2626] shadow-sm transition-colors hover:bg-[#fef2f2] hover:border-[#b91c1c] focus:outline-none focus:ring-2 focus:ring-[#fecaca]">
                      <i class="fas fa-archive text-[11px]"></i>
                      <span id="terminatedRecordsToggleLabel">Show Terminated/Ended Contracts</span>
                    </button>
                  </div>
                </div>
              </div>

              <div class="px-4 sm:px-6 py-4 overflow-x-auto flex-1">
                <div id="terminatedTableTitle" class="hidden mb-4">
                  <h3 class="text-2xl font-bold text-[#991b1b]">Ended Contract / Terminated</h3>
                </div>
                <table class="table-zebra min-w-full text-xs border border-[#dbe2ea] rounded-lg overflow-hidden">
                  <thead class="bg-gradient-to-r from-[#052c6a] to-[#0d8ddb] text-white">
                    <tr>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">No.</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Scholarship Grant</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Full Name</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Program / Year</th>
                      <th id="assignedOfficeHeader" class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Assigned Office</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Semester</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Academic Year</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Status</th>
                      <th id="actionReasonHeader" class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Action</th>
                    </tr>
                  </thead>
                  <tbody id="scholarRows" class="divide-y divide-[#e5e7eb] bg-white">
                    <tr>
                      <td colspan="9" class="px-3 py-8 text-center text-gray-500 italic">No records yet for Official Scholars.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>

    <script>
      // Client-side store for scholar tabs, renewal flows, modal state, and table rendering.
      const selectedSchoolYear = <?php echo json_encode($activeInstitutionalSchoolYear); ?>;
      const selectedSemester = <?php echo json_encode($activeInstitutionalSemester); ?>;
      const displaySchoolYear = <?php echo json_encode($displaySchoolYear); ?>;
      const displaySemester = <?php echo json_encode($displaySemester); ?>;
      const currentSchoolYear = <?php echo json_encode($currentSchoolYear); ?>;
      const currentSemester = <?php echo json_encode($currentSemester); ?>;
      const hasConfiguredActiveTerm = <?php echo json_encode($hasConfiguredInstitutionalActiveTerm); ?>;
      const availableSchoolYears = <?php echo json_encode(array_values(array_unique($schoolYearOptions)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const activeFilterSchoolYear = String(selectedSchoolYear || displaySchoolYear || "").trim();
      const activeFilterSemester = String(selectedSemester || displaySemester || "").trim();
      const initialActiveCategory = <?php echo json_encode($activeCategoryParam); ?>;
      const serverScholarRecords = <?php echo json_encode($serverScholarRecords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const terminatedScholarRecords = <?php echo json_encode($terminatedScholarRecords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const assignedOfficeHistoryRecords = <?php echo json_encode($assignedOfficeHistoryRecords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const availableAssignedOffices = <?php echo json_encode($assignedOfficeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const autoImportMessage = <?php echo json_encode($autoImportMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const autoImportType = <?php echo json_encode($autoImportType, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const actionNoticeMessage = <?php echo json_encode($actionNoticeMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const actionNoticeType = <?php echo json_encode($actionNoticeType, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const messageNoticeMessage = <?php echo json_encode($messageNoticeMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const messageNoticeType = <?php echo json_encode($messageNoticeType, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const scholarMessageReturnSchoolYear = <?php echo json_encode($showAllInstitutionalSchoolYears ? "all" : $selectedSchoolYear, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const scholarMessageReturnSemester = <?php echo json_encode($rawSelectedSemester === null ? "" : $selectedSemester, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      let scholarSearchTerm = "";
      let terminatedSearchTerm = "";

      const categoryConfig = {
        official: {
          label: "Official Scholars"
        },
        student_assistant: {
          label: "Student Assistant"
        },
        kabayani: {
          label: "Kabayani"
        },
        academic: {
          label: "Academic"
        },
        others: {
          label: "Others"
        },
        terminated_records: {
          label: "Terminated / Ended Contracts"
        }
      };
      let previousScholarCategory = categoryConfig[initialActiveCategory] && initialActiveCategory !== "terminated_records"
        ? initialActiveCategory
        : "official";

      const scholarStore = {};
      Object.keys(categoryConfig).forEach((category) => {
        if (category === "terminated_records") {
          scholarStore[category] = Array.isArray(terminatedScholarRecords)
            ? terminatedScholarRecords.map((record) => ({ ...record }))
            : [];
          return;
        }
        const records = serverScholarRecords && typeof serverScholarRecords === "object"
          ? serverScholarRecords[category]
          : [];
        scholarStore[category] = Array.isArray(records)
          ? records.map((record) => ({ ...record }))
          : [];
      });

      let activeCategory = categoryConfig[initialActiveCategory] ? initialActiveCategory : "official";

      function escapeHtml(value) {
        return String(value ?? "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/\"/g, "&quot;")
          .replace(/'/g, "&#39;");
      }

      function isValidEmailAddress(value) {
        const emailValue = String(value || "").trim();
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue);
      }

      function showScholarToast(message, type, queryParams) {
        if (!message) {
          return;
        }

        const currentUrl = new URL(window.location.href);
        queryParams.forEach((queryParam) => {
          currentUrl.searchParams.delete(queryParam);
        });
        window.history.replaceState({}, document.title, currentUrl.toString());

        if (typeof Swal === "undefined") {
          window.alert(message);
          return;
        }

        Swal.fire({
          toast: true,
          position: "top-end",
          showConfirmButton: false,
          icon: type === "error" ? "error" : "success",
          title: message,
          timer: type === "error" ? 4200 : 3200,
          timerProgressBar: true,
          background: type === "error" ? "#fef2f2" : "#f0fdf4",
          color: type === "error" ? "#991b1b" : "#166534",
        });
      }

      function getCategoryRecords(category) {
        const config = categoryConfig[category];
        if (!config) return [];
        const records = scholarStore[category];
        return Array.isArray(records) ? records : [];
      }

      function getAssignedOfficeChoices(currentOffice = "") {
        const choices = [];
        const seen = new Set();
        const addChoice = (value) => {
          const office = String(value || "").trim();
          if (office === "") return;
          const key = office.toLowerCase();
          if (seen.has(key)) return;
          seen.add(key);
          choices.push(office);
        };

        if (Array.isArray(availableAssignedOffices)) {
          availableAssignedOffices.forEach(addChoice);
        }
        addChoice(currentOffice);
        return choices;
      }

      function getAssignedOfficeHistoryForRecord(record) {
        if (!record || typeof record !== "object" || !Array.isArray(assignedOfficeHistoryRecords)) {
          return [];
        }

        const scholarId = String(record.scholar_id || "").trim().toLowerCase();
        const academicYear = String(record.academic_year || "").trim().toLowerCase();
        const semester = String(record.semester || "").trim().toLowerCase();
        const sourceApplicationId = Number(record.source_application_id || 0);

        return assignedOfficeHistoryRecords.filter((history) => {
          const historyScholarId = String(history && typeof history === "object" ? (history.scholar_id || "") : "").trim().toLowerCase();
          const historyAcademicYear = String(history && typeof history === "object" ? (history.academic_year || "") : "").trim().toLowerCase();
          const historySemester = String(history && typeof history === "object" ? (history.semester || "") : "").trim().toLowerCase();
          const historySourceApplicationId = Number(history && typeof history === "object" ? (history.source_application_id || 0) : 0);

          if (scholarId !== "" && historyScholarId !== "" && historyScholarId === scholarId) {
            return historyAcademicYear === academicYear && historySemester === semester;
          }
          return sourceApplicationId > 0
            && historySourceApplicationId === sourceApplicationId
            && historyAcademicYear === academicYear
            && historySemester === semester;
        });
      }

      function formatHistoryTimestamp(value) {
        const rawValue = String(value || "").trim();
        if (rawValue === "") return "";
        const parsedDate = new Date(rawValue.replace(" ", "T"));
        if (Number.isNaN(parsedDate.getTime())) return rawValue;
        return parsedDate.toLocaleString("en-US", {
          year: "numeric",
          month: "short",
          day: "2-digit",
          hour: "numeric",
          minute: "2-digit"
        });
      }

      function getHistoryTimestampMs(value) {
        const rawValue = String(value || "").trim();
        if (rawValue === "") return 0;
        const parsedDate = new Date(rawValue.replace(" ", "T"));
        const time = parsedDate.getTime();
        return Number.isNaN(time) ? 0 : time;
      }

      function getAssignedOfficeTimelineForRecord(record) {
        if (!record || typeof record !== "object") {
          return [];
        }

        const historyRows = getAssignedOfficeHistoryForRecord(record)
          .slice()
          .sort((left, right) => {
            const leftTime = getHistoryTimestampMs(left && typeof left === "object" ? left.changed_at : "");
            const rightTime = getHistoryTimestampMs(right && typeof right === "object" ? right.changed_at : "");
            if (leftTime === rightTime) {
              return Number(left && typeof left === "object" ? (left.id || 0) : 0) - Number(right && typeof right === "object" ? (right.id || 0) : 0);
            }
            return leftTime - rightTime;
          });
        const currentOffice = String(record.assigned_office || "").trim();
        const createdAt = String(record.created_at || "").trim();
        const timeline = [];
        let activeOffice = historyRows.length > 0
          ? String(historyRows[0].from_office || "").trim()
          : currentOffice;
        let activeStartedAt = createdAt;

        historyRows.forEach((history) => {
          const fromOffice = String(history.from_office || "").trim();
          const toOffice = String(history.to_office || "").trim();
          const changedAt = String(history.changed_at || "").trim();
          const displayOffice = activeOffice !== "" ? activeOffice : fromOffice;

          if (displayOffice !== "" || changedAt !== "") {
            timeline.push({
              office: displayOffice !== "" ? displayOffice : "Unassigned",
              started_at: activeStartedAt,
              ended_at: changedAt,
              moved_to: toOffice,
              changed_by: String(history.changed_by || "").trim(),
              is_current: false
            });
          }

          activeOffice = toOffice;
          activeStartedAt = changedAt;
        });

        const finalOffice = currentOffice !== "" ? currentOffice : activeOffice;
        if (finalOffice !== "" || timeline.length > 0) {
          timeline.push({
            office: finalOffice !== "" ? finalOffice : "Unassigned",
            started_at: activeStartedAt,
            ended_at: "",
            moved_to: "",
            changed_by: "",
            is_current: true
          });
        }

        return timeline;
      }

      function getLogicalScholarMatchKey(record) {
        if (!record || typeof record !== "object") return "";

        const scholarId = String(record.scholar_id || "").trim().toLowerCase();
        if (scholarId !== "") return "sid:" + scholarId;

        const fullName = String(record.full_name || "").trim().toLowerCase();
        const semester = String(record.semester || "").trim().toLowerCase();
        const academicYear = String(record.academic_year || "").trim().toLowerCase();
        if (fullName === "" && semester === "" && academicYear === "") return "";

        return "name:" + fullName + "|" + semester + "|" + academicYear;
      }

      function saveCategoryRecords(category, records) {
        const config = categoryConfig[category];
        if (!config) return;
        scholarStore[category] = Array.isArray(records)
          ? records.map((record) => ({ ...record }))
          : [];
      }

      function inferGrantFromOtherCategories(record) {
        const recordKey = getLogicalScholarMatchKey(record);
        if (recordKey === "") return "";
        const lookupCategories = ["student_assistant", "kabayani", "academic", "others"];

        for (let i = 0; i < lookupCategories.length; i += 1) {
          const category = lookupCategories[i];
          const records = getCategoryRecords(category);
          for (let j = 0; j < records.length; j += 1) {
            const item = records[j];
            if (getLogicalScholarMatchKey(item) !== recordKey) continue;

            const explicitGrant = String(item && typeof item === "object" ? (item.grant_applied || item.grant || "") : "").trim();
            if (explicitGrant !== "") return explicitGrant;

            if (categoryConfig[category] && categoryConfig[category].label) {
              return String(categoryConfig[category].label).trim();
            }
          }
        }

        return "";
      }

      function resolveGrantApplied(category, record) {
        const explicitGrant = String(record && typeof record === "object" ? (record.grant_applied || record.grant || "") : "").trim();
        if (explicitGrant !== "") return explicitGrant;

        if (category !== "official") {
          return categoryConfig[category] && categoryConfig[category].label
            ? String(categoryConfig[category].label).trim()
            : "Others";
        }

        const inferredGrant = inferGrantFromOtherCategories(record);
        return inferredGrant !== "" ? inferredGrant : "Others";
      }

      function inferCategoryFromGrantLabel(grantValue) {
        const normalized = String(grantValue || "").trim().toLowerCase();
        if (normalized.includes("assistant")) return "student_assistant";
        if (normalized.includes("kabayani")) return "kabayani";
        if (normalized.includes("academic")) return "academic";
        return "others";
      }

      function normalizeGrantLabels() {
        const nonOfficialCategories = ["student_assistant", "kabayani", "academic", "others"];
        let hasChanges = false;

        nonOfficialCategories.forEach((category) => {
          const label = categoryConfig[category] && categoryConfig[category].label
            ? String(categoryConfig[category].label).trim()
            : "Others";
          const records = getCategoryRecords(category);
          const nextRecords = records.map((record) => {
            const currentGrant = String(record && typeof record === "object" ? (record.grant_applied || record.grant || "") : "").trim();
            if (currentGrant !== "") return record;
            hasChanges = true;
            return { ...record, grant_applied: label };
          });
          saveCategoryRecords(category, nextRecords);
        });

        const officialRecords = getCategoryRecords("official");
        const nextOfficialRecords = officialRecords.map((record) => {
          const currentGrant = String(record && typeof record === "object" ? (record.grant_applied || record.grant || "") : "").trim();
          if (currentGrant !== "") return record;
          hasChanges = true;
          return { ...record, grant_applied: resolveGrantApplied("official", record) };
        });
        saveCategoryRecords("official", nextOfficialRecords);

        return hasChanges;
      }

      function getRecordKey(record, index) {
        const recordId = Number(record && typeof record === "object" ? (record.id ?? 0) : 0);
        if (recordId > 0) return "rid-" + recordId;

        const scholarId = String(record && typeof record === "object" ? (record.scholar_id ?? "") : "").trim();
        if (scholarId !== "") return "sid-" + scholarId;

        return "idx-" + index;
      }

      function upsertCategoryRecord(category, record) {
        const config = categoryConfig[category];
        if (!config || !record || typeof record !== "object") return;

        const recordId = Number(record.id || 0);
        if (recordId <= 0) return;

        const records = getCategoryRecords(category);
        const existingIndex = records.findIndex((item) => Number(item && item.id ? item.id : 0) === recordId);

        if (existingIndex >= 0) {
          records[existingIndex] = { ...records[existingIndex], ...record };
        } else {
          records.push(record);
        }
        saveCategoryRecords(category, records);
      }

      function updateCounts() {
        const officialRecords = getCategoryRecords("official");
        const counts = {
          official: officialRecords.length,
          student_assistant: 0,
          kabayani: 0,
          academic: 0,
          others: 0
        };

        officialRecords.forEach((record) => {
          const grantLabel = resolveGrantApplied("official", record);
          const category = inferCategoryFromGrantLabel(grantLabel);
          if (Object.prototype.hasOwnProperty.call(counts, category)) {
            counts[category] += 1;
          }
        });

        const countElements = {
          official: document.getElementById("count-official"),
          student_assistant: document.getElementById("count-student-assistant"),
          kabayani: document.getElementById("count-kabayani"),
          academic: document.getElementById("count-academic"),
          others: document.getElementById("count-others")
        };

        Object.entries(countElements).forEach(([category, element]) => {
          if (element) element.textContent = counts[category];
        });
      }

      function normalizeScholarStatus(rawStatus) {
        const value = String(rawStatus || "").trim().toLowerCase();
        if (value === "") return "";

        if (["official scholar", "official_scholar", "official", "active", "hired", "confirmed from approved applications"].includes(value)) {
          return "official_scholar";
        }
        if (["for renewal", "for_renewal", "needs renewal"].includes(value)) {
          return "for_renewal";
        }
        if (value === "renewed") {
          return "renewed";
        }
        if (["expired", "do not renew", "do_not_renew"].includes(value)) {
          return "expired";
        }
        if (["contract ended", "contract_ended", "end contract", "ended contract"].includes(value)) {
          return "contract_ended";
        }
        if (["terminated", "terminate"].includes(value)) {
          return "terminated";
        }
        return "";
      }

      function parseAcademicYearRange(value) {
        const raw = String(value || "").trim();
        const match = raw.match(/^(\d{4})\s*-\s*(\d{4})$/);
        if (!match) return null;

        const startYear = Number(match[1]);
        const endYear = Number(match[2]);
        if (!Number.isFinite(startYear) || !Number.isFinite(endYear)) return null;
        if (endYear <= startYear) return null;

        return { startYear, endYear };
      }

      function getRecordAcademicYearRange(record) {
        const fromRecord = parseAcademicYearRange(record && typeof record === "object" ? record.academic_year : "");
        if (fromRecord) return fromRecord;

        const fromFilter = parseAcademicYearRange(activeFilterSchoolYear);
        if (fromFilter) return fromFilter;

        const fromCurrent = parseAcademicYearRange(currentSchoolYear);
        if (fromCurrent) return fromCurrent;

        const currentYear = new Date().getFullYear();
        return {
          startYear: currentYear,
          endYear: currentYear + 1
        };
      }

      function getNextAcademicYearForRecord(record) {
        const range = getRecordAcademicYearRange(record);
        if (!range) return "";
        return String(Number(range.startYear) + 1) + "-" + String(Number(range.endYear) + 1);
      }

      function isSchoolYearAvailable(targetSchoolYear) {
        const targetValue = String(targetSchoolYear || "").trim().toLowerCase();
        if (targetValue === "") return false;
        return Array.isArray(availableSchoolYears)
          && availableSchoolYears.some((value) => String(value || "").trim().toLowerCase() === targetValue);
      }

      function getRecordSemesterPhase(record) {
        const semesterValue = String(record && typeof record === "object" ? (record.semester || "") : "").trim().toLowerCase();
        if (semesterValue.includes("2nd")) return "second";
        if (semesterValue.includes("1st")) return "first";

        const fallbackSemester = String(activeFilterSemester || displaySemester || "").trim().toLowerCase();
        if (fallbackSemester.includes("2nd")) return "second";
        return "first";
      }

      function getActiveSemesterPhase() {
        const activeSemesterValue = String(currentSemester || displaySemester || "").trim().toLowerCase();
        if (activeSemesterValue.includes("2nd")) return "second";
        if (activeSemesterValue.includes("1st")) return "first";
        return "";
      }

      function getActiveSchoolYearRange() {
        return parseAcademicYearRange(currentSchoolYear)
          || parseAcademicYearRange(displaySchoolYear);
      }

      function getScholarStatusContext(record) {
        const explicitStatus = normalizeScholarStatus(
          record && typeof record === "object" ? (record.status || record.scholar_status || "") : ""
        );
        if (explicitStatus === "terminated") {
          return {
            statusKey: "terminated",
            nextScope: "",
            renewEnabled: false,
            reason: "Scholar already terminated."
          };
        }
        if (explicitStatus === "contract_ended" || (record && record.contract_ended === true)) {
          return {
            statusKey: "contract_ended",
            nextScope: "",
            renewEnabled: false,
            reason: "Contract already ended."
          };
        }

        const explicitRenewed = explicitStatus === "renewed";
        const range = getRecordAcademicYearRange(record);
        const activeRange = getActiveSchoolYearRange();
        const activeSemesterPhase = getActiveSemesterPhase();
        const activeYearStart = activeRange ? Number(activeRange.startYear) : 0;
        const recordYearStart = range ? Number(range.startYear) : 0;
        const isActiveYearSameAsRecord = activeYearStart > 0 && recordYearStart > 0 && activeYearStart === recordYearStart;
        const isActiveYearAfterRecord = activeYearStart > 0 && recordYearStart > 0 && activeYearStart > recordYearStart;

        const renewalScope = String(record && typeof record === "object" ? (record.renewal_scope || "") : "").trim().toLowerCase();
        const secondSemesterRenewed = (record && record.second_semester_renewed === true)
          || renewalScope === "2nd_semester"
          || renewalScope === "school_year";
        const schoolYearRenewed = renewalScope === "school_year";
        const semesterPhase = getRecordSemesterPhase(record);
        const waitingStatusKey = explicitRenewed || secondSemesterRenewed ? "renewed" : "official_scholar";

        if (explicitRenewed && (schoolYearRenewed || (renewalScope === "2nd_semester" && semesterPhase === "first"))) {
          return {
            statusKey: "renewed",
            nextScope: "",
            renewEnabled: false,
            reason: "Already renewed."
          };
        }

        if (!hasConfiguredActiveTerm) {
          return {
            statusKey: waitingStatusKey,
            nextScope: "",
            renewEnabled: false,
            reason: "Set the active school year and semester in Settings before renewing scholars."
          };
        }

        if (semesterPhase === "first" && !secondSemesterRenewed) {
          if (isActiveYearSameAsRecord && activeSemesterPhase === "second") {
            return {
              statusKey: "for_renewal",
              nextScope: "2nd_semester",
              renewEnabled: true,
              reason: ""
            };
          }

          if (isActiveYearAfterRecord) {
            const nextAcademicYearValue = getNextAcademicYearForRecord(record);
            const nextRange = parseAcademicYearRange(nextAcademicYearValue);
            const activeSchoolYearMatchesNext = Boolean(
              activeRange &&
              nextRange &&
              Number(activeRange.startYear) === Number(nextRange.startYear)
            );

            if (activeSchoolYearMatchesNext) {
              return {
                statusKey: "for_renewal",
                nextScope: "school_year",
                renewEnabled: true,
                reason: ""
              };
            }

            return {
              statusKey: waitingStatusKey,
              nextScope: "school_year",
              renewEnabled: false,
              reason: nextAcademicYearValue !== ""
                ? "Set the active school year to " + nextAcademicYearValue + " in Settings before renewing for next School Year."
                : "Set the active school year in Settings before renewing for next School Year."
            };
          }

          return {
            statusKey: waitingStatusKey,
            nextScope: "",
            renewEnabled: false,
            reason: "Set the active term to 2nd Semester for this school year before renewing for 2nd Semester."
          };
        }

        if (!schoolYearRenewed) {
          const nextAcademicYearValue = getNextAcademicYearForRecord(record);
          const nextRange = parseAcademicYearRange(nextAcademicYearValue);
          const nextSchoolYearReady = isSchoolYearAvailable(nextAcademicYearValue);
          const activeSchoolYearMatchesNext = Boolean(
            activeRange &&
            nextRange &&
            Number(activeRange.startYear) === Number(nextRange.startYear)
          );

          if (!nextSchoolYearReady) {
            return {
              statusKey: waitingStatusKey,
              nextScope: "school_year",
              renewEnabled: false,
              reason: nextAcademicYearValue !== ""
                ? "Next school year " + nextAcademicYearValue + " is not yet available in the system."
                : "Next school year is not yet available in the system."
            };
          }

          if (!activeSchoolYearMatchesNext) {
            return {
              statusKey: waitingStatusKey,
              nextScope: "school_year",
              renewEnabled: false,
              reason: nextAcademicYearValue !== ""
                ? "Set the active school year to " + nextAcademicYearValue + " in Settings before renewing for next School Year."
                : "Set the active school year in Settings before renewing for next School Year."
            };
          }

          return {
            statusKey: "for_renewal",
            nextScope: "school_year",
            renewEnabled: true,
            reason: ""
          };
        }

        return {
          statusKey: "renewed",
          nextScope: "",
          renewEnabled: false,
          reason: "Already renewed."
        };
      }

      function isHistoricalRenewedRecord(record) {
        const status = normalizeScholarStatus(
          record && typeof record === "object" ? (record.status || record.scholar_status || "") : ""
        );
        const renewalScope = String(record && typeof record === "object" ? (record.renewal_scope || "") : "").trim().toLowerCase();
        const semesterPhase = getRecordSemesterPhase(record);

        return status === "renewed" && (
          renewalScope === "school_year" ||
          (renewalScope === "2nd_semester" && semesterPhase === "first")
        );
      }

      function getPreferredRenewalScope(record, statusContext = null) {
        const context = statusContext && typeof statusContext === "object"
          ? statusContext
          : getScholarStatusContext(record);
        const recordRange = getRecordAcademicYearRange(record);
        const currentRange = parseAcademicYearRange(currentSchoolYear) || parseAcademicYearRange(displaySchoolYear);
        const isBehindCurrentSchoolYear = Boolean(
          recordRange &&
          currentRange &&
          Number(recordRange.startYear) < Number(currentRange.startYear)
        );
        if (context.statusKey === "for_renewal" && isBehindCurrentSchoolYear) {
          return "school_year";
        }

        const explicitScope = String(context.nextScope || "").trim();
        if (explicitScope === "2nd_semester" || explicitScope === "school_year") {
          return explicitScope;
        }

        const renewalScope = String(record && typeof record === "object" ? (record.renewal_scope || "") : "").trim().toLowerCase();
        const secondSemesterRenewed = (record && record.second_semester_renewed === true)
          || renewalScope === "2nd_semester"
          || renewalScope === "school_year";
        const semesterPhase = getRecordSemesterPhase(record);
        if (semesterPhase === "first" && !secondSemesterRenewed) {
          return "2nd_semester";
        }
        return "school_year";
      }

      function getStatusBadgeHtml(statusKey) {
        if (statusKey === "renewed") {
          return '<span class="inline-flex items-center rounded-full bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 text-[10px] font-semibold">Renewed</span>';
        }
        if (statusKey === "contract_ended") {
          return '<span class="inline-flex items-center rounded-full bg-slate-100 text-slate-700 border border-slate-300 px-2 py-0.5 text-[10px] font-semibold">End Contract</span>';
        }
        if (statusKey === "terminated") {
          return '<span class="inline-flex items-center rounded-full bg-red-50 text-red-700 border border-red-200 px-2 py-0.5 text-[10px] font-semibold">Terminated</span>';
        }
        if (statusKey === "for_renewal" || statusKey === "expired") {
          return '<span class="inline-flex items-center rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 text-[10px] font-semibold">For Renewal</span>';
        }
        return '<span class="inline-flex items-center rounded-full bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 text-[10px] font-semibold">Official Scholar</span>';
      }

      function getPrintableScholarRecords(category = activeCategory) {
        const printCategory = categoryConfig[category] ? category : "official";
        const seenKeys = new Set();
        return getCategoryRecords(printCategory)
          .map((record, index) => ({ ...record, __recordKey: getRecordKey(record, index) }))
          .filter((record) => {
            const recordYear = String(record.academic_year || "").trim();
            const recordSemester = String(record.semester || "").trim();
            const normalizedStatus = normalizeScholarStatus(record.status || record.scholar_status || "");
            if (printCategory !== "terminated_records" && (record.contract_ended === true || normalizedStatus === "contract_ended" || normalizedStatus === "terminated")) {
              return false;
            }
            if (selectedSchoolYear !== "" && recordYear !== selectedSchoolYear) {
              return false;
            }
            if (selectedSemester !== "" && recordSemester !== selectedSemester) {
              return false;
            }

            if (printCategory !== "terminated_records") {
              const logicalKey = getLogicalScholarMatchKey(record) || record.__recordKey;
              if (seenKeys.has(logicalKey)) {
                return false;
              }
              seenKeys.add(logicalKey);
            }
            return true;
          })
          .sort((left, right) => {
            const leftGrant = resolveGrantApplied(printCategory, left).toLowerCase();
            const rightGrant = resolveGrantApplied(printCategory, right).toLowerCase();
            if (leftGrant !== rightGrant) return leftGrant.localeCompare(rightGrant);
            const leftName = String(left.full_name || "").trim().toLowerCase();
            const rightName = String(right.full_name || "").trim().toLowerCase();
            return leftName.localeCompare(rightName);
          });
      }

      function getPrintFilterLabel() {
        const schoolYearLabel = selectedSchoolYear !== "" ? selectedSchoolYear : "All School Years";
        const semesterLabel = selectedSemester !== "" ? selectedSemester : "All Semesters";
        return semesterLabel + ", S.Y. " + schoolYearLabel;
      }

      function getPrintCategoryTitle(category) {
        if (category === "official") return "Institutional Scholars List";
        if (category === "terminated_records") return "Terminated / Ended Contracts";
        const label = categoryConfig[category] && categoryConfig[category].label
          ? String(categoryConfig[category].label).trim()
          : "Institutional Scholars";
        return label + " Scholars";
      }

      function buildPrintableScholarTable(records, category = activeCategory, startIndex = 0) {
        if (!Array.isArray(records) || records.length === 0) {
          return '<p class="empty">No scholar records found for the selected filters.</p>';
        }

        const rows = records.map((record, index) => {
          const grantApplied = category === "terminated_records"
            ? String(record.grant_applied || "").trim()
            : resolveGrantApplied(category, record);
          const assignedOffice = String(record.assigned_office || "").trim();
          return "<tr>" +
            "<td>" + (startIndex + index + 1) + "</td>" +
            "<td>" + escapeHtml(grantApplied) + "</td>" +
            "<td>" + escapeHtml(record.full_name) + "</td>" +
            "<td>" + escapeHtml(record.program_year) + "</td>" +
            "<td>" + escapeHtml(assignedOffice !== "" ? assignedOffice : "-") + "</td>" +
            "<td>" + escapeHtml(record.semester) + "</td>" +
            "<td>" + escapeHtml(record.academic_year) + "</td>" +
          "</tr>";
        }).join("");

        return '<table class="scholar-print-table">' +
          '<thead>' +
            '<tr>' +
              '<th>No.</th>' +
              '<th>Scholarship Grant</th>' +
              '<th>Full Name</th>' +
              '<th>Program / Year</th>' +
              '<th>Assigned Office</th>' +
              '<th>Semester</th>' +
              '<th>Academic Year</th>' +
            '</tr>' +
          '</thead>' +
          '<tbody>' + rows + '</tbody>' +
        '</table>';
      }

      function buildScholarPrintDocument(title, bodyHtml, totalCount) {
        const smccLogo = new URL("../img/SMCCNEWLOGO.png", window.location.href).href;
        const socotecLogo = new URL("../img/SOCO-PAB-1024x672.jpg", window.location.href).href;
        return '<!doctype html>' +
          '<html lang="en">' +
          '<head>' +
            '<meta charset="utf-8" />' +
            '<title>' + escapeHtml(title) + '</title>' +
            '<style>' +
              '@page { size: Legal landscape; margin: 10mm; }' +
              '* { box-sizing: border-box; }' +
              'body { margin: 0; font-family: "Times New Roman", serif; color: #111827; background: #fff; }' +
              '.print-wrap { width: 100%; }' +
              '.report-header { margin-bottom: 1rem; text-align: center; }' +
              '.header-top { display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem; }' +
              '.header-left { display: flex; align-items: center; gap: 0.5rem; }' +
              '.header-left img { width: 80px; height: 80px; object-fit: contain; }' +
              '.header-left-text { line-height: 1.1; text-align: center; }' +
              '.header-left-text h1 { font-weight: 700; font-size: 16pt; margin: 0; }' +
              '.header-left-text p { margin: 0; font-size: 10pt; }' +
              '.header-right { display: flex; flex-direction: column; gap: 0.2rem; align-items: center; }' +
              '.header-right img { width: 100px; height: 80px; object-fit: contain; }' +
              '.title-block { border-top: 1px solid #111827; padding-top: 0.45rem; margin-bottom: 0.75rem; text-align: center; }' +
              '.title-block h2 { margin: 0; font-size: 14pt; text-transform: uppercase; }' +
              '.title-block p { margin: 0.15rem 0 0; font-size: 10pt; font-weight: 700; }' +
              '.total { margin: 0 0 0.45rem; font-size: 10pt; font-weight: 700; }' +
              '.scholar-print-table { width: 100%; border-collapse: collapse; font-size: 9pt; }' +
              '.scholar-print-table th, .scholar-print-table td { border: 1px solid #111827; padding: 4px 5px; vertical-align: top; }' +
              '.scholar-print-table th { background: #052c6a; color: #fff; font-weight: 700; text-align: center; -webkit-print-color-adjust: exact; print-color-adjust: exact; }' +
              '.scholar-print-table td:nth-child(1) { width: 36px; text-align: center; }' +
              '.scholar-print-table td:nth-child(2) { width: 20%; }' +
              '.scholar-print-table td:nth-child(5) { width: 16%; }' +
              '.empty { margin: 1.5rem 0; text-align: center; font-size: 11pt; font-style: italic; }' +
            '</style>' +
          '</head>' +
          '<body>' +
            '<div class="print-wrap">' +
              '<header class="report-header">' +
                '<div class="header-top">' +
                  '<div class="header-left">' +
                    '<img src="' + escapeHtml(smccLogo) + '" alt="Seal of Saint Michael College of Caraga" />' +
                    '<div class="header-left-text">' +
                      '<h1>Saint Michael College of Caraga</h1>' +
                      '<p>Atupan St., Brgy. 4, Nasipit, Agusan del Norte 8602, Philippines</p>' +
                      '<p>Website: www.smccnasipit.edu.ph ; Tel. Nos. 085 300-2932</p>' +
                    '</div>' +
                  '</div>' +
                  '<div class="header-right">' +
                    '<img src="' + escapeHtml(socotecLogo) + '" alt="SOCOTEC ISO 9001 logo" />' +
                  '</div>' +
                '</div>' +
              '</header>' +
              '<section class="title-block">' +
                '<h2>' + escapeHtml(title) + '</h2>' +
                '<p>' + escapeHtml(getPrintFilterLabel()) + '</p>' +
              '</section>' +
              '<p class="total">Total Scholars: ' + escapeHtml(totalCount) + '</p>' +
              bodyHtml +
            '</div>' +
            '<script>window.addEventListener("load", function () { window.focus(); window.print(); });<\/script>' +
          '</body>' +
          '</html>';
      }

      function openScholarPrintWindow(title, bodyHtml, totalCount) {
        const printWindow = window.open("", "_blank", "width=1200,height=800");
        if (!printWindow) {
          window.alert("Please allow pop-ups to print the scholar list.");
          return;
        }

        printWindow.document.open();
        printWindow.document.write(buildScholarPrintDocument(title, bodyHtml, totalCount));
        printWindow.document.close();
      }

      function printScholarList() {
        const printCategory = categoryConfig[activeCategory] ? activeCategory : "official";
        const records = getPrintableScholarRecords(printCategory);
        openScholarPrintWindow(
          getPrintCategoryTitle(printCategory),
          buildPrintableScholarTable(records, printCategory),
          records.length
        );
      }

      function setupScholarPrinting() {
        const printButton = document.getElementById("printScholarsBtn");

        if (printButton) {
          printButton.addEventListener("click", () => {
            printScholarList();
          });
        }
      }

      function submitScholarAction(action, record, renewalScope = "", newAssignedOffice = "", terminationReason = "", newFullName = "", newProgramYear = "", newEmail = "") {
        if (!record || typeof record !== "object") return;

        const url = new URL(window.location.href);
        url.searchParams.set("scholar_action", action);
        url.searchParams.delete("scholar_notice");
        url.searchParams.delete("scholar_notice_message");

        const scholarRecordId = Number(record.id || 0);
        if (scholarRecordId > 0) {
          url.searchParams.set("id", String(scholarRecordId));
          url.searchParams.delete("scholar_record_id");
        } else {
          url.searchParams.delete("id");
          url.searchParams.delete("scholar_record_id");
        }
        url.searchParams.delete("scholar_id");

        const normalizedScope = String(renewalScope || "").trim();
        if (normalizedScope !== "") {
          url.searchParams.set("renewal_scope", normalizedScope);
        } else {
          url.searchParams.delete("renewal_scope");
        }

        const normalizedAssignedOffice = String(newAssignedOffice || "").trim();
        if (action === "change_office" && normalizedAssignedOffice !== "") {
          url.searchParams.set("new_assigned_office", normalizedAssignedOffice);
        } else {
          url.searchParams.delete("new_assigned_office");
        }

        if (action === "edit_scholar") {
          url.searchParams.set("new_full_name", String(newFullName || "").trim());
          url.searchParams.set("new_program_year", String(newProgramYear || "").trim());
          url.searchParams.set("new_email", String(newEmail || "").trim());
          if (normalizedAssignedOffice !== "") {
            url.searchParams.set("new_assigned_office", normalizedAssignedOffice);
          }
        } else {
          url.searchParams.delete("new_full_name");
          url.searchParams.delete("new_program_year");
          url.searchParams.delete("new_email");
        }

        const normalizedTerminationReason = String(terminationReason || "").trim();
        if (action === "terminate" && normalizedTerminationReason !== "") {
          url.searchParams.set("termination_reason", normalizedTerminationReason);
        } else {
          url.searchParams.delete("termination_reason");
        }

        url.searchParams.set("active_category", activeCategory);
        window.location.href = url.toString();
      }

      function shouldShowAssignedOfficeColumn(category) {
        return category === "student_assistant" || category === "official" || category === "terminated_records";
      }

      function renderRenewalTermNotice() {
        const notice = document.getElementById("renewalTermNotice");
        if (!notice) return;
        notice.classList.remove("hidden");
        notice.textContent = "Status is automatic: scholars are tagged For Renewal only when their renewal target is the active term in Settings.";
      }

      function getRecordByKey(category, recordKey) {
        const records = getCategoryRecords(category);
        for (let i = 0; i < records.length; i += 1) {
          if (getRecordKey(records[i], i) === recordKey) {
            return records[i];
          }
        }
        return null;
      }

      function getScholarRecordId(record) {
        const recordId = Number(record && typeof record === "object" ? (record.id || record.scholar_record_id || 0) : 0);
        return Number.isFinite(recordId) && recordId > 0 ? recordId : 0;
      }

      function closeScholarMessageModal() {
        const modal = document.getElementById("scholarMessageModal");
        const messageBody = document.getElementById("scholarMessageBody");
        const submitButton = document.getElementById("scholarMessageSubmit");
        if (modal) {
          modal.classList.add("hidden");
          modal.classList.remove("flex");
          document.body.classList.remove("overflow-hidden");
        }
        if (messageBody) {
          messageBody.value = "";
        }
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.classList.remove("opacity-60", "cursor-not-allowed");
        }
      }

      function showScholarMessageModal(category, recordKey) {
        const record = getRecordByKey(category, recordKey);
        if (!record) return;

        const scholarRecordId = getScholarRecordId(record);
        const recipientEmail = String(record.email || "").trim();
        const recipientName = String(record.full_name || "").trim();
        if (scholarRecordId <= 0 || !isValidEmailAddress(recipientEmail)) {
          const message = "No valid email address found for this scholar. Click Edit first to add one.";
          if (typeof Swal !== "undefined") {
            Swal.fire({
              title: "Message Unavailable",
              text: message,
              icon: "info",
              confirmButtonColor: "#0d8ddb"
            });
          } else {
            window.alert(message);
          }
          return;
        }

        const modal = document.getElementById("scholarMessageModal");
        const recordIdInput = document.getElementById("scholarMessageRecordId");
        const nameInput = document.getElementById("scholarMessageRecipientName");
        const emailInput = document.getElementById("scholarMessageRecipientEmail");
        const returnSchoolYearInput = document.getElementById("scholarMessageReturnSchoolYear");
        const returnSemesterInput = document.getElementById("scholarMessageReturnSemester");
        const returnCategoryInput = document.getElementById("scholarMessageReturnCategory");
        const messageBody = document.getElementById("scholarMessageBody");

        if (recordIdInput) recordIdInput.value = String(scholarRecordId);
        if (nameInput) nameInput.value = recipientName;
        if (emailInput) emailInput.value = recipientEmail;
        if (returnSchoolYearInput) returnSchoolYearInput.value = String(scholarMessageReturnSchoolYear || "").trim();
        if (returnSemesterInput) returnSemesterInput.value = String(scholarMessageReturnSemester || "").trim();
        if (returnCategoryInput) returnCategoryInput.value = categoryConfig[activeCategory] && activeCategory !== "terminated_records"
          ? activeCategory
          : "official";
        if (messageBody) {
          messageBody.value = "";
        }

        if (modal) {
          modal.classList.remove("hidden");
          modal.classList.add("flex");
          document.body.classList.add("overflow-hidden");
        }
        if (messageBody) {
          messageBody.focus();
        }
      }

      function setupScholarMessageModal() {
        const modal = document.getElementById("scholarMessageModal");
        const form = document.getElementById("scholarMessageForm");
        const closeButton = document.getElementById("scholarMessageClose");
        const cancelButton = document.getElementById("scholarMessageCancel");
        const messageBody = document.getElementById("scholarMessageBody");
        const submitButton = document.getElementById("scholarMessageSubmit");

        [closeButton, cancelButton].forEach((button) => {
          if (button) {
            button.addEventListener("click", closeScholarMessageModal);
          }
        });
        if (modal) {
          modal.addEventListener("click", (event) => {
            if (event.target === modal) {
              closeScholarMessageModal();
            }
          });
        }
        document.addEventListener("keydown", (event) => {
          if (event.key === "Escape" && modal && !modal.classList.contains("hidden")) {
            closeScholarMessageModal();
          }
        });
        if (form) {
          form.addEventListener("submit", (event) => {
            if (messageBody && String(messageBody.value || "").trim() === "") {
              event.preventDefault();
              messageBody.focus();
              return;
            }
            if (submitButton) {
              submitButton.disabled = true;
              submitButton.classList.add("opacity-60", "cursor-not-allowed");
            }
          });
        }
      }

      function showRenewOptions(category, recordKey, forcedScope = "") {
        const record = getRecordByKey(category, recordKey);
        if (!record) return;

        const statusContext = getScholarStatusContext(record);
        if (statusContext.statusKey === "contract_ended") {
          const message = "Contract already ended.";
          if (typeof Swal !== "undefined") {
            Swal.fire({
              title: "Action Disabled",
              text: message,
              icon: "info",
              confirmButtonColor: "#0d8ddb"
            });
          } else {
            window.alert(message);
          }
          return;
        }

        if (statusContext.renewEnabled !== true) {
          const message = String(statusContext.reason || "Renewal is not available for this scholar right now.");
          if (typeof Swal !== "undefined") {
            Swal.fire({
              title: "Action Disabled",
              text: message,
              icon: "info",
              confirmButtonColor: "#0d8ddb"
            });
          } else {
            window.alert(message);
          }
          return;
        }

        const renewalScope = String(forcedScope || getPreferredRenewalScope(record, statusContext)).trim();
        if (renewalScope !== "2nd_semester" && renewalScope !== "school_year") {
          const message = "Unable to determine renewal scope for this scholar.";
          if (typeof Swal !== "undefined") {
            Swal.fire({
              title: "Action Disabled",
              text: message,
              icon: "info",
              confirmButtonColor: "#0d8ddb"
            });
          } else {
            window.alert(message);
          }
          return;
        }

        const targetLabel = renewalScope === "2nd_semester" ? "2nd Semester" : "Next School Year";
        if (typeof Swal === "undefined") {
          const shouldRenew = window.confirm("Renew scholar for " + targetLabel + "?");
          if (shouldRenew) {
            submitScholarAction("renew", record, renewalScope);
          }
          return;
        }

        Swal.fire({
          title: "Renew Scholar",
          text: "Renew this scholar for " + targetLabel + "?",
          icon: "question",
          showCancelButton: true,
          confirmButtonText: "Yes, renew",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#16a34a"
        }).then((result) => {
          if (!result.isConfirmed) return;
          submitScholarAction("renew", record, renewalScope);
        });
      }

      function showEndContractConfirm(category, recordKey) {
        const record = getRecordByKey(category, recordKey);
        if (!record) return;

        if (typeof Swal === "undefined") {
          const shouldContinue = window.confirm("Mark this scholar as Contract Ended?");
          if (shouldContinue) {
            submitScholarAction("end_contract", record);
          }
          return;
        }

        Swal.fire({
          title: "End Contract?",
          text: "This scholar will be tagged as Contract Ended.",
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Yes, end contract",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#334155"
        }).then((result) => {
          if (!result.isConfirmed) return;
          submitScholarAction("end_contract", record);
        });
      }

      function showTerminatePrompt(category, recordKey) {
        const record = getRecordByKey(category, recordKey);
        if (!record) return;

        if (typeof Swal === "undefined") {
          const reason = window.prompt("Reason for termination:");
          if (reason === null) return;
          const normalizedReason = String(reason || "").trim();
          if (normalizedReason === "") {
            window.alert("Termination reason is required.");
            return;
          }
          submitScholarAction("terminate", record, "", "", normalizedReason);
          return;
        }

        Swal.fire({
          title: "Terminate Scholar",
          text: "Enter the reason for termination.",
          input: "textarea",
          inputPlaceholder: "Reason for termination",
          inputAttributes: {
            "aria-label": "Reason for termination"
          },
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Terminate",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#dc2626",
          inputValidator: (value) => {
            if (String(value || "").trim() === "") {
              return "Termination reason is required.";
            }
            return undefined;
          }
        }).then((result) => {
          if (!result.isConfirmed) return;
          submitScholarAction("terminate", record, "", "", String(result.value || "").trim());
        });
      }

      function showEditScholarPrompt(category, recordKey) {
        const record = getRecordByKey(category, recordKey);
        if (!record) return;

        const currentFullName = String(record.full_name || "").trim();
        const currentProgramYear = String(record.program_year || "").trim();
        const currentEmail = String(record.email || "").trim();
        const grantApplied = resolveGrantApplied(category, record);
        const isStudentAssistantGrant = String(grantApplied || "").trim().toLowerCase().includes("assistant");
        const currentOffice = String(record.assigned_office || "").trim();
        const officeChoices = getAssignedOfficeChoices(currentOffice);
        if (typeof Swal === "undefined") {
          const nextFullNameRaw = window.prompt("Full name:", currentFullName);
          if (nextFullNameRaw === null) return;
          const nextFullName = String(nextFullNameRaw || "").trim();
          if (nextFullName === "") {
            window.alert("Full name is required.");
            return;
          }
          const nextProgramYearRaw = window.prompt("Program / Year:", currentProgramYear);
          if (nextProgramYearRaw === null) return;
          const nextEmailRaw = window.prompt("Email:", currentEmail);
          if (nextEmailRaw === null) return;
          const nextEmail = String(nextEmailRaw || "").trim();
          if (!isValidEmailAddress(nextEmail)) {
            window.alert("Valid email address is required.");
            return;
          }
          let nextOffice = "";
          if (isStudentAssistantGrant) {
            const nextOfficeRaw = window.prompt("Assigned office:", currentOffice);
            if (nextOfficeRaw === null) return;
            nextOffice = String(nextOfficeRaw || "").trim();
          }
          submitScholarAction("edit_scholar", record, "", nextOffice, "", nextFullName, String(nextProgramYearRaw || "").trim(), nextEmail);
          return;
        }

        const hasCurrentOffice = officeChoices.some((office) => office.toLowerCase() === currentOffice.toLowerCase());
        const selectedOfficeValue = hasCurrentOffice ? currentOffice : officeChoices[0];
        const officeFieldHtml = isStudentAssistantGrant && officeChoices.length > 0
          ? '<label class="block text-left text-xs font-semibold text-slate-700" for="editScholarOffice">Assigned Office</label>' +
            '<select id="editScholarOffice" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">' +
              officeChoices.map((office) => (
                '<option value="' + escapeHtml(office) + '"' + (office === selectedOfficeValue ? " selected" : "") + '>' + escapeHtml(office) + '</option>'
              )).join("") +
            '</select>'
          : "";

        Swal.fire({
          title: "Edit Scholar",
          html:
            '<div class="space-y-3 text-left">' +
              '<div>' +
                '<label class="block text-left text-xs font-semibold text-slate-700" for="editScholarFullName">Full Name</label>' +
                '<input id="editScholarFullName" type="text" value="' + escapeHtml(currentFullName) + '" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700" />' +
              '</div>' +
              '<div>' +
                '<label class="block text-left text-xs font-semibold text-slate-700" for="editScholarProgramYear">Program / Year</label>' +
                '<input id="editScholarProgramYear" type="text" value="' + escapeHtml(currentProgramYear) + '" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700" />' +
              '</div>' +
              '<div>' +
                '<label class="block text-left text-xs font-semibold text-slate-700" for="editScholarEmail">Email</label>' +
                '<input id="editScholarEmail" type="email" value="' + escapeHtml(currentEmail) + '" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700" />' +
              '</div>' +
              (officeFieldHtml !== "" ? '<div>' + officeFieldHtml + '</div>' : "") +
            '</div>',
          focusConfirm: false,
          showCancelButton: true,
          confirmButtonText: "Save",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#0d8ddb",
          preConfirm: () => {
            const fullNameInput = document.getElementById("editScholarFullName");
            const programYearInput = document.getElementById("editScholarProgramYear");
            const emailInput = document.getElementById("editScholarEmail");
            const officeInput = document.getElementById("editScholarOffice");
            const nextFullName = String(fullNameInput ? fullNameInput.value : "").trim();
            const nextProgramYear = String(programYearInput ? programYearInput.value : "").trim();
            const nextEmail = String(emailInput ? emailInput.value : "").trim();
            const nextOffice = String(officeInput ? officeInput.value : currentOffice).trim();

            if (nextFullName === "") {
              Swal.showValidationMessage("Full name is required.");
              return false;
            }
            if (!isValidEmailAddress(nextEmail)) {
              Swal.showValidationMessage("Valid email address is required.");
              return false;
            }
            if (isStudentAssistantGrant && officeChoices.length > 0 && nextOffice === "") {
              Swal.showValidationMessage("Assigned office is required.");
              return false;
            }
            return {
              fullName: nextFullName,
              programYear: nextProgramYear,
              email: nextEmail,
              assignedOffice: isStudentAssistantGrant ? nextOffice : ""
            };
          }
        }).then((result) => {
          if (!result.isConfirmed) return;
          const nextValues = result.value && typeof result.value === "object" ? result.value : {};
          submitScholarAction(
            "edit_scholar",
            record,
            "",
            String(nextValues.assignedOffice || "").trim(),
            "",
            String(nextValues.fullName || "").trim(),
            String(nextValues.programYear || "").trim(),
            String(nextValues.email || "").trim()
          );
        });
      }

      function showAssignedOfficeHistory(category, recordKey) {
        const record = getRecordByKey(category, recordKey);
        if (!record) return;

        const officeTimeline = getAssignedOfficeTimelineForRecord(record);
        if (typeof Swal === "undefined") {
          if (officeTimeline.length === 0) {
            window.alert("No assigned office history yet.");
            return;
          }
          window.alert(officeTimeline.map((item) => {
            const office = String(item.office || "").trim() || "Unassigned";
            const startedAt = formatHistoryTimestamp(item.started_at) || "Date not recorded";
            const endedAt = formatHistoryTimestamp(item.ended_at);
            const movedTo = String(item.moved_to || "").trim();
            const changedBy = String(item.changed_by || "").trim();
            if (item.is_current) {
              return office + " - started " + startedAt + " (current)";
            }
            return office + " - started " + startedAt + (endedAt ? ", moved on " + endedAt : "") + (movedTo ? " to " + movedTo : "") + (changedBy ? " by " + changedBy : "");
          }).join("\n"));
          return;
        }

        const titleName = String(record.full_name || "Student Assistant").trim();
        const html = officeTimeline.length === 0
          ? '<div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm text-slate-600">No assigned office history yet.</div>'
          : '<div class="max-h-[420px] overflow-y-auto text-left">' +
              officeTimeline.map((item) => {
                const office = escapeHtml(String(item.office || "").trim() || "Unassigned");
                const startedAt = escapeHtml(formatHistoryTimestamp(item.started_at) || "Date not recorded");
                const endedAt = escapeHtml(formatHistoryTimestamp(item.ended_at));
                const movedTo = escapeHtml(String(item.moved_to || "").trim());
                const changedBy = escapeHtml(String(item.changed_by || "").trim());
                const isCurrent = item.is_current === true;
                return '<div class="mb-3 rounded-lg border border-[#dbe7ff] bg-[#f8fbff] px-4 py-3">' +
                  '<div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">' +
                    '<div>' +
                      '<p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Assigned Office</p>' +
                      '<p class="text-sm font-semibold text-[#052c6a]">' + office + '</p>' +
                    '</div>' +
                    (isCurrent ? '<span class="inline-flex rounded-full bg-green-50 px-2 py-1 text-[10px] font-semibold text-green-700">Current</span>' : '') +
                  '</div>' +
                  '<div class="mt-3 grid gap-2 text-[11px] text-slate-600 sm:grid-cols-2">' +
                    '<p><span class="font-semibold text-slate-700">Started:</span> ' + startedAt + '</p>' +
                    (isCurrent
                      ? '<p><span class="font-semibold text-slate-700">Status:</span> Current office</p>'
                      : '<p><span class="font-semibold text-slate-700">Moved:</span> ' + (endedAt !== "" ? endedAt : "Date not recorded") + (movedTo !== "" ? ' to ' + movedTo : '') + '</p>') +
                  '</div>' +
                  (!isCurrent && changedBy ? '<p class="mt-2 text-[11px] text-slate-500">Updated by ' + changedBy + '</p>' : '') +
                '</div>';
              }).join("") +
            '</div>';

        Swal.fire({
          title: "Assigned Office History",
          html: '<p class="mb-3 text-sm font-semibold text-[#052c6a]">' + escapeHtml(titleName) + '</p>' + html,
          width: 720,
          confirmButtonColor: "#0d8ddb"
        });
      }

      function renderTable(category) {
        const config = categoryConfig[category];
        const tableBody = document.getElementById("scholarRows");
        const assignedOfficeHeader = document.getElementById("assignedOfficeHeader");
        const actionReasonHeader = document.getElementById("actionReasonHeader");
        const scholarSearchWrap = document.getElementById("scholarSearchWrap");
        const terminatedSearchWrap = document.getElementById("terminatedSearchWrap");
        const terminatedTableTitle = document.getElementById("terminatedTableTitle");
        const showAssignedOffice = shouldShowAssignedOfficeColumn(category);
        const columnCount = showAssignedOffice ? 9 : 8;
        if (assignedOfficeHeader) {
          assignedOfficeHeader.classList.toggle("hidden", !showAssignedOffice);
        }
        if (actionReasonHeader) {
          actionReasonHeader.textContent = category === "terminated_records" ? "Reason" : "Action";
        }
        if (scholarSearchWrap) {
          scholarSearchWrap.classList.toggle("hidden", category === "terminated_records");
        }
        if (terminatedSearchWrap) {
          terminatedSearchWrap.classList.toggle("hidden", category !== "terminated_records");
        }
        if (terminatedTableTitle) {
          terminatedTableTitle.classList.toggle("hidden", category !== "terminated_records");
        }
        const records = getCategoryRecords(category);
        const filteredRecords = records
          .map((record, index) => ({ ...record, __recordKey: getRecordKey(record, index) }))
          .filter((record) => {
          const recordYear = String(record.academic_year || "").trim();
          const recordSemester = String(record.semester || "").trim();
          const normalizedStatus = normalizeScholarStatus(record.status || record.scholar_status || "");
          if (category !== "terminated_records" && (record.contract_ended === true || normalizedStatus === "contract_ended" || normalizedStatus === "terminated")) {
            return false;
          }
          if (category !== "terminated_records" && scholarSearchTerm !== "") {
            const haystack = [
              record.scholar_id,
              record.grant_applied,
              record.full_name,
              record.email,
              record.program_year,
              record.assigned_office,
              record.semester,
              record.academic_year,
              record.status,
              resolveGrantApplied(category, record)
            ].map((value) => String(value || "").toLowerCase()).join(" ");
            if (!haystack.includes(scholarSearchTerm)) {
              return false;
            }
          }
          if (category === "terminated_records" && terminatedSearchTerm !== "") {
            const haystack = [
              record.scholar_id,
              record.grant_applied,
              record.full_name,
              record.email,
              record.program_year,
              record.assigned_office,
              record.semester,
              record.academic_year,
              record.action_type,
              record.reason,
              record.created_by,
              record.created_at
            ].map((value) => String(value || "").toLowerCase()).join(" ");
            if (!haystack.includes(terminatedSearchTerm)) {
              return false;
            }
          }
          const matchesYear = selectedSchoolYear === "" || recordYear === selectedSchoolYear;
          const matchesSemester = selectedSemester === "" || recordSemester === selectedSemester;
          return matchesYear && matchesSemester;
        });

        document.getElementById("activeCategoryLabel").textContent = config.label;
        tableBody.innerHTML = "";

        if (filteredRecords.length === 0) {
          const filterSuffix =
            selectedSchoolYear !== "" || selectedSemester !== ""
              ? " for the selected School Year/Semester."
              : ".";
          tableBody.innerHTML =
            '<tr><td colspan="' + columnCount + '" class="px-3 py-8 text-center text-gray-500 italic">No records yet for ' +
            escapeHtml(config.label) +
            escapeHtml(filterSuffix) +
            "</td></tr>";
          return;
        }

        if (category === "terminated_records") {
          filteredRecords.forEach((record, index) => {
            const actionType = String(record.action_type || "").trim().toLowerCase();
            const statusKey = actionType === "terminated" ? "terminated" : "contract_ended";
            const createdMeta = String(record.created_at || "").trim();
            const createdBy = String(record.created_by || "").trim();
            const reason = String(record.reason || "").trim();
            const row = document.createElement("tr");
            row.innerHTML =
              '<td class="px-3 py-2">' + (index + 1) + "</td>" +
              '<td class="px-3 py-2">' + escapeHtml(record.grant_applied) + "</td>" +
              '<td class="px-3 py-2">' + escapeHtml(record.full_name) + "</td>" +
              '<td class="px-3 py-2">' + escapeHtml(record.program_year) + "</td>" +
              '<td class="px-3 py-2">' + escapeHtml(String(record.assigned_office || "").trim() !== "" ? record.assigned_office : "-") + "</td>" +
              '<td class="px-3 py-2">' + escapeHtml(record.semester) + "</td>" +
              '<td class="px-3 py-2">' + escapeHtml(record.academic_year) + "</td>" +
              '<td class="px-3 py-2">' + getStatusBadgeHtml(statusKey) + "</td>" +
              '<td class="px-3 py-2 min-w-[220px]">' +
                '<div class="space-y-1">' +
                  '<p class="font-semibold text-slate-700">' + escapeHtml(reason !== "" ? reason : "No reason provided.") + "</p>" +
                  '<p class="text-[10px] text-slate-500">' + escapeHtml(createdMeta !== "" ? createdMeta : "") + (createdBy !== "" ? " by " + escapeHtml(createdBy) : "") + "</p>" +
                "</div>" +
              "</td>";
            tableBody.appendChild(row);
          });
          return;
        }

        filteredRecords.forEach((record, index) => {
          const grantApplied = resolveGrantApplied(category, record);
          const normalizedGrantApplied = String(grantApplied || "").trim().toLowerCase();
          const isStudentAssistantGrant = normalizedGrantApplied.includes("assistant");
          const assignedOfficeRaw = String(record.assigned_office || "").trim();
          const assignedOfficeCell = showAssignedOffice
            ? ('<td class="px-3 py-2">' + escapeHtml(assignedOfficeRaw !== "" ? assignedOfficeRaw : "-") + "</td>")
            : "";
          const statusContext = getScholarStatusContext(record);
          const statusKey = statusContext.statusKey;
          const isContractEnded = statusKey === "contract_ended";
          const actionsLocked = isContractEnded || isHistoricalRenewedRecord(record);
          const renewTargetScope = getPreferredRenewalScope(record, statusContext);
          const renewLabel = renewTargetScope === "2nd_semester"
            ? "Renew to 2nd Sem"
            : (renewTargetScope === "school_year" ? "Renew Next SY" : "Renew");
          const renewBtnClasses =
            statusKey === "renewed"
              ? "bg-green-600 text-white border-green-600"
              : "bg-white text-green-700 border-green-300 hover:bg-green-50";
          const endContractBtnClasses =
            isContractEnded
              ? "bg-slate-700 text-white border-slate-700"
              : "bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700";
          const renewDisabled = actionsLocked || statusContext.renewEnabled !== true;
          const renewalDisabledClasses = renewDisabled ? "opacity-50 cursor-not-allowed hover:bg-transparent" : "";
          const renewalDisabledTitle = isContractEnded
            ? "Contract already ended."
            : String(statusContext.reason || "Renewal is not available.");
          const renewalDisabledAttrs = renewDisabled
            ? ' disabled title="' + escapeHtml(renewalDisabledTitle) + '" aria-disabled="true" '
            : "";
          const lockedActionTitle = isContractEnded
            ? "Contract already ended."
            : "This previous term is already renewed.";
          const endContractDisabledAttrs = actionsLocked
            ? ' disabled title="' + escapeHtml(lockedActionTitle) + '" aria-disabled="true" '
            : "";
          const endContractDisabledClasses = actionsLocked ? "opacity-60 cursor-not-allowed" : "";
          const terminateDisabledAttrs = actionsLocked
            ? ' disabled title="' + escapeHtml(lockedActionTitle) + '" aria-disabled="true" '
            : "";
          const terminateDisabledClasses = actionsLocked ? "opacity-60 cursor-not-allowed" : "";
          const canMessageScholar = getScholarRecordId(record) > 0 && isValidEmailAddress(record.email);
          const messageScholarDisabledAttrs = canMessageScholar
            ? ' title="Send message" aria-label="Send message to ' + escapeHtml(record.full_name || "scholar") + '" '
            : ' disabled title="No email address for this scholar." aria-disabled="true" aria-label="Message unavailable" ';
          const messageScholarDisabledClasses = canMessageScholar ? "" : "opacity-50 cursor-not-allowed";
          const messageScholarBtnHtml =
            '<button type="button" data-status-action="message_scholar" data-record-key="' + escapeHtml(record.__recordKey) + '" class="inline-flex h-7 w-7 items-center justify-center rounded border text-[11px] font-semibold transition-colors bg-white text-[#052c6a] border-[#b7cbe8] hover:bg-[#eff6ff] ' + messageScholarDisabledClasses + '"' + messageScholarDisabledAttrs + '>' +
              '<i class="fas fa-envelope" aria-hidden="true"></i>' +
            '</button>';
          const editScholarBtnHtml = !actionsLocked
            ? '<button type="button" data-status-action="edit_scholar" data-record-key="' + escapeHtml(record.__recordKey) + '" class="px-2 py-1 rounded border text-[10px] font-semibold transition-colors bg-white text-[#0d8ddb] border-[#7cc5ee] hover:bg-[#ebf7ff]">Edit</button>'
            : "";
          const officeHistoryBtnHtml = (isStudentAssistantGrant && !actionsLocked)
            ? '<button type="button" data-status-action="office_history" data-record-key="' + escapeHtml(record.__recordKey) + '" class="px-2 py-1 rounded border text-[10px] font-semibold transition-colors bg-white text-slate-700 border-slate-300 hover:bg-slate-50">History</button>'
            : "";

          const row = document.createElement("tr");
          row.innerHTML =
            '<td class="px-3 py-2">' + (index + 1) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(grantApplied) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.full_name) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.program_year) + "</td>" +
            assignedOfficeCell +
            '<td class="px-3 py-2">' + escapeHtml(record.semester) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.academic_year) + "</td>" +
            '<td class="px-3 py-2">' + getStatusBadgeHtml(statusKey) + "</td>" +
            '<td class="px-3 py-2">' +
              '<div class="flex flex-wrap gap-1 min-w-[200px]">' +
                '<button type="button" data-status-action="renew" data-next-scope="' + escapeHtml(renewTargetScope) + '" data-record-key="' + escapeHtml(record.__recordKey) + '" class="px-2 py-1 rounded border text-[10px] font-semibold transition-colors ' + renewBtnClasses + ' ' + renewalDisabledClasses + '"' + renewalDisabledAttrs + '>' + escapeHtml(renewLabel) + '</button>' +
                '<button type="button" data-status-action="end_contract" data-record-key="' + escapeHtml(record.__recordKey) + '" class="px-2 py-1 rounded border text-[10px] font-semibold transition-colors ' + endContractBtnClasses + ' ' + endContractDisabledClasses + '"' + endContractDisabledAttrs + '>End Contract</button>' +
                '<button type="button" data-status-action="terminate" data-record-key="' + escapeHtml(record.__recordKey) + '" class="px-2 py-1 rounded border text-[10px] bg-red-50 text-red-700 border-red-200 font-semibold transition-colors hover:bg-red-100 ' + terminateDisabledClasses + '"' + terminateDisabledAttrs + '>Terminate</button>' +
                editScholarBtnHtml +
                officeHistoryBtnHtml +
                messageScholarBtnHtml +
              "</div>" +
            "</td>";
          tableBody.appendChild(row);
        });
      }

      function setupRenewalActions() {
        const tableBody = document.getElementById("scholarRows");
        if (!tableBody) return;

        tableBody.addEventListener("click", (event) => {
          const target = event.target instanceof Element ? event.target : null;
          if (!target) return;

          const button = target.closest("[data-status-action]");
          if (!button) return;
          if (button.disabled) return;

          const recordKey = String(button.getAttribute("data-record-key") || "").trim();
          const action = String(button.getAttribute("data-status-action") || "").trim();
          if (recordKey === "" || (action !== "message_scholar" && action !== "renew" && action !== "end_contract" && action !== "terminate" && action !== "change_office" && action !== "edit_scholar" && action !== "office_history")) return;

          if (action === "message_scholar") {
            showScholarMessageModal(activeCategory, recordKey);
            return;
          }

          if (action === "renew") {
            const nextScope = String(button.getAttribute("data-next-scope") || "").trim();
            showRenewOptions(activeCategory, recordKey, nextScope);
            return;
          }

          if (action === "end_contract") {
            showEndContractConfirm(activeCategory, recordKey);
            return;
          }

          if (action === "terminate") {
            showTerminatePrompt(activeCategory, recordKey);
            return;
          }

          if (action === "change_office" || action === "edit_scholar") {
            showEditScholarPrompt(activeCategory, recordKey);
            return;
          }

          if (action === "office_history") {
            showAssignedOfficeHistory(activeCategory, recordKey);
          }

        });
      }

      function setActiveCategoryButton(selectedCategory) {
        document.querySelectorAll(".category-btn").forEach((button) => {
          const isActive = button.dataset.category === selectedCategory;
          const isTerminatedToggle = button.dataset.category === "terminated_records";
          if (isTerminatedToggle) {
            button.classList.toggle("bg-[#dc2626]", isActive);
            button.classList.toggle("text-white", isActive);
            button.classList.toggle("border-[#dc2626]", true);
            button.classList.toggle("bg-white", !isActive);
            button.classList.toggle("text-[#dc2626]", !isActive);
            button.classList.toggle("hover:bg-[#b91c1c]", isActive);
            button.classList.toggle("hover:border-[#b91c1c]", isActive);
            button.classList.toggle("hover:bg-[#fef2f2]", !isActive);
            const label = document.getElementById("terminatedRecordsToggleLabel");
            if (label) {
              label.textContent = isActive ? "Unshow Terminated/Ended Contracts" : "Show Terminated/Ended Contracts";
            }
            return;
          }
          button.classList.toggle("bg-[#052c6a]", isActive);
          button.classList.toggle("text-white", isActive);
          button.classList.toggle("shadow-sm", isActive);
          button.classList.toggle("border-transparent", isActive);
          button.classList.toggle("bg-white", !isActive);
          button.classList.toggle("text-[#334155]", !isActive);
          button.classList.toggle("border-[#e2e8f0]", !isActive);
        });
        document.querySelectorAll('input[name="return_active_category"]').forEach((input) => {
          input.value = selectedCategory === "terminated_records" ? "official" : selectedCategory;
        });
      }

      function setupCategorySwitching() {
        const scholarSearchInput = document.getElementById("scholarSearchInput");
        if (scholarSearchInput) {
          scholarSearchInput.addEventListener("input", () => {
            scholarSearchTerm = String(scholarSearchInput.value || "").trim().toLowerCase();
            if (activeCategory !== "terminated_records") {
              renderTable(activeCategory);
            }
          });
        }

        const terminatedSearchInput = document.getElementById("terminatedSearchInput");
        if (terminatedSearchInput) {
          terminatedSearchInput.addEventListener("input", () => {
            terminatedSearchTerm = String(terminatedSearchInput.value || "").trim().toLowerCase();
            if (activeCategory === "terminated_records") {
              renderTable(activeCategory);
            }
          });
        }

        document.querySelectorAll(".category-btn").forEach((button) => {
          button.addEventListener("click", () => {
            const nextCategory = button.dataset.category;
            if (nextCategory === "terminated_records" && activeCategory === "terminated_records") {
              activeCategory = previousScholarCategory;
            } else {
              if (nextCategory === "terminated_records" && activeCategory !== "terminated_records") {
                previousScholarCategory = activeCategory;
              }
              activeCategory = nextCategory;
            }
            setActiveCategoryButton(activeCategory);
            document.querySelectorAll('input[name="active_category"]').forEach((input) => {
              input.value = activeCategory === "terminated_records" ? "official" : activeCategory;
            });
            renderTable(activeCategory);
          });
        });
      }

      function setupManualAddForm() {
        const openButton = document.getElementById("openManualAddModal");
        const closeButton = document.getElementById("closeManualAddModal");
        const cancelButton = document.getElementById("cancelManualAddModal");
        const modal = document.getElementById("manualAddModal");
        const form = document.getElementById("manualAddForm");
        const importForm = document.getElementById("importScholarForm");
        const returnCategoryInputs = document.querySelectorAll('input[name="return_active_category"]');
        const manualGrantSelect = document.getElementById("manualGrantApplied");
        const manualAssignedOfficeSelect = document.getElementById("manualAssignedOffice");
        const manualAssignedOfficeHelp = document.getElementById("manualAssignedOfficeHelp");
        const hasAssignedOfficeOptions = <?php echo empty($assignedOfficeOptions) ? "false" : "true"; ?>;

        const syncManualAssignedOffice = () => {
          if (!manualGrantSelect || !manualAssignedOfficeSelect) return;

          const isStudentAssistant = manualGrantSelect.value.trim().toLowerCase() === "student assistant";
          manualAssignedOfficeSelect.disabled = !isStudentAssistant || !hasAssignedOfficeOptions;
          manualAssignedOfficeSelect.classList.toggle("bg-slate-100", !isStudentAssistant);
          manualAssignedOfficeSelect.classList.toggle("text-slate-400", !isStudentAssistant);
          manualAssignedOfficeSelect.classList.toggle("cursor-not-allowed", !isStudentAssistant);

          if (!isStudentAssistant) {
            manualAssignedOfficeSelect.value = "";
          }
          if (manualAssignedOfficeHelp) {
            manualAssignedOfficeHelp.classList.toggle("hidden", isStudentAssistant);
          }
        };

        const closeModal = () => {
          if (!modal) return;
          modal.classList.add("hidden");
          modal.classList.remove("flex");
          document.body.classList.remove("overflow-hidden");
        };

        const openModal = () => {
          if (!modal) return;
          modal.classList.remove("hidden");
          modal.classList.add("flex");
          document.body.classList.add("overflow-hidden");
        };

        if (openButton && modal) {
          openButton.addEventListener("click", openModal);
        }
        if (closeButton) {
          closeButton.addEventListener("click", closeModal);
        }
        if (cancelButton) {
          cancelButton.addEventListener("click", closeModal);
        }
        if (manualGrantSelect) {
          manualGrantSelect.addEventListener("change", syncManualAssignedOffice);
          syncManualAssignedOffice();
        }
        if (modal) {
          modal.addEventListener("click", (event) => {
            if (event.target === modal) {
              closeModal();
            }
          });
        }
        document.addEventListener("keydown", (event) => {
          if (event.key === "Escape" && modal && !modal.classList.contains("hidden")) {
            closeModal();
          }
        });

        [form, importForm].forEach((targetForm) => {
          if (!targetForm) return;
          targetForm.addEventListener("submit", () => {
            if (openButton) {
              openButton.disabled = true;
            }
          });
        });

        if (returnCategoryInputs.length > 0) {
          returnCategoryInputs.forEach((input) => {
            input.value = activeCategory;
          });
        }
      }

      function setupSidebar() {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");

        if (toggleBtn && sidebar) {
          toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
          });

          sidebar.querySelectorAll("li").forEach((item) => {
            item.addEventListener("click", (event) => {
              if (event.target.closest("summary")) {
                return;
              }
              if (window.innerWidth < 768) {
                sidebar.classList.add("-translate-x-full");
              }
            });
          });
        }
      }

      function markActiveSidebarItem() {
        const sidebar = document.getElementById("sidebar");
        if (!sidebar) return;

        const currentPage = window.location.pathname.split("/").pop().toLowerCase();
        const sidebarAliases = {
          "summary-of-applicants.php": "applicant.php",
          "declined-applicants.php": "applicant.php",
          "reserved-applicants.php": "applicant.php",
          "view-application.php": "applicant.php",
          "department-evaluation-indi.php": "department-evaluation-list.php",
          "summary-reports.php": "summary-report.php",
          "list-0f-qualified.php": "list-of-qualified.php",
          "institutional-scholars.php": "isg-scholars.php"
        };

        const activePage = sidebarAliases[currentPage] || currentPage;

        sidebar.querySelectorAll("[data-nav]").forEach((item) => {
          const target = (item.dataset.nav || "").toLowerCase();
          const isActive = target === activePage;
          item.classList.toggle("bg-[#fcdc2f]", isActive);
          item.classList.toggle("bg-opacity-90", isActive);
          item.classList.toggle("text-[#052c6a]", isActive);
          item.classList.toggle("hover:bg-white/15", !isActive);
        });
      }

      document.addEventListener("DOMContentLoaded", () => {
        const currentUrl = new URL(window.location.href);
        if (currentUrl.searchParams.has("source") || currentUrl.searchParams.has("applicant_id")) {
          currentUrl.searchParams.delete("source");
          currentUrl.searchParams.delete("applicant_id");
          window.history.replaceState({}, document.title, currentUrl.toString());
        }
        showScholarToast(autoImportMessage, autoImportType, ["source", "applicant_id"]);
        showScholarToast(actionNoticeMessage, actionNoticeType, ["scholar_notice", "scholar_notice_message"]);
        showScholarToast(messageNoticeMessage, messageNoticeType, ["message_status"]);

        normalizeGrantLabels();

        setupSidebar();
        markActiveSidebarItem();
        setupCategorySwitching();
        setupManualAddForm();
        setupScholarMessageModal();
        setupRenewalActions();
        setupScholarPrinting();
        updateCounts();
        setActiveCategoryButton(activeCategory);
        renderTable(activeCategory);
      });
    </script>
    <script>
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        if (!sidebar) return;

        const currentPage = window.location.pathname.split("/").pop().toLowerCase();
        const applicantPages = new Set([
          "applicant.php",
          "declined-applicants.php",
          "reserved-applicants.php",
          "summary-of-applicants.php",
          "view-application.php"
        ]);
        const applicantMenuTrigger = sidebar.querySelector('summary[data-nav="applicant.php"]');
        const applicantMenu = applicantMenuTrigger ? applicantMenuTrigger.closest("details") : null;
        if (applicantMenu) {
          applicantMenu.open = applicantPages.has(currentPage);
        }

        const applicantSubmenuAliases = {
          "view-application.php": "applicant.php"
        };
        const activeApplicantSubmenu = applicantSubmenuAliases[currentPage] || currentPage;
        sidebar.querySelectorAll('details a[href]').forEach((link) => {
          const linkPage = link.getAttribute("href").split("?")[0].split("#")[0].split("/").pop().toLowerCase();
          const isActive = linkPage === activeApplicantSubmenu;
          link.classList.toggle("bg-white/15", isActive);
          link.classList.toggle("text-white", isActive);
          link.classList.toggle("font-bold", isActive);
          link.classList.toggle("text-blue-50", !isActive);
          link.classList.toggle("hover:bg-white/15", !isActive);
        });
      });
    </script>  </body>
</html>







