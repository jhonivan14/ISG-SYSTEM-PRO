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
    <link
      href="https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap"
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

      /* Department evaluation form styling */
      .eval-page {
        font-family: "Times New Roman", serif;
        color: #111827;
        background: #ffffff;
      }

      .eval-page header {
        text-align: center;
        margin-bottom: 1rem;
      }

      .eval-page .header-top {
        margin-top: 3rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 0.5rem;
      }

      .eval-page .header-logo,
      .eval-page .header-cert {
        display: flex;
        justify-content: center;
        align-items: center;
      }

      .eval-page .header-logo img {
        width: 80px;
        height: 80px;
        object-fit: contain;
      }

      .eval-page .header-center {
        line-height: 1.2;
        text-align: center;
      }

      .eval-page .header-center h1 {
        font-weight: 700;
        font-size: 16pt;
        margin: 0;
      }

      .eval-page .header-center p {
        margin: 0;
        font-size: 10pt;
      }

      .eval-page .header-cert img {
        width: 100px;
        height: 80px;
        object-fit: contain;
      }

      .eval-page .paper {
        padding: 1rem 1.4rem 2.5rem;
      }

      .eval-page .title {
        font-size: 15px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        text-align: center;
        margin-bottom: 1rem;
      }

      .eval-page .info-block {
        margin-bottom: 1.1rem;
        font-size: 12px;
      }

      .eval-page .info-row {
        display: grid;
        grid-template-columns: 235px minmax(0, 320px);
        justify-content: start;
        align-items: end;
        gap: 0.45rem;
        margin-bottom: 0.3rem;
      }

      .eval-page .info-label {
        font-weight: 600;
      }

      .eval-page .info-value {
        border-bottom: 1px solid #111827;
        min-height: 1rem;
        padding-left: 0.35rem;
      }

      .eval-page .direction {
        font-size: 12px;
        line-height: 1.5;
        margin-bottom: 1.1rem;
      }

      .eval-page table {
        width: 100%;
        border-collapse: collapse;
      }

      .eval-page th,
      .eval-page td {
        border: 1px solid #111827;
        padding: 0.38rem 0.55rem;
        font-size: 12px;
        vertical-align: middle;
      }

      .eval-page th {
        background: #f7f7f7;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
      }

      .eval-page .scale-table {
        border: none;
        margin-bottom: 1.2rem;
      }

      .eval-page .scale-table th,
      .eval-page .scale-table td {
        border: none;
      }

      .eval-page .scale-table th {
        background: transparent;
        border-bottom: 1px solid #111827;
        padding-bottom: 0.5rem;
      }

      .eval-page .scale-table td {
        padding: 0.5rem 0.55rem;
        font-size: 11px;
      }

      .eval-page .scale-table td:first-child {
        width: 9%;
        text-align: center;
        font-weight: 700;
      }

      .eval-page .rating-table th {
        font-size: 11px;
        text-align: center;
      }

      .eval-page .rating-table td {
        height: 2.05rem;
        text-align: center;
      }

      .eval-page .rating-table td:first-child {
        text-align: left;
      }

      .eval-page .section-label {
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background: #f3f4f6;
      }

      .eval-page .subtotal {
        font-weight: 600;
      }

      .eval-page .comment-box {
        border: 1px solid #111827;
        min-height: 3.2rem;
        margin-top: 0.6rem;
        padding: 0.45rem 0.6rem;
        font-size: 12px;
        line-height: 1.45;
      }

      .eval-page .signature-row {
        display: flex;
        justify-content: flex-end;
        margin-top: 2.4rem;
      }

      .eval-page .signature-block {
        width: 220px;
        font-size: 12px;
        text-align: center;
      }

      .eval-page .signature-line {
        border-top: 1px solid #111827;
        margin-top: 0.9rem;
        padding-top: 0.25rem;
        text-align: center;
      }

      .eval-page footer {
        margin-top: 1.5rem;
      }

      .eval-page footer img {
        width: 100%;
        height: auto;
        object-fit: contain;
      }

      .eval-page .footer-box {
        margin-top: 1.5rem;
        display: flex;
        justify-content: flex-start;
        padding-left: 0.25rem;
      }

      .eval-page .footer-box img {
        width: 18rem;
        max-width: calc(100% - 0.5rem);
        height: auto;
        object-fit: contain;
      }

      /* Evaluation result block pulled from copyOfEval.php */
      .eval-result {
        font-family: "Times New Roman", serif;
        color: #111827;
      }

      .eval-result .result-table {
        border-collapse: collapse;
        width: 100%;
      }

      .eval-result .result-table th,
      .eval-result .result-table td {
        border: 1px solid #000;
        padding: 6px 8px;
        font-size: 12px;
      }

      @page {
        /* Use long bond (8.5in x 13in) for printing */
        size: 8.5in 13in;
        margin: 0.28in;
      }

      @media print {
        * {
          -webkit-print-color-adjust: exact;
          print-color-adjust: exact;
        }

        .eval-page {
          background: #ffffff;
        }

        /* Tighter spacing for document 1 so it fits on one page */
        #print-eval-form .eval-page {
          font-size: 10px;
          line-height: 1.2;
        }

        #print-eval-form .header-top {
          margin-top: 1.2rem;
          gap: 0.4rem;
          margin-bottom: 0.25rem;
        }

        #print-eval-form .header-logo img,
        #print-eval-form .header-cert img {
          width: 60px;
          height: 60px;
        }

        #print-eval-form .header-center h1 {
          font-size: 13pt;
        }

        #print-eval-form .header-center p {
          font-size: 8.5pt;
        }

        #print-eval-form .paper {
          border: none;
          padding: 0.4rem 0.5rem 0.7rem;
        }

        #print-eval-form .title {
          font-size: 12px;
          margin-bottom: 0.45rem;
        }

        #print-eval-form .info-block {
          margin-bottom: 0.5rem;
          font-size: 10px;
        }

        #print-eval-form .info-row {
          grid-template-columns: 215px minmax(0, 280px);
          gap: 0.25rem;
          margin-bottom: 0.12rem;
        }

        #print-eval-form .info-value {
          min-height: 0.65rem;
        }

        #print-eval-form .direction {
          font-size: 10px;
          line-height: 1.28;
          margin-bottom: 0.25rem;
        }

        #print-eval-form table th,
        #print-eval-form table td {
          padding: 0.14rem 0.28rem;
          font-size: 10px;
        }

        #print-eval-form .scale-table {
          margin-bottom: 0.4rem;
        }

        #print-eval-form section.pt-4 {
          padding-top: 0.2rem !important;
        }

        #print-eval-form .scale-table td {
          font-size: 9.5px;
          padding: 0.14rem 0.28rem;
        }

        #print-eval-form .rating-table td {
          height: 1.25rem;
        }

        #print-eval-form .comment-box {
          min-height: 2.1rem;
          padding: 0.3rem 0.45rem;
          line-height: 1.28;
        }

        #print-eval-form .signature-row {
          margin-top: 1rem;
        }

        #print-eval-form .signature-line {
          margin-top: 0.4rem;
        }

        #print-eval-form .footer-box {
          margin-top: 0.12rem;
          padding-bottom: 0.05rem;
        }

        #print-eval-form footer {
          margin-top: 0.24rem;
          padding-top: 0.05rem;
        }

        /* Tighter print fit for the evaluation result (second document) */
        #print-eval-result {
          transform: scale(0.88);
          transform-origin: top center;
          box-shadow: none !important;
          border: none !important;
          background: #ffffff !important;
          border-radius: 0 !important;
        }
        #print-eval-result .eval-result {
          padding: 0.26in 0.28in 0.22in;
          font-size: 11px;
          line-height: 1.25;
          box-shadow: none !important;
          border: none !important;
          background: transparent !important;
        }
        #print-eval-result h1,
        #print-eval-result h2,
        #print-eval-result p {
          margin-top: 0.12rem;
          margin-bottom: 0.12rem;
        }
        #print-eval-result .space-y-2 > * + * {
          margin-top: 0.15rem;
        }
        #print-eval-result .result-table th,
        #print-eval-result .result-table td {
          padding: 3px 5px;
        }
        #print-eval-result .result-table {
          border-collapse: collapse;
        }
        #print-eval-result .grid {
          margin-top: 0.6rem !important;
        }
        #print-eval-result .max-w-4xl.mx-auto.mt-10 {
          margin-top: 1rem !important;
        }
      }
    </style>
  </head>
  <body class="bg-gray-200 font-sans">
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
              class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer"
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
              class="bg-[#fcdc2f] bg-opacity-90 text-[#052c6a] flex items-center gap-2 px-4 py-3 cursor-pointer"
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
          class="fixed top-0 left-0 md:left-56 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
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

        <!-- Department Evaluation Form -->
        <section id="evaluation-details" class="mt-12 px-4 sm:px-6 py-6 bg-gray-200 flex-1">
          <div class="max-w-6xl mx-auto flex justify-end gap-2 mb-3">
            <button
              type="button"
              class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold text-white bg-[#052c6a] hover:bg-[#0d8ddb] rounded shadow transition"
              onclick="printSection('print-eval-form')"
            >
              <i class="fas fa-print"></i>
              <span>Print Evaluation Form</span>
            </button>
          </div>
          <div id="print-eval-form" class="max-w-6xl mx-auto bg-white rounded-lg shadow-sm">
            <div class="eval-page">
              <header>
                <div class="header-top">
                  <div class="header-logo">
                    <img src="../img/SMCCNEWLOGO.png" alt="Seal of Saint Michael College of Caraga" />
                  </div>
                  <div class="header-center">
                    <h1>Saint Michael College of Caraga</h1>
                    <p>
                      Brgy. 4, Nasipit, Agusan del Norte, Philippines<br />
                      District 8, Brgy. Triangulo, Nasipit, Agusan del Norte, Philippines
                    </p>
                    <p>Tel. Nos. +63 085 343-3251 / +63 085 283-3113</p>
                    <p>
                      <a href="http://www.smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">www.smccnasipit.edu.ph</a>
                    </p>
                  </div>
                  <div class="header-cert">
                    <img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC ISO 9001 logo" />
                  </div>
                </div>
              </header>

              <main class="paper">
                <h2 class="title">Student Assistants' Evaluation Form</h2>

                <section class="info-block">
                  <div class="info-row">
                    <span class="info-label">Name of Student Assistant:</span>
                    <span class="info-value">John Michael Santos</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Semester &amp; School Year:</span>
                    <span class="info-value">2nd Semester, S.Y. 2024-2025</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Area of Assignment:</span>
                    <span class="info-value">Learning Resource Center</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Head of Office:</span>
                    <span class="info-value">Mary Ann L. Rosales</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Date of Evaluation:</span>
                    <span class="info-value">March 15, 2025</span>
                  </div>
                </section>

                <section class="direction">
                  <p>
                    <span class="font-semibold">Direction:</span> Please rate each item below to determine the performance of the assigned student assistant of your respective office/department. Put a check (&#10003;) to rate their performance.
                  </p>
                </section>

                <section>
                  <table class="scale-table">
                    <thead>
                      <tr>
                        <th style="width: 9%;">Scale</th>
                        <th style="width: 18%;">Verbal Description</th>
                        <th>Verbal Interpretation</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>4</td>
                        <td>Excellent</td>
                        <td>This rating is given to student assistants who consistently exceed expectations and demonstrate outstanding performance in their assigned tasks.</td>
                      </tr>
                      <tr>
                        <td>3</td>
                        <td>Good</td>
                        <td>This rating is given to student assistants who consistently meet expectations and perform their assigned tasks satisfactorily.</td>
                      </tr>
                      <tr>
                        <td>2</td>
                        <td>Fair</td>
                        <td>This rating is given to student assistants who occasionally meet expectations but may have areas for improvement.</td>
                      </tr>
                      <tr>
                        <td>1</td>
                        <td>Poor</td>
                        <td>This rating is given to student assistants who consistently fail to meet expectations and demonstrate unsatisfactory performance in their assigned tasks.</td>
                      </tr>
                    </tbody>
                  </table>
                </section>

                <section class="pt-4">
                  <table class="rating-table">
                    <thead>
                      <tr>
                        <th rowspan="2" style="width: 47%;">Performance Indicators</th>
                        <th colspan="4">Rating</th>
                      </tr>
                      <tr>
                        <th>Excellent (4)</th>
                        <th>Good (3)</th>
                        <th>Fair (2)</th>
                        <th>Poor (1)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td class="section-label">A. Quality and Quantity of Work</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>A.1 Accurate at work assigned</td>
                        <td></td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>A.2 Always completes tasks</td>
                        <td></td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>A.3 Works in a timely manner</td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>A.4 Asks for more work when assigned tasks are done</td>
                        <td></td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>A.5 Easily accepts new responsibilities</td>
                        <td></td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td class="subtotal">Total</td>
                        <td>4</td>
                        <td>12</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td class="section-label">B. Interpersonal Skills</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>B.1 Answers patron's questions accurately</td>
                        <td></td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>B.2 Deals with patrons well</td>
                        <td></td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>B.3 Deals with personnel well</td>
                        <td></td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>B.4 Knows how to effectively communicate with others</td>
                        <td></td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>B.5 Has a good relationship with other student assistants</td>
                        <td></td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td class="subtotal">Total</td>
                        <td></td>
                        <td>15</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td class="section-label">C. Attendance and Reliability</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>C.1 Perfect attendance</td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>C.2 Reports duty on time, rarely comes late</td>
                        <td></td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>C.3 Following assigned schedule</td>
                        <td></td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>C.4 Able to work without direct supervision</td>
                        <td></td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td>C.5 Carries out instructions successfully</td>
                        <td></td>
                        <td>&#10003;</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td class="subtotal">Total</td>
                        <td>4</td>
                        <td>12</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td class="subtotal">Over-all Total</td>
                        <td>8</td>
                        <td>39</td>
                        <td></td>
                        <td></td>
                      </tr>
                    </tbody>
                  </table>
                </section>

                <section class="pt-6 text-[12px]">
                  <p class="font-semibold">D. Strength(s)/Areas for Improvement:</p>
                  <div class="comment-box">
                    Displays strong communication skills and consistently meets assigned deadlines. Encourage taking initiative on larger projects.
                  </div>
                </section>

                <section class="pt-4 text-[12px]">
                  <p class="font-semibold">E. Evaluator's Comment(s)/Recommendation:</p>
                  <div class="comment-box">
                    Recommended for retention as Student Assistant. Provide additional training on the library cataloging system to support expanded duties.
                  </div>
                </section>

                <div class="signature-row">
                  <div class="signature-block">
                    <p>Evaluator's Signature</p>
                    <div class="signature-line"></div>
                  </div>
                </div>

                <div class="footer-box">
                  <img src="../img/box.png" alt="footer box" />
                </div>
              </main>

              <footer>
                <img src="../img/footer.png" alt="SMCC footer" />
              </footer>
            </div>
          </div>

          <!-- Separator between documents with light design -->
          <div class="max-w-6xl mx-auto mt-6 mb-6 px-2">
            <div class="flex items-center gap-3 text-[11px] text-gray-500">
              <div class="flex-1 h-px bg-gradient-to-r from-transparent via-gray-300 to-gray-200"></div>
              <div class="px-3 py-1 rounded-full border border-dashed border-gray-300 bg-white shadow-sm uppercase tracking-wide font-semibold">
                Next Document
              </div>
              <div class="flex-1 h-px bg-gradient-to-l from-transparent via-gray-300 to-gray-200"></div>
            </div>
          </div>

          <!-- Evaluation Result (from copyOfEval.php) -->
          <div class="max-w-6xl mx-auto flex items-center justify-between gap-2 mt-8 mb-3 px-2">
            <span class="inline-flex items-center gap-2 px-3 py-1 text-[11px] font-semibold text-[#052c6a] uppercase tracking-wide bg-[#f8fafc] border border-[#0d8ddb]/30 rounded-full shadow-sm">
              <i class="fas fa-copy text-[10px]"></i>
              Copy of Evaluation
            </span>
            <button
              type="button"
              class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold text-white bg-[#052c6a] hover:bg-[#0d8ddb] rounded shadow transition"
              onclick="printSection('print-eval-result')"
            >
              <i class="fas fa-print"></i>
              <span>Print Evaluation Result</span>
            </button>
          </div>
          <div id="print-eval-result" class="max-w-6xl mx-auto mt-0 bg-white rounded-lg shadow-sm">
            <div class="eval-result p-6 sm:p-8">
              <header class="text-center mb-4">
                <div class="flex flex-wrap items-center justify-center gap-4 mb-2">
                  <img src="../img/SMCCNEWLOGO.png" alt="Seal of Saint Michael College of Caraga" class="w-20 h-20 object-contain" />
                  <div class="leading-tight text-center">
                    <h1 class="font-bold text-[16pt] m-0">Saint Michael College of Caraga</h1>
                    <p class="m-0 text-[10pt]">
                      Brgy. 4, Nasipit, Agusan del Norte, Philippines<br />
                      District 8, Brgy. Triangulo, Nasipit, Agusan del Norte, Philippines
                    </p>
                    <p class="m-0 text-[10pt]">Tel. Nos. +63 085 343-3251 / +63 085 283-3113</p>
                    <p class="m-0 text-[10pt]">
                      <a href="http://www.smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">www.smccnasipit.edu.ph</a>
                    </p>
                  </div>
                  <img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC ISO 9001 logo" class="w-24 h-20 object-contain" />
                </div>
              </header>

              <section class="text-center mb-6">
                <h2 class="font-bold text-[13pt] tracking-wide m-0">STUDENT ASSISTANTS' EVALUATION RESULT</h2>
                <p class="text-[11pt] mt-1 mb-0">1st Semester, S.Y. 2024-2025</p>
              </section>

              <div class="max-w-4xl mx-auto text-[12px] font-serif space-y-2 mb-6">
                <div class="flex items-center max-w-3xl">
                  <span class="w-56">Name of Student Assistant</span>
                  <span>:</span>
                  <span class="border-b border-black ml-2 h-5 inline-block w-80"></span>
                </div>
                <div class="flex items-center max-w-3xl">
                  <span class="w-56">Program/Year Level</span>
                  <span>:</span>
                  <span class="border-b border-black ml-2 h-5 inline-block w-80"></span>
                </div>
                <div class="flex items-center max-w-3xl">
                  <span class="w-56">Assigned Office</span>
                  <span>:</span>
                  <span class="border-b border-black ml-2 h-5 inline-block w-80"></span>
                </div>
                <div class="flex items-center max-w-3xl">
                  <span class="w-56">Head of Office</span>
                  <span>:</span>
                  <span class="border-b border-black ml-2 h-5 inline-block w-80"></span>
                </div>
              </div>

              <div class="max-w-3xl mx-auto">
                <table class="result-table">
                  <thead>
                    <tr>
                      <th class="w-1/2">Criteria</th>
                      <th class="w-24">Rating</th>
                      <th>Verbal Description</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>A. Quality and Quantity of Work</td>
                      <td></td>
                      <td></td>
                    </tr>
                    <tr>
                      <td>B. Interpersonal Skills</td>
                      <td></td>
                      <td></td>
                    </tr>
                    <tr>
                      <td>C. Attendance and Reliability</td>
                      <td></td>
                      <td></td>
                    </tr>
                    <tr>
                      <td class="font-semibold">Overall Rating</td>
                      <td></td>
                      <td></td>
                    </tr>
                    <tr>
                      <td>D. Strength(s)/Areas for Improvement</td>
                      <td colspan="2"><div class="h-16"></div></td>
                    </tr>
                    <tr>
                      <td>E. Evaluator's Comment(s)/Recommendation</td>
                      <td colspan="2"><div class="h-16"></div></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="max-w-4xl mx-auto grid grid-cols-1 sm:grid-cols-3 gap-8 mt-6 text-[12px] font-serif">
                <div>
                  <p class="mb-8">Prepared by:</p>
                  <p class="font-bold">ARLYN B. TUYOGON, MMBM</p>
                  <p>Head, Admission &amp; Scholarship</p>
                </div>
                <div>
                  <p class="mb-8">Checked by:</p>
                  <p class="font-bold">FELMARIE MANLUNAS, MACDDS</p>
                  <p>Head, Student Affairs &amp; Services</p>
                </div>
                <div>
                  <p class="mb-8">Noted by:</p>
                  <p class="font-bold">RICKY E. DESTACAMENTO, RGC, MAED</p>
                  <p>Head, HRMDO</p>
                </div>
              </div>

              <div class="max-w-4xl mx-auto mt-10 text-[12px] font-serif space-y-2">
                <p class="m-0">CC:</p>
                <div class="flex items-center gap-6">
                  <div class="flex items-center gap-2">
                    <span>Mr. Imam</span>
                    <span class="border-b border-black w-48 h-5 inline-block"></span>
                    <span class="ml-2">Date Received:</span>
                    <span class="border-b border-black w-32 h-5 inline-block"></span>
                  </div>
                </div>
                <div class="flex items-center gap-6">
                  <div class="flex items-center gap-2">
                    <span>Mr. Tabanao</span>
                    <span class="border-b border-black w-48 h-5 inline-block"></span>
                    <span class="ml-2">Date Received:</span>
                    <span class="border-b border-black w-32 h-5 inline-block"></span>
                  </div>
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

      function printSection(sectionId) {
        const section = document.getElementById(sectionId);
        if (!section) return;

        const styleTag = document.querySelector("style");
        const styles = styleTag ? styleTag.innerHTML : "";
        const landscapePage =
          sectionId === "print-eval-result"
            ? "@page { size: 11in 8.5in; margin: 0.2in; }"
            : "";
        const tailwindScript = document.querySelector('script[src*="tailwindcss"]');
        const fontAwesome = document.querySelector('link[href*="font-awesome"]');
        const fontFamily = document.querySelector('link[href*="Times+New+Roman"]');

        const printWindow = window.open("", "_blank", "width=900,height=1200");
        if (!printWindow) return;

        const baseHref = window.location.href;

        printWindow.document.write("<html><head><title>Print</title>");
        printWindow.document.write('<base href="' + baseHref + '" />');
        if (tailwindScript) printWindow.document.write(tailwindScript.outerHTML);
        if (fontAwesome) printWindow.document.write(fontAwesome.outerHTML);
        if (fontFamily) printWindow.document.write(fontFamily.outerHTML);
        printWindow.document.write("<style>" + styles + landscapePage + "</style>");
        printWindow.document.write("</head><body class='bg-white'>");
        printWindow.document.write(section.outerHTML);
        printWindow.document.write("</body></html>");
        printWindow.document.close();

        printWindow.onload = () => {
          printWindow.focus();
          printWindow.print();
          printWindow.close();
        };
      }

    </script>
  </body>
</html>
