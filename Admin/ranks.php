<?php
$defaultBatchLabel = "Batch 2";
require_once __DIR__ . "/includes/school-term-filter.php";
require_once __DIR__ . "/includes/panelist-sent-applicants.php";
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">   
    <title>Student Assistant Scholarship - Applicants' Rank</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <style>
      ::-webkit-scrollbar {
        width: 6px;
      }
      ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
        border-radius: 999px;
      }
      body {
           background-color: #edf2fb;
      }
      .paper {
        width: 100%;
        max-width: none;
        padding: 18px;
        background-color: #fff;
        font-family: "Times New Roman", serif;
        background-image:
          radial-gradient(rgba(0, 0, 0, 0.035) 0.5px, transparent 0.5px),
          radial-gradient(rgba(0, 0, 0, 0.03) 0.5px, transparent 0.5px);
        background-size: 8px 8px, 8px 8px;
        background-position: 0 0, 4px 4px;
      }
      .document-header {
        margin-bottom: 1rem;
        text-align: center;
      }
      .header-top {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 0.5rem;
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
      .rating-exam {
        background-color: #bbf7d0;
      }
      .rating-interview {
        background-color: #fed7aa;
      }
      .rating-grades {
        background-color: #fef08a;
      }
      @media (max-width: 767px) {
        .paper {
          padding: 12px;
        }
        .header-left {
          flex-direction: column;
          text-align: center;
        }
        .header-left-text {
          text-align: center;
        }
        .header-left img {
          width: 64px;
          height: 64px;
        }
        .header-right img {
          width: 84px;
          height: 64px;
        }
      }
      @page {
        size: A4;
        margin: 10mm;
      }
      @media print {
        body {
          background: white !important;
        }
        #sidebar {
          display: none !important;
        }
        .paper,
        .paper * {
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }
        .admin-sidebar,
        .admin-topbar,
        .no-print {
          display: none !important;
        }
        .admin-content {
          margin-left: 0 !important;
        }
        .paper {
          width: auto;
          min-height: auto;
          padding: 0;
          box-shadow: none !important;
          border: 0 !important;
          background-image: none;
        }
        table {
          page-break-inside: auto;
        }
        tr {
          page-break-inside: avoid;
          page-break-after: auto;
        }
        .paper table {
          width: 100% !important;
          table-layout: fixed;
          font-size: 9.5px !important;
        }

        .paper th,
        .paper td {
          padding: 2px 3px !important;
          line-height: 1.15 !important;
          min-width: 0 !important;
          white-space: normal !important;
          overflow-wrap: anywhere;
          word-break: break-word;
        }

        .paper .overflow-x-auto {
          overflow: visible !important;
        }
        .rating-exam {
          background-color: #bbf7d0 !important;
        }
        .rating-interview {
          background-color: #fed7aa !important;
        }
        .rating-grades {
          background-color: #fef08a !important;
        }
        .header-top {
          flex-wrap: nowrap !important;
          align-items: flex-start !important;
          justify-content: center !important;
          gap: 0.75rem !important;
        }
        .header-left {
          flex-direction: row !important;
          align-items: flex-start !important;
          gap: 0.5rem !important;
        }
        .header-left-text {
          text-align: center !important;
        }
        .header-right {
          flex-direction: row !important;
          align-items: flex-start !important;
          align-self: flex-start !important;
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
  <body class="font-sans">
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
      <main class="admin-content ml-0 md:ml-64 flex flex-col min-h-screen pt-14 bg-[#f8fafc]">
        <header class="admin-topbar hidden fixed top-0 left-0 md:left-64 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2">
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
            <button
              type="button"
              class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 flex items-center gap-1 font-normal"
            >
              <i class="fas fa-user"></i>
              Admin panel
            </button>
            <button type="button" class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 font-normal">
              Account
            </button>
          </div>
        </header>

        <section
          class="page-header no-print fixed top-0 left-0 md:left-64 right-0 z-20 bg-white border-b border-slate-200 px-4 sm:px-6 py-3 shadow-sm"
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
           RANKING
          </h2>
          </div>
        </section>

        <section class="flex flex-col px-3 sm:px-4 lg:px-6 pt-6 md:pt-16 pb-6 bg-[#eef2f7] flex-1 min-h-[calc(100vh-3rem)]">
          <div class="no-print w-full mb-4 rounded-lg border border-[#0d8ddb] bg-white p-3 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <form class="flex flex-wrap items-center gap-2 lg:justify-end" method="get" action="ranks.php">
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
                    href="ranks.php"
                    class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm"
                  >
                    Clear
                  </a>
                <?php endif; ?>
              </form>
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

          <div class="paper w-full bg-white border border-slate-300 shadow-xl print:shadow-none print:border-0">
            <div class="document-header">
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
            </div>
            <div class="text-center mt-3">
              <p class="text-[12px]">Student Assistance Scholarship Program (SASP) Applicants' Rank</p>
              <p class="text-[12px]" id="termText"><?php echo htmlspecialchars($displaySemester); ?>, S.Y. <?php echo htmlspecialchars($displaySchoolYear); ?></p>
              <p class="text-[12px]" id="batchText"><?php echo htmlspecialchars($displayBatch); ?></p>
            </div>
            <div class="overflow-x-auto mt-3">
              <table class="w-full border-collapse border border-black text-[12px]">
                <colgroup>
                  <col style="width: 4%" />
                  <col style="width: 26%" />
                  <col style="width: 6%" />
                  <col style="width: 6%" />
                  <col style="width: 6%" />
                  <col style="width: 6%" />
                  <col style="width: 6%" />
                  <col style="width: 6%" />
                  <col style="width: 8%" />
                  <col style="width: 5%" />
                  <col style="width: 21%" />
                </colgroup>
                <thead>
                  <tr class="text-center font-semibold bg-white">
                    <th rowspan="3" class="border border-black px-2 py-2 w-[56px]">SEQ.</th>
                    <th rowspan="3" class="border border-black px-2 py-2 text-left min-w-[260px]">NAME OF APPLICANT</th>
                    <th colspan="6" class="border border-black px-2 py-2">RATING</th>
                    <th rowspan="3" class="border border-black px-2 py-2 w-[78px]">100%<br />AVERAGE</th>
                    <th rowspan="3" class="border border-black px-2 py-2 w-[64px]">RANK</th>
                    <th rowspan="3" class="border border-black px-2 py-2 min-w-[170px]">REMARKS</th>
                </tr>
                  <tr class="text-center font-semibold bg-white">
                    <th colspan="2" class="border border-black px-2 py-2 bg-green-200 rating-exam">Examination</th>
                    <th colspan="2" class="border border-black px-2 py-2 bg-orange-200 rating-interview">Interview</th>
                    <th colspan="2" class="border border-black px-2 py-2 bg-yellow-200 rating-grades">Grades</th>
                  </tr>
                  <tr class="text-center font-semibold bg-white">
                    <th class="border border-black px-2 py-2">Rating</th>
                    <th class="border border-black px-2 py-2">30%</th>
                    <th class="border border-black px-2 py-2">Rating</th>
                    <th class="border border-black px-2 py-2">40%</th>
                    <th class="border border-black px-2 py-2">Rating</th>
                    <th class="border border-black px-2 py-2">30%</th>
                  </tr>
                </thead>
                <tbody id="rankTableBody"></tbody>
              </table>
            </div>
            <div class="mt-8 text-[12px] space-y-8">
              <div>
                <p class="mb-10">Prepared by:</p>
                <p class="font-semibold border-t border-black inline-block pt-1">ARLYN B. TUYOGON, MMBM</p>
                <p class="text-[11px]">Head, Admission & Scholarship</p>
              </div>
              <div>
                <p class="mb-10">Checked by:</p>
                <p class="font-semibold border-t border-black inline-block pt-1">FELMARIE MANLUNAS, MACDDS</p>
                <p class="text-[11px]">Head, Student Affairs & Services</p>
              </div>
              <div>
                <p class="mb-10">Noted by:</p>
                <p class="font-semibold border-t border-black inline-block pt-1">RICKY E. DESTACAMENTO, RGC, MAED</p>
                <p class="text-[11px]">Head, HRMDO</p>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>
    <script>
      const sampleRows = [
        { name: "Ramon B. Cruz", ex30: 25.20, exRate: 84.00, in40: 36.80, inRate: 92.00, gr30: 28.20, grRate: 94.00, avg: 90.20, rank: 1, remarks: "" },
        { name: "Jessa Mae G. Vargas", ex30: 24.00, exRate: 80.00, in40: 34.40, inRate: 86.00, gr30: 27.00, grRate: 90.00, avg: 85.40, rank: 2, remarks: "" },
        { name: "Lawrence T. Banaybanay", ex30: 23.10, exRate: 77.00, in40: 32.80, inRate: 82.00, gr30: 27.90, grRate: 93.00, avg: 83.80, rank: 3, remarks: "" },
        { name: "Shiela Marie P. Aquino", ex30: 24.00, exRate: 80.00, in40: 31.40, inRate: 78.50, gr30: 28.50, grRate: 95.00, avg: 83.90, rank: 4, remarks: "" },
        { name: "Emmanuel D. Ybanez", ex30: 22.50, exRate: 75.00, in40: 30.00, inRate: 75.00, gr30: 26.70, grRate: 89.00, avg: 79.20, rank: 5, remarks: "" },
        { name: "Kristine Joy R. Sabellano", ex30: 21.60, exRate: 72.00, in40: 29.20, inRate: 73.00, gr30: 26.40, grRate: 88.00, avg: 76.80, rank: 6, remarks: "" },
        { name: "Ralph Adrian B. Daguplo", ex30: 21.30, exRate: 71.00, in40: 28.80, inRate: 72.00, gr30: 25.50, grRate: 85.00, avg: 75.60, rank: 7, remarks: "" },
        { name: "Princess Mae G. Rebato", ex30: 20.70, exRate: 69.00, in40: 27.60, inRate: 69.00, gr30: 24.90, grRate: 83.00, avg: 73.20, rank: 8, remarks: "" },
        { name: "Michael Lloyd E. Ceballos", ex30: 19.80, exRate: 66.00, in40: 26.80, inRate: 67.00, gr30: 23.70, grRate: 79.00, avg: 70.30, rank: 9, remarks: "" },
        { name: "Mary Rose F. Tual", ex30: 19.20, exRate: 64.00, in40: 25.60, inRate: 64.00, gr30: 22.50, grRate: 75.00, avg: 67.30, rank: 10, remarks: "" }
      ];
      const sentApplicants = <?php echo json_encode(array_map(function ($item) {
        return ["name" => (string)($item["name"] ?? "")];
      }, $panelistSentApplicants), JSON_UNESCAPED_SLASHES); ?>;
      const rows = sentApplicants.length > 0
        ? sentApplicants.map((item, index) => ({
            name: item.name,
            ex30: 0,
            exRate: 0,
            in40: 0,
            inRate: 0,
            gr30: 0,
            grRate: 0,
            avg: 0,
            rank: index + 1,
            remarks: ""
          }))
        : sampleRows;

      function renderRankRows() {
        const tbody = document.getElementById("rankTableBody");
        if (!tbody) return;

        tbody.innerHTML = "";
        rows.forEach((row, index) => {
          const tr = document.createElement("tr");
          tr.className = "border border-black text-[12px]";
          tr.innerHTML = `
            <td class="border border-black px-2 py-2 text-center">${index + 1}</td>
            <td class="border border-black px-2 py-2">${row.name}</td>
            <td class="border border-black px-2 py-2 text-center">${row.exRate.toFixed(2)}</td>
            <td class="border border-black px-2 py-2 text-center">${row.ex30.toFixed(2)}</td>
            <td class="border border-black px-2 py-2 text-center">${row.inRate.toFixed(2)}</td>
            <td class="border border-black px-2 py-2 text-center">${row.in40.toFixed(2)}</td>
            <td class="border border-black px-2 py-2 text-center">${row.grRate.toFixed(2)}</td>
            <td class="border border-black px-2 py-2 text-center">${row.gr30.toFixed(2)}</td>
            <td class="border border-black px-2 py-2 text-center font-semibold">${row.avg.toFixed(2)}</td>
            <td class="border border-black px-2 py-2 text-center font-semibold">${row.rank}</td>
            <td class="border border-black px-2 py-2">${row.remarks}</td>
          `;
          tbody.appendChild(tr);
        });
      }

      function setupTermText() {
        const academicYearSelect = document.getElementById("academicYear");
        const semesterSelect = document.getElementById("semesterSelect");
        const termText = document.getElementById("termText");
        if (!academicYearSelect || !semesterSelect || !termText) return;

        const updateTermText = () => {
          const semester = semesterSelect.value || "1st Semester";
          const schoolYear = academicYearSelect.value || "<?php echo htmlspecialchars($currentSchoolYear, ENT_QUOTES); ?>";
          termText.textContent = `${semester}, S.Y. ${schoolYear}`;
        };

        academicYearSelect.addEventListener("change", updateTermText);
        semesterSelect.addEventListener("change", updateTermText);
        updateTermText();
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
      }

      document.addEventListener("DOMContentLoaded", () => {
        renderRankRows();
        setupTermText();
        setupSidebar();
        markActiveSidebarItem();
      });
    </script>
  </body>
</html>









