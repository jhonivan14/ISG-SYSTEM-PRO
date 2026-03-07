<?php
require_once __DIR__ . "/includes/school-term-filter.php";

$assistantEvaluationRecords = [];

function isgSplitProgramYearForDepartmentList(string $programYear): array
{
  $value = trim($programYear);
  if ($value === "") {
    return ["", ""];
  }

  $parts = preg_split('/\s*\/\s*/', $value, 2);
  $course = trim((string)($parts[0] ?? ""));
  $yearLevel = trim((string)($parts[1] ?? ""));
  if ($course === "") {
    $course = $value;
  }

  return [$course, $yearLevel];
}

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
      ORDER BY a.assigned_office ASC, a.applicant_name ASC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
      if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
      }
      if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $assistantEvaluationRecords[] = [
              "applicantId" => (int)($row["id"] ?? 0),
            "name" => trim((string)($row["applicant_name"] ?? "")),
            "course" => trim((string)($row["program_course"] ?? "")),
            "yearLevel" => trim((string)($row["year_level"] ?? "")),
            "office" => trim((string)($row["assigned_office"] ?? "")),
            "status" => "not yet evaluated",
            "academicYear" => trim((string)($row["school_year"] ?? "")),
            "semester" => trim((string)($row["semester"] ?? "")),
            "evaluatedAt" => null,
              "sourceType" => "application",
            ];
        }
        $result->free();
      }
      $stmt->close();
    }
  } elseif ($rankInputTableResult instanceof mysqli_result) {
    $rankInputTableResult->free();
  }

  $institutionalTableResult = $conn->query("SHOW TABLES LIKE 'institutional_scholar_records'");
  if ($institutionalTableResult instanceof mysqli_result) {
    $hasInstitutionalTable = $institutionalTableResult->num_rows > 0;
    $institutionalTableResult->free();

    if ($hasInstitutionalTable) {
      $hasContractEndedColumn = false;
      $contractEndedColumnResult = $conn->query("SHOW COLUMNS FROM institutional_scholar_records LIKE 'contract_ended'");
      if ($contractEndedColumnResult instanceof mysqli_result) {
        $hasContractEndedColumn = $contractEndedColumnResult->num_rows > 0;
        $contractEndedColumnResult->free();
      }

      $isrWhereClauses = [
        "LOWER(TRIM(COALESCE(category, ''))) = 'student_assistant'",
        "TRIM(COALESCE(assigned_office, '')) <> ''",
        "(scholar_id LIKE 'MAN-%' OR scholar_id LIKE 'CSV-%')",
      ];
      $isrParams = [];
      $isrTypes = "";

      if ($hasContractEndedColumn) {
        $isrWhereClauses[] = "COALESCE(contract_ended, 0) = 0";
      }
      if ($selectedSchoolYear !== "") {
        $isrWhereClauses[] = "academic_year = ?";
        $isrParams[] = $selectedSchoolYear;
        $isrTypes .= "s";
      }
      if ($selectedSemester !== "") {
        $isrWhereClauses[] = "semester = ?";
        $isrParams[] = $selectedSemester;
        $isrTypes .= "s";
      }

      $isrSql = "
        SELECT
          id,
          scholar_id,
          full_name,
          program_year,
          assigned_office,
          academic_year,
          semester
        FROM institutional_scholar_records
        WHERE " . implode(" AND ", $isrWhereClauses) . "
        ORDER BY assigned_office ASC, full_name ASC
      ";
      $isrStmt = $conn->prepare($isrSql);
      if ($isrStmt) {
        if (!empty($isrParams)) {
          $isrStmt->bind_param($isrTypes, ...$isrParams);
        }
        if ($isrStmt->execute()) {
          $isrResult = $isrStmt->get_result();
          while ($row = $isrResult->fetch_assoc()) {
            [$programCourse, $yearLevel] = isgSplitProgramYearForDepartmentList((string)($row["program_year"] ?? ""));
            $assistantEvaluationRecords[] = [
              "applicantId" => 0 - (int)($row["id"] ?? 0),
              "name" => trim((string)($row["full_name"] ?? "")),
              "course" => $programCourse,
              "yearLevel" => $yearLevel,
              "office" => trim((string)($row["assigned_office"] ?? "")),
              "status" => "not yet evaluated",
              "academicYear" => trim((string)($row["academic_year"] ?? "")),
              "semester" => trim((string)($row["semester"] ?? "")),
              "evaluatedAt" => null,
              "sourceType" => "institutional",
            ];
          }
          $isrResult->free();
        }
        $isrStmt->close();
      }
    }
  }

  if (!empty($assistantEvaluationRecords)) {
    usort($assistantEvaluationRecords, static function (array $left, array $right): int {
      $leftOffice = strtolower(trim((string)($left["office"] ?? "")));
      $rightOffice = strtolower(trim((string)($right["office"] ?? "")));
      if ($leftOffice !== $rightOffice) {
        return $leftOffice <=> $rightOffice;
      }

      $leftName = strtolower(trim((string)($left["name"] ?? "")));
      $rightName = strtolower(trim((string)($right["name"] ?? "")));
      return $leftName <=> $rightName;
    });
  }
}
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Student Assistants Evaluation List</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
            DEPARTMENTAL EVALUATION
          </h2>
          </div>
        </section>

        <section class="mt-12 px-3 sm:px-4 lg:px-6 py-4 bg-gray-100 flex-1 min-h-[calc(100vh-3rem)]">
          <div class="w-full space-y-4 h-full flex flex-col">
            <div class="bg-white rounded-lg shadow-sm border border-[#e5e7eb] px-4 sm:px-6 py-4 space-y-3">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                  <h2 class="text-lg font-semibold text-[#052c6a]">Student Assistants Evaluation List</h2>
                  <p class="text-sm text-gray-600">Open the window per semester to show status and actions.</p>
                  <p class="text-xs text-gray-500 mt-1">Evaluation window: <span id="evalWindowLabel" class="font-semibold text-[#052c6a]">Closed</span></p>
                </div>
                <button
                  id="openEvalBtn"
                  class="bg-[#0d8ddb] hover:bg-[#0b7cc4] text-white text-sm font-semibold px-4 py-2 rounded shadow-sm transition"
                >
                  Open for evaluation
                </button>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                  <label for="searchInput" class="text-xs text-gray-600">Search</label>
                  <input
                    id="searchInput"
                    type="text"
                    placeholder="Search name, course, office..."
                    class="mt-1 w-full border border-[#e5e7eb] rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]"
                  />
                </div>
                <form method="get" action="department-evaluation-list.php" class="contents">
                  <div>
                    <label for="yearFilter" class="text-xs text-gray-600">Academic Year</label>
                    <select
                      id="yearFilter"
                      name="school_year"
                      class="mt-1 w-full border border-[#e5e7eb] rounded px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]"
                      aria-label="Select academic year"
                      onchange="this.form.submit()"
                    >
                      <option value="" <?php echo $selectedSchoolYear === "" ? "selected" : ""; ?>>All Academic Years</option>
                      <?php foreach ($schoolYearOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedSchoolYear === $option ? "selected" : ""; ?>>
                          <?php echo htmlspecialchars($option); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label for="semFilter" class="text-xs text-gray-600">Semester</label>
                    <select
                      id="semFilter"
                      name="semester"
                      class="mt-1 w-full border border-[#e5e7eb] rounded px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]"
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
                        href="department-evaluation-list.php"
                        class="inline-flex items-center mt-2 rounded border border-[#e5e7eb] bg-white px-3 py-1.5 text-xs font-semibold text-[#052c6a]"
                      >
                        Clear
                      </a>
                    <?php endif; ?>
                  </div>
                </form>
              </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-[#e5e7eb] flex-1 flex flex-col min-h-[420px]">
              <div class="flex items-center gap-2 text-xs text-gray-500 px-4 sm:px-6 pt-4">
                <span class="h-2 w-2 rounded-full bg-green-500 inline-block"></span> Evaluated
                <span class="h-2 w-2 rounded-full bg-yellow-400 inline-block ml-3"></span> Not yet evaluated
              </div>
              <div class="px-4 sm:px-6 pb-6 pt-3 overflow-x-auto flex-1">
                <table class="min-w-full text-xs border border-[#e5e7eb]">
                  <thead class="bg-gray-50 text-[#052c6a]">
                    <tr>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Timestamp</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Name</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Course</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Year Level</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Assigned Office</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Status</th>
                      <th class="text-left font-semibold px-3 py-2 border-b border-[#e5e7eb]">Action</th>
                    </tr>
                  </thead>
                  <tbody id="assistantRows" class="divide-y divide-[#e5e7eb]">
                    <!-- Filled by JS -->
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>

    <script>
      document.addEventListener("DOMContentLoaded", () => {
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
      });

      // Populate student assistants list; hide status/action until opened
      document.addEventListener("DOMContentLoaded", () => {
        const assistantData = <?php echo json_encode($assistantEvaluationRecords, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

        const tbody = document.getElementById("assistantRows");
        const openBtn = document.getElementById("openEvalBtn");
        const windowLabel = document.getElementById("evalWindowLabel");
        const searchInput = document.getElementById("searchInput");
        const yearFilter = document.getElementById("yearFilter");
        const semFilter = document.getElementById("semFilter");
        if (!tbody) return;

        let evalOpen = false;
        let searchTerm = "";
        let yearSelection = yearFilter && yearFilter.value !== "" ? yearFilter.value : "all";
        let semSelection = semFilter && semFilter.value !== "" ? semFilter.value : "all";

        const renderRows = () => {
          tbody.innerHTML = "";
          const filtered = assistantData.filter((item) => {
            const matchesSearch =
              !searchTerm ||
              item.name.toLowerCase().includes(searchTerm) ||
              item.course.toLowerCase().includes(searchTerm) ||
              item.office.toLowerCase().includes(searchTerm);
            const matchesYear = yearSelection === "all" || item.academicYear === yearSelection;
            const matchesSem = semSelection === "all" || item.semester === semSelection;
            return matchesSearch && matchesYear && matchesSem;
          });

          if (filtered.length === 0) {
            tbody.innerHTML = `
              <tr>
                <td colspan="7" class="px-3 py-6 text-center text-gray-500 italic">
                  No student assistants with assigned office found.
                </td>
              </tr>
            `;
            return;
          }

          filtered.forEach((item) => {
            const row = document.createElement("tr");

            const timestamp =
              evalOpen && item.status === "evaluated" && item.evaluatedAt
                ? new Date(item.evaluatedAt).toLocaleString()
                : "--";

            const statusClasses =
              item.status === "evaluated"
                ? "bg-green-100 text-green-800"
                : "bg-yellow-100 text-yellow-800";

            const disabled = !evalOpen;
            const actionLabel = item.status === "evaluated" ? "View Evaluation" : "Open Evaluation";
            const actionHref = `department-evaluation-indi.php?application_id=${encodeURIComponent(String(item.applicantId || ""))}#evaluation-details`;

            row.innerHTML = `
              <td class="px-3 py-2 text-[#052c6a]">${timestamp}</td>
              <td class="px-3 py-2 text-[#052c6a] font-semibold">${item.name}</td>
              <td class="px-3 py-2 text-[#052c6a]">${item.course}</td>
              <td class="px-3 py-2 text-[#052c6a]">${item.yearLevel}</td>
              <td class="px-3 py-2 text-[#052c6a]">${item.office}</td>
              <td class="px-3 py-2">
                ${
                  evalOpen
                    ? `<span class="px-2 py-1 rounded-full text-[11px] ${statusClasses}">
                        ${item.status === "evaluated" ? "Evaluated" : "Not yet evaluated"}
                      </span>`
                    : `<span class="text-[11px] text-red-600 italic">Not yet opened</span>`
                }
              </td>
              <td class="px-3 py-2">
                ${
                  evalOpen
                    ? `<a
                        href="${disabled ? "#" : actionHref}"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded text-white text-[11px] ${
                          disabled ? "bg-gray-400 cursor-not-allowed opacity-70" : "bg-[#0d8ddb] hover:bg-[#0b7cc4]"
                        }"
                        ${disabled ? "aria-disabled='true' tabindex='-1'" : ""}
                      >
                        <i class="fas fa-eye"></i>
                        ${actionLabel}
                      </a>`
                    : `<span class="text-[11px] text-red-600 italic">Not yet opened</span>`
                }
              </td>
            `;

            tbody.appendChild(row);
          });
        };

        if (openBtn && windowLabel) {
          openBtn.addEventListener("click", () => {
            evalOpen = true;
            windowLabel.textContent = "Open";
            openBtn.textContent = "Evaluation is open";
            openBtn.disabled = true;
            openBtn.classList.remove("hover:bg-[#0b7cc4]");
            openBtn.classList.add("bg-gray-400", "cursor-not-allowed", "opacity-80");
            renderRows();
          });
        }

        if (searchInput) {
          searchInput.addEventListener("input", (e) => {
            searchTerm = e.target.value.trim().toLowerCase();
            renderRows();
          });
        }

        if (yearFilter) {
          yearFilter.addEventListener("change", (e) => {
            yearSelection = e.target.value || "all";
            renderRows();
          });
        }

        if (semFilter) {
          semFilter.addEventListener("change", (e) => {
            semSelection = e.target.value || "all";
            renderRows();
          });
        }

        renderRows();
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










