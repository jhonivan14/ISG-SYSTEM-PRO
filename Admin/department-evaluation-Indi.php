<?php
// Guide: Detailed department evaluation viewer and print page for one scholar or evaluation record.
// Trace: load scholar/evaluation data -> compute summaries -> render sections -> print/sidebar helpers.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once "../db.php";
require_once "../includes/administrator-sa-assignments.php";
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
$selectedEvaluationSemester = "";
$selectedEvaluationSchoolYear = "";
$evaluationDisplayRecords = [];
$evaluatorOptions = ["Self", "Head of Office", "Administrator"];
$displayEvaluatorRole = "department_head";
$evaluatorType = "Head of Office";

$ratingOptions = [
  4 => [
    "label" => "Very Good",
    "short" => "VG",
    "interpretation" => "Consistently exceeds the performance expectations stated in the indicator.",
  ],
  3 => [
    "label" => "Good",
    "short" => "G",
    "interpretation" => "Consistently meets the performance expectations stated in the indicator.",
  ],
  2 => [
    "label" => "Poor",
    "short" => "P",
    "interpretation" => "Frequently falls below the performance expectations stated in the indicator and requires improvement.",
  ],
  1 => [
    "label" => "Needs Improvement",
    "short" => "NI",
    "interpretation" => "Consistently fails to meet the performance expectations stated in the indicator and requires close supervision and additional guidance.",
  ],
];
$evaluationSections = [
  "a" => [
    "title" => "A. Quality and Quantity of Work",
    "criteria" => [
      "score-a1" => "A.1 Completes assigned tasks accurately and with minimal errors.",
      "score-a2" => "A.2 Completes assigned tasks thoroughly and according to instructions.",
      "score-a3" => "A.3 Completes work within the required time.",
      "score-a4" => "A.4 Demonstrates initiative by seeking additional responsibilities after completing assigned works.",
      "score-a5" => "A.5 Willingly accepts new assignments and responsibilities.",
    ],
  ],
  "b" => [
    "title" => "B. Interpersonal Skills",
    "criteria" => [
      "score-b1" => "B.1 Communicates clearly and respectfully with students, employees, and visitors.",
      "score-b2" => "B.2 Demonstrates courtesy and professionalism when assisting students, employees, parents, and visitors.",
      "score-b3" => "B.3 Works cooperatively with office personnel and fellow student assistants.",
      "score-b4" => "B.4 Responds appropriately to questions, concerns, and requests.",
      "score-b5" => "B.5 Contributes positively to teamwork and collaborates effectively with colleagues.",
    ],
  ],
  "c" => [
    "title" => "C. Attendance and Reliability",
    "criteria" => [
      "score-c1" => "C.1 Maintains regular attendance and provides timely notification for any authorized absence.",
      "score-c2" => "C.2 Reports for duty punctually and observes the assigned work schedule.",
      "score-c3" => "C.3 Participates actively in institutional activities, meetings, orientations, and trainings when required.",
      "score-c4" => "C.4 Works responsibly with minimal supervision.",
      "score-c5" => "C.5 Follows instructions and completes assigned responsibilities consistently.",
    ],
  ],
  "d" => [
    "title" => "D. Professionalism and Ethical Conduct",
    "criteria" => [
      "score-d1" => "D.1 Demonstrates honesty and integrity in performing assigned duties.",
      "score-d2" => "D.2 Maintains confidentiality of office records and information.",
      "score-d3" => "D.3 Shows respect for institutional policies and procedures.",
      "score-d4" => "D.4 Maintains a positive attitude and professional demeanor while performing assigned duties.",
      "score-d5" => "D.5 Observes proper dress code and behaves appropriately while on duty.",
    ],
  ],
];

$ratings = [];
$sectionFields = [];
$sectionWeightedTotals = [];
foreach ($evaluationSections as $sectionKey => $section) {
  $sectionFields[$sectionKey] = array_keys($section["criteria"]);
  $sectionWeightedTotals[$sectionKey] = array_fill_keys(array_keys($ratingOptions), 0);
  foreach ($sectionFields[$sectionKey] as $field) {
    $ratings[$field] = 0;
  }
}
$overallWeightedTotals = array_fill_keys(array_keys($ratingOptions), 0);
$resolvedScholarRow = null;
$loadedByEvaluationId = false;

// Helpers below load the selected evaluation record and format print-friendly score summaries.

$evaluatorTypeFromRole = static function (string $role): string {
  $role = isgNormalizeEvaluatorRole($role);
  if ($role === "student_assistant") {
    return "Self";
  }
  if ($role === "administrator") {
    return "Administrator";
  }
  return "Head of Office";
};

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
      dhe.evaluator_role,
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
              TRIM(COALESCE(u.name, '')),
              CASE
              WHEN TRIM(COALESCE(u.name, '')) <> '' AND TRIM(COALESCE(u.lastname, '')) <> '' THEN ' '
              ELSE ''
            END,
            TRIM(COALESCE(u.lastname, ''))
          )
        )
        FROM users u
        WHERE u.username = dhe.head_username
          AND u.role = dhe.evaluator_role
        LIMIT 1
      ) AS head_account_full_name,
      (
        SELECT TRIM(COALESCE(u.lastname, ''))
        FROM users u
        WHERE u.username = dhe.head_username
          AND u.role = dhe.evaluator_role
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
        $selectedEvaluationSemester = $semester;
        $selectedEvaluationSchoolYear = $schoolYear;
        if ($semester !== "" && $schoolYear !== "") {
          $displaySemesterSchoolYear = $semester . ", S.Y. " . $schoolYear;
        } elseif ($semester !== "") {
          $displaySemesterSchoolYear = $semester;
        } elseif ($schoolYear !== "") {
          $displaySemesterSchoolYear = $schoolYear;
        }
        $displayAreaOfAssignment = trim((string)($evaluationRow["assigned_office"] ?? ""));
        $displayEvaluatorRole = isgNormalizeEvaluatorRole((string)($evaluationRow["evaluator_role"] ?? "department_head"));
        $evaluatorType = $evaluatorTypeFromRole($displayEvaluatorRole);
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
            if ($score >= 1 && $score <= 4) {
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
        evaluator_role,
        evaluation_date,
        ratings_json,
        strengths,
        areas_improvement,
        recommendations,
        signature_data,
        (
          SELECT TRIM(
            CONCAT(
              TRIM(COALESCE(u.name, '')),
              CASE
                WHEN TRIM(COALESCE(u.name, '')) <> '' AND TRIM(COALESCE(u.lastname, '')) <> '' THEN ' '
                ELSE ''
              END,
              TRIM(COALESCE(u.lastname, ''))
            )
          )
          FROM users u
          WHERE u.username = department_head_evaluations.head_username
            AND u.role = department_head_evaluations.evaluator_role
          LIMIT 1
        ) AS head_account_full_name,
        (
          SELECT TRIM(COALESCE(u.lastname, ''))
          FROM users u
          WHERE u.username = department_head_evaluations.head_username
            AND u.role = department_head_evaluations.evaluator_role
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
          $selectedEvaluationSemester = $semester;
          $selectedEvaluationSchoolYear = $schoolYear;
          if ($semester !== "" && $schoolYear !== "") {
            $displaySemesterSchoolYear = $semester . ", S.Y. " . $schoolYear;
          } elseif ($semester !== "") {
            $displaySemesterSchoolYear = $semester;
          } elseif ($schoolYear !== "") {
            $displaySemesterSchoolYear = $schoolYear;
          }
          $displayAreaOfAssignment = trim((string)($row["assigned_office"] ?? ""));
          $displayEvaluatorRole = isgNormalizeEvaluatorRole((string)($row["evaluator_role"] ?? "department_head"));
          $evaluatorType = $evaluatorTypeFromRole($displayEvaluatorRole);
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
              if ($score >= 1 && $score <= 4) {
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
    if ($score >= 1 && $score <= 4) {
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

$sectionScoreTotals = array_fill_keys(array_keys($sectionFields), 0.0);
$sectionRatedCounts = array_fill_keys(array_keys($sectionFields), 0);
foreach ($sectionFields as $sectionKey => $fields) {
  foreach ($fields as $field) {
    $score = (int)($ratings[$field] ?? 0);
    if ($score >= 1 && $score <= 4) {
      $sectionScoreTotals[$sectionKey] += $score;
      $sectionRatedCounts[$sectionKey]++;
    }
  }
}

$sectionAAvg = $sectionRatedCounts["a"] > 0 ? ($sectionScoreTotals["a"] / $sectionRatedCounts["a"]) : null;
$sectionBAvg = $sectionRatedCounts["b"] > 0 ? ($sectionScoreTotals["b"] / $sectionRatedCounts["b"]) : null;
$sectionCAvg = $sectionRatedCounts["c"] > 0 ? ($sectionScoreTotals["c"] / $sectionRatedCounts["c"]) : null;
$sectionDAvg = $sectionRatedCounts["d"] > 0 ? ($sectionScoreTotals["d"] / $sectionRatedCounts["d"]) : null;

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

  if ($value >= 4.0) {
    return "Very Good";
  }
  if ($value >= 3.0) {
    return "Good";
  }
  if ($value >= 2.0) {
    return "Poor";
  }
  return "Needs Improvement";
};

$buildEvaluationDisplayRecord = static function (array $row) use ($ratings, $sectionFields, $ratingOptions, $evaluatorTypeFromRole): array {
  $recordRatings = $ratings;
  $decodedRatings = json_decode((string)($row["ratings_json"] ?? ""), true);
  if (is_array($decodedRatings)) {
    foreach ($recordRatings as $field => $unusedValue) {
      $score = isset($decodedRatings[$field]) ? (int)$decodedRatings[$field] : 0;
      if ($score >= 1 && $score <= 4) {
        $recordRatings[$field] = $score;
      }
    }
  }

  $recordSectionWeightedTotals = [];
  foreach ($sectionFields as $sectionKey => $fields) {
    $recordSectionWeightedTotals[$sectionKey] = array_fill_keys(array_keys($ratingOptions), 0);
  }
  $recordOverallWeightedTotals = array_fill_keys(array_keys($ratingOptions), 0);
  $recordSectionScoreTotals = array_fill_keys(array_keys($sectionFields), 0.0);
  $recordSectionRatedCounts = array_fill_keys(array_keys($sectionFields), 0);

  foreach ($sectionFields as $sectionKey => $fields) {
    foreach ($fields as $field) {
      $score = (int)($recordRatings[$field] ?? 0);
      if ($score >= 1 && $score <= 4) {
        $recordSectionWeightedTotals[$sectionKey][$score] += $score;
        $recordOverallWeightedTotals[$score] += $score;
        $recordSectionScoreTotals[$sectionKey] += $score;
        $recordSectionRatedCounts[$sectionKey]++;
      }
    }
  }

  $recordEvaluatorRole = isgNormalizeEvaluatorRole((string)($row["evaluator_role"] ?? "department_head"));
  if ($recordEvaluatorRole === "") {
    $recordEvaluatorRole = "department_head";
  }

  $semester = trim((string)($row["semester"] ?? ""));
  $schoolYear = trim((string)($row["school_year"] ?? ""));
  $semesterSchoolYear = "";
  if ($semester !== "" && $schoolYear !== "") {
    $semesterSchoolYear = $semester . ", S.Y. " . $schoolYear;
  } elseif ($semester !== "") {
    $semesterSchoolYear = $semester;
  } elseif ($schoolYear !== "") {
    $semesterSchoolYear = $schoolYear;
  }

  $resolvedHeadFullName = trim((string)($row["head_account_full_name"] ?? ""));
  $headOfOffice = $resolvedHeadFullName !== ""
    ? $resolvedHeadFullName
    : trim((string)($row["head_name"] ?? ""));
  if ($headOfOffice === "") {
    $headOfOffice = trim((string)($row["head_username"] ?? ""));
  }

  $evaluationDate = "";
  $rawDate = trim((string)($row["evaluation_date"] ?? ""));
  if ($rawDate !== "") {
    $parsedDate = strtotime($rawDate);
    $evaluationDate = $parsedDate !== false ? date("F j, Y", $parsedDate) : $rawDate;
  }

  $overallRatedCount = array_sum($recordSectionRatedCounts);

  return [
    "evaluation_id" => (int)($row["evaluation_id"] ?? ($row["id"] ?? 0)),
    "application_id" => (int)($row["application_id"] ?? 0),
    "applicant_name" => trim((string)($row["applicant_name"] ?? "")),
    "semester_school_year" => $semesterSchoolYear,
    "area_of_assignment" => trim((string)($row["assigned_office"] ?? "")),
    "head_of_office" => $headOfOffice,
    "head_account_lastname" => trim((string)($row["head_account_lastname"] ?? "")),
    "evaluation_date" => $evaluationDate,
    "program_year_level" => trim((string)($row["program_year"] ?? "")),
    "evaluator_role" => $recordEvaluatorRole,
    "evaluator_type" => $evaluatorTypeFromRole($recordEvaluatorRole),
    "ratings" => $recordRatings,
    "section_weighted_totals" => $recordSectionWeightedTotals,
    "overall_weighted_totals" => $recordOverallWeightedTotals,
    "section_a_avg" => $recordSectionRatedCounts["a"] > 0 ? ($recordSectionScoreTotals["a"] / $recordSectionRatedCounts["a"]) : null,
    "section_b_avg" => $recordSectionRatedCounts["b"] > 0 ? ($recordSectionScoreTotals["b"] / $recordSectionRatedCounts["b"]) : null,
    "section_c_avg" => $recordSectionRatedCounts["c"] > 0 ? ($recordSectionScoreTotals["c"] / $recordSectionRatedCounts["c"]) : null,
    "section_d_avg" => $recordSectionRatedCounts["d"] > 0 ? ($recordSectionScoreTotals["d"] / $recordSectionRatedCounts["d"]) : null,
    "overall_avg" => $overallRatedCount > 0 ? (array_sum($recordSectionScoreTotals) / $overallRatedCount) : null,
    "strengths" => trim((string)($row["strengths"] ?? "")),
    "areas_improvement" => trim((string)($row["areas_improvement"] ?? "")),
    "recommendations" => trim((string)($row["recommendations"] ?? "")),
    "signature_data" => trim((string)($row["signature_data"] ?? "")),
  ];
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

if (($conn ?? null) instanceof mysqli && $applicationId !== 0) {
  isgEnsureAdministratorSaAssignmentsTable($conn);

  $selectedScholarRecordId = abs((int)$applicationId);
  $selectedSourceApplicationId = 0;
  $selectedScholarRow = adminDepartmentEvaluationLoadScholarRecord(
    $conn,
    $applicationId < 0 ? abs((int)$applicationId) : $selectedScholarRecordId,
    $applicationId > 0 ? (int)$applicationId : 0
  );
  if (is_array($selectedScholarRow)) {
    $selectedScholarRecordId = (int)($selectedScholarRow["id"] ?? $selectedScholarRecordId);
    $selectedSourceApplicationId = (int)($selectedScholarRow["source_application_id"] ?? 0);
  }

  $allEvaluationCandidateIds = [];
  foreach ([0 - $selectedScholarRecordId, (int)$applicationId, $selectedSourceApplicationId] as $candidateId) {
    $candidateId = (int)$candidateId;
    if ($candidateId !== 0 && !in_array($candidateId, $allEvaluationCandidateIds, true)) {
      $allEvaluationCandidateIds[] = $candidateId;
    }
  }

  $tableResult = $conn->query("SHOW TABLES LIKE 'department_head_evaluations'");
  $hasEvaluationTable = $tableResult instanceof mysqli_result && $tableResult->num_rows > 0;
  if ($tableResult instanceof mysqli_result) {
    $tableResult->free();
  }

  if ($hasEvaluationTable && !empty($allEvaluationCandidateIds)) {
    $allEvaluationPlaceholders = implode(", ", array_fill(0, count($allEvaluationCandidateIds), "?"));
    $allEvaluationWhere = ["dhe.application_id IN ($allEvaluationPlaceholders)"];
    $allEvaluationParams = $allEvaluationCandidateIds;
    $allEvaluationTypes = str_repeat("i", count($allEvaluationCandidateIds));

    if ($selectedEvaluationSchoolYear !== "") {
      $allEvaluationWhere[] = "TRIM(COALESCE(dhe.school_year, '')) = ?";
      $allEvaluationParams[] = $selectedEvaluationSchoolYear;
      $allEvaluationTypes .= "s";
    }
    if ($selectedEvaluationSemester !== "") {
      $allEvaluationWhere[] = "TRIM(COALESCE(dhe.semester, '')) = ?";
      $allEvaluationParams[] = $selectedEvaluationSemester;
      $allEvaluationTypes .= "s";
    }

    $allEvaluationStmt = $conn->prepare(
      "SELECT
        dhe.id AS evaluation_id,
        dhe.application_id,
        dhe.applicant_name,
        dhe.semester,
        dhe.school_year,
        dhe.assigned_office,
        dhe.head_name,
        dhe.head_username,
        dhe.evaluator_role,
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
              TRIM(COALESCE(u.name, '')),
              CASE
                WHEN TRIM(COALESCE(u.name, '')) <> '' AND TRIM(COALESCE(u.lastname, '')) <> '' THEN ' '
                ELSE ''
              END,
              TRIM(COALESCE(u.lastname, ''))
            )
          )
          FROM users u
          WHERE u.username = dhe.head_username
            AND u.role = dhe.evaluator_role
          LIMIT 1
        ) AS head_account_full_name,
        (
          SELECT TRIM(COALESCE(u.lastname, ''))
          FROM users u
          WHERE u.username = dhe.head_username
            AND u.role = dhe.evaluator_role
          LIMIT 1
        ) AS head_account_lastname
      FROM department_head_evaluations dhe
      LEFT JOIN institutional_scholar_records isr
        ON isr.id = ABS(dhe.application_id)
      WHERE " . implode(" AND ", $allEvaluationWhere) . "
      ORDER BY
        CASE dhe.evaluator_role
          WHEN 'student_assistant' THEN 0
          WHEN 'department_head' THEN 1
          WHEN 'administrator' THEN 2
          ELSE 3
        END,
        dhe.updated_at DESC,
        dhe.id DESC"
    );

    if ($allEvaluationStmt) {
      $allEvaluationStmt->bind_param($allEvaluationTypes, ...$allEvaluationParams);
      if ($allEvaluationStmt->execute()) {
        $allEvaluationResult = $allEvaluationStmt->get_result();
        $seenEvaluationRoles = [];
        while ($evaluationRow = $allEvaluationResult->fetch_assoc()) {
          $recordRole = isgNormalizeEvaluatorRole((string)($evaluationRow["evaluator_role"] ?? "department_head"));
          if ($recordRole === "") {
            $recordRole = "department_head";
          }
          if (!in_array($recordRole, ["student_assistant", "department_head", "administrator"], true)) {
            continue;
          }

          $recordScholarId = abs((int)($evaluationRow["application_id"] ?? 0));
          $recordSemester = trim((string)($evaluationRow["semester"] ?? ""));
          $recordSchoolYear = trim((string)($evaluationRow["school_year"] ?? ""));
          if ($recordRole === "administrator") {
            $assignedAdministrator = isgLoadAdministratorSaAssignment($conn, $recordScholarId, $recordSchoolYear, $recordSemester);
            $assignedUsername = is_array($assignedAdministrator)
              ? strtolower(trim((string)($assignedAdministrator["administrator_username"] ?? "")))
              : "";
            $evaluationUsername = strtolower(trim((string)($evaluationRow["head_username"] ?? "")));
            if ($assignedUsername === "" || $evaluationUsername === "" || $assignedUsername !== $evaluationUsername) {
              continue;
            }
          }

          $roleKey = $recordRole . "|" . strtolower($recordSchoolYear) . "|" . strtolower($recordSemester);
          if (isset($seenEvaluationRoles[$roleKey])) {
            continue;
          }
          $seenEvaluationRoles[$roleKey] = true;
          $evaluationDisplayRecords[] = $buildEvaluationDisplayRecord($evaluationRow);
        }
        if ($allEvaluationResult instanceof mysqli_result) {
          $allEvaluationResult->free();
        }
      }
      $allEvaluationStmt->close();
    }
  }
}

if (empty($evaluationDisplayRecords)) {
  $evaluationDisplayRecords[] = [
    "evaluation_id" => $evaluationId,
    "application_id" => $applicationId,
    "applicant_name" => $displayApplicantName,
    "semester_school_year" => $displaySemesterSchoolYear,
    "area_of_assignment" => $displayAreaOfAssignment,
    "head_of_office" => $displayHeadOfOffice,
    "head_account_lastname" => $displayHeadAccountLastName,
    "evaluation_date" => $displayEvaluationDate,
    "program_year_level" => $displayProgramYearLevel,
    "evaluator_role" => $displayEvaluatorRole,
    "evaluator_type" => $evaluatorType,
    "ratings" => $ratings,
    "section_weighted_totals" => $sectionWeightedTotals,
    "overall_weighted_totals" => $overallWeightedTotals,
    "section_a_avg" => $sectionAAvg,
    "section_b_avg" => $sectionBAvg,
    "section_c_avg" => $sectionCAvg,
    "section_d_avg" => $sectionDAvg,
    "overall_avg" => $overallAvg,
    "strengths" => $displayStrengths,
    "areas_improvement" => $displayAreasImprovement,
    "recommendations" => $displayRecommendations,
    "signature_data" => $displaySignatureData,
  ];
}

$aggregateEvaluatorRoles = ["student_assistant", "department_head", "administrator"];
$aggregateRoleLabels = [
  "student_assistant" => "Student Assistant",
  "department_head" => "Head of Office",
  "administrator" => "Administrator",
];
$evaluationResultRecordsByRole = [];
foreach ($evaluationDisplayRecords as $evaluationDisplayRecord) {
  $recordRole = isgNormalizeEvaluatorRole((string)($evaluationDisplayRecord["evaluator_role"] ?? ""));
  if ($recordRole === "" || !in_array($recordRole, $aggregateEvaluatorRoles, true) || isset($evaluationResultRecordsByRole[$recordRole])) {
    continue;
  }
  $evaluationResultRecordsByRole[$recordRole] = $evaluationDisplayRecord;
}

$hasCompleteAggregateResult = true;
foreach ($aggregateEvaluatorRoles as $requiredRole) {
  if (!isset($evaluationResultRecordsByRole[$requiredRole])) {
    $hasCompleteAggregateResult = false;
    break;
  }
}

$averageAggregateField = static function (array $recordsByRole, array $requiredRoles, string $field, bool $isComplete): ?float {
  if (!$isComplete) {
    return null;
  }

  $total = 0.0;
  $count = 0;
  foreach ($requiredRoles as $role) {
    $value = $recordsByRole[$role][$field] ?? null;
    if (!is_numeric($value)) {
      return null;
    }
    $total += (float)$value;
    $count++;
  }

  return $count > 0 ? ($total / $count) : null;
};

$collectAggregateComments = static function (array $recordsByRole, array $requiredRoles, array $roleLabels, string $field): array {
  $parts = [];
  foreach ($requiredRoles as $role) {
    if (!isset($recordsByRole[$role])) {
      continue;
    }

    $text = trim((string)($recordsByRole[$role][$field] ?? ""));
    if ($text === "") {
      continue;
    }

    $personName = trim((string)($recordsByRole[$role]["head_of_office"] ?? ""));
    $label = (string)($roleLabels[$role] ?? ucwords(str_replace("_", " ", $role)));
    if ($personName !== "") {
      $label .= " - " . $personName;
    }
    $parts[] = [
      "label" => $label,
      "text" => $text,
    ];
  }

  return $parts;
};

$renderAggregateCommentsHtml = static function (array $comments): string {
  $htmlBlocks = [];
  foreach ($comments as $comment) {
    $label = trim((string)($comment["label"] ?? ""));
    $text = trim((string)($comment["text"] ?? ""));
    if ($text === "") {
      continue;
    }

    $labelHtml = $label !== ""
      ? "<strong>" . htmlspecialchars($label . ":", ENT_QUOTES, "UTF-8") . "</strong><br>"
      : "";
    $textHtml = nl2br(htmlspecialchars($text, ENT_QUOTES, "UTF-8"));
    $htmlBlocks[] = $labelHtml . $textHtml;
  }

  return implode("<br><br>", $htmlBlocks);
};

$firstEvaluationRecord = $evaluationDisplayRecords[0] ?? [];
$aggregateEvaluationResult = [
  "applicant_name" => (string)($firstEvaluationRecord["applicant_name"] ?? $displayApplicantName),
  "semester_school_year" => (string)($firstEvaluationRecord["semester_school_year"] ?? $displaySemesterSchoolYear),
  "area_of_assignment" => (string)($firstEvaluationRecord["area_of_assignment"] ?? $displayAreaOfAssignment),
  "program_year_level" => (string)($firstEvaluationRecord["program_year_level"] ?? $displayProgramYearLevel),
  "evaluator_label" => "Student Assistant, Head of Office, and Administrator",
  "section_a_avg" => $averageAggregateField($evaluationResultRecordsByRole, $aggregateEvaluatorRoles, "section_a_avg", $hasCompleteAggregateResult),
  "section_b_avg" => $averageAggregateField($evaluationResultRecordsByRole, $aggregateEvaluatorRoles, "section_b_avg", $hasCompleteAggregateResult),
  "section_c_avg" => $averageAggregateField($evaluationResultRecordsByRole, $aggregateEvaluatorRoles, "section_c_avg", $hasCompleteAggregateResult),
  "section_d_avg" => $averageAggregateField($evaluationResultRecordsByRole, $aggregateEvaluatorRoles, "section_d_avg", $hasCompleteAggregateResult),
  "overall_avg" => $averageAggregateField($evaluationResultRecordsByRole, $aggregateEvaluatorRoles, "overall_avg", $hasCompleteAggregateResult),
  "strengths" => $collectAggregateComments($evaluationResultRecordsByRole, $aggregateEvaluatorRoles, $aggregateRoleLabels, "strengths"),
  "areas_improvement" => $collectAggregateComments($evaluationResultRecordsByRole, $aggregateEvaluatorRoles, $aggregateRoleLabels, "areas_improvement"),
  "recommendations" => $collectAggregateComments($evaluationResultRecordsByRole, $aggregateEvaluatorRoles, $aggregateRoleLabels, "recommendations"),
  "has_complete_result" => $hasCompleteAggregateResult,
];

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
        margin-top: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
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
        line-height: 1.1;
        text-align: left;
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

      .eval-page .performance-summary-cell {
        height: auto;
        padding: 0.55rem 0.65rem;
        text-align: left;
        vertical-align: top;
      }

      .eval-page .performance-summary-title {
        margin: 0 0 0.7rem;
        font-weight: 700;
      }

      .eval-page .performance-summary-lines {
        display: grid;
        gap: 0.42rem;
        font-style: italic;
        font-weight: 700;
      }

      .eval-page .performance-summary-lines p {
        margin: 0;
      }

      .eval-page .performance-summary-label {
        display: inline-block;
        min-width: 9.2rem;
      }

      .eval-page .performance-summary-line {
        display: inline-block;
        min-width: 4.6rem;
        border-bottom: 1px solid #111827;
        text-align: center;
        font-style: normal;
        font-weight: 400;
      }

      .eval-page .performance-level-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.55rem 0.8rem;
      }

      .eval-page .performance-level-option {
        display: inline-flex;
        align-items: center;
        gap: 0.28rem;
        font-weight: 400;
      }

      .eval-page .performance-level-box {
        display: inline-flex;
        width: 0.72rem;
        height: 0.72rem;
        align-items: center;
        justify-content: center;
        border: 1px solid #111827;
        font-size: 0.55rem;
        line-height: 1;
      }

      .eval-page .comment-box {
        border: 1px solid #111827;
        min-height: 3.2rem;
        margin-top: 0.6rem;
        padding: 0.45rem 0.6rem;
        font-size: 12px;
        line-height: 1.45;
      }

      .eval-page .retention-note {
        margin-top: 1.6rem;
        font-size: 12px;
        line-height: 1.45;
      }

      .eval-page .retention-note-title {
        margin: 0 0 0.65rem;
        font-style: italic;
        font-weight: 700;
      }

      .eval-page .retention-note-copy {
        margin: 0;
        text-indent: 1.8rem;
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

      .eval-page + .eval-page,
      .eval-result + .eval-result {
        border-top: 12px solid #e5e7eb;
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
        size: 8.5in 13in portrait;
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

        .eval-page + .eval-page,
        .eval-result + .eval-result {
          border-top: 0 !important;
          page-break-before: always;
          break-before: page;
        }

        .eval-page {
          background: #ffffff;
        }

        /* Document 1: Evaluation Form (portrait long bond 8.5in x 13in) */

        #print-eval-form .eval-page {
          font-size: 11.5px;
          line-height: 1.28;
        }

        #print-eval-form .header-top {
          margin-top: 0;
          gap: 0.65rem;
          margin-bottom: 0.45rem;
        }

        #print-eval-form .header-logo img,
        #print-eval-form .header-cert img {
          width: 70px;
          height: 70px;
        }

        #print-eval-form .header-center h1 {
          font-size: 14.5pt;
        }

        #print-eval-form .header-center p {
          font-size: 9.4pt;
        }

        #print-eval-form .paper {
          padding: 0.45rem 0.65rem 0.55rem;
        }

        #print-eval-form .title {
          font-size: 13.5px;
          margin-bottom: 0.45rem;
        }

        #print-eval-form .info-block {
          margin-bottom: 0.45rem;
          font-size: 11.5px;
        }

        #print-eval-form .info-row {
          grid-template-columns: 225px minmax(0, 300px);
          gap: 0.35rem;
          margin-bottom: 0.14rem;
        }

        #print-eval-form .info-value {
          min-height: 0.75rem;
        }

        #print-eval-form .direction {
          font-size: 11.5px;
          line-height: 1.35;
          margin-bottom: 0.35rem;
        }

        #print-eval-form table th,
        #print-eval-form table td {
          padding: 0.18rem 0.32rem;
          font-size: 11px;
        }

        #print-eval-form .scale-table {
          margin-bottom: 0.45rem;
        }

        #print-eval-form .scale-table td {
          font-size: 10.5px;
          padding: 0.14rem 0.32rem;
        }

        #print-eval-form .rating-table td {
          height: 1.55rem;
        }

        #print-eval-form section.pt-4,
        #print-eval-form section.pt-6 {
          padding-top: 0.35rem !important;
        }

        #print-eval-form .comment-box {
          min-height: 2.7rem;
          padding: 0.35rem 0.5rem;
          line-height: 1.3;
        }

        #print-eval-form .retention-note {
          margin-top: 1rem;
          line-height: 1.32;
        }

        #print-eval-form .signature-row {
          margin-top: 1.15rem;
        }

        #print-eval-form .signature-line {
          margin-top: 0.55rem;
        }

        #print-eval-form .footer-box {
          margin-top: 0.35rem;
        }

        #print-eval-form footer {
          margin-top: 0.35rem;
          padding-top: 0.1rem;
        }

        /* Document 2: Copy of Evaluation (portrait long bond 8.5in x 13in) */

        #print-eval-result .eval-result {
          padding: 1.1rem 1.6rem;
          font-size: 13px;
          line-height: 1.45;
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
          padding: 6px 9px;
          font-size: 12.5px;
        }

        /* Tall enough to fill the page without overflowing portrait height */
        #print-eval-result .result-table .h-16 {
          height: 3.25rem !important;
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
            <?php foreach ($evaluationDisplayRecords as $evaluationDisplayRecord): ?>
              <?php
                $displayApplicantName = (string)($evaluationDisplayRecord["applicant_name"] ?? "");
                $displaySemesterSchoolYear = (string)($evaluationDisplayRecord["semester_school_year"] ?? "");
                $displayAreaOfAssignment = (string)($evaluationDisplayRecord["area_of_assignment"] ?? "");
                $displayHeadOfOffice = (string)($evaluationDisplayRecord["head_of_office"] ?? "");
                $displayEvaluationDate = (string)($evaluationDisplayRecord["evaluation_date"] ?? "");
                $displayStrengths = (string)($evaluationDisplayRecord["strengths"] ?? "");
                $displayAreasImprovement = (string)($evaluationDisplayRecord["areas_improvement"] ?? "");
                $displayRecommendations = (string)($evaluationDisplayRecord["recommendations"] ?? "");
                $displaySignatureData = (string)($evaluationDisplayRecord["signature_data"] ?? "");
                $evaluatorType = (string)($evaluationDisplayRecord["evaluator_type"] ?? "Head of Office");
                $ratings = (array)($evaluationDisplayRecord["ratings"] ?? $ratings);
                $sectionWeightedTotals = (array)($evaluationDisplayRecord["section_weighted_totals"] ?? $sectionWeightedTotals);
                $overallWeightedTotals = (array)($evaluationDisplayRecord["overall_weighted_totals"] ?? $overallWeightedTotals);
                $overallAvg = $evaluationDisplayRecord["overall_avg"] ?? null;
                $overallTotalScore = array_sum(array_map("intval", $overallWeightedTotals));
                $overallMaxScore = count($ratings) * max(array_keys($ratingOptions));
                $performanceLevel = $verbalFromAverage(is_numeric($overallAvg) ? (float)$overallAvg : null);
              ?>
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
                    <p>Atupan St., Brgy. 4, Nasipit, Agusan del Norte 8602, Philippines</p>
                    <p>
                      <a>Website: www.smccnasipit.edu.ph ; Tel. Nos. 085 300-2932</a>
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
                    <span class="info-label">Evaluator Name:</span>
                    <span class="info-value"><?= htmlspecialchars($displayHeadOfOffice) ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Date of Evaluation:</span>
                    <span class="info-value"><?= htmlspecialchars($displayEvaluationDate) ?></span>
                  </div>
                </section>

                <section class="direction">
                  <p class="flex flex-wrap items-center gap-4">
                    <span class="font-semibold">Evaluator:</span>
                    <?php foreach ($evaluatorOptions as $option): ?>
                      <span class="inline-flex items-center gap-1">
                        <span class="inline-flex h-4 w-4 items-center justify-center border border-black text-[10px] leading-none">
                          <?= $evaluatorType === $option ? "&#10003;" : "" ?>
                        </span>
                        <span><?= htmlspecialchars($option) ?></span>
                      </span>
                    <?php endforeach; ?>
                  </p>
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
                      <?php foreach ($ratingOptions as $scale => $option): ?>
                        <tr>
                          <td><?= htmlspecialchars((string)$scale) ?></td>
                          <td><?= htmlspecialchars($option["label"] . " (" . $option["short"] . ")") ?></td>
                          <td><?= htmlspecialchars($option["interpretation"]) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </section>

                <section class="pt-4">
                  <table class="rating-table">
                    <thead>
                      <tr>
                        <th rowspan="2" style="width: 47%;">Performance Indicators</th>
                        <th colspan="<?= count($ratingOptions) ?>">Rating</th>
                      </tr>
                      <tr>
                        <?php foreach ($ratingOptions as $scale => $option): ?>
                          <th><?= htmlspecialchars(((int)$scale === 1 ? "NI" : $option["label"]) . " (" . $scale . ")") ?></th>
                        <?php endforeach; ?>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($evaluationSections as $sectionKey => $section): ?>
                        <tr>
                          <td class="section-label"><?= htmlspecialchars($section["title"]) ?></td>
                          <?php foreach ($ratingOptions as $unusedScale => $unusedOption): ?>
                            <td></td>
                          <?php endforeach; ?>
                        </tr>
                        <?php foreach ($section["criteria"] as $fieldName => $criterion): ?>
                          <tr>
                            <td><?= htmlspecialchars($criterion) ?></td>
                            <?php foreach ($ratingOptions as $scale => $unusedOption): ?>
                              <td><?= $checkMark($ratings, $fieldName, (int)$scale) ?></td>
                            <?php endforeach; ?>
                          </tr>
                        <?php endforeach; ?>
                        <tr>
                          <td class="subtotal">Total</td>
                          <?php foreach ($ratingOptions as $scale => $unusedOption): ?>
                            <td><?= htmlspecialchars($weightedTotalText($sectionWeightedTotals[$sectionKey] ?? [], (int)$scale)) ?></td>
                          <?php endforeach; ?>
                        </tr>
                      <?php endforeach; ?>

                      <tr>
                        <td class="subtotal">Over-all Total</td>
                        <?php foreach ($ratingOptions as $scale => $unusedOption): ?>
                          <td><?= htmlspecialchars($weightedTotalText($overallWeightedTotals, (int)$scale)) ?></td>
                        <?php endforeach; ?>
                      </tr>
                      <tr>
                        <td colspan="<?= 1 + count($ratingOptions) ?>" class="performance-summary-cell">
                          <p class="performance-summary-title">Performance Summary</p>
                          <div class="performance-summary-lines">
                            <p>
                              <span class="performance-summary-label">Overall Total Score:</span>
                              <span class="performance-summary-line"><?= $overallTotalScore > 0 ? htmlspecialchars((string)$overallTotalScore) : "" ?></span>/<?= htmlspecialchars((string)$overallMaxScore) ?>
                            </p>
                            <p>
                              <span class="performance-summary-label">Average Rating:</span>
                              <span class="performance-summary-line"><?= htmlspecialchars($formatAverage(is_numeric($overallAvg) ? (float)$overallAvg : null)) ?></span>
                            </p>
                            <p class="performance-level-row">
                              <span class="performance-summary-label">Performance Level:</span>
                              <?php foreach (["Very Good", "Good", "Poor", "Needs Improvement"] as $levelOption): ?>
                                <span class="performance-level-option">
                                  <span class="performance-level-box"><?= $performanceLevel === $levelOption ? "&#10003;" : "" ?></span>
                                  <span><?= htmlspecialchars($levelOption) ?></span>
                                </span>
                              <?php endforeach; ?>
                            </p>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </section>

                <section class="pt-6 text-[12px]">
                  <p class="font-semibold">E. Strength(s):</p>
                  <div class="comment-box">
                    <?= $displayStrengths !== "" ? nl2br(htmlspecialchars($displayStrengths)) : "&nbsp;" ?>
                  </div>
                </section>

                <section class="pt-4 text-[12px]">
                  <p class="font-semibold">F. Area(s) for Improvement:</p>
                  <div class="comment-box">
                    <?= $displayAreasImprovement !== "" ? nl2br(htmlspecialchars($displayAreasImprovement)) : "&nbsp;" ?>
                  </div>
                </section>

                <section class="pt-4 text-[12px]">
                  <p class="font-semibold">G. Evaluator's Comment(s):</p>
                  <div class="comment-box">
                    <?= $displayRecommendations !== "" ? nl2br(htmlspecialchars($displayRecommendations)) : "&nbsp;" ?>
                  </div>
                </section>

                <section class="retention-note">
                  <p class="retention-note-title">Note for Retention</p>
                  <p class="retention-note-copy">A Student Assistant must obtain an overall performance rating of at least &quot;Good&quot; and comply with all provisions of the Student Assistant Scholarship Agreement to remain eligible for retention in the Student Assistant Scholarship Program.</p>
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
                  <img src="../img/boxEvaluator.png" alt="footer box" />
                </div>
              </main>

              <footer>
                <img src="../img/newfooter.jpg" alt="SMCC footer" />
              </footer>
            </div>
            <?php endforeach; ?>
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
            <?php
              $displayApplicantName = (string)($aggregateEvaluationResult["applicant_name"] ?? "");
              $displaySemesterSchoolYear = (string)($aggregateEvaluationResult["semester_school_year"] ?? "");
              $displayAreaOfAssignment = (string)($aggregateEvaluationResult["area_of_assignment"] ?? "");
              $displayProgramYearLevel = (string)($aggregateEvaluationResult["program_year_level"] ?? "");
              $displayStrengths = (array)($aggregateEvaluationResult["strengths"] ?? []);
              $displayAreasImprovement = (array)($aggregateEvaluationResult["areas_improvement"] ?? []);
              $displayRecommendations = (array)($aggregateEvaluationResult["recommendations"] ?? []);
              $evaluatorType = (string)($aggregateEvaluationResult["evaluator_label"] ?? "Student Assistant, Head of Office, and Administrator");
              $sectionAAvg = $aggregateEvaluationResult["section_a_avg"] ?? null;
              $sectionBAvg = $aggregateEvaluationResult["section_b_avg"] ?? null;
              $sectionCAvg = $aggregateEvaluationResult["section_c_avg"] ?? null;
              $sectionDAvg = $aggregateEvaluationResult["section_d_avg"] ?? null;
              $overallAvg = $aggregateEvaluationResult["overall_avg"] ?? null;
            ?>
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
                      Atupan St., Brgy. 4, Nasipit, Agusan del Norte 8602, Philippines
                    </p>
                    <p class="m-0 text-[10pt]">
                      <a>Website: www.smccnasipit.edu.ph ; Tel. Nos. 085 300-2932</a>
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
                <p class="text-[10pt] mt-1 mb-0 font-semibold">Evaluator: <?= htmlspecialchars($evaluatorType) ?></p>
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
                  <span class="w-56">Evaluators</span>
                  <span>:</span>
                  <span class="border-b border-black ml-2 h-5 inline-block w-80"><?= htmlspecialchars($evaluatorType) ?></span>
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
                      <td>D. Professionalism and Ethical Conduct</td>
                      <td><?= htmlspecialchars($formatAverage($sectionDAvg)) ?></td>
                      <td><?= htmlspecialchars($verbalFromAverage($sectionDAvg)) ?></td>
                    </tr>
                    <tr>
                      <td class="font-semibold">Overall Rating</td>
                      <td><?= htmlspecialchars($formatAverage($overallAvg)) ?></td>
                      <td><?= htmlspecialchars($verbalFromAverage($overallAvg)) ?></td>
                    </tr>
                    <tr>
                      <td>E. Strength(s)</td>
                      <td colspan="2"><div class="min-h-16 whitespace-pre-wrap"><?= $renderAggregateCommentsHtml($displayStrengths) ?></div></td>
                    </tr>
                    <tr>
                      <td>F. Area(s) for Improvement</td>
                      <td colspan="2"><div class="min-h-16 whitespace-pre-wrap"><?= $renderAggregateCommentsHtml($displayAreasImprovement) ?></div></td>
                    </tr>
                    <tr>
                      <td>G. Evaluator's Comment(s)/Recommendation</td>
                      <td colspan="2"><div class="min-h-16 whitespace-pre-wrap"><?= $renderAggregateCommentsHtml($displayRecommendations) ?></div></td>
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

        // Dynamically set the correct @page size for this document.
        // Both evaluation form and result print in portrait long bond.
        let pageOverride = document.getElementById("page-size-override");
        if (!pageOverride) {
          pageOverride = document.createElement("style");
          pageOverride.id = "page-size-override";
          document.head.appendChild(pageOverride);
        }
        pageOverride.textContent = isResult
          ? "@page { size: 8.5in 13in portrait; margin: 0.28in; }"
          : "@page { size: 8.5in 13in portrait; margin: 0.28in; }";

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










