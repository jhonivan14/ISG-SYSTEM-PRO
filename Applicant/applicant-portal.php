<?php
session_start();
$_SESSION["from_index"] = true;

require_once "../application-reference.php";

$prefilledReference = isset($_GET["reference"])
    ? normalizeApplicationReference((string)$_GET["reference"])
    : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Applicant Portal</title>
  <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
  <link rel="stylesheet" href="../assets/css/tailwind.css">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    :root {
      --ink: #052c6a;
      --accent: #0d8ddb;
      --gold: #fcdc2f;
    }

    body {
      font-family: "IBM Plex Sans", sans-serif;
      background:
        radial-gradient(circle at top left, rgba(13, 141, 219, 0.18), transparent 28%),
        radial-gradient(circle at 85% 15%, rgba(252, 220, 47, 0.2), transparent 20%),
        linear-gradient(180deg, #dfefff 0%, #f7fbff 42%, #edf5ff 100%);
    }

    h1,
    h2,
    h3,
    .display-font {
      font-family: "Outfit", sans-serif;
    }

    .top-brand,
    .top-brand * {
      font-family: sans-serif;
    }

    .break-anywhere {
      overflow-wrap: anywhere;
      word-break: break-word;
    }
  </style>
</head>
<body class="min-h-screen overflow-x-hidden text-[#052c6a]">
  <header class="top-brand sticky top-0 z-20 bg-gradient-to-r from-[#052c6a] via-[#0d8ddb] to-[#1d4ed8] shadow-md">
    <div class="w-full flex items-center gap-3 px-4 sm:px-6 lg:px-10 py-3">
      <div class="flex items-center justify-center">
        <img
          src="../img/SMCCNEWLOGO.png"
          alt="SMCC Logo"
          class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover bg-white shadow-md border border-white"
        />
      </div>

      <div class="flex-1 min-w-0">
        <p class="text-[10px] leading-4 sm:text-xs text-blue-100 uppercase tracking-[0.12em] sm:tracking-[0.18em]">
          SMCC Admission and Scholarship Office
        </p>
        <div class="mt-1 flex flex-wrap items-center gap-1.5 sm:gap-2">
          <h1 class="text-white text-sm sm:text-base font-semibold leading-tight">
            Institutional Scholarship Grants
          </h1>
          <span class="inline-flex max-w-full items-center gap-1 rounded-full bg-white/10 px-2 py-[2px] text-[10px] sm:text-[11px] text-blue-50">
            Applicant Portal
          </span>
        </div>
        <p class="mt-1 text-[10px] leading-4 sm:text-xs text-blue-100">
          Track an existing application or start a new scholarship application.
        </p>
      </div>
    </div>
  </header>

  <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-[1.5rem] border border-[#cfe2ff] bg-white/90 shadow-[0_28px_60px_-36px_rgba(5,44,106,0.55)] sm:rounded-[2rem]">
      <div class="grid gap-6 px-4 py-5 sm:px-5 sm:py-6 lg:grid-cols-[1.1fr_0.9fr] lg:px-8 lg:py-8">
        <div class="rounded-[1.5rem] bg-gradient-to-br from-[#052c6a] via-[#0d8ddb] to-[#38bdf8] px-5 py-5 text-white sm:rounded-[1.75rem] sm:px-6 sm:py-6">
          <span class="inline-flex w-fit max-w-full rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] sm:text-[11px] sm:tracking-[0.18em]">
            Applicant Access
          </span>
          <h2 class="mt-4 text-2xl font-extrabold sm:text-3xl">
            Choose what you need to do today.
          </h2>
          <p class="mt-3 max-w-xl text-sm leading-6 text-blue-50 sm:text-base">
            Use your application reference number to check the latest status, or continue to the scholarship list and requirements page if you are applying for the first time.
          </p>
          <div class="mt-6 grid gap-3 text-sm sm:grid-cols-3">
            <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
              <p class="font-semibold">Track Status</p>
              <p class="mt-1 text-blue-100">Reference-based lookup for submitted applications.</p>
            </div>
            <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
              <p class="font-semibold">Apply</p>
              <p class="mt-1 text-blue-100">Review grant requirements before completing the form.</p>
            </div>
            <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
              <p class="font-semibold">Reference Format</p>
              <p class="break-anywhere mt-1 text-blue-100">Example: ISG-20260413-7KQ29X</p>
            </div>
          </div>
        </div>

        <div class="grid gap-4">
          <section class="rounded-[1.5rem] border border-[#d9e7ff] bg-[#f8fbff] px-4 py-4 shadow-sm sm:rounded-[1.75rem] sm:px-5 sm:py-5">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-[#0d8ddb]">
              Track Existing Application
            </p>
            <h3 class="mt-2 text-xl font-extrabold text-[#052c6a]">
              Open tracking dashboard
            </h3>
            <p class="mt-2 text-sm leading-6 text-[#052c6a]/80">
              Paste the application reference number that was shown after submission.
            </p>
            <form action="tracking-dashboard.php" method="get" class="mt-4 space-y-3">
              <div>
                <label class="mb-1 block text-sm font-semibold text-[#052c6a]" for="reference">
                  Application Reference Number
                </label>
                <input
                  id="reference"
                  name="reference"
                  type="text"
                  value="<?php echo htmlspecialchars($prefilledReference); ?>"
                  placeholder="ISG-20260413-7KQ29X"
                  class="break-anywhere w-full rounded-2xl border border-[#c7dcff] bg-white px-4 py-3 text-sm uppercase tracking-[0.04em] sm:tracking-[0.08em] text-[#052c6a] outline-none transition focus:border-[#0d8ddb] focus:ring-4 focus:ring-[#0d8ddb]/15"
                  required
                />
              </div>
              <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-full bg-[#0d8ddb] px-5 py-3 text-sm font-semibold text-white shadow-md transition hover:-translate-y-[1px] hover:bg-[#0b63d1]"
              >
                Track Application
              </button>
            </form>
          </section>

          <section class="rounded-[1.5rem] border border-[#e6edf8] bg-white px-4 py-4 shadow-sm sm:rounded-[1.75rem] sm:px-5 sm:py-5">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] sm:tracking-[0.18em] text-[#0d8ddb]">
              New Applicant
            </p>
            <h3 class="mt-2 text-xl font-extrabold text-[#052c6a]">
              Proceed to ISG list and requirements
            </h3>
            <p class="mt-2 text-sm leading-6 text-[#052c6a]/80">
              Review the available scholarship grants and discounts, then continue to the application form.
            </p>
            <a
              href="applicationReq.php"
              class="mt-4 inline-flex w-full items-center justify-center rounded-full border border-[#052c6a] px-5 py-3 text-sm font-semibold text-[#052c6a] transition hover:bg-[#052c6a] hover:text-white"
            >
              Proceed to Application
            </a>
          </section>
        </div>
      </div>
    </section>

    <div class="flex flex-col items-start gap-2 text-left text-sm text-[#052c6a]/70 sm:flex-row sm:items-center sm:justify-between">
      <a href="../index.php" class="inline-flex items-center gap-2 hover:text-[#0d8ddb] hover:underline">
        <span>&larr;</span>
        <span>Back to homepage</span>
      </a>
      <p class="sm:text-right">
        Keep your reference number in a safe place after submitting.
      </p>
    </div>
  </main>
</body>
</html>
