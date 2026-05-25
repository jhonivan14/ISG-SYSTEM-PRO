<?php
// Guide: Shared school year, semester, and batch filter bootstrap for admin pages.
// Trace: ensure DB and auth context -> build option lists -> normalize selected filters -> expose display values.

require_once __DIR__ . "/admin-auth.php";
adminRequireLogin();

if (!(($conn ?? null) instanceof mysqli)) {
  require_once dirname(__DIR__) . "/../db.php";
}

$currentYear = (int)date("Y");
$currentMonth = (int)date("n");
$currentSchoolYearStart = $currentMonth < 6 ? $currentYear - 1 : $currentYear;
$dateBasedSchoolYear = $currentSchoolYearStart . "-" . ($currentSchoolYearStart + 1);
$dateBasedSemester = $currentMonth < 6 ? "2nd Semester" : "1st Semester";
$currentSchoolYear = $dateBasedSchoolYear;
$currentSemester = $dateBasedSemester;

if (!function_exists("schoolTermNormalizeSchoolYear")) {
  function schoolTermNormalizeSchoolYear(string $schoolYear): string
  {
    $value = trim($schoolYear);
    if (!preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $value, $matches)) {
      return "";
    }

    $startYear = (int)$matches[1];
    $endYear = (int)$matches[2];
    if ($endYear !== $startYear + 1) {
      return "";
    }

    return $startYear . "-" . $endYear;
  }
}

if (!function_exists("schoolTermEnsureSchoolYearsTable")) {
  function schoolTermEnsureSchoolYearsTable(mysqli $conn): bool
  {
    return (bool)$conn->query("
      CREATE TABLE IF NOT EXISTS school_years (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_year VARCHAR(20) NOT NULL,
        opened_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_school_years_school_year (school_year)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
  }
}

if (!function_exists("schoolTermEnsureSettingsTable")) {
  function schoolTermEnsureSettingsTable(mysqli $conn): bool
  {
    return (bool)$conn->query("
      CREATE TABLE IF NOT EXISTS school_term_settings (
        id TINYINT NOT NULL PRIMARY KEY,
        active_school_year VARCHAR(20) NOT NULL,
        active_semester VARCHAR(50) NOT NULL,
        updated_by VARCHAR(100) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
  }
}

if (!function_exists("schoolTermLoadSettings")) {
  function schoolTermLoadSettings(mysqli $conn): array
  {
    if (!schoolTermEnsureSettingsTable($conn)) {
      return [
        "active_school_year" => "",
        "active_semester" => "",
      ];
    }

    $result = $conn->query("
      SELECT active_school_year, active_semester
      FROM school_term_settings
      WHERE id = 1
      LIMIT 1
    ");
    if (!$result instanceof mysqli_result) {
      return [
        "active_school_year" => "",
        "active_semester" => "",
      ];
    }

    $row = $result->fetch_assoc();
    $result->free();
    if (!is_array($row)) {
      return [
        "active_school_year" => "",
        "active_semester" => "",
      ];
    }

    return [
      "active_school_year" => schoolTermNormalizeSchoolYear((string)($row["active_school_year"] ?? "")),
      "active_semester" => trim((string)($row["active_semester"] ?? "")),
    ];
  }
}

if (!function_exists("schoolTermSaveSettings")) {
  function schoolTermSaveSettings(mysqli $conn, string $schoolYear, string $semester, string $updatedBy = ""): string
  {
    $normalizedSchoolYear = schoolTermNormalizeSchoolYear($schoolYear);
    if ($normalizedSchoolYear === "") {
      return "invalid_school_year";
    }

    $semester = trim($semester);
    if ($semester !== "1st Semester" && $semester !== "2nd Semester") {
      return "invalid_semester";
    }

    if (!schoolTermEnsureSettingsTable($conn)) {
      return "error";
    }

    if (schoolTermOpenSchoolYear($conn, $normalizedSchoolYear, $updatedBy) === "error") {
      return "error";
    }

    $stmt = $conn->prepare("
      INSERT INTO school_term_settings (id, active_school_year, active_semester, updated_by)
      VALUES (1, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        active_school_year = VALUES(active_school_year),
        active_semester = VALUES(active_semester),
        updated_by = VALUES(updated_by),
        updated_at = CURRENT_TIMESTAMP
    ");
    if (!$stmt) {
      return "error";
    }

    $stmt->bind_param("sss", $normalizedSchoolYear, $semester, $updatedBy);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok ? "saved" : "error";
  }
}

if (!function_exists("schoolTermOpenSchoolYear")) {
  function schoolTermOpenSchoolYear(mysqli $conn, string $schoolYear, string $openedBy = ""): string
  {
    $normalizedSchoolYear = schoolTermNormalizeSchoolYear($schoolYear);
    if ($normalizedSchoolYear === "") {
      return "invalid";
    }

    if (!schoolTermEnsureSchoolYearsTable($conn)) {
      return "error";
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO school_years (school_year, opened_by) VALUES (?, ?)");
    if (!$stmt) {
      return "error";
    }

    $stmt->bind_param("ss", $normalizedSchoolYear, $openedBy);
    $ok = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if (!$ok) {
      return "error";
    }

    return $affectedRows > 0 ? "opened" : "exists";
  }
}

$configuredSchoolTerm = schoolTermLoadSettings($conn);
$configuredSchoolYear = (string)($configuredSchoolTerm["active_school_year"] ?? "");
$configuredSemester = (string)($configuredSchoolTerm["active_semester"] ?? "");
if ($configuredSchoolYear !== "") {
  $currentSchoolYear = $configuredSchoolYear;
}
if ($configuredSemester === "1st Semester" || $configuredSemester === "2nd Semester") {
  $currentSemester = $configuredSemester;
}

$schoolYearOptions = [];
$schoolYearResult = $conn->query("SELECT DISTINCT school_year FROM applications WHERE school_year IS NOT NULL AND TRIM(school_year) <> ''");
if ($schoolYearResult instanceof mysqli_result) {
  while ($row = $schoolYearResult->fetch_assoc()) {
    $value = schoolTermNormalizeSchoolYear((string)($row["school_year"] ?? ""));
    if ($value !== "") {
      $schoolYearOptions[] = $value;
    }
  }
  $schoolYearResult->free();
}

if (schoolTermEnsureSchoolYearsTable($conn)) {
  $openedSchoolYearResult = $conn->query("SELECT school_year FROM school_years ORDER BY school_year ASC");
  if ($openedSchoolYearResult instanceof mysqli_result) {
    while ($row = $openedSchoolYearResult->fetch_assoc()) {
      $value = schoolTermNormalizeSchoolYear((string)($row["school_year"] ?? ""));
      if ($value !== "") {
        $schoolYearOptions[] = $value;
      }
    }
    $openedSchoolYearResult->free();
  }
}

$schoolYearOptions = array_values(array_unique($schoolYearOptions));
if ($currentSchoolYear !== "" && !in_array($currentSchoolYear, $schoolYearOptions, true)) {
  $schoolYearOptions[] = $currentSchoolYear;
}
usort($schoolYearOptions, function ($a, $b) {
  $aYear = (int)substr((string)$a, 0, 4);
  $bYear = (int)substr((string)$b, 0, 4);
  if ($aYear === $bYear) {
    return strcmp((string)$a, (string)$b);
  }
  return $aYear <=> $bYear;
});

$semesterOptions = ["1st Semester", "2nd Semester"];
$batchOptions = ["Batch 1", "Batch 2", "Batch 3", "Batch 4", "Batch 5"];

$rawSelectedSchoolYear = array_key_exists("school_year", $_GET) ? trim((string)$_GET["school_year"]) : null;
$rawSelectedSemester = array_key_exists("semester", $_GET) ? trim((string)$_GET["semester"]) : null;
$rawSelectedBatch = array_key_exists("batch", $_GET) ? trim((string)$_GET["batch"]) : null;

$selectedSchoolYear = $rawSelectedSchoolYear !== null ? $rawSelectedSchoolYear : "";
$selectedSemester = $rawSelectedSemester !== null ? $rawSelectedSemester : "";
$selectedBatch = $rawSelectedBatch !== null ? $rawSelectedBatch : "";
if (strtolower($selectedSchoolYear) === "all") {
  $selectedSchoolYear = "";
}
if (strtolower($selectedSemester) === "all") {
  $selectedSemester = "";
}
if (strtolower($selectedBatch) === "all") {
  $selectedBatch = "";
}

if ($selectedSchoolYear !== "" && !in_array($selectedSchoolYear, $schoolYearOptions, true)) {
  $selectedSchoolYear = "";
}
if ($selectedSemester !== "" && !in_array($selectedSemester, $semesterOptions, true)) {
  array_unshift($semesterOptions, $selectedSemester);
}
if ($selectedBatch !== "" && !in_array($selectedBatch, $batchOptions, true)) {
  array_unshift($batchOptions, $selectedBatch);
}

$defaultBatchLabel = isset($defaultBatchLabel) && is_string($defaultBatchLabel) && $defaultBatchLabel !== ""
  ? $defaultBatchLabel
  : "Batch 1";

$defaultSchoolYear = $currentSchoolYear !== "" ? $currentSchoolYear : $dateBasedSchoolYear;
if ($defaultSchoolYear === "" && !empty($schoolYearOptions)) {
  $sortedSchoolYearOptions = array_values($schoolYearOptions);
  $defaultSchoolYear = (string)end($sortedSchoolYearOptions);
}

$displaySchoolYear = $selectedSchoolYear !== "" ? $selectedSchoolYear : $defaultSchoolYear;
$displaySemester = $selectedSemester !== "" ? $selectedSemester : $currentSemester;
$displayBatch = $selectedBatch !== "" ? $selectedBatch : $defaultBatchLabel;
$schoolYearFilterValue = $selectedSchoolYear !== "" ? $selectedSchoolYear : "all";
$semesterFilterValue = $selectedSemester !== "" ? $selectedSemester : "all";
$activeSchoolYearFilter = $rawSelectedSchoolYear === null ? $displaySchoolYear : $selectedSchoolYear;
$activeSemesterFilter = $rawSelectedSemester === null ? "" : $selectedSemester;
$activeBatchFilter = $rawSelectedBatch === null
  ? (strcasecmp($displayBatch, "All Batches") === 0 ? "" : $displayBatch)
  : $selectedBatch;
