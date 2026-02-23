<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Assistants&#39; Evaluation Form</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
      :root {
        --navy: #052c6a;
        --blue: #0d8ddb;
        --gold: #fcdc2f;
        --ink: #0b1b3a;
      }

      body {
        font-family: "Space Grotesk", sans-serif;
        color: var(--ink);
        background:
          radial-gradient(1200px 700px at 12% -10%, #e7f3ff 0%, transparent 60%),
          radial-gradient(800px 480px at 92% 8%, #fff2c9 0%, transparent 60%),
          linear-gradient(180deg, #f7fbff 0%, #eef4ff 50%, #f4f7fb 100%);
      }

      body::before {
        content: "";
        position: fixed;
        inset: 0;
        background-image:
          linear-gradient(transparent 23px, rgba(5, 44, 106, 0.045) 24px),
          linear-gradient(90deg, transparent 23px, rgba(5, 44, 106, 0.045) 24px);
        background-size: 24px 24px;
        opacity: 0.7;
        pointer-events: none;
        z-index: 0;
      }

      .page-shell {
        position: relative;
        z-index: 1;
      }

      .glass-card {
        background: rgba(255, 255, 255, 0.92);
        border: 1px solid rgba(13, 141, 219, 0.25);
        box-shadow: 0 18px 45px rgba(5, 44, 106, 0.12);
        backdrop-filter: blur(12px);
      }

      ::-webkit-scrollbar {
        width: 6px;
      }
      ::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.12);
      }
      ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
        border-radius: 999px;
      }

      #sidebar nav ul {
        padding: 0.35rem 0.5rem 5.5rem;
      }

      .panel-nav-item {
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

      .panel-nav-item:hover {
        transform: translateX(2px);
        background-color: rgba(255, 255, 255, 0.15);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.16);
      }

      .panel-nav-item.active {
        background-color: rgba(252, 220, 47, 0.95);
        color: #052c6a;
        box-shadow: 0 8px 20px rgba(252, 220, 47, 0.25);
      }
    </style>
</head>
<body class="bg-white text-[#0b1b3a] font-sans">
    <div class="min-h-screen page-shell">
      <aside
        id="sidebar"
        class="flex flex-col bg-gradient-to-b from-[#031f4f] via-[#0a4b86] to-[#0f9ad8] text-white w-64 h-screen fixed left-0 top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out overflow-y-auto shadow-[12px_0_28px_-12px_rgba(4,31,79,0.65)]"
      >
        <div class="mx-3 mt-3 rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
          <div class="flex items-center gap-3">
            <div class="relative shrink-0">
              <span class="absolute -inset-1 rounded-full bg-white/15 blur-sm"></span>
              <img
                src="../img/SMCCNEWLOGO.png"
                class="relative rounded-full w-14 h-14 object-cover ring-2 ring-white/45"
                alt="SMCC Logo"
              />
            </div>
            <span class="text-sm font-semibold leading-tight text-white">
              Admission and Scholarship Office
            </span>
          </div>
        </div>

        <nav class="flex-1 mt-2">
          <ul class="text-xs font-semibold">
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='headDashboard.php'">
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='headDashboard.php?tab=my-sas'">
              <i class="fas fa-user-friends w-5"></i>
              <span>My SA's</span>
            </li>
            <li class="panel-nav-item active gap-2 cursor-pointer" onclick="window.location.href='headDashboard.php?tab=show-evaluation'">
              <i class="fas fa-check-circle w-5"></i>
              <span>Show Evaluation</span>
            </li>
            <li class="panel-nav-item gap-2 cursor-pointer" onclick="window.location.href='head-changePassword.php'">
              <i class="fas fa-key w-5"></i>
              <span>Change Password</span>
            </li>
          </ul>
        </nav>

        <div class="absolute bottom-0 left-0 w-full p-2">
          <div class="rounded-xl border border-white/20 bg-white/10 backdrop-blur-sm overflow-hidden">
            <div class="h-px w-full bg-gradient-to-r from-transparent via-[#8bcfff] to-transparent opacity-80"></div>
            <div class="px-4 pt-2 pb-1 flex items-center gap-2 text-[11px] text-blue-100/90">
              <div class="w-7 h-7 rounded-full bg-white/10 flex items-center justify-center">
                <i class="fas fa-user-tie text-[12px]"></i>
              </div>
              <div class="leading-tight min-w-0">
                <p class="font-semibold truncate">Head of Office</p>
                <p class="text-[10px] text-blue-200/80 truncate">department head</p>
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
        </div>
      </aside>

      <main class="ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pb-8 pt-14">
        <header
          class="fixed top-0 left-0 md:left-64 right-0 z-20 h-14 flex items-center bg-white border-b border-slate-200 px-4 sm:px-6 shadow-sm"
        >
          <div class="flex items-center gap-2">
            <button
              id="sidebarToggle"
              class="md:hidden inline-flex items-center justify-center p-2 rounded bg-slate-700 text-white hover:bg-slate-800 focus:outline-none transition-colors"
              type="button"
            >
              <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-[#0d4b84] text-lg font-semibold flex items-center gap-2">
              <i class="fas fa-clipboard-check"></i>
              Student Assistant Evaluation
            </h2>
          </div>
        </header>

        <section class="px-4 sm:px-6 pt-6">
          <div class="mx-auto w-full max-w-6xl rounded-3xl glass-card overflow-hidden">
        <header class="border-b border-slate-200 px-10 py-8">
            <h1 class="text-center text-2xl font-semibold uppercase tracking-wide text-slate-800">Student Assistants&#39; Evaluation Form</h1>

            <div class="mt-6 grid gap-4 text-sm text-slate-700 md:grid-cols-2">
                <div class="space-y-3">
                    <div class="flex items-center gap-4">
                        <span class="w-48 font-medium">Name of Student Assistant</span>
                        <div class="h-8 flex-1 border-b border-slate-400"></div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="w-48 font-medium">Semester &amp; School Year</span>
                        <div class="h-8 flex-1 border-b border-slate-400"></div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="w-48 font-medium">Area of Assignment</span>
                        <div class="h-8 flex-1 border-b border-slate-400"></div>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center gap-4">
                        <span class="w-40 font-medium">Head of Office</span>
                        <div class="h-8 flex-1 border-b border-slate-400"></div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="w-40 font-medium">Date of Evaluation</span>
                        <div class="h-8 flex-1 border-b border-slate-400"></div>
                    </div>
                </div>
            </div>

            <p class="mt-6 text-justify text-xs leading-relaxed text-slate-600">
                <span class="font-semibold">Direction:</span> Please rate each item below to determine the performance of the assigned student assistant of your respective office/department. Select the appropriate rating for every item.
            </p>
        </header>

        <section class="px-10 py-6">
            <div class="overflow-hidden rounded-lg border border-slate-300 text-sm text-slate-700">
                <table class="min-w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-100">
                            <th class="w-16 border border-slate-300 px-3 py-3 text-left font-semibold">Scale</th>
                            <th class="w-48 border border-slate-300 px-3 py-3 text-left font-semibold">Verbal Description</th>
                            <th class="border border-slate-300 px-3 py-3 text-left font-semibold">Verbal Interpretation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="border border-slate-300 px-3 py-3 font-semibold">4</td>
                            <td class="border border-slate-300 px-3 py-3">Excellent</td>
                            <td class="border border-slate-300 px-3 py-3 text-xs leading-5">This rating is given to student assistants who consistently exceed expectations and demonstrate outstanding performance in their assigned tasks.</td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-3 font-semibold">3</td>
                            <td class="border border-slate-300 px-3 py-3">Good</td>
                            <td class="border border-slate-300 px-3 py-3 text-xs leading-5">This rating is given to student assistants who consistently meet expectations and perform their assigned tasks satisfactorily.</td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-3 font-semibold">2</td>
                            <td class="border border-slate-300 px-3 py-3">Fair</td>
                            <td class="border border-slate-300 px-3 py-3 text-xs leading-5">This rating is given to student assistants who occasionally meet expectations but may have some areas for improvement.</td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-3 font-semibold">1</td>
                            <td class="border border-slate-300 px-3 py-3">Poor</td>
                            <td class="border border-slate-300 px-3 py-3 text-xs leading-5">This rating is given to student assistants who consistently fail to meet expectations and demonstrate unsatisfactory performance in their assigned tasks.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form class="mt-8 overflow-hidden rounded-lg border border-slate-300">
                <table class="min-w-full border-collapse text-sm text-slate-700">
                    <thead>
                        <tr class="bg-slate-100">
                            <th class="w-16 border border-slate-300 px-3 py-2 text-left font-semibold"></th>
                            <th class="border border-slate-300 px-3 py-2 text-left font-semibold">Criteria</th>
                            <th class="w-24 border border-slate-300 px-3 py-2 text-center font-semibold">Excellent<br><span class="text-xs">(4)</span></th>
                            <th class="w-24 border border-slate-300 px-3 py-2 text-center font-semibold">Good<br><span class="text-xs">(3)</span></th>
                            <th class="w-24 border border-slate-300 px-3 py-2 text-center font-semibold">Fair<br><span class="text-xs">(2)</span></th>
                            <th class="w-24 border border-slate-300 px-3 py-2 text-center font-semibold">Poor<br><span class="text-xs">(1)</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-blue-50/60 font-semibold">
                            <td colspan="6" class="border border-slate-300 px-3 py-2">A. Quality and Quantity of Work</td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">A.1</td>
                            <td class="border border-slate-300 px-3 py-2">Accurate at work assigned</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a1" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a1" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a1" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a1" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-2">A.2</td>
                            <td class="border border-slate-300 px-3 py-2">Always completes tasks</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a2" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a2" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a2" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a2" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">A.3</td>
                            <td class="border border-slate-300 px-3 py-2">Works in a timely manner</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a3" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a3" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a3" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a3" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-2">A.4</td>
                            <td class="border border-slate-300 px-3 py-2">Asks for more work when assigned tasks are done</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a4" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a4" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a4" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a4" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">A.5</td>
                            <td class="border border-slate-300 px-3 py-2">Easily accepts new responsibilities</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a5" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a5" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a5" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-a5" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-100 font-semibold">
                            <td class="border border-slate-300 px-3 py-2" colspan="2">Total</td>
                            <td class="border border-slate-300"></td>
                            <td class="border border-slate-300"></td>
                            <td class="border border-slate-300"></td>
                            <td class="border border-slate-300"></td>
                        </tr>

                        <tr class="bg-blue-50/60 font-semibold">
                            <td colspan="6" class="border border-slate-300 px-3 py-2">B. Interpersonal Skills</td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">B.1</td>
                            <td class="border border-slate-300 px-3 py-2">Answers patrons&#39; questions accurately</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b1" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b1" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b1" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b1" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-2">B.2</td>
                            <td class="border border-slate-300 px-3 py-2">Deals with patrons well</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b2" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b2" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b2" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b2" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">B.3</td>
                            <td class="border border-slate-300 px-3 py-2">Deals with personnel well</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b3" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b3" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b3" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b3" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-2">B.4</td>
                            <td class="border border-slate-300 px-3 py-2">Knows how to effectively communicate with others</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b4" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b4" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b4" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b4" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">B.5</td>
                            <td class="border border-slate-300 px-3 py-2">Has a good relationship with other student assistants</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b5" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b5" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b5" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-b5" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-100 font-semibold">
                            <td class="border border-slate-300 px-3 py-2" colspan="2">Total</td>
                            <td class="border border-slate-300"></td>
                            <td class="border border-slate-300"></td>
                            <td class="border border-slate-300"></td>
                            <td class="border border-slate-300"></td>
                        </tr>

                        <tr class="bg-blue-50/60 font-semibold">
                            <td colspan="6" class="border border-slate-300 px-3 py-2">C. Attendance and Reliability</td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">C.1</td>
                            <td class="border border-slate-300 px-3 py-2">Perfect attendance</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c1" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c1" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c1" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c1" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-2">C.2</td>
                            <td class="border border-slate-300 px-3 py-2">Reports duty on time; rarely comes late</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c2" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c2" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c2" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c2" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">C.3</td>
                            <td class="border border-slate-300 px-3 py-2">Following assigned schedule</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c3" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c3" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c3" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c3" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="border border-slate-300 px-3 py-2">C.4</td>
                            <td class="border border-slate-300 px-3 py-2">Able to work without direct supervision</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c4" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c4" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c4" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c4" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">C.5</td>
                            <td class="border border-slate-300 px-3 py-2">Carries out instructions successfully</td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c5" value="4" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c5" value="3" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c5" value="2" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                            <td class="border border-slate-300 text-center">
                                <input type="radio" name="score-c5" value="1" class="h-4 w-4 text-slate-900 focus:ring-yellow-400" />
                            </td>
                        </tr>
                        <tr class="bg-slate-100 font-semibold">
                            <td class="border border-slate-300 px-3 py-2" colspan="2">Total</td>
                            <td class="border border-slate-300"></td>
                            <td class="border border-slate-300"></td>
                            <td class="border border-slate-300"></td>
                            <td class="border border-slate-300"></td>
                        </tr>

                        <tr class="bg-slate-100 font-semibold">
                            <td class="border border-slate-300 px-3 py-2" colspan="2">Over-all Total</td>
                            <td class="border border-slate-300"></td>
                            <td class="border border-slate-300"></td>
                            <td class="border border-slate-300"></td>
                            <td class="border border-slate-300"></td>
                        </tr>
                    </tbody>
                </table>
            </form>

            <div class="mt-8 space-y-6 text-sm text-slate-700">
                <div>
                    <p class="font-semibold">D. Strength(s)/Areas for Improvement:</p>
                    <div class="mt-2 h-16 rounded-lg border border-dashed border-slate-300"></div>
                </div>
                <div>
                    <p class="font-semibold">E. Evaluator&#39;s Comment(s)/Recommendation:</p>
                    <div class="mt-2 h-16 rounded-lg border border-dashed border-slate-300"></div>
                </div>
            </div>

            <div class="mt-10 flex justify-end">
                <div class="w-64 text-center text-sm text-slate-700">
                    <div class="h-10 border-b border-slate-400"></div>
                    <p class="mt-2 italic">Evaluator&#39;s Signature</p>
                </div>
            </div>
        </section>
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
        }
      });
    </script>
</body>
</html>
