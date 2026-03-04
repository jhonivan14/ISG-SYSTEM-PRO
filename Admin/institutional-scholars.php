<?php
require_once __DIR__ . "/includes/school-term-filter.php";

$autoImportType = "";
$autoImportMessage = "";
$pendingImportRecord = null;
$pendingImportCategory = "others";

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

$serverScholarRecords = [
  "official" => [],
  "student_assistant" => [],
];

if (($conn ?? null) instanceof mysqli) {
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

  $rankInputTableResult = $conn->query("SHOW TABLES LIKE 'applicant_rank_inputs'");
  if ($hasAssignedOfficeColumn && $rankInputTableResult instanceof mysqli_result && $rankInputTableResult->num_rows > 0) {
    $rankInputTableResult->free();

    $whereClauses = [
      "a.grant_id = 1",
      "LOWER(TRIM(a.status)) = 'approved'",
      "LOWER(TRIM(COALESCE(ari.remarks, ''))) = 'hired'",
      "TRIM(COALESCE(a.assigned_office, '')) <> ''",
    ];
    $params = [];
    $types = "";

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
      WHERE " . implode(" AND ", $whereClauses) . "
      ORDER BY a.applicant_name ASC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
      if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
      }
      if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
          $program = trim((string)($row["program_course"] ?? ""));
          $yearLevel = trim((string)($row["year_level"] ?? ""));
          $programYear = $program;
          if ($yearLevel !== "") {
            $programYear .= ($programYear !== "" ? " / " : "") . $yearLevel;
          }

          $record = [
            "source_application_id" => (int)($row["id"] ?? 0),
            "scholar_id" => "APP-" . str_pad((string)((int)($row["id"] ?? 0)), 5, "0", STR_PAD_LEFT),
            "grant_applied" => "Student Assistant",
            "full_name" => trim((string)($row["applicant_name"] ?? "")),
            "program_year" => $programYear,
            "assigned_office" => trim((string)($row["assigned_office"] ?? "")),
            "semester" => trim((string)($row["semester"] ?? "")),
            "academic_year" => trim((string)($row["school_year"] ?? "")),
            "remarks" => "Hired",
          ];

          $serverScholarRecords["official"][] = $record;
          $serverScholarRecords["student_assistant"][] = $record;
        }
        $result->free();
      }
      $stmt->close();
    }
  } elseif ($rankInputTableResult instanceof mysqli_result) {
    $rankInputTableResult->free();
  }
}

if (
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
          $pendingImportCategory = $grantToCategoryMap[$grantId] ?? "others";
          $grantLabel = $grantLabels[$grantId] ?? "Others";

          $program = trim((string)($row["program_course"] ?? ""));
          $yearLevel = trim((string)($row["year_level"] ?? ""));
          $programYear = $program;
          if ($yearLevel !== "") {
            $programYear .= ($programYear !== "" ? " / " : "") . $yearLevel;
          }

          $applicantName = trim((string)($row["applicant_name"] ?? ""));
          $schoolYear = trim((string)($row["school_year"] ?? ""));
          $semester = trim((string)($row["semester"] ?? ""));
          $pendingImportRecord = [
            "source_application_id" => (int)($row["id"] ?? 0),
            "scholar_id" => "APP-" . str_pad((string)((int)($row["id"] ?? 0)), 5, "0", STR_PAD_LEFT),
            "grant_applied" => $grantLabel,
            "full_name" => $applicantName,
            "program_year" => $programYear,
            "assigned_office" => "",
            "semester" => $semester !== "" ? $semester : $displaySemester,
            "academic_year" => $schoolYear !== "" ? $schoolYear : $displaySchoolYear,
            "remarks" => "Confirmed from Approved Applications",
          ];

          $autoImportType = "success";
          $autoImportMessage = $applicantName !== ""
            ? ($applicantName . " was added to Institutional Scholars.")
            : "Applicant was added to Institutional Scholars.";
        }
      }
    } else {
      $autoImportType = "error";
      $autoImportMessage = "Unable to load applicant details right now.";
    }
  }
}
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Institutional Scholars</title>
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

              <?php if ($autoImportMessage !== ""): ?>
                <div class="mt-3 rounded-lg border px-3 py-2 text-xs font-semibold <?php echo $autoImportType === "success" ? "border-green-200 bg-green-50 text-green-700" : "border-red-200 bg-red-50 text-red-700"; ?>">
                  <?php echo htmlspecialchars($autoImportMessage); ?>
                </div>
              <?php endif; ?>

              <form class="mt-4 flex flex-wrap gap-2" method="get" action="institutional-scholars.php">
                <select
                  class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                  name="school_year"
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
                <?php if ($selectedSchoolYear !== "" || $selectedSemester !== ""): ?>
                  <a
                    href="institutional-scholars.php"
                    class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm"
                  >
                    Clear
                  </a>
                <?php endif; ?>
              </form>

              <div
                id="renewalTermNotice"
                class="mt-3 hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-800"
              ></div>

              <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 mt-4 text-xs">
                <div class="rounded-lg border border-[#dbeafe] bg-[#eff6ff] px-3 py-2">
                  <p class="text-[#1e3a8a] font-semibold">Official Scholars</p>
                  <p id="count-official" class="text-lg font-bold text-[#052c6a]">0</p>
                </div>
                <div class="rounded-lg border border-[#dcfce7] bg-[#f0fdf4] px-3 py-2">
                  <p class="text-[#166534] font-semibold">Student Assistant</p>
                  <p id="count-student-assistant" class="text-lg font-bold text-[#14532d]">0</p>
                </div>
                <div class="rounded-lg border border-[#fae8ff] bg-[#fdf4ff] px-3 py-2">
                  <p class="text-[#86198f] font-semibold">Kabayani</p>
                  <p id="count-kabayani" class="text-lg font-bold text-[#701a75]">0</p>
                </div>
                <div class="rounded-lg border border-[#fef9c3] bg-[#fefce8] px-3 py-2">
                  <p class="text-[#854d0e] font-semibold">Academic</p>
                  <p id="count-academic" class="text-lg font-bold text-[#713f12]">0</p>
                </div>
                <div class="rounded-lg border border-[#fee2e2] bg-[#fef2f2] px-3 py-2">
                  <p class="text-[#991b1b] font-semibold">Others</p>
                  <p id="count-others" class="text-lg font-bold text-[#7f1d1d]">0</p>
                </div>
              </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-[#e5e7eb] overflow-hidden flex-1 flex flex-col min-h-[420px]">
              <div class="px-4 sm:px-6 py-4 border-b border-[#e5e7eb] bg-[#f8fafc]">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <div>
                    <h2 class="text-base font-bold text-[#052c6a]">Scholar Category Tables</h2>
                    <p class="text-xs text-gray-600">Live records include hired student assistants with assigned office plus any saved scholar entries.</p>
                  </div>
                  <span class="inline-flex items-center gap-2 text-[11px] font-semibold text-[#0f172a] bg-white border border-[#e2e8f0] px-3 py-1 rounded-full">
                    Active: <span id="activeCategoryLabel" class="text-[#052c6a]">Official Scholars</span>
                  </span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                  <button type="button" data-category="official" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-transparent bg-[#052c6a] text-white shadow-sm">Official Scholars</button>
                  <button type="button" data-category="student_assistant" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-[#e2e8f0] bg-white text-[#334155]">Student Assistant</button>
                  <button type="button" data-category="kabayani" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-[#e2e8f0] bg-white text-[#334155]">Kabayani</button>
                  <button type="button" data-category="academic" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-[#e2e8f0] bg-white text-[#334155]">Academic</button>
                  <button type="button" data-category="others" class="category-btn px-3 py-2 rounded-lg text-xs font-semibold border border-[#e2e8f0] bg-white text-[#334155]">Others</button>
                </div>
              </div>

              <div class="px-4 sm:px-6 py-4 overflow-x-auto flex-1">
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
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Remarks</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#0f172a]/20">Action</th>
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
      const selectedSchoolYear = <?php echo json_encode($selectedSchoolYear); ?>;
      const selectedSemester = <?php echo json_encode($selectedSemester); ?>;
      const displaySchoolYear = <?php echo json_encode($displaySchoolYear); ?>;
      const displaySemester = <?php echo json_encode($displaySemester); ?>;
      const currentSchoolYear = <?php echo json_encode($currentSchoolYear); ?>;
      const nextSchoolYear = <?php
        $currentStartYear = (int)substr((string)$currentSchoolYear, 0, 4);
        echo json_encode(($currentStartYear + 1) . "-" . ($currentStartYear + 2));
      ?>;
      const activeFilterSchoolYear = String(selectedSchoolYear || displaySchoolYear || "").trim();
      const activeFilterSemester = String(selectedSemester || displaySemester || "").trim();
      const pendingImportRecord = <?php echo json_encode($pendingImportRecord, JSON_UNESCAPED_UNICODE); ?>;
      const pendingImportCategory = <?php echo json_encode($pendingImportCategory); ?>;
      const serverScholarRecords = <?php echo json_encode($serverScholarRecords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

      const categoryConfig = {
        official: {
          label: "Official Scholars",
          storageKey: "isg_scholars_official"
        },
        student_assistant: {
          label: "Student Assistant",
          storageKey: "isg_scholars_student_assistant"
        },
        kabayani: {
          label: "Kabayani",
          storageKey: "isg_scholars_kabayani"
        },
        academic: {
          label: "Academic",
          storageKey: "isg_scholars_academic"
        },
        others: {
          label: "Others",
          storageKey: "isg_scholars_others"
        }
      };

      let activeCategory = "official";

      function getServerBackedRecords(category) {
        const records = serverScholarRecords && typeof serverScholarRecords === "object"
          ? serverScholarRecords[category]
          : [];
        return Array.isArray(records) ? records : [];
      }

      function isServerBackedCategory(category) {
        return category === "official" || category === "student_assistant";
      }

      function safeParseArray(value) {
        try {
          const parsed = JSON.parse(value);
          return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
          return [];
        }
      }

      function escapeHtml(value) {
        return String(value ?? "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/\"/g, "&quot;")
          .replace(/'/g, "&#39;");
      }

      function getCategoryRecords(category) {
        const config = categoryConfig[category];
        if (!config) return [];

        const raw = localStorage.getItem(config.storageKey);
        if (raw === null) {
          localStorage.setItem(config.storageKey, "[]");
        }

        const persistedRecords = raw === null ? [] : safeParseArray(raw);
        if (!isServerBackedCategory(category)) {
          return persistedRecords;
        }

        const serverRecords = getServerBackedRecords(category);
        const persistedByKey = new Map();
        persistedRecords.forEach((record, index) => {
          persistedByKey.set(getRecordKey(record, index), record);
        });

        const mergedRecords = serverRecords.map((record, index) => {
          const recordKey = getRecordKey(record, index);
          const persisted = persistedByKey.get(recordKey);
          if (!persisted || typeof persisted !== "object") {
            return record;
          }

          return {
            ...record,
            renewal_status: String(persisted.renewal_status || "").trim(),
            renewal_scope: String(persisted.renewal_scope || "").trim(),
            second_semester_renewed: persisted.second_semester_renewed === true
          };
        });

        const mergedKeys = new Set(mergedRecords.map((record, index) => getRecordKey(record, index)));
        persistedRecords.forEach((record, index) => {
          const recordKey = getRecordKey(record, index);
          if (!mergedKeys.has(recordKey)) {
            mergedRecords.push(record);
          }
        });

        return mergedRecords;
      }

      function saveCategoryRecords(category, records) {
        const config = categoryConfig[category];
        if (!config) return;
        localStorage.setItem(config.storageKey, JSON.stringify(Array.isArray(records) ? records : []));
      }

      function inferGrantFromOtherCategories(record) {
        const sourceId = String(record && typeof record === "object" ? (record.source_application_id ?? "") : "").trim();
        const scholarId = String(record && typeof record === "object" ? (record.scholar_id ?? "") : "").trim();
        const lookupCategories = ["student_assistant", "kabayani", "academic", "others"];

        for (let i = 0; i < lookupCategories.length; i += 1) {
          const category = lookupCategories[i];
          const records = getCategoryRecords(category);
          for (let j = 0; j < records.length; j += 1) {
            const item = records[j];
            const itemSourceId = String(item && typeof item === "object" ? (item.source_application_id ?? "") : "").trim();
            const itemScholarId = String(item && typeof item === "object" ? (item.scholar_id ?? "") : "").trim();
            const sameSource = sourceId !== "" && itemSourceId === sourceId;
            const sameScholar = scholarId !== "" && itemScholarId === scholarId;
            if (!sameSource && !sameScholar) continue;

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
        const sourceId = String(record && typeof record === "object" ? (record.source_application_id ?? "") : "").trim();
        if (sourceId !== "") return "app-" + sourceId;

        const scholarId = String(record && typeof record === "object" ? (record.scholar_id ?? "") : "").trim();
        if (scholarId !== "") return "sid-" + scholarId;

        return "idx-" + index;
      }

      function upsertCategoryRecord(category, record) {
        const config = categoryConfig[category];
        if (!config || !record || typeof record !== "object") return;

        const sourceId = String(record.source_application_id || "").trim();
        if (sourceId === "") return;

        const records = getCategoryRecords(category);
        const existingIndex = records.findIndex((item) => String(item.source_application_id || "").trim() === sourceId);

        if (existingIndex >= 0) {
          records[existingIndex] = { ...records[existingIndex], ...record };
        } else {
          records.push(record);
        }

        localStorage.setItem(config.storageKey, JSON.stringify(records));
      }

      function updateCounts() {
        const counts = {
          official: getCategoryRecords("official").length,
          student_assistant: getCategoryRecords("student_assistant").length,
          kabayani: getCategoryRecords("kabayani").length,
          academic: getCategoryRecords("academic").length,
          others: getCategoryRecords("others").length
        };

        document.getElementById("count-official").textContent = counts.official;
        document.getElementById("count-student-assistant").textContent = counts.student_assistant;
        document.getElementById("count-kabayani").textContent = counts.kabayani;
        document.getElementById("count-academic").textContent = counts.academic;
        document.getElementById("count-others").textContent = counts.others;
      }

      function updateRecordRenewalStatus(category, recordKey, renewalStatus, renewalScope = "") {
        const config = categoryConfig[category];
        if (!config) return;

        const records = getCategoryRecords(category);
        const nextRecords = records.map((record, index) => {
          if (getRecordKey(record, index) !== recordKey) return record;
          const normalizedScope = renewalStatus === "renew" ? String(renewalScope || "").trim() : "";
          const alreadySecondSemRenewed = record && record.second_semester_renewed === true;
          const secondSemRenewed = alreadySecondSemRenewed || (renewalStatus === "renew" && normalizedScope === "2nd_semester");
          return {
            ...record,
            renewal_status: renewalStatus,
            renewal_scope: normalizedScope,
            second_semester_renewed: secondSemRenewed
          };
        });

        localStorage.setItem(config.storageKey, JSON.stringify(nextRecords));
      }

      function getRenewalStatusLabel(status, scope) {
        if (status === "renew") {
          if (scope === "2nd_semester") {
            return '<span class="inline-flex items-center rounded-full bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 text-[10px] font-semibold">Renewed (2nd Semester)</span>';
          }
          if (scope === "school_year") {
            return '<span class="inline-flex items-center rounded-full bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 text-[10px] font-semibold">Renewed (School Year)</span>';
          }
          return '<span class="inline-flex items-center rounded-full bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 text-[10px] font-semibold">Renewed</span>';
        }

        if (status === "do_not_renew") {
          return '<span class="inline-flex items-center rounded-full bg-red-50 text-red-700 border border-red-200 px-2 py-0.5 text-[10px] font-semibold">Do Not Renew</span>';
        }

        return '<span class="inline-flex items-center rounded-full bg-slate-50 text-slate-600 border border-slate-200 px-2 py-0.5 text-[10px] font-semibold">No action yet</span>';
      }

      function shouldShowAssignedOfficeColumn(category) {
        return category === "student_assistant" || category === "official";
      }

      function getRenewalActionAvailability() {
        const semesterValue = String(activeFilterSemester || "").trim().toLowerCase();
        const schoolYearValue = String(activeFilterSchoolYear || "").trim();
        const isSecondSemester = semesterValue === "2nd semester";
        const isNextSchoolYear = schoolYearValue === nextSchoolYear;
        const enabled = isSecondSemester || isNextSchoolYear;
        const reason = enabled
          ? ""
          : ("Renew/Do Not Renew is disabled until Semester is 2nd Semester or School Year is " + nextSchoolYear + ".");

        return {
          enabled,
          reason
        };
      }

      function renderRenewalTermNotice() {
        const notice = document.getElementById("renewalTermNotice");
        if (!notice) return;

        const availability = getRenewalActionAvailability();
        if (availability.enabled) {
          notice.classList.add("hidden");
          notice.textContent = "";
          return;
        }

        notice.classList.remove("hidden");
        notice.textContent = availability.reason;
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

      function hasSecondSemesterRenewal(record) {
        if (!record || typeof record !== "object") return false;
        if (record.second_semester_renewed === true) return true;

        const status = String(record.renewal_status || "").trim();
        const scope = String(record.renewal_scope || "").trim();
        return status === "renew" && scope === "2nd_semester";
      }

      function showRenewOptions(category, recordKey) {
        const availability = getRenewalActionAvailability();
        if (!availability.enabled) {
          if (typeof Swal !== "undefined") {
            Swal.fire({
              title: "Action Disabled",
              text: availability.reason,
              icon: "info",
              confirmButtonColor: "#0d8ddb"
            });
          } else {
            window.alert(availability.reason);
          }
          return;
        }

        if (typeof Swal === "undefined") {
          const useSecondSem = window.confirm("Renew for 2nd Semester?\nClick Cancel for School Year.");
          if (!useSecondSem) {
            const record = getRecordByKey(category, recordKey);
            if (!hasSecondSemesterRenewal(record)) {
              window.alert("Cannot renew for School Year yet. Renew this scholar for 2nd Semester first.");
              return;
            }
          }
          updateRecordRenewalStatus(category, recordKey, "renew", useSecondSem ? "2nd_semester" : "school_year");
          renderTable(category);
          return;
        }

        Swal.fire({
          title: "Renew Scholar",
          text: "Choose the renewal coverage. (School Year is allowed only after 2nd Semester renewal.)",
          icon: "question",
          showCancelButton: true,
          showDenyButton: true,
          confirmButtonText: "2nd Semester",
          denyButtonText: "School Year",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#16a34a",
          denyButtonColor: "#0d8ddb",
          reverseButtons: true
        }).then((result) => {
          if (result.isConfirmed) {
            updateRecordRenewalStatus(category, recordKey, "renew", "2nd_semester");
            renderTable(category);
            Swal.fire({
              title: "Renewed",
              text: "Scholar is marked as renewed for 2nd Semester.",
              icon: "success",
              timer: 1500,
              showConfirmButton: false
            });
          } else if (result.isDenied) {
            const record = getRecordByKey(category, recordKey);
            if (!hasSecondSemesterRenewal(record)) {
              Swal.fire({
                title: "Not Allowed Yet",
                text: "Renew for 2nd Semester first before renewing for School Year.",
                icon: "warning",
                confirmButtonColor: "#0d8ddb"
              });
              return;
            }
            updateRecordRenewalStatus(category, recordKey, "renew", "school_year");
            renderTable(category);
            Swal.fire({
              title: "Renewed",
              text: "Scholar is marked as renewed for School Year.",
              icon: "success",
              timer: 1500,
              showConfirmButton: false
            });
          }
        });
      }

      function showDoNotRenewConfirm(category, recordKey) {
        const availability = getRenewalActionAvailability();
        if (!availability.enabled) {
          if (typeof Swal !== "undefined") {
            Swal.fire({
              title: "Action Disabled",
              text: availability.reason,
              icon: "info",
              confirmButtonColor: "#0d8ddb"
            });
          } else {
            window.alert(availability.reason);
          }
          return;
        }

        if (typeof Swal === "undefined") {
          const shouldContinue = window.confirm("Mark this scholar as Do Not Renew?");
          if (shouldContinue) {
            updateRecordRenewalStatus(category, recordKey, "do_not_renew", "");
            renderTable(category);
          }
          return;
        }

        Swal.fire({
          title: "Do Not Renew?",
          text: "This scholar will be tagged as Do Not Renew.",
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Yes, continue",
          cancelButtonText: "Cancel",
          confirmButtonColor: "#dc2626"
        }).then((result) => {
          if (!result.isConfirmed) return;
          updateRecordRenewalStatus(category, recordKey, "do_not_renew", "");
          renderTable(category);
          Swal.fire({
            title: "Updated",
            text: "Scholar is marked as Do Not Renew.",
            icon: "success",
            timer: 1500,
            showConfirmButton: false
          });
        });
      }

      function renderTable(category) {
        const config = categoryConfig[category];
        const tableBody = document.getElementById("scholarRows");
        const assignedOfficeHeader = document.getElementById("assignedOfficeHeader");
        const showAssignedOffice = shouldShowAssignedOfficeColumn(category);
        const renewalAvailability = getRenewalActionAvailability();
        const actionButtonsEnabled = renewalAvailability.enabled;
        const actionDisabledReason = renewalAvailability.reason;
        const columnCount = showAssignedOffice ? 9 : 8;
        if (assignedOfficeHeader) {
          assignedOfficeHeader.classList.toggle("hidden", !showAssignedOffice);
        }
        const records = getCategoryRecords(category);
        const filteredRecords = records
          .map((record, index) => ({ ...record, __recordKey: getRecordKey(record, index) }))
          .filter((record) => {
          const recordYear = String(record.academic_year || "").trim();
          const recordSemester = String(record.semester || "").trim();
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

        filteredRecords.forEach((record, index) => {
          const grantApplied = resolveGrantApplied(category, record);
          const assignedOfficeRaw = String(record.assigned_office || "").trim();
          const assignedOfficeCell = showAssignedOffice
            ? ('<td class="px-3 py-2">' + escapeHtml(assignedOfficeRaw !== "" ? assignedOfficeRaw : "-") + "</td>")
            : "";
          const renewalStatus = String(record.renewal_status || "").trim();
          const renewalScope = String(record.renewal_scope || "").trim();
          const renewBtnClasses =
            renewalStatus === "renew"
              ? "bg-green-600 text-white border-green-600"
              : "bg-white text-green-700 border-green-300 hover:bg-green-50";
          const doNotRenewBtnClasses =
            renewalStatus === "do_not_renew"
              ? "bg-red-600 text-white border-red-600"
              : "bg-white text-red-700 border-red-300 hover:bg-red-50";
          const disabledClasses = actionButtonsEnabled ? "" : "opacity-50 cursor-not-allowed hover:bg-transparent";
          const disabledAttrs = actionButtonsEnabled
            ? ""
            : (' disabled title="' + escapeHtml(actionDisabledReason) + '" aria-disabled="true" ');

          const row = document.createElement("tr");
          row.innerHTML =
            '<td class="px-3 py-2">' + (index + 1) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(grantApplied) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.full_name) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.program_year) + "</td>" +
            assignedOfficeCell +
            '<td class="px-3 py-2">' + escapeHtml(record.semester) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.academic_year) + "</td>" +
            '<td class="px-3 py-2">' + escapeHtml(record.remarks) + "</td>" +
            '<td class="px-3 py-2">' +
              '<div class="flex flex-col gap-1 min-w-[140px]">' +
                '<div class="flex gap-1">' +
                  '<button type="button" data-renew-action="renew" data-record-key="' + escapeHtml(record.__recordKey) + '" class="px-2 py-1 rounded border text-[10px] font-semibold transition-colors ' + renewBtnClasses + ' ' + disabledClasses + '"' + disabledAttrs + '>Renew</button>' +
                  '<button type="button" data-renew-action="do_not_renew" data-record-key="' + escapeHtml(record.__recordKey) + '" class="px-2 py-1 rounded border text-[10px] font-semibold transition-colors ' + doNotRenewBtnClasses + ' ' + disabledClasses + '"' + disabledAttrs + '>Do Not Renew</button>' +
                '</div>' +
                getRenewalStatusLabel(renewalStatus, renewalScope) +
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

          const button = target.closest("[data-renew-action]");
          if (!button) return;
          if (button.disabled) return;

          const recordKey = String(button.getAttribute("data-record-key") || "").trim();
          const action = String(button.getAttribute("data-renew-action") || "").trim();
          if (recordKey === "" || (action !== "renew" && action !== "do_not_renew")) return;

          if (action === "renew") {
            showRenewOptions(activeCategory, recordKey);
            return;
          }

          showDoNotRenewConfirm(activeCategory, recordKey);
        });
      }

      function setActiveCategoryButton(selectedCategory) {
        document.querySelectorAll(".category-btn").forEach((button) => {
          const isActive = button.dataset.category === selectedCategory;
          button.classList.toggle("bg-[#052c6a]", isActive);
          button.classList.toggle("text-white", isActive);
          button.classList.toggle("shadow-sm", isActive);
          button.classList.toggle("border-transparent", isActive);
          button.classList.toggle("bg-white", !isActive);
          button.classList.toggle("text-[#334155]", !isActive);
          button.classList.toggle("border-[#e2e8f0]", !isActive);
        });
      }

      function setupCategorySwitching() {
        document.querySelectorAll(".category-btn").forEach((button) => {
          button.addEventListener("click", () => {
            activeCategory = button.dataset.category;
            setActiveCategoryButton(activeCategory);
            renderTable(activeCategory);
          });
        });
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
        if (pendingImportRecord && typeof pendingImportRecord === "object") {
          upsertCategoryRecord("official", pendingImportRecord);
          if (pendingImportCategory && pendingImportCategory !== "official") {
            upsertCategoryRecord(pendingImportCategory, pendingImportRecord);
          }

          const currentUrl = new URL(window.location.href);
          currentUrl.searchParams.delete("source");
          currentUrl.searchParams.delete("applicant_id");
          window.history.replaceState({}, document.title, currentUrl.toString());
        }

        normalizeGrantLabels();

        setupSidebar();
        markActiveSidebarItem();
        setupCategorySwitching();
        setupRenewalActions();
        renderRenewalTermNotice();
        updateCounts();
        setActiveCategoryButton(activeCategory);
        renderTable(activeCategory);
      });
    </script>
  </body>
</html>









