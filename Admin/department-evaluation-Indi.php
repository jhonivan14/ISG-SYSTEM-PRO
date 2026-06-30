<?php
// Guide: Detailed department evaluation viewer and print page for one scholar or evaluation record.
// Trace: load scholar/evaluation data -> compute summaries -> render sections -> print/sidebar helpers.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once "../db.php";
require_once __DIR__ . "/includes/applicant-sidebar-badge.php";

$evaluationId = isset($_GET["evaluation_id"])
  ? (int)$_GET["evaluation_id"]
  : 0;
$applicationId = isset($_GET["application_id"])
  ? (int)$_GET["application_id"]
  : (isset($_GET["id"]) ? (int)$_GET["id"] : 0);

$displayApplicantName = "";
$displaySemesterSchoolYear = "";
$displayAreaOfAssignment = "";
$displayHeadOfOffice = "";
$displayEvaluationDate = "";
$displayStrengths = "";
$displayAreasImprovement = "";
$displayRecommendations = "";
$displaySignatureData = "";
$displayProgramYearLevel = "";
$displayHeadAccountLastName = "";

$ratings = [
  "score-a1" => 0,
  "score-a2" => 0,
  "score-a3" => 0,
  "score-a4" => 0,
  "score-a5" => 0,
  "score-b1" => 0,
  "score-b2" => 0,
  "score-b3" => 0,
  "score-b4" => 0,
  "score-b5" => 0,
  "score-c1" => 0,
  "score-c2" => 0,
  "score-c3" => 0,
  "score-c4" => 0,
  "score-c5" => 0,
];

$sectionFields = [
  "a" => ["score-a1", "score-a2", "score-a3", "score-a4", "score-a5"],
  "b" => ["score-b1", "score-b2", "score-b3", "score-b4", "score-b5"],
  "c" => ["score-c1", "score-c2", "score-c3", "score-c4", "score-c5"],
];

$sectionWeightedTotals = [
  "a" => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
  "b" => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
  "c" => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
];
$overallWeightedTotals = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$resolvedScholarRow = null;
$loadedByEvaluationId = false;

// Helpers below load the selected evaluation record and format print-friendly score summaries.

function adminDepartmentEvaluationLoadScholarRecord(mysqli $conn, int $recordId, int $sourceApplicationId): ?array
{
  $sql = "
    SELECT
      id,
      source_application_id,
      program_year
    FROM institutional_scholar_records
    WHERE (
        (? > 0 AND id = ?)
        OR
        (? > 0 AND source_application_id = ?)
      )
      AND (
        LOWER(TRIM(COALESCE(category, ''))) = 'student_assistant'
        OR (
          LOWER(TRIM(COALESCE(category, ''))) = 'official'
          AND LOWER(TRIM(COALESCE(grant_applied, ''))) LIKE '%assistant%'
        )
      )
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
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return null;
  }

  $stmt->bind_param("iiii", $recordId, $recordId, $sourceApplicationId, $sourceApplicationId);
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

if (($conn ?? null) instanceof mysqli && $evaluationId > 0) {
  $evaluationStmt = $conn->prepare(
    "SELECT
      dhe.application_id,
      dhe.applicant_name,
      dhe.semester,
      dhe.school_year,
      dhe.assigned_office,
      dhe.head_name,
      dhe.head_username,
      dhe.evaluation_date,
      dhe.ratings_json,
      dhe.strengths,
      dhe.areas_improvement,
      dhe.recommendations,
      dhe.signature_data,
      COALESCE(NULLIF(TRIM(isr.program_year), ''), '') AS program_year,
      (
        SELECT TRIM(
          CONCAT(
            TRIM(COALESCE(ho.name, '')),
            CASE
              WHEN TRIM(COALESCE(ho.name, '')) <> '' AND TRIM(COALESCE(ho.lastname, '')) <> '' THEN ' '
              ELSE ''
            END,
            TRIM(COALESCE(ho.lastname, ''))
          )
        )
        FROM head_offices ho
        WHERE ho.username = dhe.head_username
        LIMIT 1
      ) AS head_account_full_name,
      (
        SELECT TRIM(COALESCE(ho.lastname, ''))
        FROM head_offices ho
        WHERE ho.username = dhe.head_username
        LIMIT 1
      ) AS head_account_lastname
    FROM department_head_evaluations dhe
    LEFT JOIN institutional_scholar_records isr
      ON isr.id = ABS(dhe.application_id)
    WHERE dhe.id = ?
    LIMIT 1"
  );

  if ($evaluationStmt) {
    $evaluationStmt->bind_param("i", $evaluationId);
    if ($evaluationStmt->execute()) {
      $evaluationResult = $evaluationStmt->get_result();
      $evaluationRow = $evaluationResult ? $evaluationResult->fetch_assoc() : null;
      if (is_array($evaluationRow)) {
        $loadedByEvaluationId = true;
        $applicationId = (int)($evaluationRow["application_id"] ?? 0);
        $displayApplicantName = trim((string)($evaluationRow["applicant_name"] ?? ""));
        $semester = trim((string)($evaluationRow["semester"] ?? ""));
        $schoolYear = trim((string)($evaluationRow["school_year"] ?? ""));
        if ($semester !== "" && $schoolYear !== "") {
          $displaySemesterSchoolYear = $semester . ", S.Y. " . $schoolYear;
        } elseif ($semester !== "") {
          $displaySemesterSchoolYear = $semester;
        } elseif ($schoolYear !== "") {
          $displaySemesterSchoolYear = $schoolYear;
        }
        $displayAreaOfAssignment = trim((string)($evaluationRow["assigned_office"] ?? ""));
        $resolvedHeadFullName = trim((string)($evaluationRow["head_account_full_name"] ?? ""));
        $displayHeadOfOffice = $resolvedHeadFullName !== ""
          ? $resolvedHeadFullName
          : trim((string)($evaluationRow["head_name"] ?? ""));
        if ($displayHeadOfOffice === "") {
          $displayHeadOfOffice = trim((string)($evaluationRow["head_username"] ?? ""));
        }
        $displayHeadAccountLastName = trim((string)($evaluationRow["head_account_lastname"] ?? ""));
        $displayStrengths = trim((string)($evaluationRow["strengths"] ?? ""));
        $displayAreasImprovement = trim((string)($evaluationRow["areas_improvement"] ?? ""));
        $displayRecommendations = trim((string)($evaluationRow["recommendations"] ?? ""));
        $displaySignatureData = trim((string)($evaluationRow["signature_data"] ?? ""));
        $displayProgramYearLevel = trim((string)($evaluationRow["program_year"] ?? ""));

        $rawDate = trim((string)($evaluationRow["evaluation_date"] ?? ""));
        if ($rawDate !== "") {
          $parsedDate = strtotime($rawDate);
          $displayEvaluationDate = $parsedDate !== false ? date("F j, Y", $parsedDate) : $rawDate;
        }

        $decodedRatings = json_decode((string)($evaluationRow["ratings_json"] ?? ""), true);
        if (is_array($decodedRatings)) {
          foreach ($ratings as $field => $unusedValue) {
            $score = isset($decodedRatings[$field]) ? (int)$decodedRatings[$field] : 0;
            if ($score >= 1 && $score <= 5) {
              $ratings[$field] = $score;
            }
          }
        }
      }
      if ($evaluationResult instanceof mysqli_result) {
        $evaluationResult->free();
      }
    }
    $evaluationStmt->close();
  }
}

if (($conn ?? null) instanceof mysqli && !$loadedByEvaluationId && $applicationId !== 0) {
  $resolvedScholarRow = adminDepartmentEvaluationLoadScholarRecord(
    $conn,
    $applicationId < 0 ? abs($applicationId) : 0,
    $applicationId > 0 ? $applicationId : 0
  );
  $resolvedScholarRecordId = is_array($resolvedScholarRow) ? (int)($resolvedScholarRow["id"] ?? 0) : 0;
  $resolvedSourceApplicationId = is_array($resolvedScholarRow) ? (int)($resolvedScholarRow["source_application_id"] ?? 0) : 0;
  $canonicalApplicationId = $resolvedScholarRecordId > 0 ? (0 - $resolvedScholarRecordId) : $applicationId;
  $candidateApplicationIds = [];
  foreach ([$canonicalApplicationId, $applicationId, $resolvedSourceApplicationId] as $candidateId) {
    $candidateId = (int)$candidateId;
    if ($candidateId !== 0 && !in_array($candidateId, $candidateApplicationIds, true)) {
      $candidateApplicationIds[] = $candidateId;
    }
  }

  $tableResult = $conn->query("SHOW TABLES LIKE 'department_head_evaluations'");
  $hasEvaluationTable = $tableResult instanceof mysqli_result && $tableResult->num_rows > 0;
  if ($tableResult instanceof mysqli_result) {
    $tableResult->free();
  }

  if ($hasEvaluationTable && !empty($candidateApplicationIds)) {
    $evaluationIdPlaceholders = implode(", ", array_fill(0, count($candidateApplicationIds), "?"));
    $evaluationStmt = $conn->prepare(
      "SELECT
        applicant_name,
        semester,
        school_year,
        assigned_office,
        head_name,
        head_username,
        evaluation_date,
        ratings_json,
        strengths,
        areas_improvement,
        recommendations,
        signature_data,
        (
          SELECT TRIM(
            CONCAT(
              TRIM(COALESCE(ho.name, '')),
              CASE
                WHEN TRIM(COALESCE(ho.name, '')) <> '' AND TRIM(COALESCE(ho.lastname, '')) <> '' THEN ' '
                ELSE ''
              END,
              TRIM(COALESCE(ho.lastname, ''))
            )
          )
          FROM head_offices ho
          WHERE ho.username = department_head_evaluations.head_username
          LIMIT 1
        ) AS head_account_full_name,
        (
          SELECT TRIM(COALESCE(ho.lastname, ''))
          FROM head_offices ho
          WHERE ho.username = department_head_evaluations.head_username
          LIMIT 1
        ) AS head_account_lastname
      FROM department_head_evaluations
      WHERE application_id IN ($evaluationIdPlaceholders)
      ORDER BY
        CASE
          WHEN application_id = ? THEN 0
          WHEN application_id = ? THEN 1
          ELSE 2
        END,
        updated_at DESC,
        id DESC
      LIMIT 1"
    );

    if ($evaluationStmt) {
      $bindValues = array_merge($candidateApplicationIds, [$canonicalApplicationId, $applicationId]);
      $bindTypes = str_repeat("i", count($bindValues));
      $evaluationStmt->bind_param($bindTypes, ...$bindValues);
      if ($evaluationStmt->execute()) {
        $result = $evaluationStmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        if (is_array($row)) {
          $displayApplicantName = trim((string)($row["applicant_name"] ?? ""));
          $semester = trim((string)($row["semester"] ?? ""));
          $schoolYear = trim((string)($row["school_year"] ?? ""));
          if ($semester !== "" && $schoolYear !== "") {
            $displaySemesterSchoolYear = $semester . ", S.Y. " . $schoolYear;
          } elseif ($semester !== "") {
            $displaySemesterSchoolYear = $semester;
          } elseif ($schoolYear !== "") {
            $displaySemesterSchoolYear = $schoolYear;
          }
          $displayAreaOfAssignment = trim((string)($row["assigned_office"] ?? ""));
          $resolvedHeadFullName = trim((string)($row["head_account_full_name"] ?? ""));
          $displayHeadOfOffice = $resolvedHeadFullName !== ""
            ? $resolvedHeadFullName
            : trim((string)($row["head_name"] ?? ""));
          if ($displayHeadOfOffice === "") {
            $displayHeadOfOffice = trim((string)($row["head_username"] ?? ""));
          }
          $displayHeadAccountLastName = trim((string)($row["head_account_lastname"] ?? ""));
          $displayStrengths = trim((string)($row["strengths"] ?? ""));
          $displayAreasImprovement = trim((string)($row["areas_improvement"] ?? ""));
          $displayRecommendations = trim((string)($row["recommendations"] ?? ""));
          $displaySignatureData = trim((string)($row["signature_data"] ?? ""));

          $rawDate = trim((string)($row["evaluation_date"] ?? ""));
          if ($rawDate !== "") {
            $parsedDate = strtotime($rawDate);
            $displayEvaluationDate = $parsedDate !== false ? date("F j, Y", $parsedDate) : $rawDate;
          }

          $decodedRatings = json_decode((string)($row["ratings_json"] ?? ""), true);
          if (is_array($decodedRatings)) {
            foreach ($ratings as $field => $unusedValue) {
              $score = isset($decodedRatings[$field]) ? (int)$decodedRatings[$field] : 0;
              if ($score >= 1 && $score <= 5) {
                $ratings[$field] = $score;
              }
            }
          }
        }

        if ($result instanceof mysqli_result) {
          $result->free();
        }
      }
      $evaluationStmt->close();
    }
  }

  $resolvedProgramYear = is_array($resolvedScholarRow) ? trim((string)($resolvedScholarRow["program_year"] ?? "")) : "";
  if ($resolvedProgramYear !== "") {
    $displayProgramYearLevel = $resolvedProgramYear;
  } elseif ($applicationId > 0) {
    $programStmt = $conn->prepare(
      "SELECT
        TRIM(COALESCE(program_course, '')) AS program_course,
        TRIM(COALESCE(year_level, '')) AS year_level
      FROM applications
      WHERE id = ?
      LIMIT 1"
    );
    if ($programStmt) {
      $programStmt->bind_param("i", $applicationId);
      if ($programStmt->execute()) {
        $programResult = $programStmt->get_result();
        $programRow = $programResult ? $programResult->fetch_assoc() : null;
        if (is_array($programRow)) {
          $programCourse = trim((string)($programRow["program_course"] ?? ""));
          $yearLevel = trim((string)($programRow["year_level"] ?? ""));
          if ($programCourse !== "" && $yearLevel !== "") {
            $displayProgramYearLevel = $programCourse . " / " . $yearLevel;
          } elseif ($programCourse !== "") {
            $displayProgramYearLevel = $programCourse;
          } else {
            $displayProgramYearLevel = $yearLevel;
          }
        }
        if ($programResult instanceof mysqli_result) {
          $programResult->free();
        }
      }
      $programStmt->close();
    }
  } elseif ($applicationId < 0) {
    $scholarRecordId = abs($applicationId);
    if ($scholarRecordId > 0) {
      $programStmt = $conn->prepare(
        "SELECT TRIM(COALESCE(program_year, '')) AS program_year
        FROM institutional_scholar_records
        WHERE id = ?
        LIMIT 1"
      );
      if ($programStmt) {
        $programStmt->bind_param("i", $scholarRecordId);
        if ($programStmt->execute()) {
          $programResult = $programStmt->get_result();
          $programRow = $programResult ? $programResult->fetch_assoc() : null;
          if (is_array($programRow)) {
            $displayProgramYearLevel = trim((string)($programRow["program_year"] ?? ""));
          }
          if ($programResult instanceof mysqli_result) {
            $programResult->free();
          }
        }
        $programStmt->close();
      }
    }
  }
}

foreach ($sectionFields as $sectionKey => $fields) {
  foreach ($fields as $field) {
    $score = (int)($ratings[$field] ?? 0);
    if ($score >= 1 && $score <= 5) {
      $sectionWeightedTotals[$sectionKey][$score] += $score;
      $overallWeightedTotals[$score] += $score;
    }
  }
}

$checkMark = static function (array $ratingMap, string $field, int $scale): string {
  return ((int)($ratingMap[$field] ?? 0) === $scale) ? "&#10003;" : "";
};

$weightedTotalText = static function (array $weightedTotals, int $scale): string {
  $value = (int)($weightedTotals[$scale] ?? 0);
  return $value > 0 ? (string)$value : "";
};

$sectionScoreTotals = ["a" => 0.0, "b" => 0.0, "c" => 0.0];
$sectionRatedCounts = ["a" => 0, "b" => 0, "c" => 0];
foreach ($sectionFields as $sectionKey => $fields) {
  foreach ($fields as $field) {
    $score = (int)($ratings[$field] ?? 0);
    if ($score >= 1 && $score <= 5) {
      $sectionScoreTotals[$sectionKey] += $score;
      $sectionRatedCounts[$sectionKey]++;
    }
  }
}

$sectionAAvg = $sectionRatedCounts["a"] > 0 ? ($sectionScoreTotals["a"] / $sectionRatedCounts["a"]) : null;
$sectionBAvg = $sectionRatedCounts["b"] > 0 ? ($sectionScoreTotals["b"] / $sectionRatedCounts["b"]) : null;
$sectionCAvg = $sectionRatedCounts["c"] > 0 ? ($sectionScoreTotals["c"] / $sectionRatedCounts["c"]) : null;

$overallAvg = null;
if (array_sum($sectionRatedCounts) > 0) {
  $overallAvg = array_sum($sectionScoreTotals) / array_sum($sectionRatedCounts);
}

$truncateAverage = static function (?float $value): ?float {
  if ($value === null) {
    return null;
  }

  if ($value >= 0) {
    return floor($value * 100) / 100;
  }

  return ceil($value * 100) / 100;
};

$formatAverage = static function (?float $value) use ($truncateAverage): string {
  $truncatedValue = $truncateAverage($value);
  return $truncatedValue === null ? "" : number_format($truncatedValue, 2, ".", "");
};

$verbalFromAverage = static function (?float $value): string {
  if ($value === null || $value <= 0) {
    return "";
  }

  if ($value >= 5.0) {
    return "Excellent";
  }
  if ($value >= 4.0) {
    return "Very Good";
  }
  if ($value >= 3.0) {
    return "Good";
  }
  if ($value >= 2.0) {
    return "Fair";
  }
  return "Poor";
};

$extractLastName = static function (string $fullName): string {
  $clean = trim(preg_replace('/\s+/', ' ', $fullName));
  if ($clean === '') {
    return '';
  }

  $parts = preg_split('/\s+/', $clean);
  if (!is_array($parts) || empty($parts)) {
    return '';
  }

  $suffixes = ["JR", "SR", "II", "III", "IV", "V", "PHD", "MD", "MAED", "RGC", "MMBM", "MACDDS"];
  while (!empty($parts)) {
    $candidate = strtoupper(trim((string)end($parts), "., "));
    if ($candidate === "") {
      array_pop($parts);
      continue;
    }
    if (in_array($candidate, $suffixes, true)) {
      array_pop($parts);
      continue;
    }
    break;
  }

  if (empty($parts)) {
    return '';
  }

  return trim((string)end($parts), ",. ");
};

$ccHeadLabel = "Sir/maam.";
$headLastName = $displayHeadAccountLastName !== ""
  ? $displayHeadAccountLastName
  : $extractLastName($displayHeadOfOffice);
if ($headLastName !== "") {
  $ccHeadLabel = "Sir/Maam. " . $headLastName;
}

$ccAssistantLabel = "Mr./Ms.";
$assistantFullName = trim($displayApplicantName);
if ($assistantFullName !== "") {
  $ccAssistantLabel .= " " . $assistantFullName;
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Student Assistants Evaluation List</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <link rel="stylesheet" href="../assets/css/tailwind.css">

    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
      rel="stylesheet"
    />
    <link
      href="https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap"
      rel="stylesheet"
    />

    <style>
      /* Custom scrollbar for sidebar */
      ::-webkit-scrollbar {
        width: 6px;
      }
      ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
        border-radius: 999px;
      }

      /* Department evaluation form styling */
      .eval-page {
        font-family: "Times New Roman", serif;
        color: #111827;
        background: #ffffff;
      }

      .eval-page header {
        text-align: center;
        margin-bottom: 1rem;
      }

      .eval-page .header-top {
        margin-top: 3rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 0.5rem;
      }

      .eval-page .header-logo,
      .eval-page .header-cert {
        display: flex;
        justify-content: center;
        align-items: center;
      }

      .eval-page .header-logo img {
        width: 80px;
        height: 80px;
        object-fit: contain;
      }

      .eval-page .header-center {
        line-height: 1.2;
        text-align: center;
      }

      .eval-page .header-center h1 {
        font-weight: 700;
        font-size: 16pt;
        margin: 0;
      }

      .eval-page .header-center p {
        margin: 0;
        font-size: 10pt;
      }

      .eval-page .header-cert img {
        width: 100px;
        height: 80px;
        object-fit: contain;
      }

      .eval-page .paper {
        padding: 1rem 1.4rem 2.5rem;
      }

      .eval-page .title {
        font-size: 15px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        text-align: center;
        margin-bottom: 1rem;
      }

      .eval-page .info-block {
        margin-bottom: 1.1rem;
        font-size: 12px;
      }

      .eval-page .info-row {
        display: grid;
        grid-template-columns: 235px minmax(0, 320px);
        justify-content: start;
        align-items: end;
        gap: 0.45rem;
        margin-bottom: 0.3rem;
      }

      .eval-page .info-label {
        font-weight: 600;
      }

      .eval-page .info-value {
        border-bottom: 1px solid #111827;
        min-height: 1rem;
        padding-left: 0.35rem;
      }

      .eval-page .direction {
        font-size: 12px;
        line-height: 1.5;
        margin-bottom: 1.1rem;
      }

      .eval-page table {
        width: 100%;
        border-collapse: collapse;
      }

      .eval-page th,
      .eval-page td {
        border: 1px solid #111827;
        padding: 0.38rem 0.55rem;
        font-size: 12px;
        vertical-align: middle;
      }

      .eval-page th {
        background: #f7f7f7;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
      }

      .eval-page .scale-table {
        border: none;
        margin-bottom: 1.2rem;
      }

      .eval-page .scale-table th,
      .eval-page .scale-table td {
        border: none;
      }

      .eval-page .scale-table th {
        background: transparent;
        border-bottom: 1px solid #111827;
        padding-bottom: 0.5rem;
      }

      .eval-page .scale-table td {
        padding: 0.5rem 0.55rem;
        font-size: 11px;
      }

      .eval-page .scale-table td:first-child {
        width: 9%;
        text-align: center;
        font-weight: 700;
      }

      .eval-page .rating-table th {
        font-size: 11px;
        text-align: center;
      }

      .eval-page .rating-table td {
        height: 2.05rem;
        text-align: center;
      }

      .eval-page .rating-table td:first-child {
        text-align: left;
      }

      .eval-page .section-label {
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background: #f3f4f6;
      }

      .eval-page .subtotal {
        font-weight: 600;
      }

      .eval-page .comment-box {
        border: 1px solid #111827;
        min-height: 3.2rem;
        margin-top: 0.6rem;
        padding: 0.45rem 0.6rem;
        font-size: 12px;
        line-height: 1.45;
      }

      .eval-page .signature-row {
        display: flex;
        justify-content: flex-end;
        margin-top: 2.4rem;
      }

      .eval-page .signature-block {
        width: 220px;
        font-size: 12px;
        text-align: center;
      }

      .eval-page .signature-line {
        border-top: 1px solid #111827;
        margin-top: 0.9rem;
        padding-top: 0.25rem;
        text-align: center;
      }

      .eval-page footer {
        margin-top: 0rem;
      }

      .eval-page footer img {
        width: 100%;
        height: auto;
        object-fit: contain;
      }

      .eval-page .footer-box {
        margin-top: 0rem;
        display: flex;
        justify-content: flex-start;
        padding-left: 0.25rem;
      }

      .eval-page .footer-box img {
        width: 13rem;
        max-width: calc(100% - 0.5rem);
        height: auto;
        object-fit: contain;
      }

      /* Evaluation result block pulled from copyOfEval.php */
      .eval-result {
        font-family: "Times New Roman", serif;
        color: #111827;
      }

      .eval-result .result-table {
        border-collapse: collapse;
        width: 100%;
      }

      .eval-result .result-table th,
      .eval-result .result-table td {
        border: 1px solid #000;
        padding: 6px 8px;
        font-size: 12px;
      }

      @page {
        /* Use long bond (8.5in x 13in) for printing */
        size: 8.5in 13in;
        margin: 0.28in;
      }

      @media print {
        * {
          -webkit-print-color-adjust: exact;
          print-color-adjust: exact;
        }

        /* Hide all page chrome — sidebar, topbar, buttons, separator */
        body.is-printing #sidebar,
        body.is-printing .page-header,
        body.is-printing main > header {
          display: none !important;
        }

        body.is-printing main {
          margin-left: 0 !important;
          padding-top: 0 !important;
          background: white !important;
        }

        body.is-printing #evaluation-details {
          margin-top: 0 !important;
          padding: 0 !important;
          background: none !important;
        }

        /* Hide print buttons, separator, and doc label (everything that isn't a print section) */
        body.is-printing #evaluation-details > div:not(#print-eval-form):not(#print-eval-result) {
          display: none !important;
        }

        /* Hide the non-target document */
        body.printing-eval-form #print-eval-result {
          display: none !important;
        }

        body.printing-eval-result #print-eval-form {
          display: none !important;
        }

        /* Reset outer wrappers — @page margins handle all spacing */
        #print-eval-form,
        #print-eval-result {
          max-width: 100% !important;
          width: 100% !important;
          margin: 0 !important;
          padding: 0 !important;
          box-shadow: none !important;
          border-radius: 0 !important;
          border: none !important;
          background: #ffffff !important;
        }

        .eval-page {
          background: #ffffff;
        }

        /* ── Document 1: Evaluation Form (portrait 8.5in × 13in) ─────────── */

        #print-eval-form .eval-page {
          font-size: 10px;
          line-height: 1.2;
        }

        #print-eval-form .header-top {
          margin-top: 0;
          gap: 0.4rem;
          margin-bottom: 0.3rem;
        }

        #print-eval-form .header-logo img,
        #print-eval-form .header-cert img {
          width: 60px;
          height: 60px;
        }

        #print-eval-form .header-center h1 {
          font-size: 13pt;
        }

        #print-eval-form .header-center p {
          font-size: 8.5pt;
        }

        #print-eval-form .paper {
          padding: 0.3rem 0.5rem 0.4rem;
        }

        #print-eval-form .title {
          font-size: 11.5px;
          margin-bottom: 0.35rem;
        }

        #print-eval-form .info-block {
          margin-bottom: 0.35rem;
          font-size: 10px;
        }

        #print-eval-form .info-row {
          grid-template-columns: 210px minmax(0, 260px);
          gap: 0.25rem;
          margin-bottom: 0.1rem;
        }

        #print-eval-form .info-value {
          min-height: 0.6rem;
        }

        #print-eval-form .direction {
          font-size: 10px;
          line-height: 1.25;
          margin-bottom: 0.25rem;
        }

        #print-eval-form table th,
        #print-eval-form table td {
          padding: 0.13rem 0.25rem;
          font-size: 10px;
        }

        #print-eval-form .scale-table {
          margin-bottom: 0.35rem;
        }

        #print-eval-form .scale-table td {
          font-size: 9.5px;
          padding: 0.1rem 0.25rem;
        }

        #print-eval-form .rating-table td {
          height: 1.25rem;
        }

        #print-eval-form section.pt-4,
        #print-eval-form section.pt-6 {
          padding-top: 0.2rem !important;
        }

        #print-eval-form .comment-box {
          min-height: 2.2rem;
          padding: 0.25rem 0.4rem;
          line-height: 1.2;
        }

        #print-eval-form .signature-row {
          margin-top: 1rem;
        }

        #print-eval-form .signature-line {
          margin-top: 0.4rem;
        }

        #print-eval-form .footer-box {
          margin-top: 0.2rem;
        }

        #print-eval-form footer {
          margin-top: 0.2rem;
          padding-top: 0.05rem;
        }

        /* ── Document 2: Copy of Evaluation (landscape 11in × 8.5in) ─────── */

        #print-eval-result .eval-result {
          padding: 1rem 1.5rem;
          font-size: 12px;
          line-height: 1.4;
          box-shadow: none !important;
          border: none !important;
          background: transparent !important;
        }

        #print-eval-result header.text-center {
          margin-bottom: 0.7rem !important;
        }

        #print-eval-result .mb-4 {
          margin-bottom: 0.6rem !important;
        }

        #print-eval-result .mb-6 {
          margin-bottom: 1rem !important;
        }

        #print-eval-result .mt-6 {
          margin-top: 1rem !important;
        }

        #print-eval-result .mt-10 {
          margin-top: 1.2rem !important;
        }

        #print-eval-result .gap-8 {
          gap: 1.5rem !important;
        }

        #print-eval-result .result-table {
          border-collapse: collapse;
        }

        #print-eval-result .result-table th,
        #print-eval-result .result-table td {
          padding: 5px 8px;
          font-size: 12px;
        }

        /* Tall enough to fill the page without overflowing landscape height */
        #print-eval-result .result-table .h-16 {
          height: 3rem !important;
          min-height: 0 !important;
        }
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

  <body class="bg-gray-200 font-sans">
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
              data-nav="adminDashboard.php"
              onclick="window.location.href='adminDashboard.php'"
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
              data-nav="approved.php"
              onclick="window.location.href='approved.php'"
            >
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="interviewEvaluation.php"
              onclick="window.location.href='interviewEvaluation.php'"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="ranks.php"
              onclick="window.location.href='ranks.php'"
            >
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="list-of-qualified.php"
              onclick="window.location.href='list-of-qualified.php'"
            >
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="department-evaluation-list.php"
              onclick="window.location.href='department-evaluation-list.php'"
            >
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="summary-report.php"
              onclick="window.location.href='summary-report.php'"
            >
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="institutional-scholars.php"
              onclick="window.location.href='institutional-scholars.php'"
            >
              <i class="fas fa-chart-line w-5"></i>
              <span>Institutional Scholars</span>
            </li>



            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="accounts.php"
              onclick="window.location.href='accounts.php'"
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

      <!-- Main content -->
      <main class="ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pt-14">
        <!-- Top bar -->
        <header
          class="hidden fixed top-0 left-0 md:left-64 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
        >
          <div class="flex items-center gap-2">
            <!-- Mobile menu button -->
            <button
              id="sidebarToggleTop"
              class="md:hidden inline-flex items-center justify-center p-2 rounded bg-[#0d8ddb] focus:outline-none"
              type="button"
            >
              <i class="fas fa-bars"></i>
            </button>
            <span class="text-[11px] font-semibold md:hidden">
              Admission &amp; Scholarship
            </span>
          </div>
          <div class="flex gap-2 text-xs">
            <button
              class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 flex items-center gap-1 font-normal"
              type="button"
            >
              <i class="fas fa-user"></i>
              Admin panel
            </button>
            <button
              class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 font-normal"
              type="button"
            >
              Account
            </button>
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
            DEPARTMENTAL EVALUATION
          </h2>
          </div>
        </section>

        <!-- Department Evaluation Form -->
        <section
          id="evaluation-details"
          class="mt-12 px-4 sm:px-6 py-6 bg-gray-200 flex-1"
        >
          <div class="max-w-6xl mx-auto flex justify-end gap-2 mb-3">
            <button
              type="button"
              class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold text-white bg-[#052c6a] hover:bg-[#0d8ddb] rounded shadow transition"
              onclick="printSection('print-eval-form')"
            >
              <i class="fas fa-print"></i>
              <span>Print Evaluation Form</span>
            </button>
          </div>

          <div id="print-eval-form" class="max-w-6xl mx-auto bg-white rounded-lg shadow-sm">
            <div class="eval-page">
              <header>
                <div class="header-top">
                  <div class="header-logo">
                    <img
                      src="../img/SMCCNEWLOGO.png"
                      alt="Seal of Saint Michael College of Caraga"
                    />
                  </div>
                  <div class="header-center">
                    <h1>Saint Michael College of Caraga</h1>
                    <p>
                      Atupan St.,Brgy. 4, Nasipit, Agusan del Norte, Philippines<br />
                    </p>
                    <p>Tel. Nos. +63 085 343-3251 / +63 085 283-3113</p>
                    <p>
                      <a
                        href="http://www.smccnasipit.edu.ph"
                        style="color: blue; text-decoration: underline;"
                        >www.smccnasipit.edu.ph</a
                      >
                    </p>
                  </div>
                  <div class="header-cert">
                    <img
                      src="../img/SOCO-PAB-1024x672.jpg"
                      alt="SOCOTEC ISO 9001 logo"
                    />
                  </div>
                </div>
              </header>

              <main class="paper">
                <h2 class="title">Student Assistants' Evaluation Form</h2>

                <section class="info-block">
                  <div class="info-row">
                    <span class="info-label">Name of Student Assistant:</span>
                    <span class="info-value"><?= htmlspecialchars($displayApplicantName) ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Semester &amp; School Year:</span>
                    <span class="info-value"><?= htmlspecialchars($displaySemesterSchoolYear) ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Area of Assignment:</span>
                    <span class="info-value"><?= htmlspecialchars($displayAreaOfAssignment) ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Head of Office:</span>
                    <span class="info-value"><?= htmlspecialchars($displayHeadOfOffice) ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Date of Evaluation:</span>
                    <span class="info-value"><?= htmlspecialchars($displayEvaluationDate) ?></span>
                  </div>
                </section>

                <section class="direction">
                  <p>
                    <span class="font-semibold">Direction:</span> Please rate each item below to determine the performance of the assigned student assistant of your respective office/department. Put a check (&#10003;) to rate their performance.
                  </p>
                </section>

                <section>
                  <table class="scale-table">
                    <thead>
                      <tr>
                        <th style="width: 9%;">Scale</th>
                        <th style="width: 18%;">Verbal Description</th>
                        <th>Verbal Interpretation</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>5</td>
                        <td>Excellent</td>
                        <td>This rating is given to student assistants who consistently exceed expectations and demonstrate outstanding performance in their assigned tasks.</td>
                      </tr>
                      <tr>
                        <td>4</td>
                        <td>Very Good</td>
                        <td>This rating is given to student assistants who frequently exceed expectations and demonstrate a high level of performance in their assigned tasks.</td>
                      </tr>
                      <tr>
                        <td>3</td>
                        <td>Good</td>
                        <td>This rating is given to student assistants who consistently meet expectations and perform their assigned tasks satisfactorily.</td>
                      </tr>
                      <tr>
                        <td>2</td>
                        <td>Fair</td>
                        <td>This rating is given to student assistants who occasionally meet expectations but may have areas for improvement.</td>
                      </tr>
                      <tr>
                        <td>1</td>
                        <td>Poor</td>
                        <td>This rating is given to student assistants who consistently fail to meet expectations and demonstrate unsatisfactory performance in their assigned tasks.</td>
                      </tr>
                    </tbody>
                  </table>
                </section>

                <section class="pt-4">
                  <table class="rating-table">
                    <thead>
                      <tr>
                        <th rowspan="2" style="width: 47%;">Performance Indicators</th>
                        <th colspan="5">Rating</th>
                      </tr>
                      <tr>
                        <th>Excellent (5)</th>
                        <th>Very Good (4)</th>
                        <th>Good (3)</th>
                        <th>Fair (2)</th>
                        <th>Poor (1)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td class="section-label">A. Quality and Quantity of Work</td>
                        <td></td><td></td><td></td><td></td><td></td>
                      </tr>
                      <tr>
                        <td>A.1 Accurate at work assigned</td>
                        <td><?= $checkMark($ratings, "score-a1", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-a1", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-a1", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-a1", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-a1", 1) ?></td>
                      </tr>
                      <tr>
                        <td>A.2 Always completes tasks</td>
                        <td><?= $checkMark($ratings, "score-a2", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-a2", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-a2", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-a2", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-a2", 1) ?></td>
                      </tr>
                      <tr>
                        <td>A.3 Works in a timely manner</td>
                        <td><?= $checkMark($ratings, "score-a3", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-a3", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-a3", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-a3", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-a3", 1) ?></td>
                      </tr>
                      <tr>
                        <td>A.4 Asks for more work when assigned tasks are done</td>
                        <td><?= $checkMark($ratings, "score-a4", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-a4", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-a4", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-a4", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-a4", 1) ?></td>
                      </tr>
                      <tr>
                        <td>A.5 Easily accepts new responsibilities</td>
                        <td><?= $checkMark($ratings, "score-a5", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-a5", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-a5", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-a5", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-a5", 1) ?></td>
                      </tr>
                      <tr>
                        <td class="subtotal">Total</td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["a"], 5)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["a"], 4)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["a"], 3)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["a"], 2)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["a"], 1)) ?></td>
                      </tr>

                      <tr>
                        <td class="section-label">B. Interpersonal Skills</td>
                        <td></td><td></td><td></td><td></td><td></td>
                      </tr>
                      <tr>
                        <td>B.1 Answers patron's questions accurately</td>
                        <td><?= $checkMark($ratings, "score-b1", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-b1", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-b1", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-b1", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-b1", 1) ?></td>
                      </tr>
                      <tr>
                        <td>B.2 Deals with patrons well</td>
                        <td><?= $checkMark($ratings, "score-b2", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-b2", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-b2", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-b2", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-b2", 1) ?></td>
                      </tr>
                      <tr>
                        <td>B.3 Deals with personnel well</td>
                        <td><?= $checkMark($ratings, "score-b3", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-b3", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-b3", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-b3", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-b3", 1) ?></td>
                      </tr>
                      <tr>
                        <td>B.4 Knows how to effectively communicate with others</td>
                        <td><?= $checkMark($ratings, "score-b4", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-b4", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-b4", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-b4", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-b4", 1) ?></td>
                      </tr>
                      <tr>
                        <td>B.5 Has a good relationship with other student assistants</td>
                        <td><?= $checkMark($ratings, "score-b5", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-b5", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-b5", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-b5", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-b5", 1) ?></td>
                      </tr>
                      <tr>
                        <td class="subtotal">Total</td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["b"], 5)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["b"], 4)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["b"], 3)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["b"], 2)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["b"], 1)) ?></td>
                      </tr>

                      <tr>
                        <td class="section-label">C. Attendance and Reliability</td>
                        <td></td><td></td><td></td><td></td><td></td>
                      </tr>
                      <tr>
                        <td>C.1 Perfect attendance</td>
                        <td><?= $checkMark($ratings, "score-c1", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-c1", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-c1", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-c1", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-c1", 1) ?></td>
                      </tr>
                      <tr>
                        <td>C.2 Reports duty on time, rarely comes late</td>
                        <td><?= $checkMark($ratings, "score-c2", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-c2", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-c2", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-c2", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-c2", 1) ?></td>
                      </tr>
                      <tr>
                        <td>C.3 Following assigned schedule</td>
                        <td><?= $checkMark($ratings, "score-c3", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-c3", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-c3", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-c3", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-c3", 1) ?></td>
                      </tr>
                      <tr>
                        <td>C.4 Able to work without direct supervision</td>
                        <td><?= $checkMark($ratings, "score-c4", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-c4", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-c4", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-c4", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-c4", 1) ?></td>
                      </tr>
                      <tr>
                        <td>C.5 Carries out instructions successfully</td>
                        <td><?= $checkMark($ratings, "score-c5", 5) ?></td>
                        <td><?= $checkMark($ratings, "score-c5", 4) ?></td>
                        <td><?= $checkMark($ratings, "score-c5", 3) ?></td>
                        <td><?= $checkMark($ratings, "score-c5", 2) ?></td>
                        <td><?= $checkMark($ratings, "score-c5", 1) ?></td>
                      </tr>
                      <tr>
                        <td class="subtotal">Total</td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["c"], 5)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["c"], 4)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["c"], 3)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["c"], 2)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals["c"], 1)) ?></td>
                      </tr>

                      <tr>
                        <td class="subtotal">Over-all Total</td>
                        <td><?= htmlspecialchars($weightedTotalText($overallWeightedTotals, 5)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($overallWeightedTotals, 4)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($overallWeightedTotals, 3)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($overallWeightedTotals, 2)) ?></td>
                        <td><?= htmlspecialchars($weightedTotalText($overallWeightedTotals, 1)) ?></td>
                      </tr>
                    </tbody>
                  </table>
                </section>

                <section class="pt-6 text-[12px]">
                  <p class="font-semibold">D. Strength(s) / Area(s) for Improvement:</p>
                  <div class="comment-box">
                    <?php
                      $combinedStrAreas = array_filter([$displayStrengths, $displayAreasImprovement], fn($s) => $s !== "");
                      $combinedStrAreas = implode(" / ", $combinedStrAreas);
                      echo $combinedStrAreas !== "" ? nl2br(htmlspecialchars($combinedStrAreas)) : "&nbsp;";
                    ?>
                  </div>
                </section>

                <section class="pt-4 text-[12px]">
                  <p class="font-semibold">E. Evaluator's Comment(s)/Recommendation:</p>
                  <div class="comment-box">
                    <?= $displayRecommendations !== "" ? nl2br(htmlspecialchars($displayRecommendations)) : "&nbsp;" ?>
                  </div>
                </section>

                <div class="signature-row">
                  <div class="signature-block">
                    <?php if ($displaySignatureData !== ""): ?>
                        <img src="<?= htmlspecialchars($displaySignatureData) ?>" alt="Evaluator signature" class="mx-auto h-12 object-contain" />
                      <?php endif; ?>
                    <div class="signature-line">
                     <p>Evaluator's Signature</p>

                    </div>
                  </div>
                </div>

                <div class="footer-box">
                  <img src="../img/newboxHead.png" alt="footer box" />
                </div>
              </main>

              <footer>
                <img src="../img/newfooter.jpg" alt="SMCC footer" />
              </footer>
            </div>
          </div>

          <!-- Separator between documents with light design -->
          <div class="max-w-6xl mx-auto mt-6 mb-6 px-2">
            <div class="flex items-center gap-3 text-[11px] text-gray-500">
              <div class="flex-1 h-px bg-gradient-to-r from-transparent via-gray-300 to-gray-200"></div>
              <div class="px-3 py-1 rounded-full border border-dashed border-gray-300 bg-white shadow-sm uppercase tracking-wide font-semibold">
                Next Document
              </div>
              <div class="flex-1 h-px bg-gradient-to-l from-transparent via-gray-300 to-gray-200"></div>
            </div>
          </div>

          <!-- Evaluation Result (from copyOfEval.php) -->
          <div class="max-w-6xl mx-auto flex items-center justify-between gap-2 mt-8 mb-3 px-2">
            <span class="inline-flex items-center gap-2 px-3 py-1 text-[11px] font-semibold text-[#052c6a] uppercase tracking-wide bg-[#f8fafc] border border-[#0d8ddb]/30 rounded-full shadow-sm">
              <i class="fas fa-copy text-[10px]"></i>
              Copy of Evaluation
            </span>
            <button
              type="button"
              class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold text-white bg-[#052c6a] hover:bg-[#0d8ddb] rounded shadow transition"
              onclick="printSection('print-eval-result')"
            >
              <i class="fas fa-print"></i>
              <span>Print Evaluation Result</span>
            </button>
          </div>

          <div id="print-eval-result" class="max-w-6xl mx-auto mt-0 bg-white rounded-lg shadow-sm">
            <div class="eval-result p-6 sm:p-8">
              <header class="text-center mb-4">
                <div class="flex flex-wrap items-center justify-center gap-4 mb-2">
                  <img
                    src="../img/SMCCNEWLOGO.png"
                    alt="Seal of Saint Michael College of Caraga"
                    class="w-20 h-20 object-contain"
                  />
                  <div class="leading-tight text-center">
                    <h1 class="font-bold text-[16pt] m-0">Saint Michael College of Caraga</h1>
                    <p class="m-0 text-[10pt]">
                      Atupan St.,Brgy. 4, Nasipit, Agusan del Norte, Philippines<br />
                    </p>
                    <p class="m-0 text-[10pt]">Tel. Nos. +63 085 343-3251 / +63 085 283-3113</p>
                    <p class="m-0 text-[10pt]">
                      <a
                        href="http://www.smccnasipit.edu.ph"
                        style="color: blue; text-decoration: underline;"
                        >www.smccnasipit.edu.ph</a
                      >
                    </p>
                  </div>
                  <img
                    src="../img/SOCO-PAB-1024x672.jpg"
                    alt="SOCOTEC ISO 9001 logo"
                    class="w-24 h-20 object-contain"
                  />
                </div>
              </header>

              <section class="text-center mb-6">
                <h2 class="font-bold text-[13pt] tracking-wide m-0">STUDENT ASSISTANTS' EVALUATION RESULT</h2>
                <p class="text-[11pt] mt-1 mb-0"><?= htmlspecialchars($displaySemesterSchoolYear) ?></p>
              </section>

              <div class="max-w-4xl mx-auto text-[12px] font-serif space-y-2 mb-6">
                <div class="flex items-center max-w-3xl">
                  <span class="w-56">Name of Student Assistant</span>
                  <span>:</span>
                  <span class="border-b border-black ml-2 h-5 inline-block w-80"><?= htmlspecialchars($displayApplicantName) ?></span>
                </div>
                <div class="flex items-center max-w-3xl">
                  <span class="w-56">Program/Year Level</span>
                  <span>:</span>
                  <span class="border-b border-black ml-2 h-5 inline-block w-80"><?= htmlspecialchars($displayProgramYearLevel) ?></span>
                </div>
                <div class="flex items-center max-w-3xl">
                  <span class="w-56">Assigned Office</span>
                  <span>:</span>
                  <span class="border-b border-black ml-2 h-5 inline-block w-80"><?= htmlspecialchars($displayAreaOfAssignment) ?></span>
                </div>
                <div class="flex items-center max-w-3xl">
                  <span class="w-56">Head of Office</span>
                  <span>:</span>
                  <span class="border-b border-black ml-2 h-5 inline-block w-80"><?= htmlspecialchars($displayHeadOfOffice) ?></span>
                </div>
              </div>

              <div class="max-w-3xl mx-auto">
                <table class="result-table">
                  <thead>
                    <tr>
                      <th class="w-1/2">Criteria</th>
                      <th class="w-24">Rating</th>
                      <th>Verbal Description</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>A. Quality and Quantity of Work</td>
                      <td><?= htmlspecialchars($formatAverage($sectionAAvg)) ?></td>
                      <td><?= htmlspecialchars($verbalFromAverage($sectionAAvg)) ?></td>
                    </tr>
                    <tr>
                      <td>B. Interpersonal Skills</td>
                      <td><?= htmlspecialchars($formatAverage($sectionBAvg)) ?></td>
                      <td><?= htmlspecialchars($verbalFromAverage($sectionBAvg)) ?></td>
                    </tr>
                    <tr>
                      <td>C. Attendance and Reliability</td>
                      <td><?= htmlspecialchars($formatAverage($sectionCAvg)) ?></td>
                      <td><?= htmlspecialchars($verbalFromAverage($sectionCAvg)) ?></td>
                    </tr>
                    <tr>
                      <td class="font-semibold">Overall Rating</td>
                      <td><?= htmlspecialchars($formatAverage($overallAvg)) ?></td>
                      <td><?= htmlspecialchars($verbalFromAverage($overallAvg)) ?></td>
                    </tr>
                    <tr>
                      <td>D. Strength(s) / Area(s) for Improvement</td>
                      <td colspan="2"><div class="h-16 whitespace-pre-wrap"><?php
                        $combinedStrAreas2 = array_filter([$displayStrengths, $displayAreasImprovement], fn($s) => $s !== "");
                        echo htmlspecialchars(implode(" / ", $combinedStrAreas2));
                      ?></div></td>
                    </tr>
                    <tr>
                      <td>E. Evaluator's Comment(s)/Recommendation</td>
                      <td colspan="2"><div class="h-16 whitespace-pre-wrap"><?= htmlspecialchars($displayRecommendations) ?></div></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="max-w-4xl mx-auto grid grid-cols-1 sm:grid-cols-3 gap-8 mt-6 text-[12px] font-serif">
                <div>
                  <p class="mb-8">Prepared by:</p>
                  <p class="font-bold">ARLYN B. TUYOGON, MM</p>
                  <p>Head, Admission &amp; Scholarship</p>
                </div>
                <div>
                  <p class="mb-8">Checked by:</p>
                  <p class="font-bold">FELMARIE MANLUNAS, MACDDS</p>
                  <p>Head, Student Affairs &amp; Services</p>
                </div>
                <div>
                  <p class="mb-8">Noted by:</p>
                  <p class="font-bold">RICKY E. DESTACAMENTO, RGC, MAED</p>
                  <p>Head, HRMDO</p>
                </div>
              </div>

              <div class="max-w-4xl mx-auto mt-10 text-[12px] font-serif space-y-2">
                <p class="m-0">CC:</p>
                <div class="flex items-center gap-6">
                  <div class="flex items-center gap-2">
                    <span><?= htmlspecialchars($ccHeadLabel) ?></span>
                    <span class="border-b border-black w-48 h-5 inline-block"></span>
                    <span class="ml-2">Date Received:</span>
                    <span class="border-b border-black w-32 h-5 inline-block"></span>
                  </div>
                </div>
                <div class="flex items-center gap-6">
                  <div class="flex items-center gap-2">
                    <span><?= htmlspecialchars($ccAssistantLabel) ?></span>
                    <span class="border-b border-black w-48 h-5 inline-block"></span>
                    <span class="ml-2">Date Received:</span>
                    <span class="border-b border-black w-32 h-5 inline-block"></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>

    <script>
      // Sidebar + Active highlight (same behavior as your evaluation list page)
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");
        if (!sidebar) return;

        // Mobile toggle
        if (toggleBtn) {
          toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
          });
        }

        // Close sidebar when clicking any nav item on small screens
        sidebar.querySelectorAll("[data-nav]").forEach((item) => {
          item.addEventListener("click", (event) => {
            if (event.target.closest("summary")) {
              return;
            }
            if (window.innerWidth < 768) {
              sidebar.classList.add("-translate-x-full");
            }
          });
        });

        // Active highlight
        const currentPage = window.location.pathname.split("/").pop().toLowerCase();
        const sidebarAliases = {
          "summary-of-applicants.php": "applicant.php",
          "declined-applicants.php": "applicant.php",
          "reserved-applicants.php": "applicant.php",
          "view-application.php": "applicant.php",
          "department-evaluation-indi.php": "department-evaluation-list.php",
          "summary-reports.php": "summary-report.php",
          "list-0f-qualified.php": "list-of-qualified.php",
        };
        const activePage = (sidebarAliases[currentPage] || currentPage).toLowerCase();

        sidebar.querySelectorAll("[data-nav]").forEach((item) => {
          const target = (item.dataset.nav || "").toLowerCase();
          const isActive = target === activePage;

          item.classList.toggle("bg-[#fcdc2f]", isActive);
          item.classList.toggle("bg-opacity-90", isActive);
          item.classList.toggle("text-[#052c6a]", isActive);
          item.classList.toggle("hover:bg-white/15", !isActive);
        });
      });

      function printSection(sectionId) {
        const isResult = sectionId === "print-eval-result";

        // Dynamically set the correct @page size for this document
        let pageOverride = document.getElementById("page-size-override");
        if (!pageOverride) {
          pageOverride = document.createElement("style");
          pageOverride.id = "page-size-override";
          document.head.appendChild(pageOverride);
        }
        pageOverride.textContent = isResult
          ? "@page { size: 11in 8.5in; margin: 0.2in; }"
          : "@page { size: 8.5in 13in; margin: 0.28in; }";

        // Add body classes so @media print CSS knows what to show/hide
        document.body.classList.add("is-printing", isResult ? "printing-eval-result" : "printing-eval-form");

        // Clean up after print dialog closes
        const cleanup = () => {
          document.body.classList.remove("is-printing", "printing-eval-form", "printing-eval-result");
          pageOverride.textContent = "";
          window.removeEventListener("afterprint", cleanup);
        };
        window.addEventListener("afterprint", cleanup);

        window.print();
      }
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










