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
      ::-webkit-scrollbar-thumb { background-color: #052c6a; border-radius: 3px; }
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
      .sig-role { font-size: 10pt; }
      /* widen table for readability */
      .plain-table table { width: 100%; table-layout: auto; }
      @media print {
        html, body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        body, .paper { background: white !important; font-family: "Times New Roman", serif !important; }
        #sidebar, .admin-topbar, .print-btn-bar { display: none !important; }
        main, section { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .paper { border: none !important; box-shadow: none !important; margin: 0 auto !important; padding: 0 !important; }
        .paper-wrap { max-width: 100% !important; width: 100% !important; padding: 0 4px 12px 4px !important; }
        .plain-table table { width: 100% !important; }
      }
    </style>
  </head>
  <body class="bg-white font-sans">
    <div class="min-h-screen">
      <!-- Sidebar -->
      <aside
        id="sidebar"
        class="flex flex-col bg-[#052c6a] text-white w-56 h-screen fixed left-0 top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out overflow-y-auto"
      >
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
              class="flex items-center gap-2 px-4 py-3"
            >
              <i class="fas fa-trophy w-5"></i>
              <span>Dashboard</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
               data-nav="adminDashboard.php" onclick="window.location.href='adminDashboard.php'"
            >
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="applicant.php" onclick="window.location.href='applicant.php'"
            >
              <i class="fas fa-user-graduate w-5"></i>
              <span>Applicants</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="approved.php" onclick="window.location.href='approved.php'"
            >
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="interviewEvaluation.php" onclick="window.location.href='interviewEvaluation.php'"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="ranks.php" onclick="window.location.href='ranks.php'"
            >
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="list-of-qualified.php" onclick="window.location.href='list-of-qualified.php'"
            >
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="department-evaluation-list.php" onclick="window.location.href='department-evaluation-list.php'"
            >
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="summary-report.php" onclick="window.location.href='summary-report.php'"
            >
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="institutional-scholars.php" onclick="window.location.href='institutional-scholars.php'"
            >
              <i class="fas fa-chart-line w-5"></i>
              <span>Institutional Scholars</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
              data-nav="accounts.php" onclick="window.location.href='accounts.php'"
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
      </aside>

      <!-- Main content -->
      <main class="ml-0 md:ml-56 flex flex-col min-h-screen">
        <!-- Top bar -->
        <header
          class="admin-topbar fixed top-0 left-0 md:left-56 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
        >
          <div class="flex items-center gap-2">
            <button
              id="sidebarToggle"
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

        <!-- Dashboard Main page -->
        <section class="mt-12 px-4 sm:px-6 py-4">
          <div class="bg-white border border-[#0d8ddb] rounded shadow-sm p-4 md:p-6 paper">
            <div class="w-full mx-auto paper-wrap">
              <div class="flex flex-wrap items-center justify-between gap-3 mb-3 print-btn-bar">
                <div class="flex flex-wrap items-center gap-3 text-xs">
                  <label for="academicYear" class="font-semibold text-slate-700">Academic Year</label>
                  <select
                    id="academicYear"
                    class="border border-slate-300 rounded px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]"
                  >
                    <option value="2025-2026" selected>2025-2026</option>
                    <option value="2024-2025">2024-2025</option>
                    <option value="2023-2024">2023-2024</option>
                  </select>
                  <label for="semesterSelect" class="font-semibold text-slate-700">Semester</label>
                  <select
                    id="semesterSelect"
                    class="border border-slate-300 rounded px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]"
                  >
                    <option value="1st" selected>1st Semester</option>
                    <option value="2nd">2nd Semester</option>
                  </select>
                </div>
                <button
                  type="button"
                  onclick="window.print()"
                  class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold text-white bg-[#052c6a] hover:bg-[#0d8ddb] rounded shadow-sm"
                >
                  <i class="fas fa-print"></i>
                  <span>Print</span>
                </button>
              </div>
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
                <p class="font-semibold text-sm" id="termText">1st Semester, S.Y. 2025-2026</p>
                <p class="font-semibold text-sm">Batch 1</p>
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
                      <tr><td>1</td><td>Maria L. Santos</td><td>Brgy. 1, Nasipit</td><td>0917 201 1123</td><td>BSIT</td><td>2</td><td>ICT Office</td></tr>
                      <tr><td>2</td><td>John P. Dela Cruz</td><td>Brgy. 7, Nasipit</td><td>0908 334 5566</td><td>BSBA</td><td>3</td><td>Registrar</td></tr>
                      <tr><td>3</td><td>Angela R. Gomez</td><td>Brgy. 4, Butuan City</td><td>0920 889 7733</td><td>BSHM</td><td>1</td><td>Hospitality Lab</td></tr>
                      <tr><td>4</td><td>Kevin J. Ramos</td><td>Brgy. 3, Nasipit</td><td>0945 123 0044</td><td>BSED English</td><td>2</td><td>Library</td></tr>
                      <tr><td>5</td><td>Ella Mae P. Rivera</td><td>Magallanes, Agusan del Norte</td><td>0910 555 8877</td><td>BEED</td><td>4</td><td>SAS Office</td></tr>
                      <tr><td>6</td><td>Mark Adrian T. Uy</td><td>Brgy. 5, Nasipit</td><td>0936 778 9922</td><td>BSCRIM</td><td>3</td><td>Security</td></tr>
                      <tr><td>7</td><td>Rose Ann G. Casing</td><td>Carmen, Agusan del Norte</td><td>0918 667 4411</td><td>BSN</td><td>1</td><td>Clinic</td></tr>
                      <tr><td>8</td><td>Jasper K. Lim</td><td>Brgy. Triangulo, Nasipit</td><td>0921 903 2205</td><td>BSIT</td><td>3</td><td>ICT Office</td></tr>
                      <tr><td>9</td><td>Patricia D. Villanueva</td><td>Buenavista, Agusan del Norte</td><td>0956 441 0033</td><td>BSAcc</td><td>2</td><td>Finance</td></tr>
                      <tr><td>10</td><td>Vincent R. Alonzo</td><td>Brgy. 2, Nasipit</td><td>0995 812 6677</td><td>BSCE</td><td>2</td><td>Engineering</td></tr>
                      <tr><td>11</td><td>Hazel Joy B. Ramos</td><td>Las Nieves, Agusan del Norte</td><td>0917 330 9911</td><td>BSHM</td><td>4</td><td>Guidance</td></tr>
                      <tr><td>12</td><td>Ryan G. Mondejar</td><td>Butuan City</td><td>0906 421 5570</td><td>BSBA</td><td>1</td><td>HRMDO</td></tr>
                      <tr><td>13</td><td>Shaira M. Quiazon</td><td>Brgy. 8, Nasipit</td><td>0927 665 3101</td><td>BSAIS</td><td>2</td><td>Accounting</td></tr>
                      <tr><td>14</td><td>Louie C. Bayla</td><td>Jabonga, Agusan del Norte</td><td>0915 220 1188</td><td>BSIT</td><td>4</td><td>Research</td></tr>
                      <tr><td>15</td><td>Kathleen S. Domingo</td><td>Kitcharao, Agusan del Norte</td><td>0991 702 4410</td><td>BSED Math</td><td>3</td><td>Basic Ed</td></tr>
                      <tr><td>16</td><td>Alfredo T. Manlangit</td><td>RTR, Agusan del Norte</td><td>0938 770 5521</td><td>BSCS</td><td>1</td><td>NSTP</td></tr>
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
            termText.textContent = `${semesterSelect.value} Semester, S.Y. ${academicYearSelect.value}`;
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
    item.classList.toggle("hover:bg-[#0d8ddb]", !isActive);
  });
});
</script>
</body>
</html>

