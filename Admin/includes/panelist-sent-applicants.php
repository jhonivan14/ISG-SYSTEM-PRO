<?php
// Guide: Shared loader for applicants already sent to panelists.
// Trace: reuse active term filters -> verify queue availability -> fetch latest sent-applicant snapshot.

require_once __DIR__ . "/admin-auth.php";
adminRequireLogin();

$panelistSentApplicants = [];
$panelistSentError = "";

if (!(($conn ?? null) instanceof mysqli)) {
  require_once dirname(__DIR__) . "/../db.php";
}

$selectedSchoolYear = isset($selectedSchoolYear) ? (string)$selectedSchoolYear : "";
$selectedSemester = isset($selectedSemester) ? (string)$selectedSemester : "";
$selectedBatch = isset($selectedBatch) ? (string)$selectedBatch : "";

$hasBatchColumn = false;
$batchColumnResult = $conn->query("SHOW COLUMNS FROM applications LIKE 'batch'");
if ($batchColumnResult instanceof mysqli_result) {
  $hasBatchColumn = $batchColumnResult->num_rows > 0;
  $batchColumnResult->free();
}

$tableResult = $conn->query("SHOW TABLES LIKE 'panelist_queue'");
if ($tableResult instanceof mysqli_result && $tableResult->num_rows > 0) {
  $tableResult->free();

  $whereClauses = [
    "a.grant_id = 1",
    "LOWER(TRIM(a.status)) = 'approved'",
  ];
  $params = [];
  $types = "";

  if ($selectedSchoolYear !== "") {
    $whereClauses[] = "a.school_year = ?";
    $params[] = $selectedSchoolYear;
    $types .= "s";
  }
  if ($selectedSemester !== "") {
    $whereClauses[] = "a.semester = ?";
    $params[] = $selectedSemester;
    $types .= "s";
  }
  if ($selectedBatch !== "" && $hasBatchColumn) {
    $whereClauses[] = "a.batch = ?";
    $params[] = $selectedBatch;
    $types .= "s";
  }

  $batchSelect = $hasBatchColumn ? ", a.batch" : "";
  $sql = "
    SELECT
      a.id,
      a.applicant_name,
      a.program_course,
      a.year_level,
      a.school_year,
      a.semester
      {$batchSelect},
      q.last_sent_at
    FROM (
      SELECT application_id, MAX(sent_at) AS last_sent_at
      FROM panelist_queue
      GROUP BY application_id
    ) AS q
    INNER JOIN applications a ON a.id = q.application_id
    WHERE " . implode(" AND ", $whereClauses) . "
    ORDER BY q.last_sent_at DESC, a.applicant_name ASC
  ";

  $stmt = $conn->prepare($sql);
  if ($stmt) {
    if (!empty($params)) {
      $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result instanceof mysqli_result) {
      while ($row = $result->fetch_assoc()) {
        $panelistSentApplicants[] = [
          "id" => (int)($row["id"] ?? 0),
          "name" => trim((string)($row["applicant_name"] ?? "")),
          "program_course" => trim((string)($row["program_course"] ?? "")),
          "year_level" => trim((string)($row["year_level"] ?? "")),
          "school_year" => trim((string)($row["school_year"] ?? "")),
          "semester" => trim((string)($row["semester"] ?? "")),
          "batch" => $hasBatchColumn ? trim((string)($row["batch"] ?? "")) : "",
          "last_sent_at" => trim((string)($row["last_sent_at"] ?? "")),
        ];
      }
      $result->free();
    }
    $stmt->close();
  } else {
    $panelistSentError = "Unable to load sent applicants for panelist flow.";
  }
} else {
  if ($tableResult instanceof mysqli_result) {
    $tableResult->free();
  }
  $panelistSentError = "panelist_queue table is not available.";
}
