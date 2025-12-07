<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Application Form</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap" rel="stylesheet" />
    <style>
      @page {
        size: A4;
        margin: 30mm 25mm 30mm 25mm;
      }
      ::-webkit-scrollbar { width: 6px; }
      ::-webkit-scrollbar-thumb { background-color: #052c6a; border-radius: 3px; }
      body { font-family: "Times New Roman", serif; }
      header { margin-bottom: 1rem; text-align: center; }
      .header-top { display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem; }
      .header-left { display: flex; align-items: center; gap: 0.5rem; }
      .header-left img { width: 80px; height: 80px; object-fit: contain; }
      .header-left-text { line-height: 1.1; text-align: left; }
      .header-left-text h1 { font-weight: 700; font-size: 16pt; margin: 0; }
      .header-left-text p { margin: 0; font-size: 10pt; }
      .header-right { display: flex; flex-direction: column; gap: 0.2rem; align-items: center; }
      .header-right img { width: 100px; height: 80px; object-fit: contain; }
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
          <img src="../img/SMCCNEWLOGO.png" class="rounded-full w-16 h-16 object-cover" alt="SMCC Logo" />
          <span class="text-sm font-normal">Admission and Scholarship Office</span>
        </div>

        <nav class="flex-1">
          <ul class="text-xs font-semibold">
            <li class="bg-[#fcdc2f] bg-opacity-90 text-[#052c6a] flex items-center gap-2 px-4 py-3 cursor-pointer">
              <i class="fas fa-trophy w-5"></i>
              <span>Dashboard</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer" onclick="window.location.href='adminDashboard.php'">
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer" onclick="window.location.href='applicant.php'">
              <i class="fas fa-user-graduate w-5"></i>
              <span>Applicants</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer" onclick="window.location.href='approved.php'">
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer" onclick="window.location.href='interviewEvaluation.php'">
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer" onclick="window.location.href='ranks.php'">
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer" onclick="window.location.href='list-of-qualified.php'">
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer" onclick="window.location.href='department-evaluation-list.php'">
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer" onclick="window.location.href='summary-report.php'">
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer" onclick="window.location.href='institutional-scholars.php'">
              <i class="fas fa-chart-line w-5"></i>
              <span>Institutional Scholars</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer" onclick="window.location.href='settings.php'">
              <i class="fas fa-cogs w-5"></i>
              <span>Settings</span>
            </li>
            <li class="flex items-center gap-2 px-4 py-3 hover:bg-[#0d8ddb] cursor-pointer" onclick="window.location.href='accounts.php'">
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
              class="w-full flex items-center justify-center gap-2 text-[11px] font-semibold bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 px-3 py-2 rounded-full shadow-md hover:shadow-lg transition-all duration-150"
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
        <header class="fixed top-0 left-0 md:left-56 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2">
          <div class="flex items-center gap-2">
            <button id="sidebarToggle" class="md:hidden inline-flex items-center justify-center p-2 rounded bg-[#0d8ddb] focus:outline-none" type="button">
              <i class="fas fa-bars"></i>
            </button>
            <span class="text-[11px] font-semibold md:hidden">Admission &amp; Scholarship</span>
          </div>
          <div class="flex gap-2 text-xs">
            <button class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 flex items-center gap-1 font-normal" type="button">
              <i class="fas fa-user"></i>
              Admin panel
            </button>
            <button class="bg-[#fcdc2f] text-[#052c6a] rounded px-3 py-1 font-normal" type="button">
              Account
            </button>
          </div>
        </header>

        <section class="mt-12 px-4 sm:px-6 py-4">
          <div class="bg-white border border-[#0d8ddb] rounded shadow-sm p-4 md:p-6">
            <div class="max-w-5xl mx-auto">
              <header>
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

              <section class="text-center mb-6">
                <h1 class="font-bold text-base">APPLICATION FORM</h1>
                <p class="font-semibold text-sm">For Institutional Scholars/Grantees</p>
              </section>

              <form>
                <fieldset class="max-w-3xl mx-auto">
                  <legend class="font-semibold text-sm mb-2">Type of Scholarship/Grant:</legend>
                  <div class="flex flex-wrap justify-between max-w-3xl mx-auto">
                    <div class="flex flex-col space-y-1 w-1/2 min-w-[180px]">
                      <label class="inline-flex items-center space-x-2">
                        <input class="w-4 h-4 border border-black" type="checkbox" />
                        <span class="text-sm">Academic</span>
                      </label>
                      <label class="inline-flex items-center space-x-2">
                        <input class="w-4 h-4 border border-black" type="checkbox" />
                        <span class="text-sm">Kabayani</span>
                      </label>
                      <label class="text-xs pl-6 pt-0.5">
                        Please specify:
                        <span class="inline-block border-b border-black w-36"><input type="text" /></span>
                      </label>
                    </div>
                    <div class="flex flex-col space-y-1 w-1/2 min-w-[180px]">
                      <label class="inline-flex items-center space-x-2">
                        <input class="w-4 h-4 border border-black" type="checkbox" />
                        <span class="text-sm">Student Assistance</span>
                      </label>
                      <label class="inline-flex items-center space-x-2">
                        <input class="w-4 h-4 border border-black" type="checkbox" />
                        <span class="text-sm">Others</span>
                      </label>
                      <label class="text-xs pl-6 pt-0.5">
                        Please specify:
                        <span class="inline-block border-b border-black w-44"><input type="text" /></span>
                      </label>
                    </div>
                  </div>
                </fieldset>

                <br />

                <div class="max-w-3xl mx-auto">
                  <p class="font-serif font-semibold text-sm mb-2">Scholar/Grantee's Profile:</p>
                  <div class="space-y-1 text-xs font-serif">
                    <div class="flex items-center">
                      <label class="w-44">Name of Applicant</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Program/Course Enrolled</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Year Level</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">School Year</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Permanent Address</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                      <label class="ml-4 w-14">Gender</label>
                      <span>:</span>
                      <span class="border-b border-black w-20 ml-2 h-4"></span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Date of Birth</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                      <label class="ml-4 w-14">Age</label>
                      <span>:</span>
                      <span class="border-b border-black w-20 ml-2 h-4"></span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Contact Number</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-72">Estimated Gross Income of the Family/Month</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                    </div>
                  </div>

                  <div class="mt-4 space-y-1 text-xs font-serif">
                    <div class="flex items-center">
                      <label class="w-44">Mother's Name</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                      <label class="ml-4 w-14">Age:</label>
                      <span class="border-b border-black w-20 ml-2 h-4"></span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Contact Number</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                      <label class="ml-4 w-20">Occupation:</label>
                      <span class="border-b border-black w-44 ml-2 h-4"></span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Company's Name</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Company's Address</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                    </div>
                  </div>

                  <div class="mt-4 space-y-1 text-xs font-serif">
                    <div class="flex items-center">
                      <label class="w-44">Father's Name</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                      <label class="ml-4 w-14">Age:</label>
                      <span class="border-b border-black w-20 ml-2 h-4"></span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Contact Number</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                      <label class="ml-4 w-20">Occupation:</label>
                      <span class="border-b border-black w-44 ml-2 h-4"></span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Company's Name</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                    </div>
                    <div class="flex items-center">
                      <label class="w-44">Company's Address</label>
                      <span>:</span>
                      <span class="border-b border-black flex-grow ml-2 h-4"></span>
                    </div>
                  </div>
                </div>

                <p class="text-xs font-serif mt-6 mb-6 max-w-3xl mx-auto">I certify that the above information is true and correct.</p>

                <div class="max-w-3xl mx-auto flex flex-wrap justify-between gap-y-8">
                  <div class="w-full sm:w-[45%]">
                    <div class="border-b border-black w-full h-4 mb-1"></div>
                    <p class="text-xs font-serif">Name and signature of applicant</p>
                  </div>
                  <div class="w-full sm:w-[45%]">
                    <div class="border-b border-black w-full h-4 mb-1"></div>
                    <p class="text-xs font-serif">
                      Name and signature of personnel<br />
                      <span class="text-[10px]">(If the scholar/grantee is personnel dependent)</span>
                    </p>
                  </div>
                </div>

                <div class="max-w-3xl mx-auto flex flex-wrap justify-between gap-y-8 mt-8">
                  <div class="w-full sm:w-[45%]">
                    <p class="text-xs font-serif mb-6">Recommending approval:</p>
                    <div class="border-b border-black w-full h-4 mb-1"></div>
                    <p class="text-xs font-serif">Head, Admission &amp; Scholarship</p>
                  </div>
                  <div class="w-full sm:w-[45%]">
                    <p class="text-xs font-serif mb-6">Approved by:</p>
                    <div class="border-b border-black w-full h-4 mb-1"></div>
                    <p class="text-xs font-serif">Head, HRMDO</p>
                  </div>
                </div>

                <div class="max-w-3xl mx-auto mt-6">
                  <div class="flex items-center">
                    <img src="../img/box.png" alt="Box" class="w-48 h-auto" />
                  </div>
                </div>
              </form>

              <footer class="max-w-3xl mx-auto mt-6">
                <div class="flex items-center justify-between text-[10px] font-semibold text-black">
                  <img src="../img/footer.png" alt="Footer" />
                </div>
              </footer>
            </div>
          </div>
        </section>

        <!-- Uploaded requirements display -->
        <section class="px-4 sm:px-6 pb-8">
          <div class="bg-white border border-[#0d8ddb] rounded shadow-sm p-4 md:p-6">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-sm font-semibold text-[#052c6a]">Uploaded Requirements</h2>
              <span class="text-[11px] text-slate-600">Replace with dynamic list from backend</span>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full border text-xs">
                <thead>
                  <tr class="bg-[#f1f5f9] text-[#052c6a]">
                    <th class="border px-3 py-2 text-left font-semibold">Requirement</th>
                    <th class="border px-3 py-2 text-left font-semibold">File</th>
                    <th class="border px-3 py-2 text-center font-semibold w-32">Action</th>
                  </tr>
                </thead>
                <tbody class="text-[#052c6a]">
                  <tr>
                    <td class="border px-3 py-2">Certificate of Registration</td>
                    <td class="border px-3 py-2 truncate">cor.pdf</td>
                    <td class="border px-3 py-2 text-center">
                      <a href="#" class="text-[#0d8ddb] hover:underline">View / Download</a>
                    </td>
                  </tr>
                  <tr>
                    <td class="border px-3 py-2">Report Card / Grades</td>
                    <td class="border px-3 py-2 truncate">grades.pdf</td>
                    <td class="border px-3 py-2 text-center">
                      <a href="#" class="text-[#0d8ddb] hover:underline">View / Download</a>
                    </td>
                  </tr>
                  <tr>
                    <td class="border px-3 py-2">Good Moral Certificate</td>
                    <td class="border px-3 py-2 truncate">good-moral.jpg</td>
                    <td class="border px-3 py-2 text-center">
                      <a href="#" class="text-[#0d8ddb] hover:underline">View / Download</a>
                    </td>
                  </tr>
                </tbody>
              </table>
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
    </script>
  </body>
</html>
