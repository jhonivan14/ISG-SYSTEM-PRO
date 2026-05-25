<?php
// Guide: Shared badge count for pending and reapplied applicant sidebar links.
// Trace: normalize optional term filters -> count reviewable applications -> expose badge text.

if (!(($conn ?? null) instanceof mysqli)) {
  return;
}

if (!function_exists("adminCountPendingAndReappliedApplicants")) {
  function adminCountPendingAndReappliedApplicants(mysqli $conn, string $schoolYear = "", string $semester = ""): int
  {
    $clauses = [
      "(status IS NULL OR TRIM(status) = '' OR LOWER(TRIM(status)) = 'pending' OR LOWER(TRIM(status)) = 'reapplied')",
    ];
    $params = [];
    $types = "";

    $schoolYear = trim($schoolYear);
    if ($schoolYear !== "") {
      $clauses[] = "TRIM(COALESCE(school_year, '')) = ?";
      $params[] = $schoolYear;
      $types .= "s";
    }

    $semester = trim($semester);
    if ($semester !== "") {
      $clauses[] = "TRIM(COALESCE(semester, '')) = ?";
      $params[] = $semester;
      $types .= "s";
    }

    $sql = "SELECT COUNT(*) AS total FROM applications WHERE " . implode(" AND ", $clauses);
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      return 0;
    }

    if (!empty($params)) {
      $stmt->bind_param($types, ...$params);
    }

    $count = 0;
    if ($stmt->execute()) {
      $result = $stmt->get_result();
      if ($result instanceof mysqli_result) {
        $row = $result->fetch_assoc();
        $count = (int)($row["total"] ?? 0);
        $result->free();
      }
    }
    $stmt->close();

    return $count;
  }
}

$sidebarPendingApplicantCount = adminCountPendingAndReappliedApplicants(
  $conn,
  (string)($activeSchoolYearFilter ?? ""),
  (string)($activeSemesterFilter ?? "")
);
$sidebarPendingApplicantBadge = $sidebarPendingApplicantCount > 99
  ? "99+"
  : (string)$sidebarPendingApplicantCount;
