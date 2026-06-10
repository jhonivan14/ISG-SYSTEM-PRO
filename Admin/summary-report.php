<?php
// Guide: Printable summary report for department evaluation results by term.
// Trace: load selected term -> collect summary rows -> sort display order -> render report and sidebar scripts.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once __DIR__ . "/includes/school-term-filter.php";
require_once __DIR__ . "/includes/applicant-sidebar-badge.php";

$summaryRecords = [];
$summaryLoadError = "";
$showExcellentOnly = isset($_GET["excellent"]) && trim((string)$_GET["excellent"]) === "1";

// Helpers below standardize weighted mean formatting before the printable summary is rendered.

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
    return "Excellent";
  }
  if ($value >= 3.0) {
    return "Good";
  }
  if ($value >= 2.0) {
    return "Fair";
  }
  return "Poor";
};

$formatCertificateDate = static function (?int $timestamp = null): string {
  $timestamp = $timestamp ?? time();
  $day = (int)date("j", $timestamp);
  $suffix = "th";
  if ($day % 100 < 11 || $day % 100 > 13) {
    $lastDigit = $day % 10;
    if ($lastDigit === 1) {
      $suffix = "st";
    } elseif ($lastDigit === 2) {
      $suffix = "nd";
    } elseif ($lastDigit === 3) {
      $suffix = "rd";
    }
  }

  return $day . $suffix . " day of " . date("F Y", $timestamp);
};

if (($conn ?? null) instanceof mysqli) {
  $evaluationTableResult = $conn->query("SHOW TABLES LIKE 'department_head_evaluations'");
  $hasEvaluationTable = $evaluationTableResult instanceof mysqli_result && $evaluationTableResult->num_rows > 0;
  if ($evaluationTableResult instanceof mysqli_result) {
    $evaluationTableResult->free();
  }

  if ($hasEvaluationTable) {
    $whereClauses = ["1 = 1"];
    $params = [];
    $paramTypes = "";

    if ($activeSchoolYearFilter !== "") {
      $whereClauses[] = "TRIM(COALESCE(school_year, '')) = ?";
      $params[] = $activeSchoolYearFilter;
      $paramTypes .= "s";
    }
    if ($activeSemesterFilter !== "") {
      $whereClauses[] = "TRIM(COALESCE(semester, '')) = ?";
      $params[] = $activeSemesterFilter;
      $paramTypes .= "s";
    }

    $sql = "
      SELECT
        id,
        application_id,
        applicant_name,
        assigned_office,
        semester,
        school_year,
        overall_total,
        strengths,
        recommendations,
        updated_at
      FROM department_head_evaluations
      WHERE " . implode(" AND ", $whereClauses) . "
      ORDER BY updated_at DESC, id DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
      if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
      }

      if ($stmt->execute()) {
        $result = $stmt->get_result();
        $seenApplicationIds = [];

        while ($row = $result->fetch_assoc()) {
          $applicationId = (int)($row["application_id"] ?? 0);
          $applicationKey = (string)$applicationId;
          if (isset($seenApplicationIds[$applicationKey])) {
            continue;
          }
          $seenApplicationIds[$applicationKey] = true;

          $overallTotal = (int)($row["overall_total"] ?? 0);
          $weightedMean = $overallTotal > 0 ? ($overallTotal / 15) : null;

          $summaryRecords[] = [
            "application_id" => $applicationId,
            "name" => trim((string)($row["applicant_name"] ?? "")),
            "assigned_office" => trim((string)($row["assigned_office"] ?? "")),
            "semester" => trim((string)($row["semester"] ?? "")),
            "school_year" => trim((string)($row["school_year"] ?? "")),
            "weighted_mean" => $weightedMean,
            "verbal_description" => $verbalFromAverage($weightedMean),
            "strengths" => trim((string)($row["strengths"] ?? "")),
            "recommendations" => trim((string)($row["recommendations"] ?? "")),
          ];
        }

        if ($result instanceof mysqli_result) {
          $result->free();
        }
      } else {
        $summaryLoadError = "Unable to load summary report records.";
      }

      $stmt->close();
    } else {
      $summaryLoadError = "Unable to prepare summary report query.";
    }
  }
}

if (!empty($summaryRecords)) {
  if ($showExcellentOnly) {
    $summaryRecords = array_values(array_filter($summaryRecords, static function (array $record): bool {
      $weightedMean = $record["weighted_mean"] ?? null;
      return is_numeric($weightedMean) && abs(((float)$weightedMean) - 4.0) < 0.00001;
    }));
  }

  usort($summaryRecords, static function (array $left, array $right): int {
    $leftName = strtolower(trim((string)($left["name"] ?? "")));
    $rightName = strtolower(trim((string)($right["name"] ?? "")));
    if ($leftName !== $rightName) {
      return $leftName <=> $rightName;
    }
    return ((int)($left["application_id"] ?? 0)) <=> ((int)($right["application_id"] ?? 0));
  });
}

$headerSemesterLabel = $activeSemesterFilter !== "" ? $activeSemesterFilter : "All Semesters";
$excellentFilterParams = [];
if ($activeSchoolYearFilter !== "") {
  $excellentFilterParams["school_year"] = $activeSchoolYearFilter;
}
if ($activeSemesterFilter !== "") {
  $excellentFilterParams["semester"] = $activeSemesterFilter;
}
$allReportParams = $excellentFilterParams;
$excellentFilterParams["excellent"] = "1";
$excellentReportUrl = "summary-report.php?" . http_build_query($excellentFilterParams);
$allReportUrl = "summary-report.php" . (!empty($allReportParams) ? ("?" . http_build_query($allReportParams)) : "");
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Summary Report</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <style>
      @page {
        size: <?php echo $showExcellentOnly ? "letter landscape" : "8.5in 13in"; ?>;
        margin: <?php echo $showExcellentOnly ? "0" : "12mm 10mm"; ?>;
      }

      ::-webkit-scrollbar {
        width: 6px;
      }

      ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
        border-radius: 999px;
      }

      .report-header {
        margin-bottom: 0.5rem;
        text-align: center;
      }

      .header-top {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 0.25rem;
      }

      .header-left {
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      .header-left img {
        width: 80px;
        height: 80px;
        object-fit: contain;
      }

      .header-left-text {
        line-height: 1.1;
        text-align: left;
      }

      .header-left-text h1 {
        font-weight: 700;
        font-size: 16pt;
        margin: 0;
      }

      .header-left-text p {
        margin: 0;
        font-size: 10pt;
      }

      .header-right {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        align-items: center;
      }

      .header-right img {
        width: 100px;
        height: 80px;
        object-fit: contain;
      }

      .title-line {
        letter-spacing: 0.5px;
      }

      .thin-rule {
        border-top: 1px solid #222;
      }

      .report-stage {
        width: 100%;
        max-width: 1250px;
        margin: 0 auto;
      }

      .report-wrapper {
        width: 100%;
      }

      .summary-table {
        width: 100%;
        table-layout: auto;
      }

      .summary-table col.col-seq {
        width: 4%;
      }

      .summary-table col.col-name {
        width: 24%;
      }

      .summary-table col.col-weighted {
        width: 10%;
      }

      .summary-table col.col-verbal {
        width: 13%;
      }

      .summary-table col.col-strength {
        width: 24%;
      }

      .summary-table col.col-comment {
        width: 25%;
      }

      .summary-table th,
      .summary-table td {
        vertical-align: middle;
        word-break: normal;
        overflow-wrap: normal;
      }

      .summary-table .summary-text-cell {
        vertical-align: top;
        white-space: normal;
        word-break: normal;
        overflow-wrap: normal;
        line-height: 1.3;
      }

      .excellent-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        overflow: hidden;
        border: 1px solid #b7c9e8;
        border-radius: 8px;
        background: #ffffff;
      }

      .excellent-table thead th {
        background: #052c6a;
        color: #ffffff;
        font-size: 11px;
        letter-spacing: 0.04em;
        padding: 10px 12px;
        text-align: left;
        text-transform: uppercase;
      }

      .excellent-table tbody td {
        border-top: 1px solid #dbe7ff;
        color: #052c6a;
        padding: 11px 12px;
        vertical-align: middle;
      }

      .excellent-table tbody tr:nth-child(even) {
        background: #f8fbff;
      }

      .excellent-rank {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        color: #052c6a;
        font-weight: 800;
      }

      .excellent-score {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 68px;
        border-radius: 999px;
        background: #e8f3ff;
        border: 1px solid #8fc8ef;
        color: #052c6a;
        font-weight: 800;
        padding: 5px 12px;
      }

      .excellent-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #047857;
        font-size: 11px;
        font-weight: 800;
        padding: 5px 12px;
      }

      .certificate-view-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 1px solid #0d8ddb;
        background: #0d8ddb;
        color: #ffffff;
        font-size: 11px;
        font-weight: 800;
        padding: 7px 12px;
        transition: background-color 0.16s ease, transform 0.16s ease;
      }

      .certificate-view-btn:hover {
        background: #0a6fac;
        transform: translateY(-1px);
      }

      .certificate-stack {
        margin-top: 1.5rem;
        display: grid;
        gap: 1.25rem;
      }

      .certificate-templates {
        display: none;
      }

      .certificate-modal[hidden] {
        display: none !important;
      }

      .certificate-modal {
        position: fixed;
        inset: 0;
        z-index: 60;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, 0.68);
        padding: 18px;
      }

      .certificate-modal-panel {
        width: min(1120px, 100%);
        max-height: 94vh;
        overflow: auto;
        border-radius: 12px;
        background: #f8fafc;
        box-shadow: 0 24px 80px rgba(15, 23, 42, 0.36);
      }

      .certificate-modal-bar {
        position: sticky;
        top: 0;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-bottom: 1px solid #dbe7ff;
        background: #ffffff;
        padding: 12px 14px;
      }

      .certificate-modal-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
      }

      .certificate-modal-btn {
        border-radius: 999px;
        border: 1px solid #0d8ddb;
        background: #0d8ddb;
        color: #ffffff;
        font-size: 12px;
        font-weight: 800;
        padding: 8px 13px;
      }

      .certificate-modal-btn.secondary {
        background: #ffffff;
        color: #052c6a;
      }

      .certificate-modal-content {
        background: linear-gradient(180deg, #f8fbff 0%, #edf5ff 100%);
        padding: 18px;
      }

      .certificate-page {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        box-sizing: border-box;
        width: min(100%, 1060px);
        aspect-ratio: 11 / 8.5;
        min-height: 0;
        margin: 0 auto;
        border: 1px solid rgba(5, 44, 106, 0.22);
        background:
          radial-gradient(circle at 50% 43%, rgba(252, 220, 47, 0.16), rgba(252, 220, 47, 0) 26%),
          linear-gradient(180deg, #fffdf8 0%, #ffffff 48%, #f8fbff 100%);
        box-shadow: 0 22px 52px rgba(15, 23, 42, 0.18);
        color: #152238;
        padding: 30px 54px 26px;
      }

      .certificate-page::before {
        content: "";
        position: absolute;
        inset: 16px;
        z-index: 0;
        border: 2px solid #d7a21f;
        box-shadow:
          inset 0 0 0 5px rgba(255, 255, 255, 0.96),
          inset 0 0 0 6px rgba(5, 44, 106, 0.25);
        pointer-events: none;
      }

      .certificate-page::after {
        content: "";
        position: absolute;
        inset: 34px;
        z-index: 0;
        border: 1px solid rgba(5, 44, 106, 0.22);
        background:
          linear-gradient(90deg, transparent 0 18%, rgba(214, 162, 44, 0.22) 18% 18.35%, transparent 18.35% 81.65%, rgba(214, 162, 44, 0.22) 81.65% 82%, transparent 82%),
          linear-gradient(0deg, transparent 0 13%, rgba(5, 44, 106, 0.12) 13% 13.3%, transparent 13.3% 86.7%, rgba(5, 44, 106, 0.12) 86.7% 87%, transparent 87%);
        pointer-events: none;
      }

      .certificate-corner {
        position: absolute;
        z-index: 0;
        width: 116px;
        height: 116px;
        pointer-events: none;
      }

      .certificate-corner::after {
        content: "";
        position: absolute;
        width: 68px;
        height: 68px;
        border-color: #fcdc2f;
        border-style: solid;
      }

      .certificate-corner-top-left {
        top: 23px;
        left: 23px;
        border-top: 10px solid #052c6a;
        border-left: 10px solid #052c6a;
      }

      .certificate-corner-top-left::after {
        top: 14px;
        left: 14px;
        border-width: 5px 0 0 5px;
      }

      .certificate-corner-top-right {
        top: 23px;
        right: 23px;
        border-top: 10px solid #052c6a;
        border-right: 10px solid #052c6a;
      }

      .certificate-corner-top-right::after {
        top: 14px;
        right: 14px;
        border-width: 5px 5px 0 0;
      }

      .certificate-corner-bottom-left {
        bottom: 23px;
        left: 23px;
        border-bottom: 10px solid #052c6a;
        border-left: 10px solid #052c6a;
      }

      .certificate-corner-bottom-left::after {
        bottom: 14px;
        left: 14px;
        border-width: 0 0 5px 5px;
      }

      .certificate-corner-bottom-right {
        right: 23px;
        bottom: 23px;
        border-right: 10px solid #052c6a;
        border-bottom: 10px solid #052c6a;
      }

      .certificate-corner-bottom-right::after {
        right: 14px;
        bottom: 14px;
        border-width: 0 5px 5px 0;
      }

      .certificate-diagonal {
        position: absolute;
        z-index: 0;
        width: 0;
        height: 0;
        pointer-events: none;
      }

      .certificate-diagonal-top-left {
        top: 0;
        left: 0;
        border-top: 230px solid rgba(5, 44, 106, 0.1);
        border-right: 230px solid transparent;
      }

      .certificate-diagonal-bottom-right {
        right: 0;
        bottom: 0;
        border-bottom: 190px solid rgba(5, 44, 106, 0.1);
        border-left: 190px solid transparent;
      }

      .certificate-watermark {
        position: absolute;
        inset: 0;
        z-index: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.055;
        pointer-events: none;
      }

      .certificate-watermark img {
        width: 410px;
        max-width: 48%;
        height: auto;
      }

      .certificate-inner {
        position: relative;
        z-index: 1;
        display: flex;
        height: 100%;
        min-height: 0;
        flex-direction: column;
        padding: 0 6px;
      }

      .certificate-school {
        display: grid;
        grid-template-columns: 150px minmax(0, 1fr) 120px;
        align-items: center;
        gap: 18px;
        border-bottom: 3px double #052c6a;
        background: rgba(255, 255, 255, 0.72);
        padding: 7px 14px 11px;
      }

      .certificate-logo-group {
        display: flex;
        align-items: center;
        gap: 9px;
      }

      .certificate-logo-group img {
        width: 64px;
        height: 64px;
        object-fit: contain;
      }

      .certificate-iso-logo {
        justify-self: end;
        width: 98px;
        height: 64px;
        object-fit: contain;
      }

      .certificate-school-text {
        text-align: center;
        line-height: 1.15;
      }

      .certificate-school-text h3 {
        margin: 0;
        color: #052c6a;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 25px;
        font-weight: 800;
        text-transform: uppercase;
      }

      .certificate-school-text p {
        margin: 2px 0 0;
        font-size: 11.5px;
      }

      .certificate-office {
        color: #052c6a;
        font-size: 13px;
        font-weight: 800;
        margin-top: 4px;
        text-transform: uppercase;
      }

      .certificate-title {
        margin-top: 24px;
        text-align: center;
      }

      .certificate-title h2 {
        color: #052c6a;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 44px;
        font-weight: 800;
        line-height: 1.04;
        margin: 0;
      }

      .certificate-title-rule {
        width: 210px;
        height: 5px;
        margin: 8px auto 0;
        border-radius: 999px;
        background: linear-gradient(90deg, transparent, #fcdc2f 22%, #052c6a 50%, #fcdc2f 78%, transparent);
      }

      .certificate-presented {
        margin-top: 13px;
        text-align: center;
        font-size: 15.5px;
        font-weight: 700;
      }

      .certificate-name-wrap {
        position: relative;
        width: min(100%, 720px);
        margin: 13px auto 12px;
        padding: 0 34px 9px;
        text-align: center;
      }

      .certificate-name-wrap::before,
      .certificate-name-wrap::after {
        content: "";
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, #052c6a 18%, #d6a22c 50%, #052c6a 82%, transparent);
      }

      .certificate-name-wrap::before {
        bottom: 6px;
        opacity: 0.35;
      }

      .certificate-name {
        color: #052c6a;
        font-family: "Brush Script MT", "Segoe Script", Georgia, serif;
        font-size: 54px;
        font-style: italic;
        line-height: 1.1;
        overflow-wrap: break-word;
      }

      .certificate-body {
        margin: 0 auto;
        max-width: 900px;
        text-align: center;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 16.3px;
        line-height: 1.48;
      }

      .certificate-body p {
        margin: 0 0 12px;
      }

      .certificate-body strong {
        color: #052c6a;
      }

      .certificate-date {
        margin-top: 22px;
        padding-top: 0;
        text-align: center;
        font-size: 16.5px;
        font-weight: 700;
      }

      .certificate-date[contenteditable="true"] {
        border-radius: 8px;
        cursor: text;
        outline: 1px dashed transparent;
        outline-offset: 5px;
        transition: background-color 0.16s ease, outline-color 0.16s ease;
      }

      .certificate-date[contenteditable="true"]:hover,
      .certificate-date[contenteditable="true"]:focus {
        background: rgba(252, 220, 47, 0.12);
        outline-color: rgba(5, 44, 106, 0.4);
      }

      .certificate-signatories {
        margin-top: auto;
        padding-top: 30px;
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 22px 34px;
        text-align: center;
      }

      .certificate-signatory {
        grid-column: span 2;
      }

      .certificate-signatory:nth-child(4) {
        grid-column: 2 / span 2;
      }

      .certificate-signatory:nth-child(5) {
        grid-column: 4 / span 2;
      }

      .certificate-sign-line {
        border-bottom: 1.5px solid #111827;
        height: 20px;
        margin-bottom: 6px;
      }

      .certificate-sign-name {
        color: #052c6a;
        font-size: 13px;
        font-weight: 800;
        text-transform: uppercase;
      }

      .certificate-sign-role {
        color: #334155;
        font-size: 12.2px;
      }

      @media screen and (max-width: 920px) {
        .certificate-page {
          aspect-ratio: auto;
          min-height: auto;
          padding: 28px;
        }

        .certificate-school {
          grid-template-columns: 1fr;
          gap: 8px;
          text-align: center;
        }

        .certificate-logo-group {
          justify-content: center;
        }

        .certificate-iso-logo {
          justify-self: center;
        }

        .certificate-name {
          font-size: 42px;
        }

        .certificate-signatories {
          grid-template-columns: 1fr;
        }

        .certificate-signatory,
        .certificate-signatory:nth-child(4),
        .certificate-signatory:nth-child(5) {
          grid-column: auto;
        }
      }

      @media print {
        #sidebar,
        .topbar,
        .page-header,
        .term-filter-bar,
        #sidebarToggle {
          display: none !important;
        }

        html,
        body {
          margin: 0 !important;
          padding: 0 !important;
          background: #fff !important;
        }

        main {
          margin-left: 0 !important;
          padding: 0 !important;
        }

        .report-stage {
          max-width: none !important;
        }

        .report-wrapper {
          width: 100% !important;
          max-width: none !important;
          border: none !important;
          box-shadow: none !important;
          border-radius: 0 !important;
          padding: 0 !important;
        }

        .report-wrapper,
        .report-wrapper * {
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }

        .report-wrapper .overflow-x-auto {
          overflow: visible !important;
        }

        .summary-table {
          width: 100% !important;
          table-layout: auto !important;
          font-size: 10px !important;
        }

        .summary-table col.col-seq {
          width: 4% !important;
        }

        .summary-table col.col-name {
          width: 24% !important;
        }

        .summary-table col.col-weighted {
          width: 10% !important;
        }

        .summary-table col.col-verbal {
          width: 13% !important;
        }

        .summary-table col.col-strength {
          width: 24% !important;
        }

        .summary-table col.col-comment {
          width: 25% !important;
        }

        .summary-table th,
        .summary-table td {
          padding: 3px 4px !important;
          line-height: 1.2 !important;
        }

        .summary-table .summary-text-cell {
          white-space: normal !important;
          word-break: normal !important;
          overflow-wrap: normal !important;
          line-height: 1.25 !important;
        }

        .excellent-table {
          border-collapse: collapse !important;
          border-radius: 0 !important;
          font-size: 11px !important;
        }

        .excellent-table th,
        .excellent-table td {
          border: 1px solid #000 !important;
          padding: 6px 8px !important;
        }

        .excellent-rank,
        .excellent-score,
        .excellent-badge {
          border-radius: 0 !important;
          border: none !important;
          background: transparent !important;
          color: #000 !important;
          min-width: 0 !important;
          padding: 0 !important;
        }

        .certificate-stack {
          display: block !important;
          margin-top: 0 !important;
        }

        .certificate-page {
          width: 11in !important;
          height: 8.5in !important;
          aspect-ratio: auto !important;
          min-height: 0 !important;
          box-sizing: border-box !important;
          box-shadow: none !important;
          break-after: page;
          page-break-after: always;
          margin: 0 auto !important;
          padding: 30px 54px 26px !important;
        }

        .certificate-page,
        .certificate-page *,
        .certificate-page::before,
        .certificate-page::after,
        .certificate-corner,
        .certificate-corner::after,
        .certificate-diagonal {
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }

        .certificate-inner {
          height: 100% !important;
          min-height: 0 !important;
          padding: 0 6px !important;
        }

        .certificate-page:last-child {
          break-after: auto;
          page-break-after: auto;
        }

        .certificate-watermark {
          opacity: 0.07 !important;
        }

        .certificate-date[contenteditable="true"] {
          background: transparent !important;
          outline: none !important;
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
  <body class="bg-[#eef2f7] font-sans">
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
              data-nav="institutional-scholars.php" onclick="window.location.href='institutional-scholars.php'"
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

      <main class="ml-0 md:ml-64 flex flex-col min-h-screen pt-14 bg-[#eef2f7]">
        <header
          class="topbar hidden fixed top-0 left-0 md:left-64 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
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
            SUMMARY EVALUATION REPORT
          </h2>
          </div>
        </section>

        <form class="term-filter-bar px-4 sm:px-6 mt-4 flex flex-wrap justify-end gap-2" method="get" action="summary-report.php">
          <?php if ($showExcellentOnly): ?>
            <input type="hidden" name="excellent" value="1" />
          <?php endif; ?>
          <select
            class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
            name="school_year"
            aria-label="Select academic year"
            onchange="this.form.submit()"
          >
            <option value="" <?php echo $rawSelectedSchoolYear !== null && $activeSchoolYearFilter === "" ? "selected" : ""; ?>>All School Years</option>
            <?php foreach ($schoolYearOptions as $option): ?>
              <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $activeSchoolYearFilter === $option ? "selected" : ""; ?>>
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
            <option value="" <?php echo $activeSemesterFilter === "" ? "selected" : ""; ?>>All Semesters</option>
            <?php foreach ($semesterOptions as $option): ?>
              <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $activeSemesterFilter === $option ? "selected" : ""; ?>>
                <?php echo htmlspecialchars($option); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($showExcellentOnly): ?>
            <a
              href="<?php echo htmlspecialchars($allReportUrl); ?>"
              class="inline-flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-[#0d8ddb] px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-[#0a6fac]"
            >
              <i class="fas fa-star text-[11px]"></i>
              <span>Excellent Student Assistants</span>
            </a>
          <?php else: ?>
            <a
              href="<?php echo htmlspecialchars($excellentReportUrl); ?>"
              class="inline-flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-white px-4 py-2 text-xs font-semibold text-[#052c6a] shadow-sm transition-colors hover:bg-[#eff6ff]"
            >
              <i class="fas fa-star text-[11px]"></i>
              <span>Excellent Student Assistants</span>
            </a>
          <?php endif; ?>
          <button
            type="button"
            onclick="window.print()"
            class="inline-flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-[#0d8ddb] px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-[#0a6fac]"
            aria-label="Print summary report"
          >
            <i class="fas fa-print text-[11px]"></i>
            <span>Print</span>
          </button>
          <?php if ($rawSelectedSchoolYear !== null || $rawSelectedSemester !== null): ?>
            <a
              href="summary-report.php"
              class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm"
            >
              Clear
            </a>
          <?php endif; ?>
          <?php if ($showExcellentOnly): ?>
            <a
              href="<?php echo htmlspecialchars($allReportUrl); ?>"
              class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm"
            >
              Show All
            </a>
          <?php endif; ?>
        </form>

        <section class="px-4 sm:px-6 py-4 lg:py-6">
          <div class="report-stage">
            <div class="report-wrapper bg-white border border-slate-200 rounded-md shadow-sm p-5 sm:p-6 lg:p-8">
            <header class="report-header">
              <div class="header-top">
                <div class="header-left">
                  <img src="../img/SMCCNEWLOGO.png" alt="Seal of Saint Michael College of Caraga" />
                  <div class="header-left-text">
                    <h1 class="text-center">Saint Michael College of Caraga</h1>
                    <p class="text-center">
                      Brgy. 4, Nasipit, Agusan del Norte, Philippines<br />
                      District 8, Brgy. Triangulo, Nasipit, Agusan del Norte, Philippines
                    </p>
                    <p class="text-center">Tel. Nos. +63 085 343-3251 / +63 085 283-3113</p>
                    <p class="text-center">
                      <a href="http://www.smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">www.smccnasipit.edu.ph</a>
                    </p>
                  </div>
                </div>
                <div class="header-right">
                  <img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC ISO 9001 logo" />
                </div>
              </div>
            </header>

            <p class="text-center text-xs -mt-2">
              Website:
              <a class="text-blue-700 underline" href="http://www.smccnasipit.edu.ph">www.smccnasipit.edu.ph</a>,
              Email:
              <a class="text-blue-700 underline" href="mailto:communications@smccnasipit.edu.ph">communications@smccnasipit.edu.ph</a>
            </p>
            <p class="text-center font-semibold title-line text-sm">OFFICE OF THE ADMISSION &amp; SCHOLARSHIP</p>
            <div class="thin-rule my-1"></div>
            <section class="text-center mb-4">
              <h2 class="font-bold text-base"><?php echo $showExcellentOnly ? "EXCELLENT STUDENT ASSISTANTS" : "STUDENT ASSISTANTS' EVALUATION SUMMARY REPORT"; ?></h2>
              <p class="font-semibold text-sm"><?php echo htmlspecialchars($headerSemesterLabel); ?>, S.Y. <?php echo htmlspecialchars($activeSchoolYearFilter !== "" ? $activeSchoolYearFilter : "All School Years"); ?></p>
            </section>

            <div class="overflow-x-auto mb-6">
              <?php if ($showExcellentOnly): ?>
                <table class="excellent-table text-xs">
                  <thead>
                    <tr>
                      <th style="width: 72px;">Seq.</th>
                      <th>Name of Student Assistant</th>
                      <th style="width: 190px;">Assigned Office</th>
                      <th style="width: 150px;">Weighted Mean</th>
                      <th style="width: 180px;">Distinction</th>
                      <th style="width: 150px;">Certificate</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($summaryLoadError !== ""): ?>
                      <tr>
                        <td class="text-center" colspan="6"><?php echo htmlspecialchars($summaryLoadError); ?></td>
                      </tr>
                    <?php elseif (empty($summaryRecords)): ?>
                      <tr>
                        <td class="text-center" colspan="6">No Excellent Student Assistants with a Weighted Mean of 4.00 found for the selected term.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($summaryRecords as $index => $record): ?>
                        <tr>
                          <td><span class="excellent-rank"><?php echo htmlspecialchars((string)($index + 1)); ?></span></td>
                          <td>
                            <div class="font-bold text-[#052c6a]">
                              <?php echo htmlspecialchars((string)($record["name"] !== "" ? $record["name"] : "N/A")); ?>
                            </div>
                          </td>
                          <td><?php echo htmlspecialchars((string)($record["assigned_office"] !== "" ? $record["assigned_office"] : "N/A")); ?></td>
                          <td>
                            <span class="excellent-score">
                              <?php
                                $weightedMean = $record["weighted_mean"];
                                echo htmlspecialchars($formatAverage(is_numeric($weightedMean) ? (float)$weightedMean : null));
                              ?>
                            </span>
                          </td>
                          <td><span class="excellent-badge">Excellent</span></td>
                          <td>
                            <button
                              type="button"
                              class="certificate-view-btn"
                              data-certificate-id="certificate-<?php echo htmlspecialchars((string)$index); ?>"
                            >
                              View Certificate
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <table class="summary-table text-xs border border-black border-collapse">
                  <colgroup>
                    <col class="col-seq" />
                    <col class="col-name" />
                    <col class="col-weighted" />
                    <col class="col-verbal" />
                    <col class="col-strength" />
                    <col class="col-comment" />
                  </colgroup>
                  <thead>
                    <tr class="bg-yellow-200">
                      <th class="border border-black p-1">Seq.</th>
                      <th class="border border-black p-1">NAME OF STUDENT ASSISTANT</th>
                      <th class="border border-black p-1">Weighted<br />Mean</th>
                      <th class="border border-black p-1">Verbal<br />Description</th>
                      <th class="border border-black p-1">Strength(s)/Areas for Improvement</th>
                      <th class="border border-black p-1">Evaluator's Comment(s)/Recommendation</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($summaryLoadError !== ""): ?>
                      <tr>
                        <td class="border border-black p-2 text-center" colspan="6"><?php echo htmlspecialchars($summaryLoadError); ?></td>
                      </tr>
                    <?php elseif (empty($summaryRecords)): ?>
                      <tr>
                        <td class="border border-black p-2 text-center" colspan="6">No department evaluation results found for the selected term.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($summaryRecords as $index => $record): ?>
                        <tr>
                          <td class="border border-black p-1 text-center"><?php echo htmlspecialchars((string)($index + 1)); ?></td>
                          <td class="border border-black p-1"><?php echo htmlspecialchars((string)($record["name"] !== "" ? $record["name"] : "N/A")); ?></td>
                          <td class="border border-black p-1 text-center">
                            <?php
                              $weightedMean = $record["weighted_mean"];
                              echo htmlspecialchars($formatAverage(is_numeric($weightedMean) ? (float)$weightedMean : null));
                            ?>
                          </td>
                          <td class="border border-black p-1 text-center"><?php echo htmlspecialchars((string)($record["verbal_description"] ?? "")); ?></td>
                          <td class="border border-black p-1 summary-text-cell">
                            <?php echo $record["strengths"] !== "" ? nl2br(htmlspecialchars((string)$record["strengths"])) : "&nbsp;"; ?>
                          </td>
                          <td class="border border-black p-1 summary-text-cell">
                            <?php echo $record["recommendations"] !== "" ? nl2br(htmlspecialchars((string)$record["recommendations"])) : "&nbsp;"; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>

            <?php if ($showExcellentOnly && $summaryLoadError === "" && !empty($summaryRecords)): ?>
              <div class="certificate-templates" aria-hidden="true">
                <?php foreach ($summaryRecords as $index => $record): ?>
                  <?php
                    $certificateName = trim((string)($record["name"] ?? ""));
                    $certificateName = $certificateName !== "" ? $certificateName : "Student Assistant";
                    $certificateMean = $formatAverage(is_numeric($record["weighted_mean"] ?? null) ? (float)$record["weighted_mean"] : null);
                    $certificateSemester = trim((string)($record["semester"] ?? ""));
                    $certificateSemester = $certificateSemester !== "" ? $certificateSemester : $headerSemesterLabel;
                    $certificateSchoolYear = trim((string)($record["school_year"] ?? ""));
                    $certificateSchoolYear = $certificateSchoolYear !== "" ? $certificateSchoolYear : ($activeSchoolYearFilter !== "" ? $activeSchoolYearFilter : "All School Years");
                    $certificateDate = $formatCertificateDate();
                  ?>
                  <section id="certificate-<?php echo htmlspecialchars((string)$index); ?>" class="certificate-page">
                    <span class="certificate-diagonal certificate-diagonal-top-left" aria-hidden="true"></span>
                    <span class="certificate-diagonal certificate-diagonal-bottom-right" aria-hidden="true"></span>
                    <span class="certificate-corner certificate-corner-top-left" aria-hidden="true"></span>
                    <span class="certificate-corner certificate-corner-top-right" aria-hidden="true"></span>
                    <span class="certificate-corner certificate-corner-bottom-left" aria-hidden="true"></span>
                    <span class="certificate-corner certificate-corner-bottom-right" aria-hidden="true"></span>
                    <div class="certificate-watermark" aria-hidden="true">
                      <img src="../img/SMCCNEWLOGO.png" alt="" />
                    </div>
                    <div class="certificate-inner">
                      <div class="certificate-school">
                        <div class="certificate-logo-group">
                          <img src="../img/SMCCNEWLOGO.png" alt="Saint Michael College of Caraga seal" />
                          <img src="../img/admission-logo.jpg" alt="Admission and Scholarship logo" />
                        </div>
                        <div class="certificate-school-text">
                          <h3>Saint Michael College of Caraga</h3>
                          <p>Atupan St., Brgy. 4, Nasipit, Agusan del Norte 8602, Philippines</p>
                          <p>Website: www.smccnasipit.edu.ph | Tel. Nos. 085 300-2732</p>
                          <div class="certificate-office">Office of the Admission &amp; Scholarship</div>
                        </div>
                        <img class="certificate-iso-logo" src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC certification logo" />
                      </div>

                      <div class="certificate-title">
                        <h2>Certificate of Recognition</h2>
                        <div class="certificate-title-rule" aria-hidden="true"></div>
                      </div>
                      <p class="certificate-presented">This Certificate of Recognition is proudly presented to</p>
                      <div class="certificate-name-wrap">
                        <div class="certificate-name"><?php echo htmlspecialchars($certificateName); ?></div>
                      </div>

                      <div class="certificate-body">
                        <p>
                          for demonstrating exemplary dedication, professionalism, and outstanding service as a Student Assistant at
                          Saint Michael College of Caraga. With a perfect performance evaluation rating of
                          <strong><?php echo htmlspecialchars($certificateMean); ?> (Excellent)</strong> for the
                          <strong><?php echo htmlspecialchars($certificateSemester); ?></strong> of
                          <strong>S.Y. <?php echo htmlspecialchars($certificateSchoolYear); ?></strong>, your commitment to excellence,
                          work ethic, and invaluable contributions to the institution serve as an inspiration to your peers and the academic community.
                        </p>
                        <p>
                          In recognition of your exceptional performance and unwavering dedication, this certificate is awarded with deep appreciation and gratitude.
                        </p>
                      </div>

                      <p class="certificate-date" contenteditable="true" spellcheck="false" aria-label="Editable certificate date and venue">
                        Given this <?php echo htmlspecialchars($certificateDate); ?> at Saint Michael College of Caraga, Nasipit, Agusan del Norte.
                      </p>

                      <div class="certificate-signatories">
                        <div class="certificate-signatory">
                          <div class="certificate-sign-line"></div>
                          <div class="certificate-sign-name">Arlyn B. Tuyogon, MMBM</div>
                          <div class="certificate-sign-role">Head, Admission &amp; Scholarship</div>
                        </div>
                        <div class="certificate-signatory">
                          <div class="certificate-sign-line"></div>
                          <div class="certificate-sign-name">Felmarie Manlunas, MACDDS</div>
                          <div class="certificate-sign-role">Head, Student Affairs &amp; Services</div>
                        </div>
                        <div class="certificate-signatory">
                          <div class="certificate-sign-line"></div>
                          <div class="certificate-sign-name">Ricky E. Destacamento, RGC, MAEd</div>
                          <div class="certificate-sign-role">Head, HRMDO</div>
                        </div>
                        <div class="certificate-signatory">
                          <div class="certificate-sign-line"></div>
                          <div class="certificate-sign-name">Beverly D. Jaminal, EdD</div>
                          <div class="certificate-sign-role">VP for Academic Affairs &amp; Research</div>
                        </div>
                        <div class="certificate-signatory">
                          <div class="certificate-sign-line"></div>
                          <div class="certificate-sign-name">Rev. Fr. Ronniel G. Babano, STL</div>
                          <div class="certificate-sign-role">School President</div>
                        </div>
                      </div>
                    </div>
                  </section>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!$showExcellentOnly): ?>
            <div class="mt-8 text-sm space-y-6">
              <div>
                <p class="font-semibold">Prepared by:</p>
                <div class="mt-2">
                  <p class="font-bold">ARLYN B. TUYOGON, MMBM</p>
                  <p class="text-xs">Head, Admission &amp; Scholarship</p>
                </div>
              </div>
              <div>
                <p class="font-semibold">Checked by:</p>
                <div class="mt-2">
                  <p class="font-bold">FELMARIE MANLUNAS, MACDDS</p>
                  <p class="text-xs">Head, Student Affairs &amp; Services</p>
                </div>
              </div>
              <div>
                <p class="font-semibold">Noted by:</p>
                <div class="mt-2">
                  <p class="font-bold">RICKY E. DESTACAMENTO, RGC, MAED</p>
                  <p class="text-xs">Head, HRMDO</p>
                </div>
              </div>
              <div>
                <p class="font-semibold">Approved by:</p>
                <div class="mt-2">
                  <p class="font-bold">REV. FR. RONNIEL G. BABANO, STL</p>
                  <p class="text-xs">School President</p>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
          </div>
        </section>
      </main>
    </div>

    <?php if ($showExcellentOnly): ?>
      <div id="certificateModal" class="certificate-modal" hidden>
        <div class="certificate-modal-panel" role="dialog" aria-modal="true" aria-labelledby="certificateModalTitle">
          <div class="certificate-modal-bar">
            <div>
              <p id="certificateModalTitle" class="text-sm font-bold text-[#052c6a]">Certificate Preview</p>
              <p class="text-xs text-slate-500">Review or print the selected certificate.</p>
            </div>
            <div class="certificate-modal-actions">
              <button id="printCertificateBtn" type="button" class="certificate-modal-btn">
                Print Certificate
              </button>
              <button id="closeCertificateModal" type="button" class="certificate-modal-btn secondary">
                Close
              </button>
            </div>
          </div>
          <div id="certificateModalContent" class="certificate-modal-content"></div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($showExcellentOnly): ?>
      <script>
        document.addEventListener("DOMContentLoaded", function () {
          const modal = document.getElementById("certificateModal");
          const modalContent = document.getElementById("certificateModalContent");
          const closeButton = document.getElementById("closeCertificateModal");
          const printButton = document.getElementById("printCertificateBtn");
          let activeCertificateHtml = "";

          if (!modal || !modalContent || !closeButton || !printButton) {
            return;
          }

          function openCertificate(certificateId) {
            const template = document.getElementById(certificateId);
            if (!template) return;
            activeCertificateHtml = template.outerHTML;
            modalContent.innerHTML = activeCertificateHtml;
            modal.hidden = false;
          }

          function closeCertificate() {
            modal.hidden = true;
            modalContent.innerHTML = "";
            activeCertificateHtml = "";
          }

          document.querySelectorAll("[data-certificate-id]").forEach((button) => {
            button.addEventListener("click", () => {
              openCertificate(String(button.getAttribute("data-certificate-id") || ""));
            });
          });

          closeButton.addEventListener("click", closeCertificate);
          modal.addEventListener("click", (event) => {
            if (event.target === modal) {
              closeCertificate();
            }
          });

          printButton.addEventListener("click", () => {
            if (activeCertificateHtml === "") return;
            const printableCertificateHtml = modalContent.innerHTML || activeCertificateHtml;
            const styleMarkup = Array.from(document.querySelectorAll("style"))
              .map((style) => "<style>" + style.textContent + "</style>")
              .join("");
            const printWindow = window.open("", "_blank", "width=1200,height=850");
            if (!printWindow) {
              window.print();
              return;
            }

            printWindow.document.write(
              "<!doctype html><html><head><meta charset=\"utf-8\"><title>Certificate of Recognition</title>" +
              styleMarkup +
              "</head><body class=\"bg-white font-sans\"><div style=\"padding:0\">" +
              printableCertificateHtml +
              "</div></body></html>"
            );
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
              printWindow.print();
            }, 350);
          });
        });
      </script>
    <?php endif; ?>

    <script>
      // Collapse the admin sidebar on mobile for the printable summary view.
      document.addEventListener("DOMContentLoaded", function () {
        var sidebar = document.getElementById("sidebar");
        var toggleBtn = document.getElementById("sidebarToggle");

        if (sidebar && toggleBtn) {
          toggleBtn.addEventListener("click", function () {
            sidebar.classList.toggle("-translate-x-full");
          });

          sidebar.querySelectorAll("li").forEach(function (item) {
            item.addEventListener("click", function (event) {
              if (event.target.closest("summary")) {
                return;
              }
              if (window.innerWidth < 768) {
                sidebar.classList.add("-translate-x-full");
              }
            });
          });
        }
      });
    </script>
  <script>
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("sidebar");
  if (!sidebar) {
    return;
  }

  // Highlight the current admin page in the shared sidebar menu.

  const currentPage = window.location.pathname.split("/").pop().toLowerCase();
  const sidebarAliases = {
    "summary-of-applicants.php": "applicant.php",
    "declined-applicants.php": "applicant.php",
    "view-application.php": "applicant.php",
    "department-evaluation-indi.php": "department-evaluation-list.php",
    "summary-reports.php": "summary-report.php",
    "list-0f-qualified.php": "list-of-qualified.php"
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
















