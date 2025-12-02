<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
      rel="stylesheet"
    />
    <style>
      /* Custom scrollbar for sidebar */
      ::-webkit-scrollbar {
        width: 6px;
      }
      ::-webkit-scrollbar-thumb {
        background-color: #052c6a; /* navy blue */
        border-radius: 3px;
      }
      /* Print styling */
      @page {
        size: Legal;
        margin: 12mm 10mm 12mm 10mm;
      }
      body, .paper {
        font-family: "Times New Roman", serif;
      }
      .paper h1,
      .paper h2,
      .paper p {
        margin: 0;
      }
      .paper header {
        margin-bottom: 0.5rem;
        text-align: center;
      }
      .paper .header-top {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 0.15rem;
      }
      .paper .header-left {
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      .paper .header-left img {
        width: 76px;
        height: 76px;
        object-fit: contain;
      }
      .paper .header-left-text {
        line-height: 1.2;
        text-align: left;
      }
      .paper .header-left-text h1 {
        font-weight: 700;
        font-size: 16pt;
      }
      .paper .header-left-text p {
        font-size: 10pt;
      }
      .paper .header-right {
        display: flex;
        align-items: center;
        gap: 0.4rem;
      }
      .paper .header-right img {
        width: 96px;
        height: 74px;
        object-fit: contain;
      }
      .title-line {
        font-weight: 700;
        letter-spacing: 0.02em;
      }
      .plain-table table {
        border-collapse: collapse;
        width: 100%;
      }
      .plain-table th,
      .plain-table td {
        border: 1px solid #000;
        font-size: 10pt;
        padding: 6px 7px;
        text-align: center;
      }
      .plain-table td:nth-child(2) {
        text-align: left;
      }
      .plain-table thead th {
        background: #f1c40f;
        color: #000;
        font-weight: 700;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .sig-role {
        font-size: 10pt;
      }
      @media print {
        html, body {
          margin: 0 !important;
          padding: 0 !important;
          width: 100% !important;
          background: #fff !important;
        }
        #sidebar,
        .print-btn-bar,
        .admin-topbar {
          display: none !important;
        }
        main, section {
          margin: 0 !important;
          padding: 0 !important;
          width: 100% !important;
        }
        .paper {
          border: none !important;
          box-shadow: none !important;
          padding: 0 !important;
          margin: 0 auto !important;
          background: #fff !important;
        }
        .paper-wrap {
          max-width: 100% !important;
          width: 100% !important;
          padding: 0 4px 12px 4px !important;
        }
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
        <div
          class="flex items-center gap-3 px-4 py-4 border-b border-[#0d8ddb]"
        >
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
            >
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-user-graduate w-5"></i>
              <span>Applicants</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>


            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-chart-line w-5"></i>
              <span>Reports</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
            >
              <i class="fas fa-cogs w-5"></i>
              <span>Settings</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
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

      <!-- Main content -->
      <main class="ml-0 md:ml-56 flex flex-col min-h-screen">
        <!-- Top bar -->
        <header
          class="admin-topbar fixed top-0 left-0 md:left-56 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
        >
          <div class="flex items-center gap-2">
            <!-- Mobile menu button -->
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
            <button
              class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 font-normal"
              type="button"
            >
              Account ▾
            </button>
          </div>
        </header>

        <!-- Print-friendly Interview Result -->
        <section class="mt-12 px-4 sm:px-6 py-6">
          <div class="bg-white border border-[#0d8ddb] rounded shadow-sm p-4 md:p-6 paper">
            <div class="w-full mx-auto paper-wrap">
              <div class="flex justify-end mb-3 print-btn-bar">
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
                      <p class="text-center">Tel. No. 085 225-0208</p>
                      <p class="text-center">
                        Website: <a href="http://www.smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">www.smccnasipit.edu.ph</a>,
                        Email: <a href="mailto:communications@smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">communications@smccnasipit.edu.ph</a>
                      </p>
                    </div>
                  </div>
                  <div class="header-right">
                    <img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC ISO 9001 logo" />
                  </div>
                </div>
              </header>

              <div class="text-center mb-1">
                <div class="title-line">Office of the Admission &amp; Scholarship</div>
              </div>
              <hr class="border-black mb-3" />

              <section class="text-center mb-4">
                <h2 class="font-bold text-base">Student Assistance Applicants' Interview Result</h2>
                <p class="font-semibold text-sm">1st Semester, S.Y. 2025-2026</p>
                <p class="font-semibold text-sm">Batch 3</p>
              </section>

              <section class="plain-table mb-8">
                <table>
                  <thead>
                    <tr>
                      <th style="width: 40px;">SEQ.</th>
                      <th>NAME OF APPLICANT</th>
                      <th style="width: 110px;">WEIGHTED MEAN</th>
                      <th style="width: 140px;">VERBAL DESCRIPTION</th>
                      <th style="width: 150px;">VERBAL INTERPRETATION</th>
                      <th style="width: 120px;">REMARKS</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr><td>1</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>2</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>3</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>4</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>5</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>6</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>7</td><td></td><td></td><td></td><td></td><td></td></tr>
                    <tr><td>8</td><td></td><td></td><td></td><td></td><td></td></tr>
                  </tbody>
                </table>
              </section>

              <div class="mt-6 grid grid-cols-1 gap-6 max-w-3xl">
                <div>
                  <div class="text-[10pt] mb-1">Prepared by:</div>
                  <div class="font-semibold">ARLYN B. TUYOGON, MMBM</div>
                  <div class="sig-role">Head, Admission &amp; Scholarship</div>
                </div>

                <div>
                  <div class="text-[10pt] mb-1">Checked by:</div>
                  <div class="font-semibold">FELMARIE MANLUNAS, MACDDS</div>
                  <div class="sig-role">Head, Student Affairs &amp; Services</div>
                </div>

                <div>
                  <div class="text-[10pt] mb-1">Noted by:</div>
                  <div class="font-semibold">RICKY E. DESTACAMENTO, RGC, MAED</div>
                  <div class="sig-role">Head, HRMDO</div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>

    <script>
      // Sidebar toggle for mobile
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");

        if (toggleBtn && sidebar) {
          toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
          });

          // Close sidebar when clicking any nav item on small screens
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
  </body>
</html>
