<?php
// Guide: Institutional scholar registry with imports, renewals, status changes, and category tabs.
// Trace: normalize filters/actions -> ensure table exists -> load scholar records -> render UI -> client-side state handlers.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once __DIR__ . "/includes/school-term-filter.php";

$autoImportType = "";
$autoImportMessage = "";
$actionNoticeType = "";
$actionNoticeMessage = "";

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

$scholarCategoryLabels = [
  "official" => "Official Scholars",
  "student_assistant" => "Student Assistant",
  "kabayani" => "Kabayani",
  "academic" => "Academic",
  "others" => "Others",
];

$manualGrantOptions = [
  "Student Assistant",
  "Academic Scholarship Program",
  "Kabayani Scholarship Program",
  "Others",
];
$manualGrantDefaultsByCategory = [
  "student_assistant" => "Student Assistant",
  "academic" => "Academic Scholarship Program",
  "kabayani" => "Kabayani Scholarship Program",
  "others" => "Others",
];
$manualDefaultGrant = $manualGrantDefaultsByCategory[$activeCategoryParam] ?? "";

$serverScholarRecords = array_fill_keys($validScholarCategories, []);
$terminatedScholarRecords = [];
$assignedOfficeOptions = [];
$noticeTypeParam = strtolower(trim((string)($_GET["scholar_notice"] ?? "")));
if (($noticeTypeParam === "success" || $noticeTypeParam === "error") && isset($_GET["scholar_notice_message"])) {
  $actionNoticeType = $noticeTypeParam;
  $actionNoticeMessage = trim((string)$_GET["scholar_notice_message"]);
}

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

function isgBuildOpenableNextSchoolYear(int $year, int $month): string
{
  $targetStartYear = $month >= 3 ? $year : $year - 1;
  return $targetStartYear . "-" . ($targetStartYear + 1);
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
      UNIQUE KEY uniq_isr_category_source (category, source_application_id),
      UNIQUE KEY uniq_isr_category_scholar (category, scholar_id),
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
    "program_year" => "VARCHAR(255) DEFAULT NULL AFTER full_name",
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

  $indexChecks = [
    "uniq_isr_category_source" => "CREATE UNIQUE INDEX uniq_isr_category_source ON institutional_scholar_records (category, source_application_id)",
    "uniq_isr_category_scholar" => "CREATE UNIQUE INDEX uniq_isr_category_scholar ON institutional_scholar_records (category, scholar_id)",
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
  $assignedOffice = trim((string)($record["assigned_office"] ?? ""));
  $semester = trim((string)($record["semester"] ?? ""));
  $academicYear = trim((string)($record["academic_year"] ?? ""));
  $status = isgCanonicalStatus((string)($record["status"] ?? ""));

  if ($sourceParam !== null) {
    $insertSql = "
      INSERT INTO institutional_scholar_records
        (source_application_id, category, scholar_id, grant_applied, full_name, program_year, assigned_office, semester, academic_year, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        scholar_id = IF(COALESCE(contract_ended, 0) = 0 AND LOWER(TRIM(COALESCE(status, ''))) <> 'terminated', VALUES(scholar_id), scholar_id),
        grant_applied = IF(COALESCE(contract_ended, 0) = 0 AND LOWER(TRIM(COALESCE(status, ''))) <> 'terminated', VALUES(grant_applied), grant_applied),
        full_name = IF(COALESCE(contract_ended, 0) = 0 AND LOWER(TRIM(COALESCE(status, ''))) <> 'terminated', VALUES(full_name), full_name),
        program_year = IF(COALESCE(contract_ended, 0) = 0 AND LOWER(TRIM(COALESCE(status, ''))) <> 'terminated', VALUES(program_year), program_year),
        assigned_office = IF(COALESCE(contract_ended, 0) = 0 AND LOWER(TRIM(COALESCE(status, ''))) <> 'terminated', VALUES(assigned_office), assigned_office),
        updated_at = IF(COALESCE(contract_ended, 0) = 0 AND LOWER(TRIM(COALESCE(status, ''))) <> 'terminated', CURRENT_TIMESTAMP, updated_at)
    ";
    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param(
      "isssssssss",
      $sourceParam,
      $category,
      $scholarId,
      $grantApplied,
      $fullName,
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
      (source_application_id, category, scholar_id, grant_applied, full_name, program_year, assigned_office, semester, academic_year, status)
    VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      grant_applied = IF(COALESCE(contract_ended, 0) = 0 AND LOWER(TRIM(COALESCE(status, ''))) <> 'terminated', VALUES(grant_applied), grant_applied),
      full_name = IF(COALESCE(contract_ended, 0) = 0 AND LOWER(TRIM(COALESCE(status, ''))) <> 'terminated', VALUES(full_name), full_name),
      program_year = IF(COALESCE(contract_ended, 0) = 0 AND LOWER(TRIM(COALESCE(status, ''))) <> 'terminated', VALUES(program_year), program_year),
      assigned_office = IF(COALESCE(contract_ended, 0) = 0 AND LOWER(TRIM(COALESCE(status, ''))) <> 'terminated', VALUES(assigned_office), assigned_office),
      updated_at = IF(COALESCE(contract_ended, 0) = 0 AND LOWER(TRIM(COALESCE(status, ''))) <> 'terminated', CURRENT_TIMESTAMP, updated_at)
  ";
  $stmt = $conn->prepare($insertSql);
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param(
    "sssssssss",
    $category,
    $scholarId,
    $grantApplied,
    $fullName,
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
  if ($scholarId !== "") {
    return "sid-" . $scholarId;
  }

  $fullName = strtolower(trim((string)($row["full_name"] ?? "")));
  $semester = strtolower(trim((string)($row["semester"] ?? "")));
  $academicYear = strtolower(trim((string)($row["academic_year"] ?? "")));
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
      category,
      scholar_id,
      grant_applied,
      full_name,
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
      terminated_by
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
      "scholar_id" => trim((string)($row["scholar_id"] ?? "")),
      "grant_applied" => trim((string)($row["grant_applied"] ?? "")),
      "full_name" => trim((string)($row["full_name"] ?? "")),
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

$nextSchoolYearToOpen = isgBuildOpenableNextSchoolYear((int)date("Y"), (int)date("n"));
$isNextSchoolYearOpen = $nextSchoolYearToOpen !== "" && in_array($nextSchoolYearToOpen, $schoolYearOptions, true);
$rawInstitutionalSchoolYearParam = array_key_exists("school_year", $_GET)
  ? trim((string)$_GET["school_year"])
  : null;
$showAllInstitutionalSchoolYears = $rawInstitutionalSchoolYearParam !== null
  && strtolower($rawInstitutionalSchoolYearParam) === "all";
$activeInstitutionalSchoolYear = $showAllInstitutionalSchoolYears
  ? ""
  : ($selectedSchoolYear !== "" ? $selectedSchoolYear : $displaySchoolYear);

if (($conn ?? null) instanceof mysqli) {
  if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && (string)($_POST["form_action"] ?? "") === "open_next_school_year") {
    $requestedSchoolYear = $nextSchoolYearToOpen;
    $returnActiveCategory = strtolower(trim((string)($_POST["return_active_category"] ?? $activeCategoryParam)));
    if (!in_array($returnActiveCategory, $validScholarCategories, true)) {
      $returnActiveCategory = "official";
    }
    $returnSemester = trim((string)($_POST["return_semester"] ?? ""));

    $openedBy = trim((string)($_SESSION["admin_username"] ?? $_SESSION["admin_name"] ?? ""));
    $openResult = function_exists("schoolTermOpenSchoolYear")
      ? schoolTermOpenSchoolYear($conn, $requestedSchoolYear, $openedBy)
      : "error";

    $openSuccess = false;
    if ($openResult === "opened") {
      $openSuccess = true;
      $openMessage = "School Year " . $requestedSchoolYear . " is now open.";
    } elseif ($openResult === "exists") {
      $openSuccess = true;
      $openMessage = "School Year " . $requestedSchoolYear . " is already open.";
    } elseif ($openResult === "invalid") {
      $openMessage = "Invalid school year format.";
    } else {
      $openMessage = "Unable to open the next school year right now.";
    }

    $redirectParams = [
      "active_category" => $returnActiveCategory,
      "scholar_notice" => $openSuccess ? "success" : "error",
      "scholar_notice_message" => $openMessage,
    ];
    if ($openSuccess && $requestedSchoolYear !== "") {
      $redirectParams["school_year"] = $requestedSchoolYear;
    }
    if ($returnSemester !== "") {
      $redirectParams["semester"] = $returnSemester;
    }

    header("Location: institutional-scholars.php?" . http_build_query($redirectParams));
    exit;
  }

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
        "SELECT id, applicant_name, program_course, year_level, school_year, semester, grant_id, status
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
    $manualMessage = "Unable to add scholar. Please provide required fields.";
    if ($manualFullName !== "" && $manualGrantApplied !== "") {
      $record = [
        "source_application_id" => 0,
        "scholar_id" => $manualScholarId,
        "grant_applied" => $manualGrantApplied,
        "full_name" => $manualFullName,
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
              $rowProgramYear = isgFirstNonEmptyValue($row, $requiredHeaderGroups["program_year"]);
              $rowAssignedOffice = isgFirstNonEmptyValue($row, $requiredHeaderGroups["assigned_office"]);
              $rowSemesterRaw = isgFirstNonEmptyValue($row, $requiredHeaderGroups["semester"]);
              $rowAcademicYear = isgFirstNonEmptyValue($row, $requiredHeaderGroups["academic_year"]);

              if (
                $rowGrant === "" ||
                $rowFullName === "" ||
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
  if ($hasScholarStorage && ($requestedAction === "renew" || $requestedAction === "end_contract" || $requestedAction === "terminate" || $requestedAction === "change_office")) {
    $targetScholarRecordId = (int)($_GET["id"] ?? ($_GET["scholar_record_id"] ?? 0));
    $renewalScope = trim((string)($_GET["renewal_scope"] ?? ""));
    $targetAssignedOffice = trim((string)($_GET["new_assigned_office"] ?? ""));
    $terminationReason = trim((string)($_GET["termination_reason"] ?? ""));

    $actionSuccess = false;
    $actionMessage = "Unable to process scholar action.";

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
          program_year,
          assigned_office,
          semester,
          academic_year
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
          } else {
            if ($renewalScope === "2nd_semester") {
              $renewSql = "
                UPDATE institutional_scholar_records
                SET
                  renewal_status = 'renew',
                  renewal_scope = '2nd_semester',
                  second_semester_renewed = 1,
                  semester = '2nd Semester',
                  status = 'renewed',
                  contract_ended = 0,
                  updated_at = CURRENT_TIMESTAMP
                WHERE $whereSql AND contract_ended = 0
              ";
              $renewStmt = $conn->prepare($renewSql);
              if ($renewStmt) {
                isgBindParams($renewStmt, $whereTypes, $whereParams);
                $renewExecuted = $renewStmt->execute();
                $renewAffectedRows = $renewStmt->affected_rows;
                $renewStmt->close();
                if ($renewExecuted && $renewAffectedRows > 0) {
                  $actionSuccess = true;
                  $actionMessage = "Scholar renewed for 2nd Semester.";
                } elseif ($renewExecuted) {
                  $actionMessage = "No renewal changes applied. Scholar may already be renewed or not active.";
                }
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
              $renewalBaseYear = trim((string)($currentSchoolYear ?? ""));
              if ($renewalBaseYear === "") {
                $renewalBaseYear = trim((string)$displaySchoolYear);
              }
              if ($renewalBaseYear === "") {
                $renewalBaseYear = $baseAcademicYear;
              }

              $nextAcademicYear = isgIncrementSchoolYear($renewalBaseYear, $displaySchoolYear);
              $nextStartYear = isgSchoolYearStart($nextAcademicYear);
              $baseStartYear = isgSchoolYearStart($baseAcademicYear);
              if ($baseStartYear > 0 && $nextStartYear > 0 && $nextStartYear <= $baseStartYear) {
                $nextAcademicYear = isgIncrementSchoolYear($baseAcademicYear, $renewalBaseYear);
              }
              $nextProgramYear = isgIncrementProgramYearLevel($baseProgramYear);

              if (!isgSchoolYearAvailableForRenewal($schoolYearOptions, $nextAcademicYear)) {
                $actionMessage = $nextAcademicYear !== ""
                  ? "Cannot renew for next School Year until " . $nextAcademicYear . " is added to the system school year list."
                  : "Cannot renew for next School Year until the next school year is added to the system school year list.";
              } else {

                $renewSql = "
                  UPDATE institutional_scholar_records
                  SET
                    renewal_status = 'renew',
                    renewal_scope = 'school_year',
                    second_semester_renewed = 0,
                    semester = '1st Semester',
                    program_year = ?,
                    academic_year = ?,
                    status = 'renewed',
                    contract_ended = 0,
                    updated_at = CURRENT_TIMESTAMP
                  WHERE $whereSql AND contract_ended = 0
                ";
                $renewStmt = $conn->prepare($renewSql);
                if ($renewStmt) {
                  isgBindParams($renewStmt, "ss" . $whereTypes, array_merge([$nextProgramYear, $nextAcademicYear], $whereParams));
                  $renewExecuted = $renewStmt->execute();
                  $renewAffectedRows = $renewStmt->affected_rows;
                  $renewStmt->close();
                  if ($renewExecuted && $renewAffectedRows > 0) {
                    $actionSuccess = true;
                    $actionMessage = "Scholar renewed for next School Year.";
                  } elseif ($renewExecuted) {
                    $actionMessage = "No renewal changes applied. Scholar may already be renewed or not active.";
                  }
                }
              }
            }
          }
        } elseif ($requestedAction === "end_contract") {
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
        } elseif ($requestedAction === "terminate") {
          if ($terminationReason === "") {
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
        } elseif ($requestedAction === "change_office") {
          if ($targetAssignedOffice === "") {
            $actionMessage = "Assigned office is required.";
          } else {
            $sourceApplicationIds = [];
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

            if ($targetCount <= 0) {
              $actionMessage = "Only active Student Assistant records can change assigned office.";
            } else {
              $updateSql = "
                UPDATE institutional_scholar_records
                SET
                  assigned_office = ?,
                  updated_at = CURRENT_TIMESTAMP
                WHERE $whereSql
                  AND COALESCE(contract_ended, 0) = 0
              ";
              $updateStmt = $conn->prepare($updateSql);
              if ($updateStmt) {
                isgBindParams($updateStmt, "s" . $whereTypes, array_merge([$targetAssignedOffice], $whereParams));
                $actionSuccess = $updateStmt->execute();
                if ($actionSuccess) {
                  $affectedRows = $updateStmt->affected_rows;
                  $applicationSyncOk = true;
                  if (!empty($sourceApplicationIds) && ($hasAssignedOfficeColumn ?? false)) {
                    foreach (array_keys($sourceApplicationIds) as $sourceId) {
                      $appUpdateStmt = $conn->prepare("UPDATE applications SET assigned_office = ? WHERE id = ?");
                      if (!$appUpdateStmt) {
                        $applicationSyncOk = false;
                        break;
                      }
                      $appUpdateStmt->bind_param("si", $targetAssignedOffice, $sourceId);
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
                    $actionMessage = "Assigned office updated in scholar records but failed to sync source application.";
                  } else {
                    $actionMessage = $affectedRows > 0
                      ? "Assigned office updated successfully."
                      : "Assigned office is already set to that value.";
                  }
                }
                $updateStmt->close();
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
      $redirectParams["termination_reason"],
      $redirectParams["scholar_notice"],
      $redirectParams["scholar_notice_message"]
    );
    $redirectParams["scholar_notice"] = $actionSuccess ? "success" : "error";
    $redirectParams["scholar_notice_message"] = $actionMessage;
    header("Location: institutional-scholars.php" . (!empty($redirectParams) ? ("?" . http_build_query($redirectParams)) : ""));
    exit;
  }

  if ($hasScholarStorage) {
    $serverScholarRecords = isgLoadScholarRecords($conn, $validScholarCategories);
    $terminatedScholarRecords = isgLoadTerminatedScholarRecords($conn);
  }
}
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Institutional Scholars</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <script src="https://cdn.tailwindcss.com"></script>
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
          #sidebar nav ul {
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

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="applicant.php" onclick="window.location.href='applicant.php'"
            >
              <i class="fas fa-user-graduate w-5"></i>
              <span>Applicants</span>
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
              <span>Accounts</span>
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

              <form id="openNextSchoolYearForm" method="post" action="institutional-scholars.php" class="hidden">
                <input type="hidden" name="form_action" value="open_next_school_year" />
                <input type="hidden" name="next_school_year" value="<?php echo htmlspecialchars($nextSchoolYearToOpen); ?>" />
                <input type="hidden" name="return_active_category" value="<?php echo htmlspecialchars($activeCategoryParam); ?>" />
                <input type="hidden" name="return_semester" value="<?php echo htmlspecialchars($selectedSemester); ?>" />
              </form>

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
                    <option value="" <?php echo $selectedSemester === "" ? "selected" : ""; ?>>All Semesters</option>
                    <?php foreach ($semesterOptions as $option): ?>
                      <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedSemester === $option ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($option); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if ($showAllInstitutionalSchoolYears || $selectedSchoolYear !== "" || $selectedSemester !== ""): ?>
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
                    type="submit"
                    form="openNextSchoolYearForm"
                    class="inline-flex items-center justify-center gap-2 rounded-full border px-4 py-2 text-xs font-semibold shadow-sm <?php echo $isNextSchoolYearOpen ? 'cursor-not-allowed border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700'; ?>"
                    <?php echo $isNextSchoolYearOpen ? "disabled" : ""; ?>
                  >
                    <i class="fas fa-calendar-plus"></i>
                    <span><?php echo $isNextSchoolYearOpen ? "Next SY Open" : "Open Next SY"; ?> <?php echo htmlspecialchars($nextSchoolYearToOpen); ?></span>
                  </button>
                  <button
                    type="button"
                    id="openManualAddModal"
                    class="inline-flex items-center justify-center rounded-full bg-[#0d8ddb] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#0a7fc8]"
                  >
                    Add Record
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
                          Required CSV columns: <strong>scholarship_grant, full_name, program_year, assigned_office, semester, academic_year</strong>.
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
      const selectedSemester = <?php echo json_encode($selectedSemester); ?>;
      const displaySchoolYear = <?php echo json_encode($displaySchoolYear); ?>;
      const displaySemester = <?php echo json_encode($displaySemester); ?>;
      const currentSchoolYear = <?php echo json_encode($currentSchoolYear); ?>;
      const nextSchoolYear = <?php echo json_encode($nextSchoolYearToOpen); ?>;
      const availableSchoolYears = <?php echo json_encode(array_values(array_unique($schoolYearOptions)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const activeFilterSchoolYear = String(selectedSchoolYear || displaySchoolYear || "").trim();
      const activeFilterSemester = String(selectedSemester || displaySemester || "").trim();
      const initialActiveCategory = <?php echo json_encode($activeCategoryParam); ?>;
      const serverScholarRecords = <?php echo json_encode($serverScholarRecords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const terminatedScholarRecords = <?php echo json_encode($terminatedScholarRecords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const availableAssignedOffices = <?php echo json_encode($assignedOfficeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const autoImportMessage = <?php echo json_encode($autoImportMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const autoImportType = <?php echo json_encode($autoImportType, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const actionNoticeMessage = <?php echo json_encode($actionNoticeMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      const actionNoticeType = <?php echo json_encode($actionNoticeType, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
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

        const range = getRecordAcademicYearRange(record);
        const firstRenewStart = new Date(range.startYear, 9, 1); // Oct 1, start year
        const firstDeadline = new Date(range.startYear, 11, 31); // Dec 31, start year
        const secondRenewStart = new Date(range.endYear, 4, 1); // May 1, end year
        const secondDeadline = new Date(range.endYear, 4, 31); // May 31, end year

        const today = new Date();
        const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());

        const renewalScope = String(record && typeof record === "object" ? (record.renewal_scope || "") : "").trim();
        const secondSemesterRenewed = (record && record.second_semester_renewed === true)
          || renewalScope === "2nd_semester"
          || renewalScope === "school_year";
        const schoolYearRenewed = renewalScope === "school_year";
        const semesterPhase = getRecordSemesterPhase(record);

        if (semesterPhase === "first" && !secondSemesterRenewed) {
          if (todayDate > firstDeadline) {
            return {
              statusKey: "for_renewal",
              nextScope: "2nd_semester",
              renewEnabled: true,
              reason: ""
            };
          }
          if (todayDate >= firstRenewStart) {
            return {
              statusKey: "for_renewal",
              nextScope: "2nd_semester",
              renewEnabled: true,
              reason: ""
            };
          }
          return {
            statusKey: "official_scholar",
            nextScope: "",
            renewEnabled: false,
            reason: "Not yet in renewal window."
          };
        }

        if (!schoolYearRenewed) {
          const nextAcademicYearValue = getNextAcademicYearForRecord(record);
          const nextSchoolYearReady = isSchoolYearAvailable(nextAcademicYearValue);
          if (!nextSchoolYearReady) {
            return {
              statusKey: secondSemesterRenewed ? "renewed" : "official_scholar",
              nextScope: "school_year",
              renewEnabled: false,
              reason: nextAcademicYearValue !== ""
                ? "Next school year " + nextAcademicYearValue + " is not yet available in the system."
                : "Next school year is not yet available in the system."
            };
          }
          if (todayDate > secondDeadline) {
            return {
              statusKey: "for_renewal",
              nextScope: "school_year",
              renewEnabled: true,
              reason: ""
            };
          }
          if (todayDate >= secondRenewStart) {
            return {
              statusKey: "for_renewal",
              nextScope: "school_year",
              renewEnabled: true,
              reason: ""
            };
          }
          return {
            statusKey: secondSemesterRenewed ? "renewed" : "official_scholar",
            nextScope: "",
            renewEnabled: false,
            reason: "Not yet in renewal window."
          };
        }

        return {
          statusKey: "renewed",
          nextScope: "",
          renewEnabled: false,
          reason: "Already renewed."
        };
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

        const renewalScope = String(record && typeof record === "object" ? (record.renewal_scope || "") : "").trim();
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

      function submitScholarAction(action, record, renewalScope = "", newAssignedOffice = "", terminationReason = "") {
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
        notice.textContent = "Status is automatic: scholars due for renewal stay tagged as For Renewal until renewed or contract ended.";
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

      function showChangeAssignedOfficePrompt(category, recordKey) {
        const record = getRecordByKey(category, recordKey);
        if (!record) return;

        const currentOffice = String(record.assigned_office || "").trim();
        const officeChoices = getAssignedOfficeChoices(currentOffice);
        if (typeof Swal === "undefined") {
          const nextOfficeRaw = window.prompt("Enter new assigned office:", currentOffice);
          if (nextOfficeRaw === null) return;
          const nextOffice = String(nextOfficeRaw || "").trim();
          if (nextOffice === "") {
            window.alert("Assigned office is required.");
            return;
          }
          if (nextOffice === currentOffice) return;
          submitScholarAction("change_office", record, "", nextOffice);
          return;
        }

        if (officeChoices.length === 0) {
          Swal.fire({
            title: "Change Assigned Office",
            text: "No office options available. Please add offices first.",
            icon: "info",
            confirmButtonColor: "#0d8ddb"
          });
          return;
        }

        const officeSelectOptions = {};
        officeChoices.forEach((office) => {
          officeSelectOptions[office] = office;
        });
        const hasCurrentOffice = officeChoices.some((office) => office.toLowerCase() === currentOffice.toLowerCase());
        const selectedOfficeValue = hasCurrentOffice ? currentOffice : officeChoices[0];

        Swal.fire({
          title: "Change Assigned Office",
          text: "This Student Assistant will move to the head office that matches the new assigned office.",
          input: "select",
          inputOptions: officeSelectOptions,
          inputValue: selectedOfficeValue,
          showCancelButton: true,
          confirmButtonText: "Save",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#0d8ddb",
          inputValidator: (value) => {
            const nextValue = String(value || "").trim();
            if (nextValue === "") {
              return "Assigned office is required.";
            }
            return undefined;
          }
        }).then((result) => {
          if (!result.isConfirmed) return;
          const nextOffice = String(result.value || "").trim();
          if (nextOffice === "" || nextOffice === currentOffice) return;
          submitScholarAction("change_office", record, "", nextOffice);
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
              : "bg-white text-slate-700 border-slate-300 hover:bg-slate-50";
          const renewDisabled = isContractEnded || statusContext.renewEnabled !== true;
          const renewalDisabledClasses = renewDisabled ? "opacity-50 cursor-not-allowed hover:bg-transparent" : "";
          const renewalDisabledTitle = isContractEnded
            ? "Contract already ended."
            : String(statusContext.reason || "Renewal is not available.");
          const renewalDisabledAttrs = renewDisabled
            ? ' disabled title="' + escapeHtml(renewalDisabledTitle) + '" aria-disabled="true" '
            : "";
          const endContractDisabledAttrs = isContractEnded
            ? ' disabled title="Contract already ended." aria-disabled="true" '
            : "";
          const endContractDisabledClasses = isContractEnded ? "opacity-60 cursor-not-allowed" : "";
          const terminateDisabledAttrs = isContractEnded
            ? ' disabled title="Contract already ended." aria-disabled="true" '
            : "";
          const terminateDisabledClasses = isContractEnded ? "opacity-60 cursor-not-allowed" : "";
          const changeOfficeBtnHtml = (isStudentAssistantGrant && !isContractEnded)
            ? ('<button type="button" data-status-action="change_office" data-record-key="' + escapeHtml(record.__recordKey) + '" class="px-2 py-1 rounded border text-[10px] font-semibold transition-colors bg-white text-[#0d8ddb] border-[#7cc5ee] hover:bg-[#ebf7ff]">Edit</button>')
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
                '<button type="button" data-status-action="end_contract" data-record-key="' + escapeHtml(record.__recordKey) + '" class="px-2 py-1 rounded border text-[10px] bg-red-600 text-white font-semibold transition-colors ' + endContractBtnClasses + ' ' + endContractDisabledClasses + '"' + endContractDisabledAttrs + '>End Contract</button>' +
                '<button type="button" data-status-action="terminate" data-record-key="' + escapeHtml(record.__recordKey) + '" class="px-2 py-1 rounded border text-[10px] bg-red-50 text-red-700 border-red-200 font-semibold transition-colors hover:bg-red-100 ' + terminateDisabledClasses + '"' + terminateDisabledAttrs + '>Terminate</button>' +
                changeOfficeBtnHtml +
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
          if (recordKey === "" || (action !== "renew" && action !== "end_contract" && action !== "terminate" && action !== "change_office")) return;

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

          if (action === "change_office") {
            showChangeAssignedOfficePrompt(activeCategory, recordKey);
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
            item.addEventListener("click", () => {
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
          "view-application.php": "applicant.php",
          "department-evaluation-indi.php": "department-evaluation-list.php",
          "summary-reports.php": "summary-report.php",
          "list-0f-qualified.php": "list-of-qualified.php",
          "institutional-scholars.php": "isg-scholars.php"
        };

        const activePage = sidebarAliases[currentPage] || currentPage;

        sidebar.querySelectorAll("li[data-nav]").forEach((item) => {
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

        normalizeGrantLabels();

        setupSidebar();
        markActiveSidebarItem();
        setupCategorySwitching();
        setupManualAddForm();
        setupRenewalActions();
        updateCounts();
        setActiveCategoryButton(activeCategory);
        renderTable(activeCategory);
      });
    </script>
  </body>
</html>
