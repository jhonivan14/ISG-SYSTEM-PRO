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
        background-color: #052c6a;
        border-radius: 3px;
      }
      body {
           background-color: #edf2fb;
      }
      .paper {
        width: 100%;
        max-width: 1150px;
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
      @page {
        size: A4;
        margin: 10mm;
      }
      @media print {
        body {
          background: white !important;
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
      }
    </style>
  </head>
  <body class="font-sans">
    <div class="min-h-screen">
      <aside class="admin-sidebar flex flex-col bg-[#052c6a] text-white w-56 h-screen fixed left-0 top-0">
        <div class="flex items-center gap-3 px-4 py-4 border-b border-[#0d8ddb]">
          <img
            src="../img/SMCCNEWLOGO.png"
            class="rounded-full w-16 h-16 object-cover"
            alt="SMCC Logo"
          />
          <span class="text-sm font-normal">
            Admission and Scholarship Office
          </span>
        </div>
        <nav class="flex-1">
          <ul class="text-xs font-semibold">
            <li
              class="bg-[#fcdc2f] bg-opacity-90 text-[#052c6a] flex items-center gap-2 px-4 py-3 cursor-pointer"
            >
              <i class="fas fa-trophy w-5"></i>
              <span>Dashboard</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
               onclick="window.location.href='adminDashboard.php'"
            >
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='applicant.php'"
            >
              <i class="fas fa-user-graduate w-5"></i>
              <span>Applicants</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='approved.php'"
            >
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='interviewEvaluation.php'"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='ranks.php'"
            >
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='list-of-qualified.php'"
            >
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='department-evaluation-list.php'"
            >
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='summary-report.php'"
            >
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='institutional-scholars.php'"
            >
              <i class="fas fa-chart-line w-5"></i>
              <span>Institutional Scholars</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='settings.php'"
            >
              <i class="fas fa-cogs w-5"></i>
              <span>Settings</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              onclick="window.location.href='accounts.php'"
            >
              <i class="fas fa-user-circle w-5"></i>
              <span>Accounts</span>
            </li>
          </ul>
        </nav>
        
         <div class="absolute bottom-0 left-0 w-full">
        <div class="h-px w-full bg-gradient-to-r from-transparent via-[#0d8ddb] to-transparent opacity-60"></div>

   
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
      >
        <i class="fas fa-sign-out-alt text-xs"></i>
        <span>Logout</span>
      </button>
    </div>
  </div>
      </aside>
      <main class="admin-content ml-56 flex flex-col min-h-screen bg-[#f8fafc]">
        <header class="admin-topbar fixed top-0 left-56 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2">
          <div></div>
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
        <section class="flex flex-col items-center px-4 pt-16 pb-8">
          <div class="no-print w-full max-w-6xl flex flex-wrap gap-3 text-xs items-center justify-center mb-4">
            <div class="flex flex-wrap items-center gap-3 justify-center">
              <label for="academicYear" class="font-semibold text-slate-600">Academic Year</label>
              <select
                id="academicYear"
                class="border border-slate-300 rounded px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]"
              >
                <option value="2025-2026" selected>2025-2026</option>
                <option value="2024-2025">2024-2025</option>
                <option value="2023-2024">2023-2024</option>
              </select>
              <label for="semesterSelect" class="font-semibold text-slate-600">Semester</label>
              <select
                id="semesterSelect"
                class="border border-slate-300 rounded px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]"
              >
                <option value="1st" selected>1st Semester</option>
                <option value="2nd">2nd Semester</option>
              </select>
            </div>
            <div class="flex items-center gap-2 justify-center">
              <button class="px-6 py-1 text-xs rounded bg-[#003b7d] text-white shadow" onclick="window.print()">Print</button>
            </div>
          </div>

          <div class="paper mx-auto bg-white border border-slate-300 shadow-xl print:shadow-none print:border-0">
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
              <p class="text-[12px]" id="termText">1st Semester, S.Y. 2025-2026</p>
              <p class="text-[12px]">Batch 2</p>
            </div>
            <div class="overflow-x-auto mt-3">
              <table class="w-full border-collapse border border-black text-[11px]">
                <thead>
                  <tr class="text-center font-semibold bg-white">
                    <th rowspan="3" class="border border-black px-1 py-1 w-[40px]">SEQ.</th>
                    <th rowspan="3" class="border border-black px-1 py-1 text-left min-w-[220px]">NAME OF APPLICANT</th>
                    <th colspan="6" class="border border-black px-1 py-1">RATING</th>
                    <th rowspan="3" class="border border-black px-1 py-1 w-[60px]">100%<br />AVERAGE</th>
                    <th rowspan="3" class="border border-black px-1 py-1 w-[50px]">RANK</th>
                    <th rowspan="3" class="border border-black px-1 py-1 min-w-[120px]">REMARKS</th>
                  </tr>
                  <tr class="text-center font-semibold bg-white">
                    <th colspan="2" class="border border-black px-1 py-1 bg-green-200">Examination</th>
                    <th colspan="2" class="border border-black px-1 py-1 bg-orange-200">Interview</th>
                    <th colspan="2" class="border border-black px-1 py-1 bg-yellow-200">Grades</th>
                  </tr>
                  <tr class="text-center font-semibold bg-white">
                    <th class="border border-black px-1 py-1">Rating</th>
                    <th class="border border-black px-1 py-1">30%</th>
                    <th class="border border-black px-1 py-1">Rating</th>
                    <th class="border border-black px-1 py-1">40%</th>
                    <th class="border border-black px-1 py-1">Rating</th>
                    <th class="border border-black px-1 py-1">30%</th>
                  </tr>
                </thead>
                <tbody id="rankTableBody"></tbody>
              </table>
            </div>
            <div class="mt-8 text-[12px] space-y-8">
              <div>
                <p class="mb-10">Prepared by:</p>
                <p class="font-semibold border-t border-black inline-block pt-1">ARLYN B. TUYOGON, MMGM</p>
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
      const rows = [
        { name: 'Ramon B. Cruz', ex30: 25.20, exRate: 84.00, in40: 36.80, inRate: 92.00, gr30: 28.20, grRate: 94.00, avg: 90.20, rank: 1, remarks: '' },
        { name: 'Jessa Mae G. Vargas', ex30: 24.00, exRate: 80.00, in40: 34.40, inRate: 86.00, gr30: 27.00, grRate: 90.00, avg: 85.40, rank: 2, remarks: '' },
        { name: 'Lawrence T. Banaybanay', ex30: 23.10, exRate: 77.00, in40: 32.80, inRate: 82.00, gr30: 27.90, grRate: 93.00, avg: 83.80, rank: 3, remarks: '' },
        { name: 'Shiela Marie P. Aquino', ex30: 24.00, exRate: 80.00, in40: 31.40, inRate: 78.50, gr30: 28.50, grRate: 95.00, avg: 83.90, rank: 4, remarks: '' },
        { name: 'Emmanuel D. Ybanez', ex30: 22.50, exRate: 75.00, in40: 30.00, inRate: 75.00, gr30: 26.70, grRate: 89.00, avg: 79.20, rank: 5, remarks: '' },
        { name: 'Kristine Joy R. Sabellano', ex30: 21.60, exRate: 72.00, in40: 29.20, inRate: 73.00, gr30: 26.40, grRate: 88.00, avg: 76.80, rank: 6, remarks: '' },
        { name: 'Ralph Adrian B. Daguplo', ex30: 21.30, exRate: 71.00, in40: 28.80, inRate: 72.00, gr30: 25.50, grRate: 85.00, avg: 75.60, rank: 7, remarks: '' },
        { name: 'Princess Mae G. Rebato', ex30: 20.70, exRate: 69.00, in40: 27.60, inRate: 69.00, gr30: 24.90, grRate: 83.00, avg: 73.20, rank: 8, remarks: '' },
        { name: 'Michael Lloyd E. Ceballos', ex30: 19.80, exRate: 66.00, in40: 26.80, inRate: 67.00, gr30: 23.70, grRate: 79.00, avg: 70.30, rank: 9, remarks: '' },
        { name: 'Mary Rose F. Tual', ex30: 19.20, exRate: 64.00, in40: 25.60, inRate: 64.00, gr30: 22.50, grRate: 75.00, avg: 67.30, rank: 10, remarks: '' },
      ];

      const tbody = document.getElementById('rankTableBody');
      rows.forEach((row, index) => {
        const tr = document.createElement('tr');
        tr.className = 'border border-black text-[11px]';
        tr.innerHTML = `
          <td class="border border-black px-1 text-center">${index + 1}</td>
          <td class="border border-black px-1">${row.name}</td>
          <td class="border border-black px-1 text-center">${row.exRate.toFixed(2)}</td>
          <td class="border border-black px-1 text-center">${row.ex30.toFixed(2)}</td>
          <td class="border border-black px-1 text-center">${row.inRate.toFixed(2)}</td>
          <td class="border border-black px-1 text-center">${row.in40.toFixed(2)}</td>
          <td class="border border-black px-1 text-center">${row.grRate.toFixed(2)}</td>
          <td class="border border-black px-1 text-center">${row.gr30.toFixed(2)}</td>
          <td class="border border-black px-1 text-center font-semibold">${row.avg.toFixed(2)}</td>
          <td class="border border-black px-1 text-center font-semibold">${row.rank}</td>
          <td class="border border-black px-1">${row.remarks}</td>
        `;
        tbody.appendChild(tr);
      });

      const academicYearSelect = document.getElementById('academicYear');
      const semesterSelect = document.getElementById('semesterSelect');
      const termText = document.getElementById('termText');

      const updateTermText = () => {
        const semester = semesterSelect.value;
        termText.textContent = `${semester} Semester, S.Y. ${academicYearSelect.value}`;
      };

      academicYearSelect.addEventListener('change', updateTermText);
      semesterSelect.addEventListener('change', updateTermText);
      updateTermText();
    </script>
  </body>
</html>
  </body>
  </html>
