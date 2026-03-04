<?php
$defaultBatchLabel = "All Batches";
require_once __DIR__ . "/includes/school-term-filter.php";

$qualifiedApplicants = [];
$qualifiedApplicantsError = "";
$officeOptions = [];
$officeOptionsError = "";
$officeSaveStatus = isset($_GET["office_save"]) ? strtolower(trim((string)$_GET["office_save"])) : "";
$officeSaveMessage = "";
$officeSaveMessageType = "";
$hasBatchColumn = false;
$hasAssignedOfficeColumn = false;
$hasRankInputTable = false;

$buildQualifiedUrl = static function (string $schoolYear, string $semester, string $batch, string $saveStatus = ""): string {
  $query = [];
  if ($schoolYear !== "") {
    $query["school_year"] = $schoolYear;
  }
  if ($semester !== "") {
    $query["semester"] = $semester;
  }
  if ($batch !== "") {
    $query["batch"] = $batch;
  }
  if ($saveStatus !== "") {
    $query["office_save"] = $saveStatus;
  }

  $queryString = http_build_query($query);
  return "list-of-qualified.php" . ($queryString !== "" ? "?" . $queryString : "");
};

if (($conn ?? null) instanceof mysqli) {
  $batchColumnResult = $conn->query("SHOW COLUMNS FROM applications LIKE 'batch'");
  if ($batchColumnResult instanceof mysqli_result) {
    $hasBatchColumn = $batchColumnResult->num_rows > 0;
    $batchColumnResult->free();
  }

  $assignedOfficeColumnResult = $conn->query("SHOW COLUMNS FROM applications LIKE 'assigned_office'");
  if ($assignedOfficeColumnResult instanceof mysqli_result) {
    $hasAssignedOfficeColumn = $assignedOfficeColumnResult->num_rows > 0;
    $assignedOfficeColumnResult->free();
    if (!$hasAssignedOfficeColumn) {
      $conn->query("ALTER TABLE applications ADD COLUMN assigned_office VARCHAR(100) DEFAULT NULL AFTER year_level");
      $hasAssignedOfficeColumn = true;
    }
  }

  $headOfficeTableResult = $conn->query("SHOW TABLES LIKE 'head_offices'");
  if ($headOfficeTableResult instanceof mysqli_result && $headOfficeTableResult->num_rows > 0) {
    $headOfficeTableResult->free();

    $officeResult = $conn->query(
      "SELECT DISTINCT TRIM(office) AS office
       FROM head_offices
       WHERE office IS NOT NULL
         AND TRIM(office) <> ''
         AND (status IS NULL OR LOWER(TRIM(status)) <> 'inactive')
       ORDER BY office ASC"
    );
    if ($officeResult instanceof mysqli_result) {
      while ($officeRow = $officeResult->fetch_assoc()) {
        $officeValue = trim((string)($officeRow["office"] ?? ""));
        if ($officeValue !== "") {
          $officeOptions[] = $officeValue;
        }
      }
      $officeResult->free();
    }
  } else {
    if ($headOfficeTableResult instanceof mysqli_result) {
      $headOfficeTableResult->free();
    }
    $officeOptionsError = "Head office accounts table is not available.";
  }

  $rankInputTableResult = $conn->query("SHOW TABLES LIKE 'applicant_rank_inputs'");
  if ($rankInputTableResult instanceof mysqli_result && $rankInputTableResult->num_rows > 0) {
    $hasRankInputTable = true;
    $rankInputTableResult->free();
  } else {
    if ($rankInputTableResult instanceof mysqli_result) {
      $rankInputTableResult->free();
    }
  }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($conn ?? null) instanceof mysqli) {
  $postedSchoolYear = trim((string)($_POST["school_year"] ?? $selectedSchoolYear));
  $postedSemester = trim((string)($_POST["semester"] ?? $selectedSemester));
  $postedBatch = trim((string)($_POST["batch"] ?? $selectedBatch));
  $postedApplicantIds = isset($_POST["applicant_ids"]) && is_array($_POST["applicant_ids"])
    ? array_values(array_unique(array_map("intval", $_POST["applicant_ids"])))
    : [];
  $postedAssignedOffices = isset($_POST["assigned_office"]) && is_array($_POST["assigned_office"])
    ? $_POST["assigned_office"]
    : [];

  if (!$hasAssignedOfficeColumn || !$hasRankInputTable) {
    header("Location: " . $buildQualifiedUrl($postedSchoolYear, $postedSemester, $postedBatch, "error"));
    exit;
  }

  $allowedApplicantIds = [];
  $whereClauses = [
    "a.grant_id = 1",
    "LOWER(TRIM(a.status)) = 'approved'",
    "LOWER(TRIM(COALESCE(ari.remarks, ''))) = 'hired'",
  ];
  $params = [];
  $types = "";

  if ($postedSchoolYear !== "") {
    $whereClauses[] = "a.school_year = ?";
    $params[] = $postedSchoolYear;
    $types .= "s";
  }
  if ($postedSemester !== "") {
    $whereClauses[] = "a.semester = ?";
    $params[] = $postedSemester;
    $types .= "s";
  }
  if ($postedBatch !== "" && $hasBatchColumn) {
    $whereClauses[] = "a.batch = ?";
    $params[] = $postedBatch;
    $types .= "s";
  }

  $allowedSql = "
    SELECT a.id
    FROM applicant_rank_inputs ari
    INNER JOIN applications a ON a.id = ari.application_id
    WHERE " . implode(" AND ", $whereClauses);
  $allowedStmt = $conn->prepare($allowedSql);
  if ($allowedStmt) {
    if (!empty($params)) {
      $allowedStmt->bind_param($types, ...$params);
    }
    if ($allowedStmt->execute()) {
      $allowedResult = $allowedStmt->get_result();
      while ($allowedRow = $allowedResult->fetch_assoc()) {
        $allowedApplicantIds[(int)($allowedRow["id"] ?? 0)] = true;
      }
      $allowedResult->free();
    }
    $allowedStmt->close();
  }

  $saveStmt = $conn->prepare("UPDATE applications SET assigned_office = ? WHERE id = ? LIMIT 1");
  if ($saveStmt) {
    foreach ($postedApplicantIds as $applicantId) {
      if ($applicantId <= 0 || !isset($allowedApplicantIds[$applicantId])) {
        continue;
      }

      $selectedOffice = array_key_exists((string)$applicantId, $postedAssignedOffices)
        ? trim((string)$postedAssignedOffices[(string)$applicantId])
        : "";
      if ($selectedOffice !== "" && !in_array($selectedOffice, $officeOptions, true)) {
        $selectedOffice = "";
      }

      $saveStmt->bind_param("si", $selectedOffice, $applicantId);
      $saveStmt->execute();
    }
    $saveStmt->close();

    header("Location: " . $buildQualifiedUrl($postedSchoolYear, $postedSemester, $postedBatch, "success"));
    exit;
  }

  header("Location: " . $buildQualifiedUrl($postedSchoolYear, $postedSemester, $postedBatch, "error"));
  exit;
}

if ($officeSaveStatus === "success") {
  $officeSaveMessage = "Assigned offices saved.";
  $officeSaveMessageType = "success";
} elseif ($officeSaveStatus === "error") {
  $officeSaveMessage = "Unable to save assigned offices.";
  $officeSaveMessageType = "error";
}

if (($conn ?? null) instanceof mysqli && $hasRankInputTable) {
  $whereClauses = [
    "a.grant_id = 1",
    "LOWER(TRIM(a.status)) = 'approved'",
    "LOWER(TRIM(COALESCE(ari.remarks, ''))) = 'hired'",
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

  $assignedOfficeSelect = $hasAssignedOfficeColumn
    ? "COALESCE(NULLIF(TRIM(a.assigned_office), ''), '') AS assigned_office"
    : "'' AS assigned_office";

  $qualifiedSql = "
    SELECT
      a.id,
      a.applicant_name,
      a.permanent_address,
      a.contact_number,
      a.program_course,
      a.year_level,
      {$assignedOfficeSelect}
    FROM applicant_rank_inputs ari
    INNER JOIN applications a ON a.id = ari.application_id
    WHERE " . implode(" AND ", $whereClauses) . "
    ORDER BY a.applicant_name ASC
  ";

  $qualifiedStmt = $conn->prepare($qualifiedSql);
  if ($qualifiedStmt) {
    if (!empty($params)) {
      $qualifiedStmt->bind_param($types, ...$params);
    }
    if ($qualifiedStmt->execute()) {
      $qualifiedResult = $qualifiedStmt->get_result();
      while ($row = $qualifiedResult->fetch_assoc()) {
        $qualifiedApplicants[] = [
          "id" => (int)($row["id"] ?? 0),
          "name" => trim((string)($row["applicant_name"] ?? "")),
          "address" => trim((string)($row["permanent_address"] ?? "")),
          "contact_number" => trim((string)($row["contact_number"] ?? "")),
          "program_course" => trim((string)($row["program_course"] ?? "")),
          "year_level" => trim((string)($row["year_level"] ?? "")),
          "assigned_office" => trim((string)($row["assigned_office"] ?? "")),
        ];
      }
      $qualifiedResult->free();
    } else {
      $qualifiedApplicantsError = "Unable to load qualified applicants.";
    }
    $qualifiedStmt->close();
  } else {
    $qualifiedApplicantsError = "Unable to prepare qualified applicants lookup.";
  }
} elseif ($qualifiedApplicantsError === "") {
  $qualifiedApplicantsError = "Ranking inputs table is not available yet.";
}
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>List of Qualified</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
      rel="stylesheet"
    />
    <style>
      @page {
        size: Legal;
        margin: 12mm 10mm 12mm 10mm;
      }
      ::-webkit-scrollbar { width: 6px; }
      ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%); border-radius: 999px; }
      .paper { font-family: "Times New Roman", serif; line-height: 1.4; }
      .paper h1, .paper h2, .paper p { margin: 0; }
      header { margin-bottom: 0.75rem; text-align: center; }
      .header-top { display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.25rem; }
      .header-left { display: flex; align-items: center; gap: 0.5rem; }
      .header-left img { width: 80px; height: 80px; object-fit: contain; }
      .header-left-text { line-height: 1.1; text-align: left; }
      .header-left-text h1 { font-weight: 700; font-size: 16pt; margin: 0; }
      .header-left-text p { margin: 0; font-size: 10pt; }
      .header-right { display: flex; flex-direction: column; gap: 0.2rem; align-items: center; }
      .header-right img { width: 100px; height: 80px; object-fit: contain; }
      .title-line { font-weight: 700; letter-spacing: 0.02em; }
      .subtle { font-size: 10pt; }
      .plain-table table { border-collapse: collapse; width: 100%; }
      .plain-table th,
      .plain-table td { border: 1px solid #000; font-size: 10pt; padding: 5px 6px; }
      .plain-table thead th {
        background: #f1c40f;
        color: #000;
        text-align: center;
        font-weight: 700;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .plain-table th:nth-child(2),
      .plain-table td:nth-child(2) { white-space: nowrap; }
      .office-select {
        width: 100%;
        border: 0;
        background: transparent;
        font: inherit;
        outline: none;
      }
      .sig-role { font-size: 10pt; }
      /* widen table for readability */
      .plain-table table { width: 100%; table-layout: auto; }
      @media print {
        html, body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        body, .paper { background: white !important; font-family: "Times New Roman", serif !important; }
        #sidebar, .admin-topbar, .print-btn-bar, .page-header { display: none !important; }
        main, section { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .paper { border: none !important; box-shadow: none !important; margin: 0 auto !important; padding: 0 !important; }
        .paper-wrap { max-width: 100% !important; width: 100% !important; padding: 0 4px 12px 4px !important; }
        .plain-table table { width: 100% !important; }
        .office-select {
          appearance: none !important;
          -webkit-appearance: none !important;
          -moz-appearance: none !important;
          background-image: none !important;
          padding-right: 0 !important;
        }
        .office-select::-ms-expand {
          display: none !important;
        }
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

      <!-- Main content -->
      <main class="ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pt-14">
        <!-- Top bar -->
        <header
          class="admin-topbar hidden fixed top-0 left-0 md:left-64 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
        >
          <div class="flex items-center gap-2">
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
            <button class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 font-normal" type="button">
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
            List of Qualified Applicants
          </h2>
          </div>
        </section>

        <!-- Dashboard Main page -->
        <section class="mt-12 px-4 sm:px-6 py-4">
          <div class="no-print print-btn-bar mb-3 rounded-lg border border-[#0d8ddb] bg-white p-3 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <form class="flex flex-wrap items-center gap-2 lg:justify-end" method="get" action="list-of-qualified.php">
                <select
                  id="academicYear"
                  name="school_year"
                  class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                  aria-label="Select academic year"
                  onchange="this.form.submit()"
                >
                  <option value="" <?php echo $selectedSchoolYear === "" ? "selected" : ""; ?>>All School Years</option>
                  <?php foreach ($schoolYearOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedSchoolYear === $option ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($option); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select
                  id="semesterSelect"
                  name="semester"
                  class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
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
                <select
                  id="batchSelect"
                  name="batch"
                  class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                  aria-label="Select batch"
                  onchange="this.form.submit()"
                >
                  <option value="" <?php echo $selectedBatch === "" ? "selected" : ""; ?>>All Batches</option>
                  <?php foreach ($batchOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedBatch === $option ? "selected" : ""; ?>>
                      <?php echo htmlspecialchars($option); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if ($selectedSchoolYear !== "" || $selectedSemester !== "" || $selectedBatch !== ""): ?>
                  <a
                    href="list-of-qualified.php"
                    class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm"
                  >
                    Clear
                  </a>
                <?php endif; ?>
              </form>
              <div class="flex flex-wrap items-center gap-2">
                <button
                  type="submit"
                  form="qualifiedOfficeForm"
                  class="inline-flex items-center gap-2 rounded-full border border-[#052c6a] bg-[#052c6a] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#041c43]"
                >
                  <i class="fas fa-save"></i>
                  <span>Save</span>
                </button>
                <button
                  type="button"
                  onclick="window.print()"
                  class="inline-flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-[#0d8ddb] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#0a6fac]"
                >
                  <i class="fas fa-print"></i>
                  <span>Print</span>
                </button>
              </div>
            </div>
          </div>

          <?php if ($officeSaveMessage !== ""): ?>
            <div class="no-print mb-4 rounded-lg border px-4 py-3 text-xs font-semibold <?php echo $officeSaveMessageType === "success" ? "border-green-200 bg-green-50 text-green-700" : "border-red-200 bg-red-50 text-red-700"; ?>">
              <?php echo htmlspecialchars($officeSaveMessage); ?>
            </div>
          <?php endif; ?>
          <?php if ($officeOptionsError !== "" || empty($officeOptions)): ?>
            <div class="no-print mb-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-xs font-semibold text-yellow-800">
              <?php echo htmlspecialchars($officeOptionsError !== "" ? $officeOptionsError : "No registered head office found yet."); ?>
            </div>
          <?php endif; ?>

          <form id="qualifiedOfficeForm" method="post">
            <input type="hidden" name="school_year" value="<?php echo htmlspecialchars($selectedSchoolYear); ?>" />
            <input type="hidden" name="semester" value="<?php echo htmlspecialchars($selectedSemester); ?>" />
            <input type="hidden" name="batch" value="<?php echo htmlspecialchars($selectedBatch); ?>" />
          <div class="bg-white border border-[#0d8ddb] rounded shadow-sm p-4 md:p-6 paper">
            <div class="w-full mx-auto paper-wrap">
              <header>
                <div class="header-top">
                  <div class="header-left">
                    <img src="../img/SMCCNEWLOGO.png" alt="Seal of Saint Michael College of Caraga" />
                    <div class="header-left-text">
                      <h1 class="text-center">Saint Michael College of Caraga</h1>
                      <p class="text-center">Brgy. 4, Nasipit, Agusan del Norte, Philippines</p>
                      <p class="text-center">Tel. Nos. +63 085 343-3251 / +63 085 283-3113</p>
                      <p class="text-center"><a href="http://www.smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">www.smccnasipit.edu.ph</a></p>
                    </div>
                  </div>
                  <div class="header-right">
                    <img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC ISO 9001 logo" />
                  </div>
                </div>
              </header>

              <div class="text-center mb-1">
                <div class="title-line">OFFICE OF THE ADMISSION &amp; SCHOLARSHIP</div>
              </div>
              <hr class="border-black mb-2" />

              <section class="text-center mb-4">
                <h2 class="font-bold text-base">List of Qualified Applicants for Student Assistance Scholarship Program</h2>
                <p class="font-semibold text-sm" id="termText"><?php echo htmlspecialchars($displaySemester); ?>, S.Y. <?php echo htmlspecialchars($displaySchoolYear); ?></p>
                <p class="font-semibold text-sm" id="batchText"><?php echo htmlspecialchars($displayBatch); ?></p>
              </section>

              <section>
                <div class="overflow-x-auto plain-table">
                  <table>
                    <thead>
                      <tr>
                        <th style="width: 32px;">#</th>
                        <th>NAME</th>
                        <th>ADDRESS</th>
                        <th>CONTACT NUMBER</th>
                        <th>PROGRAM ENROLLED</th>
                        <th>YEAR LEVEL</th>
                        <th>ASSIGNED OFFICE</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($qualifiedApplicants)): ?>
                        <?php foreach ($qualifiedApplicants as $index => $qualifiedApplicant): ?>
                          <tr>
                            <td><?php echo htmlspecialchars((string)($index + 1)); ?></td>
                            <td><?php echo htmlspecialchars((string)($qualifiedApplicant["name"] ?? "")); ?></td>
                            <td><?php echo htmlspecialchars((string)($qualifiedApplicant["address"] ?? "")); ?></td>
                            <td><?php echo htmlspecialchars((string)($qualifiedApplicant["contact_number"] ?? "")); ?></td>
                            <td><?php echo htmlspecialchars((string)($qualifiedApplicant["program_course"] ?? "")); ?></td>
                            <td><?php echo htmlspecialchars((string)($qualifiedApplicant["year_level"] ?? "")); ?></td>
                            <td>
                              <input type="hidden" name="applicant_ids[]" value="<?php echo htmlspecialchars((string)($qualifiedApplicant["id"] ?? 0)); ?>" />
                              <select
                                name="assigned_office[<?php echo htmlspecialchars((string)($qualifiedApplicant["id"] ?? 0)); ?>]"
                                class="office-select"
                                <?php echo empty($officeOptions) ? "disabled" : ""; ?>
                              >
                                <option value=""></option>
                                <?php foreach ($officeOptions as $officeOption): ?>
                                  <option
                                    value="<?php echo htmlspecialchars($officeOption); ?>"
                                    <?php echo (($qualifiedApplicant["assigned_office"] ?? "") === $officeOption) ? "selected" : ""; ?>
                                  >
                                    <?php echo htmlspecialchars($officeOption); ?>
                                  </option>
                                <?php endforeach; ?>
                              </select>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="7">
                            <?php echo htmlspecialchars($qualifiedApplicantsError !== "" ? $qualifiedApplicantsError : "No hired applicants found."); ?>
                          </td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </section>

              <div class="mt-8 grid grid-cols-1 gap-6 max-w-3xl">
                <div>
                  <div class="subtle mb-1">Prepared by:</div>
                  <div class="font-semibold">ARLYN B. TUYOGON, MMBM</div>
                  <div class="sig-role">Head, Admission &amp; Scholarship</div>
                </div>

                <div>
                  <div class="subtle mb-1">Noted by:</div>
                  <div class="font-semibold">FELMARIE MANLUNAS, MACDDS</div>
                  <div class="sig-role">Head, Student Affairs &amp; Services</div>
                </div>

                <div>
                  <div class="subtle mb-1">Recommending Approval:</div>
                  <div class="font-semibold">RICKY E. DESTACAMENTO, RGC, MAED</div>
                  <div class="sig-role">Head, HRMDO</div>
                </div>

                <div>
                  <div class="subtle mb-1">Approved by:</div>
                  <div class="font-semibold">REV. FR. RONNIEL G. BABANO, STL</div>
                  <div class="sig-role">School President</div>
                </div>
              </div>
            </div>
          </div>
          </form>
        </section>
      </main>
    </div>

    <script>
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");
        const academicYearSelect = document.getElementById("academicYear");
        const semesterSelect = document.getElementById("semesterSelect");
        const termText = document.getElementById("termText");

        if (academicYearSelect && semesterSelect && termText) {
          const updateTermText = () => {
            const semester = semesterSelect.value || "1st Semester";
            const schoolYear = academicYearSelect.value || "<?php echo htmlspecialchars($currentSchoolYear, ENT_QUOTES); ?>";
            termText.textContent = `${semester}, S.Y. ${schoolYear}`;
          };
          academicYearSelect.addEventListener("change", updateTermText);
          semesterSelect.addEventListener("change", updateTermText);
          updateTermText();
        }

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
      });
    </script>
  <script>
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("sidebar");
  if (!sidebar) {
    return;
  }

  const currentPage = window.location.pathname.split("/").pop().toLowerCase();
  const sidebarAliases = {
    "view-application.php": "applicant.php",
    "department-evaluation-indi.php": "department-evaluation-list.php",
    "summary-reports.php": "summary-report.php",
    "list-0f-qualified.php": "list-of-qualified.php"
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
});
</script>
</body>
</html>










