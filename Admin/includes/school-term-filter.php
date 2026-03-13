<?php
require_once __DIR__ . "/admin-auth.php";
adminRequireLogin();

if (!(($conn ?? null) instanceof mysqli)) {
  require_once dirname(__DIR__) . "/../db.php";
}

$currentYear = (int)date("Y");
$currentMonth = (int)date("n");
$currentSchoolYearStart = $currentMonth < 6 ? $currentYear - 1 : $currentYear;
$currentSchoolYear = $currentSchoolYearStart . "-" . ($currentSchoolYearStart + 1);
$currentSemester = $currentMonth < 6 ? "2nd Semester" : "1st Semester";

$schoolYearOptions = [];
$schoolYearResult = $conn->query("SELECT DISTINCT school_year FROM applications WHERE school_year IS NOT NULL AND TRIM(school_year) <> ''");
if ($schoolYearResult instanceof mysqli_result) {
  while ($row = $schoolYearResult->fetch_assoc()) {
    $value = trim((string)($row["school_year"] ?? ""));
    if ($value !== "") {
      $schoolYearOptions[] = $value;
    }
  }
  $schoolYearResult->free();
}

if (!in_array($currentSchoolYear, $schoolYearOptions, true)) {
  $schoolYearOptions[] = $currentSchoolYear;
}

$schoolYearOptions = array_values(array_unique($schoolYearOptions));
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

$selectedSchoolYear = isset($_GET["school_year"]) ? trim((string)$_GET["school_year"]) : "";
$selectedSemester = isset($_GET["semester"]) ? trim((string)$_GET["semester"]) : "";
$selectedBatch = isset($_GET["batch"]) ? trim((string)$_GET["batch"]) : "";
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
  array_unshift($schoolYearOptions, $selectedSchoolYear);
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

$displaySchoolYear = $selectedSchoolYear !== "" ? $selectedSchoolYear : $currentSchoolYear;
$displaySemester = $selectedSemester !== "" ? $selectedSemester : $currentSemester;
$displayBatch = $selectedBatch !== "" ? $selectedBatch : $defaultBatchLabel;
$schoolYearFilterValue = $selectedSchoolYear !== "" ? $selectedSchoolYear : "all";
$semesterFilterValue = $selectedSemester !== "" ? $selectedSemester : "all";
