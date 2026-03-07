<?php
session_start();
if (empty($_SESSION["from_index"])) {
  header("Location: ../index.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ISG List and Requirements • Step 1</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    :root {
      --ink: #052c6a;
      --accent: #0d8ddb;
      --gold: #fcdc2f;
      --card-border: rgba(13, 141, 219, 0.18);
    }

    body {
      font-family: "IBM Plex Sans", sans-serif;
      background:
        radial-gradient(circle at top left, rgba(13, 141, 219, 0.16), transparent 28%),
        radial-gradient(circle at 85% 15%, rgba(252, 220, 47, 0.22), transparent 20%),
        linear-gradient(180deg, #dfefff 0%, #f7fbff 40%, #eef6ff 100%);
    }

    h1,
    h2,
    h3,
    h4,
    .display-font {
      font-family: "Outfit", sans-serif;
    }

    .top-brand,
    .top-brand * {
      font-family: sans-serif;
    }

    .page-orbs {
      position: absolute;
      inset: 0 0 auto;
      height: 24rem;
      overflow: hidden;
      pointer-events: none;
      z-index: -1;
    }

    .page-orbs span {
      position: absolute;
      border-radius: 999px;
      filter: blur(50px);
      opacity: 0.7;
    }

    .page-orbs .orb-a {
      width: 15rem;
      height: 15rem;
      top: -3rem;
      left: -2rem;
      background: rgba(13, 141, 219, 0.22);
    }

    .page-orbs .orb-b {
      width: 13rem;
      height: 13rem;
      top: 2rem;
      right: 2rem;
      background: rgba(252, 220, 47, 0.25);
    }

    .hero-shell {
      position: relative;
      overflow: hidden;
      border: 1px solid rgba(13, 141, 219, 0.14);
      border-radius: 2rem;
      background: linear-gradient(140deg, rgba(255, 255, 255, 0.96), rgba(236, 246, 255, 0.92));
      box-shadow: 0 28px 60px -36px rgba(5, 44, 106, 0.55);
      padding: 1.35rem;
    }

    .hero-shell::before {
      content: "";
      position: absolute;
      inset: 0;
      background:
        linear-gradient(120deg, rgba(255, 255, 255, 0.24), transparent 40%),
        radial-gradient(circle at top right, rgba(252, 220, 47, 0.2), transparent 24%);
      pointer-events: none;
    }

    .hero-grid {
      display: grid;
      gap: 1rem;
      position: relative;
      z-index: 1;
    }

    .hero-card {
      border-radius: 1.5rem;
      padding: 1.35rem;
      color: #fff;
      background: linear-gradient(135deg, #052c6a 0%, #0d8ddb 58%, #37b7f5 100%);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
    }

    .step-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.38rem 0.8rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.14);
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.16em;
      text-transform: uppercase;
    }

    .step-flow {
      display: flex;
      flex-wrap: wrap;
      gap: 0.6rem;
      margin-top: 1rem;
    }

    .step-flow span {
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.18);
      background: rgba(255, 255, 255, 0.1);
      padding: 0.45rem 0.8rem;
      font-size: 0.78rem;
      font-weight: 600;
    }

    .metric-grid {
      display: grid;
      gap: 0.9rem;
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .metric-card {
      border-radius: 1.4rem;
      border: 1px solid rgba(13, 141, 219, 0.14);
      background: rgba(255, 255, 255, 0.9);
      padding: 1rem;
      box-shadow: 0 18px 36px -30px rgba(5, 44, 106, 0.45);
    }

    .metric-card strong {
      display: block;
      color: var(--ink);
      font-family: "Outfit", sans-serif;
      font-size: 1.8rem;
      line-height: 1;
    }

    .metric-card span {
      display: block;
      margin-top: 0.35rem;
      color: rgba(5, 44, 106, 0.76);
      font-size: 0.78rem;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .hero-progress {
      position: relative;
      z-index: 1;
      margin-top: 1rem;
      border-radius: 1.5rem;
      border: 1px solid rgba(13, 141, 219, 0.12);
      background: rgba(255, 255, 255, 0.78);
      padding: 1rem;
    }

    .notice-shell {
      border: 1px solid rgba(252, 220, 47, 0.45);
      border-radius: 1.75rem;
      background: linear-gradient(135deg, rgba(255, 251, 230, 0.98), rgba(255, 247, 199, 0.9));
      box-shadow: 0 18px 40px -34px rgba(120, 53, 15, 0.45);
    }

    .notice-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      border-radius: 999px;
      border: 1px solid rgba(120, 53, 15, 0.14);
      background: rgba(255, 255, 255, 0.62);
      padding: 0.42rem 0.72rem;
      font-size: 0.72rem;
      font-weight: 700;
      color: #7c3f10;
    }

    .grants-layout {
      display: grid;
      gap: 1.5rem;
      align-items: start;
      max-width: 1100px;
      margin: 0 auto;
    }

    .grants-layout > section:nth-of-type(1),
    .grants-layout > section:nth-of-type(2),
    .grants-layout > section:nth-of-type(18) {
      grid-column: 1 / -1;
    }

    main > section:nth-of-type(n+3):nth-of-type(-n+17) {
      position: relative;
      overflow: hidden;
      border-color: rgba(13, 141, 219, 0.2) !important;
      background: #ffffff !important;
      box-shadow: 0 18px 34px -30px rgba(5, 44, 106, 0.32) !important;
      backdrop-filter: none;
    }

    main > section:nth-of-type(n+3):nth-of-type(-n+17)::before {
      display: none;
    }

    main > section:nth-of-type(n+3):nth-of-type(-n+17) > div:first-child {
      position: relative;
      overflow: hidden;
      border-bottom: 1px solid rgba(13, 141, 219, 0.12);
    }

    main > section:nth-of-type(n+3):nth-of-type(-n+17) > div:first-child::after {
      display: none;
    }

    main > section:nth-of-type(n+3):nth-of-type(-n+17) > div:last-child {
      position: relative;
      background: #ffffff;
      backdrop-filter: none;
    }

    main > section:nth-of-type(n+3):nth-of-type(-n+17) ul {
      margin-top: 0.4rem;
    }

    main > section:nth-of-type(n+3):nth-of-type(-n+17) li {
      margin-bottom: 0.28rem;
      padding-left: 0.1rem;
    }

    main > section:nth-of-type(n+3):nth-of-type(-n+17) li::marker {
      color: var(--accent);
    }

    main > section:nth-of-type(n+3):nth-of-type(-n+17) button {
      border: 1px solid rgba(4, 44, 106, 0.08);
      background-image: linear-gradient(135deg, #0d8ddb 0%, #1354c8 100%);
      box-shadow: 0 14px 28px -20px rgba(13, 141, 219, 0.8);
    }

    main > section:nth-of-type(n+3):nth-of-type(-n+17) button:hover {
      transform: translateY(-2px);
      box-shadow: 0 18px 34px -22px rgba(13, 141, 219, 0.9);
    }

    .final-cta {
      border: 1px solid rgba(13, 141, 219, 0.14);
      border-radius: 1.75rem;
      background: #ffffff;
      box-shadow: 0 16px 32px -28px rgba(5, 44, 106, 0.32);
    }

    @media (min-width: 960px) {
      .grants-layout {
        grid-template-columns: minmax(0, 1fr);
      }

      .hero-grid {
        grid-template-columns: minmax(0, 1.25fr) minmax(0, 0.9fr);
        align-items: stretch;
      }
    }

    @media (max-width: 767px) {
      .metric-grid {
        grid-template-columns: 1fr;
      }

      .hero-card,
      .hero-progress,
      .metric-card {
        border-radius: 1.2rem;
      }
    }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-b from-[#e0f2ff] via-white to-[#e0f2ff] font-sans flex flex-col">
  <!-- TOP BAR / BRAND -->
  <header class="top-brand sticky top-0 z-20 bg-gradient-to-r from-[#052c6a] via-[#0d8ddb] to-[#1d4ed8] shadow-md">
    <div class="w-full flex items-center gap-3 px-4 sm:px-6 lg:px-10 py-3">
      <!-- LOGO -->
      <div class="flex items-center justify-center">
        <img
          src="../img/SMCCNEWLOGO.png"
          alt="SMCC Logo"
          class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover bg-white shadow-md border border-white"
        />
      </div>

      <!-- TEXT -->
      <div class="flex-1">
        <p class="text-[10px] sm:text-xs text-blue-100 uppercase tracking-[0.18em]">
          SMCC Admission and Scholarship Office
        </p>
        <div class="flex flex-wrap items-center gap-2">
          <h1 class="text-white text-sm sm:text-base font-semibold leading-tight">
            Institutional Scholarship Grants
          </h1>
          <span class="inline-flex items-center gap-1 px-2 py-[2px] rounded-full bg-white/10 text-[10px] sm:text-[11px] text-blue-50">
            Step 1 of 3
          </span>
        </div>
        <p class="text-[10px] sm:text-xs text-blue-100">
          View ISG List and Requirements
        </p>
      </div>
    </div>
  </header>

  <main class="grants-layout relative flex-1 w-full px-4 sm:px-6 lg:px-10 xl:px-12 py-6 sm:py-10">
    <div class="page-orbs" aria-hidden="true">
      <span class="orb-a"></span>
      <span class="orb-b"></span>
    </div>
    <!-- PAGE TITLE + STEP BAR -->
    <section class="hero-shell">
      <div class="hero-grid">
        <div class="hero-card">
          <span class="step-pill">Step 1 of 3</span>
          <h2 class="mt-4 text-3xl sm:text-4xl font-extrabold tracking-tight">
            Review Every Grant Before You Apply
          </h2>
          <p class="mt-3 max-w-2xl text-sm sm:text-base text-blue-50/92">
            Check the qualifications, prepare the correct requirements, then continue to the application form with the grant you want already selected.
          </p>
          <div class="step-flow">
            <span>Review eligibility</span>
            <span>Prepare documents</span>
            <span>Continue to Step 2</span>
          </div>
        </div>

        <div class="metric-grid">
          <div class="metric-card">
            <strong>15</strong>
            <span>Available grants and discounts</span>
          </div>
          <div class="metric-card">
            <strong>3</strong>
            <span>Simple application steps</span>
          </div>
          <div class="metric-card">
            <strong>Online</strong>
            <span>Document upload after applying</span>
          </div>
        </div>
      </div>

      <div class="hero-progress">
        <div class="flex flex-wrap justify-between gap-2 text-[10px] sm:text-xs font-semibold text-[#052c6a]/80 mb-2">
          <span class="text-[#0d8ddb]">Step 1: List & Requirements</span>
          <span>Step 2: Application Form</span>
          <span>Step 3: Upload Documents</span>
        </div>
        <div class="h-2 rounded-full bg-[#dbe6ff] overflow-hidden">
          <div class="h-full w-1/3 rounded-full bg-gradient-to-r from-[#0d8ddb] via-[#1499e8] to-[#fcdc2f]"></div>
        </div>
      </div>
    </section>

    <!-- NOTE CARD -->
    <section
      class="notice-shell px-5 sm:px-7 py-4 sm:py-5 flex gap-3"
    >
      <div class="mt-1 hidden sm:block">
        <div class="w-11 h-11 rounded-2xl bg-white/70 flex items-center justify-center border border-yellow-300/80 shadow-sm">
          <span class="text-red-500 font-bold text-sm">!</span>
        </div>
      </div>
      <div class="text-xs sm:text-sm text-[#052c6a]">
        <p class="text-sm sm:text-base">
          <span class="font-bold text-red-600">Important Reminder:</span>
          Non-compliance with any of the requirements listed under each grant, or with
          any provision of the scholarship contract, will be a ground for the forfeiture
          of the scholarship program.
        </p>
        <p class="mt-2 text-[11px] sm:text-sm text-[#052c6a]/80">
          Make sure your documents are complete and clearly scanned before you proceed to the application form.
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
          <span class="notice-chip">Check qualifications</span>
          <span class="notice-chip">Prepare clear scans</span>
          <span class="notice-chip">Choose the correct grant</span>
        </div>
      </div>
    </section>

    <!-- ============ 1. STUDENT ASSISTANT ============ -->
    <section
      class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5"
    >
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Scholarship Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            1. Student Assistant
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-[#fcdc2f] text-[#052c6a] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          Open for Application
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-6 text-[#052c6a] text-sm sm:text-base">
        <div class="space-y-2">
          <h4 class="font-semibold text-[#0d8ddb] flex items-center gap-2">
            <span class="inline-block w-1 h-5 rounded-full bg-[#fcdc2f]"></span>
            Requirements
          </h4>
          <ul class="list-disc list-inside space-y-1.5 pl-1">
            <li>
              Accomplished Application Form
              <span class="italic text-xs sm:text-sm">
                (to be secured from the Office of Admission &amp; Scholarship)
              </span>
            </li>
            <li>1 copy of 2×2 ID picture</li>
            <li>Application Letter</li>
            <li>Resume</li>
            <li>Photocopy of Form 138 or Report of Grades</li>
            <li>Certificate of Indigency</li>
            <li>Certificate of Good Moral Character</li>
          </ul>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
          <div class="space-y-2">
            <h4 class="font-semibold text-[#0d8ddb] flex items-center gap-2">
              <span class="inline-block w-1 h-5 rounded-full bg-[#0d8ddb]"></span>
              Bachelor Programs need to enroll in this grant
            </h4>
            <ul class="list-disc list-inside space-y-1.5 pl-1">
              <li>Bachelor of Arts in English Language (AB English)</li>
              <li>Bachelor of Technical Vocational Teacher Education (BTVTED)</li>
              <li>
                Bachelor of Secondary Education
                <ul class="list-disc list-inside ml-5 space-y-1">
                  <li>major in Mathematics</li>
                  <li>major in Social Studies</li>
                </ul>
              </li>
              <li>Bachelor of Science in Computer Science (BSCS)</li>
              <li>Bachelor of Library and Information Science (BLIS)</li>
              <li>Bachelor of Science in Information Systems (BSIS)</li>
              <li>Bachelor of Public Administration (BPA)</li>
              <li>Bachelor of Science in Entrepreneurship (BSE)</li>
              <li>Bachelor of Science in Accounting Information System (BSAIS)</li>
            </ul>
          </div>

          <div class="space-y-2">
            <h4 class="font-semibold text-[#0d8ddb] flex items-center gap-2">
              <span class="inline-block w-1 h-5 rounded-full bg-[#22c55e]"></span>
              New Program Offerings
            </h4>
            <ul class="list-disc list-inside space-y-1.5 pl-1">
              <li>
                Bachelor of Science in Business Administration (BSBA)
                <ul class="list-disc list-inside ml-5 space-y-1">
                  <li>major in Operations Management</li>
                  <li>major in Business Economics</li>
                </ul>
              </li>
              <li>Bachelor in Human Services (BHumserv)</li>
            </ul>
          </div>
        </div>

        <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3">
          <p class="text-[11px] sm:text-xs text-[#052c6a]/80 text-center sm:text-left">
            When you are ready, click apply to proceed to the application form for Student Assistant.
          </p>
          <button
            type="button"
            onclick="goApply(1)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS GRANT
          </button>
        </div>
      </div>
    </section>

    <!-- ============ 2. ACADEMIC SCHOLARSHIP PROGRAM ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Scholarship Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            2. Academic Scholarship Program
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          Merit-based
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <div class="space-y-1.5">
          <h4 class="font-semibold text-[#0d8ddb]">General Qualifications</h4>
          <ul class="list-disc list-inside space-y-1 pl-1">
            <li>The student must be a Top 1 or Top 2 of the batch.</li>
            <li>Elementary school completer/graduate of the batch for JHS.</li>
            <li>JHS completer of the batch for SHS.</li>
            <li>SHS graduate of the batch for college.</li>
          </ul>
        </div>

        <div class="space-y-1.5">
          <h4 class="font-semibold text-[#0d8ddb]">Requirements for Application</h4>
          <ul class="list-disc list-inside space-y-1 pl-1">
            <li>
              Original Certification that the student graduated Top 1 or Top 2 of the batch
              (with principal’s signature and school dry seal).
            </li>
            <li>1 copy of 2×2 ID picture.</li>
            <li>Photocopy of Form 138 (Report Card) or Report of Grades.</li>
            <li>Certification of Indigency.</li>
            <li>Certification of Good Moral Character.</li>
          </ul>
        </div>

        <p class="text-[11px] sm:text-xs text-[#052c6a]/80">
          <span class="font-semibold">Note:</span> Applications must be filed on the school year
          immediately after graduation/completion.
        </p>

        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(2)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS GRANT
          </button>
        </div>
      </div>
    </section>

    <!-- ============ 3. ESG PRESIDENT SCHOLARSHIP PROGRAM ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Scholarship Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            3. Executive Student Government (ESG) President Scholarship Program
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          ESG President
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <div>
          <h4 class="font-semibold text-[#0d8ddb]">General Qualifications</h4>
          <ul class="list-disc list-inside space-y-1 pl-1 mt-1">
            <li>Applicant must win the ESG Election.</li>
          </ul>
        </div>

        <div>
          <h4 class="font-semibold text-[#0d8ddb]">Requirements for Application</h4>
          <ul class="list-disc list-inside space-y-1 pl-1 mt-1">
            <li>
              Accomplished Application Form
              <span class="italic text-xs sm:text-sm">
                (to be secured from the Office of Admission &amp; Scholarship).
              </span>
            </li>
            <li>1 copy of 2×2 ID picture.</li>
            <li>Endorsement letter from the OSAS Head.</li>
          </ul>
        </div>

        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(3)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS GRANT
          </button>
        </div>
      </div>
    </section>

    <!-- ============ 4. KABAYANI SCHOLARSHIP PROGRAM ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Scholarship Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            4. Kabayani Scholarship Program
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          Endorsed Scholar
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <p class="text-xs sm:text-sm text-[#052c6a]/90 italic">
          This scholarship is for qualified first-year college students endorsed by
          the Bishop of the Diocese of Butuan (Diocesan Scholar), the School President
          (School President’s Scholar), and the Saint Michael Parish Priest
          (Saint Michael Parish Scholar). It is granted based on the availability of
          slots; only one (1) scholar may be endorsed by each endorsing authority.
        </p>

        <div>
          <h4 class="font-semibold text-[#0d8ddb]">General Qualifications</h4>
          <ul class="list-disc list-inside space-y-1 pl-1 mt-1">
            <li>
              Accomplished Application Form
              <span class="italic text-xs sm:text-sm">
                (to be secured from the Office of Admission &amp; Scholarship).
              </span>
            </li>
            <li>1 copy of 2×2 ID picture.</li>
            <li>
              Endorsement letter from the Bishop of the Diocese of Butuan /
              School President / Saint Michael Parish Priest.
            </li>
          </ul>
        </div>

        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(4)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS GRANT
          </button>
        </div>
      </div>
    </section>

    <!-- ============ 5. KABAYANI LOYALTY GRANT ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Grant Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            5. Kabayani Loyalty Grant
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          Dependents of Retirees
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <p class="text-xs sm:text-sm text-[#052c6a]/90 italic">
          This grant is given to students who are dependents of retired SMCC employees
          who have served the school for at least 20 years and are members of the
          SMCC Retirees Association. Only one (1) dependent per retiree is allowed.
        </p>

        <div>
          <h4 class="font-semibold text-[#0d8ddb]">Requirements for Application</h4>
          <ul class="list-disc list-inside space-y-1 pl-1 mt-1">
            <li>
              Accomplished Application Form
              <span class="italic text-xs sm:text-sm">
                (to be secured from the Office of Admission &amp; Scholarship).
              </span>
            </li>
            <li>1 copy of 2×2 ID picture.</li>
            <li>Certification from the External and Linkages Coordinator.</li>
            <li>Proof of relationship (e.g., Birth Certificate).</li>
            <li>Endorsement letter from the retiree.</li>
          </ul>
        </div>

        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(5)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS GRANT
          </button>
        </div>
      </div>
    </section>

    <!-- ============ 6. PWD DISCOUNT ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Discount Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            6. Discount for Persons with Disability (PWD)
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          PWD Discount
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <p class="text-xs sm:text-sm text-[#052c6a]/90 italic">
          This discount is given to students identified as PWD and who are members of
          the Persons with Disability sector of the Department of Social Welfare and
          Development (DSWD) of the Philippines.
        </p>

        <div>
          <h4 class="font-semibold text-[#0d8ddb]">Requirements for Application</h4>
          <ul class="list-disc list-inside space-y-1 pl-1 mt-1">
            <li>
              Accomplished Application Form
              <span class="italic text-xs sm:text-sm">
                (to be secured from the Office of Admission &amp; Scholarship).
              </span>
            </li>
            <li>1 copy of 2×2 ID picture.</li>
            <li>Two (2) photocopies of the PWD ID issued by the DSWD.</li>
          </ul>
        </div>

        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(6)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS DISCOUNT
          </button>
        </div>
      </div>
    </section>

    <!-- ============ 7. DISCOUNT FOR CHILDREN OF EMPLOYEES ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Discount Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            7. Discount for Children of Employees
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          Employee’s Child
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <p class="text-xs sm:text-sm text-[#052c6a]/90 italic">
          This discount is given to qualified, enrolled students in the elementary,
          junior high school (JHS), senior high school (SHS), and college levels of
          SMCC who are children of employees who have served the school for the
          required number of years. It is applicable to a maximum of three (3)
          children per eligible tenured employee from elementary to college.
        </p>

        <div>
          <h4 class="font-semibold text-[#0d8ddb]">Requirements for Application</h4>
          <ul class="list-disc list-inside space-y-1 pl-1 mt-1">
            <li>
              Accomplished Application Form
              <span class="italic text-xs sm:text-sm">
                (to be secured from the Office of Admission &amp; Scholarship).
              </span>
            </li>
            <li>1 copy of 2×2 ID picture.</li>
            <li>Certification from the HRMDO.</li>
          </ul>
        </div>

        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(7)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS DISCOUNT
          </button>
        </div>
      </div>
    </section>

    <!-- ============ 8. DISCOUNT FOR SIBLING OF EMPLOYEES ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Discount Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            8. Discount for Sibling of Employees
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          Employee’s Sibling
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <p class="text-xs sm:text-sm text-[#052c6a]/90 italic">
          This discount is granted to qualified and enrolled elementary, junior high
          school, senior high school, and college students of SMCC who are siblings of
          single employees. Only one (1) sibling per eligible employee, either brother
          or sister, from elementary to college is allowed.
        </p>

        <div>
          <h4 class="font-semibold text-[#0d8ddb]">Requirements for Application</h4>
          <ul class="list-disc list-inside space-y-1 pl-1 mt-1">
            <li>
              Form / Accomplished Application
              <span class="italic text-xs sm:text-sm">
                (to be secured from the Office of Admission &amp; Scholarship).
              </span>
            </li>
            <li>1 copy of 2×2 ID picture.</li>
            <li>Certification from the HRMDO.</li>
          </ul>
        </div>

        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(8)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS DISCOUNT
          </button>
        </div>
      </div>
    </section>

    <!-- ============ 9. SIBLING DISCOUNT ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Discount Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            9. Sibling Discount
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          Brothers &amp; Sisters
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <p class="text-xs sm:text-sm text-[#052c6a]/90 italic">
          This discount is given to brothers and sisters enrolled in SMCC
          (all departments).
        </p>

        <div>
          <h4 class="font-semibold text-[#0d8ddb]">Requirements for Application</h4>
          <ul class="list-disc list-inside space-y-1 pl-1 mt-1">
            <li>
              Accomplished Application Form
              <span class="italic text-xs sm:text-sm">
                (to be secured from the Office of Admission &amp; Scholarship).
              </span>
            </li>
            <li>1 copy of 2×2 ID picture.</li>
          </ul>
        </div>

        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(9)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS DISCOUNT
          </button>
        </div>
      </div>
    </section>

    <!-- ============ 10. DXSM-FM GRANT ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Grant Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            10. DXSM-FM Grant
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg:white/90 bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          DXSM-FM
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <p class="text-xs sm:text-sm text-[#052c6a]/90 italic">
          This grant is given to qualified college students of SMCC who are endorsed
          or recommended by the DXSM-FM Station Manager. Only one (1) grantee may be
          Manager at a time. A slot becomes available once the current grantee graduates.
        </p>

        <div>
          <h4 class="font-semibold text-[#0d8ddb]">Requirements for Application</h4>
          <ul class="list-disc list-inside space-y-1 pl-1 mt-1">
            <li>
              Accomplished Application Form
              <span class="italic text-xs sm:text-sm">
                (to be secured from the Office of Admission &amp; Scholarship).
              </span>
            </li>
            <li>1 copy of 2×2 ID picture.</li>
            <li>Endorsement letter from the FM Station Manager.</li>
          </ul>
        </div>

        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(10)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS GRANT
          </button>
        </div>
      </div>
    </section>

    <!-- ============ 11. MICHAELINIAN MIRROR GRANT ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Grant Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            11. Michaelinian Mirror Grant (Editor-in-Chief)
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          Publication Grant
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <p class="text-xs sm:text-sm text-[#052c6a]/90 italic">
          This grant is given to a qualified college student who is the Editor-in-Chief
          of <span class="font-semibold">The Michaelinian Mirror</span>, the school’s official publication.
        </p>

        <div>
          <h4 class="font-semibold text-[#0d8ddb]">Requirements for Application</h4>
          <ul class="list-disc list-inside space-y-1 pl-1 mt-1">
            <li>
              Accomplished Application Form
              <span class="italic text-xs sm:text-sm">
                (to be secured from the Office of Admission &amp; Scholarship).
              </span>
            </li>
            <li>1 copy of 2×2 ID picture.</li>
            <li>Endorsement letter from the Publication In-charge.</li>
          </ul>
        </div>

        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(11)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text:white text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS GRANT
          </button>
        </div>
      </div>
    </section>

    <!-- ============ 12. GRANT FOR THE DEPENDENTS OF A LOT DONOR ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Grant Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text:white text-white">
            12. Grant for the Dependents of a Lot Donor
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          Lot Donor
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <p class="text-xs sm:text-sm text-[#052c6a]/90 italic">
          This grant is given to the dependents of a lot donor. It is limited to three (3)
          recipients only—either a son, daughter, grandson, or granddaughter, or as stipulated
          in the Board Resolution or Certification duly signed by BOT representatives.
          This grant is applicable from elementary to senior high school only.
        </p>

        <div>
          <h4 class="font-semibold text-[#0d8ddb]">Requirements for Application</h4>
          <ul class="list-disc list-inside space-y-1 pl-1 mt-1">
            <li>
              Accomplished Application Form
              <span class="italic text-xs sm:text-sm">
                (to be secured from the Office of Admission &amp; Scholarship).
              </span>
            </li>
            <li>1 copy of 2×2 ID picture.</li>
            <li>
              Photocopy of the approved Board Resolution or Certification duly
              signed by BOT representatives.
            </li>
          </ul>
        </div>

        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(12)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS GRANT
          </button>
        </div>
      </div>
    </section>

    <!-- ============ 13. GRANT FOR DEPENDENTS OF BOT MEMBER ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Grant Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            13. Grant for the Dependents of a Board of Trustees (BOT) Member
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          BOT Member
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <p class="text-xs sm:text-sm text-[#052c6a]/90 italic">
          This grant is awarded to the dependent of a Board of Trustees (BOT) member who
          has rendered at least five (5) years of service. It is limited to one (1)
          recipient only—either a son, daughter, grandson, or granddaughter, or as
          stipulated in the Board Resolution or Certification duly signed by BOT
          representatives.
        </p>

        <div>
          <h4 class="font-semibold text-[#0d8ddb]">Requirements for Application</h4>
          <ul class="list-disc list-inside space-y-1 pl-1 mt-1">
            <li>
              Accomplished Application Form
              <span class="italic text-xs sm:text-sm">
                (to be secured from the Office of Admission &amp; Scholarship).
              </span>
            </li>
            <li>1 copy of 2×2 ID picture.</li>
            <li>
              Photocopy of the approved Board Resolution or Certification duly
              signed by BOT representatives.
            </li>
            <li>Endorsement letter from the BOT member.</li>
          </ul>
        </div>

        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(13)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS GRANT
          </button>
        </div>
      </div>
    </section>

    <!-- ============ 14. SMCC ALUMNI DISCOUNT ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Discount Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            14. SMCC Alumni Discount
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          Alumni
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <p class="text-xs sm:text-sm text-[#052c6a]/90 italic">
          This discount is given to parents in the Elementary Department for incoming
          Kindergarten and Grade 1 learners.
        </p>

        <div>
          <h4 class="font-semibold text-[#0d8ddb]">Requirements for Application</h4>
          <ul class="list-disc list-inside space-y-1 pl-1 mt-1">
            <li>
              Accomplished Application Form
              <span class="italic text-xs sm:text-sm">
                (to be secured from the Office of Admission &amp; Scholarship).
              </span>
            </li>
            <li>1 copy of 2×2 ID picture.</li>
            <li>Certification from the President of SMCC Alumni Association.</li>
          </ul>
        </div>

        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(14)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS DISCOUNT
          </button>
        </div>
      </div>
    </section>

       <!-- ============ 15. Michaelinian Stakeholders Grant  ============ -->
    <section class="bg-white/95 backdrop-blur rounded-3xl shadow-xl border border-[#cddfff] overflow-hidden transition hover:shadow-2xl hover:-translate-y-0.5">
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-6 sm:px-8 py-4 sm:py-5 flex items-center justify-between gap-3">
        <div>
          <p class="text-[11px] sm:text-xs text-blue-100 uppercase tracking-[0.25em]">
            Discount Category
          </p>
          <h3 class="text-lg sm:text-xl font-extrabold text-white">
            15. Michaelinian Stakeholders Grant
          </h3>
        </div>
        <span class="inline-flex items-center rounded-full bg-white/90 text-[#0d8ddb] text-[11px] sm:text-xs font-semibold px-3 py-1 shadow">
          Stakeholders
        </span>
      </div>

      <div class="px-6 sm:px-8 py-6 sm:py-8 space-y-4 text-[#052c6a] text-sm sm:text-base">
        <p class="text-xs sm:text-sm text-[#052c6a]/90 italic">
        </p>
        <div class="pt-2 flex justify-center">
          <button
            type="button"
            onclick="goApply(15)"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#0d8ddb] hover:bg-[#0b63d1] text-white font-semibold text-sm sm:text-base px-8 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
          >
            APPLY FOR THIS DISCOUNT
          </button>
        </div>
      </div>
    </section>

    
    <!-- GLOBAL CTA -->
     <!--
    <section class="pt-4">
      <div class="final-cta px-4 sm:px-6 py-5 flex flex-col sm:flex-row items-center justify-between gap-4">
        <p class="text-[11px] sm:text-xs text-[#052c6a]/80 text-center sm:text-left">
          Already reviewed the grants and discounts? You may now proceed to
          <span class="font-semibold text-[#0d8ddb]">Step 2: Application Form</span>.
          Selecting a grant above will automatically pass the grant type.
        </p>
        <button
          type="button"
          onclick="goApply(null)"
          class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#22c55e] hover:bg-[#16a34a] text-white font-semibold text-xs sm:text-sm px-6 sm:px-7 py-2.5 rounded-full shadow-md transition-transform duration-150 hover:-translate-y-[1px]"
        >
          GO TO STEP 2 – APPLICATION FORM
        </button>
      </div>
    </section>
    -->
    
  </main>

  <script>
    // Step 1 -> Step 2 (Application Form)
    function goApply(id) {
      var baseUrl = "isg-application-form.php"; // ilisi kung lahi imong Step 2 filename
      if (id) {
        window.location.href = baseUrl + "?grant=" + id;
      } else {
        window.location.href = baseUrl;
      }
    }
  </script>
</body>
</html>
