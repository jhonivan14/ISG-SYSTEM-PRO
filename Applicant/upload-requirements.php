<?php
session_start();
if (empty($_SESSION["from_index"])) {
  header("Location: ../index.php");
  exit;
}

$grants = [
  1 => [
    "title" => "Student Assistant",
    "requirements" => [
      "2x2 ID Picture",
      "Application Letter",
      "Resume",
      "Form 138 / Report of Grades",
      "Certificate of Indigency",
      "Certificate of Good Moral Character"
    ]
  ],

  2 => [
    "title" => "Academic Scholarship Program",
    "requirements" => [
      "Certification (Top 1 or Top 2)",
      "2x2 ID Picture",
      "Form 138 / Grades",
      "Certificate of Indigency",
      "Certificate of Good Moral Character"
    ]
  ],

  3 => [
    "title" => "ESG President Scholarship",
    "requirements" => [
      "2x2 ID Picture",
      "Endorsement Letter from OSAS"
    ]
  ],

  4 => [
    "title" => "Kabayani Scholarship Program",
    "requirements" => [
      "2x2 ID Picture",
      "Endorsement Letter"
    ]
  ],

  5 => [
    "title" => "Kabayani Loyalty Grant",
    "requirements" => [
      "2x2 ID Picture",
      "Certification from External Linkages Coordinator",
      "Proof of Relationship",
      "Endorsement Letter from Retiree"
    ]
  ],

  6 => [
    "title" => "PWD Discount",
    "requirements" => [
      "2x2 ID Picture",
      "PWD ID (2 photocopies)"
    ]
  ],

  7 => [
    "title" => "Discount for Children of Employees",
    "requirements" => [
      "2x2 ID Picture",
      "Certification from HRMDO"
    ]
  ],

  8 => [
    "title" => "Discount for Sibling of Employees",
    "requirements" => [
      "2x2 ID Picture",
      "Certification from HRMDO"
    ]
  ],

  9 => [
    "title" => "Sibling Discount",
    "requirements" => [
      "2x2 ID Picture"
    ]
  ],

  10 => [
    "title" => "DXSM-FM Grant",
    "requirements" => [
      "2x2 ID Picture",
      "Endorsement Letter from Station Manager"
    ]
  ],

  11 => [
    "title" => "Michaelinian Mirror Grant",
    "requirements" => [
      "2x2 ID Picture",
      "Endorsement Letter from Publication In-Charge"
    ]
  ],

  12 => [
    "title" => "Lot Donor Grant",
    "requirements" => [
      "2x2 ID Picture",
      "Board Resolution / Certification"
    ]
  ],

  13 => [
    "title" => "BOT Member Dependent Grant",
    "requirements" => [
      "2x2 ID Picture",
      "Board Resolution / Certification",
      "Endorsement Letter"
    ]
  ],

  14 => [
    "title" => "SMCC Alumni Discount",
    "requirements" => [
      "2x2 ID Picture",
      "Certification from Alumni Association"
    ]
  ],
];

// Prefer POST (gikan Step 2), fallback to GET (direct link)
$draft = $_SESSION["application_draft"] ?? null;
$id = $_POST["grant_id"] ?? $_GET["grant"] ?? ($draft["grant_id"] ?? 0);
$selected = $grants[$id] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Upload Requirements • Step 3</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gradient-to-b from-[#e0f2ff] via-white to-[#e0f2ff] min-h-screen font-sans">

  <!-- TOP BAR / BRAND -->
  <header class="bg-gradient-to-r from-[#052c6a] via-[#0d8ddb] to-[#1d4ed8] shadow-md">
    <div class="max-w-5xl mx-auto flex items-center gap-3 px-4 sm:px-6 py-3">
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
            Step 3 of 3
          </span>
        </div>
        <p class="text-[10px] sm:text-xs text-blue-100">
          Upload Documentary Requirements
        </p>
      </div>
    </div>
  </header>

  <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
    <?php if (empty($draft)): ?>
      <div class="mb-4 rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-xs sm:text-sm text-yellow-800">
        Please complete Step 2 first. Your application details are needed before uploading documents.
      </div>
    <?php endif; ?>
    <!-- Progress -->
    <section class="mb-4 sm:mb-6">
      <div class="max-w-md mx-auto mb-3">
        <div class="flex items-center justify-between text-[11px] sm:text-xs font-semibold text-[#052c6a]/80 mb-1">
          <span>Step 1: List & Requirements</span>
          <span>Step 2: Application Form</span>
          <span class="text-[#0d8ddb]">Step 3: Upload</span>
        </div>
        <div class="h-1.5 rounded-full bg-[#dbe6ff] overflow-hidden">
          <div class="h-full w-full bg-gradient-to-r from-[#0d8ddb] via-[#0d8ddb] to-[#fcdc2f]"></div>
        </div>
      </div>

      <!-- Back link + selected -->
      <div class="flex items-center justify-between gap-2">
        <a href="applicationReq.php"
           class="inline-flex items-center gap-1 text-xs sm:text-sm text-[#0d8ddb] hover:text-[#052c6a] hover:underline">
          <span>←</span>
          <span>Back to ISG List</span>
        </a>

        <?php if ($selected): ?>
          <span class="text-[10px] sm:text-xs text-[#052c6a]/70">
            Selected: <span class="font-semibold"><?php echo htmlspecialchars($selected["title"]); ?></span>
          </span>
        <?php endif; ?>
      </div>
    </section>

    <!-- Page title -->
    <section class="mb-4 sm:mb-6">
      <h2 class="text-xl sm:text-2xl font-extrabold text-[#052c6a] tracking-tight">
        Upload Requirements
      </h2>
      <p class="mt-1 text-xs sm:text-sm text-[#052c6a]/80 max-w-2xl">
        Please upload all the required documents for the selected scholarship grant. Make sure files are clear and readable.
      </p>
    </section>

    <!-- Main Card -->
    <section class="bg-white/95 backdrop-blur rounded-2xl sm:rounded-3xl shadow-lg border border-[#cddfff] overflow-hidden">
      <!-- Card header -->
      <div class="bg-gradient-to-r from-[#0d8ddb] to-[#052c6a] px-4 sm:px-6 py-3 sm:py-4">
        <h3 class="text-base sm:text-lg text-white font-bold">
          <?php echo $selected ? htmlspecialchars($selected["title"]) : "Select a Scholarship"; ?>
        </h3>
        <p class="text-[10px] sm:text-xs text-blue-100 tracking-[0.25em] uppercase">
          Step 3 • Upload Requirements
        </p>
      </div>

      <!-- Card body -->
      <div class="px-4 sm:px-6 py-5 sm:py-6">
        <?php if (!$selected): ?>

          <p class="text-center text-red-600 font-semibold text-sm sm:text-base py-6">
            Invalid scholarship selected. Please go back and choose a scholarship again.
          </p>

        <?php else: ?>

          <!-- Reminder / Note -->
          <div class="mb-4 sm:mb-5 rounded-xl bg-[#fef9c3] border border-[#facc15]/70 px-3 sm:px-4 py-2.5">
            <p class="text-[11px] sm:text-xs text-[#78350f]">
              <span class="font-semibold">Reminder:</span>
              Accepted file types: <span class="font-semibold">PDF, JPG, JPEG, PNG</span>.
              Upload one file per requirement.
            </p>
          </div>

          <form id="uploadForm" action="submit_upload.php" method="POST" enctype="multipart/form-data" class="space-y-4 sm:space-y-5">
            <!-- carry grant id to backend -->
            <input type="hidden" name="grant_id" value="<?php echo htmlspecialchars($id); ?>">

            <!-- Show list of requirements (summary) -->
            <div class="space-y-1 mb-2">
              <h4 class="text-sm sm:text-base font-semibold text-[#0d8ddb]">
                Requirements to upload:
              </h4>
              <ul class="list-disc list-inside text-[11px] sm:text-xs text-[#052c6a]/80">
                <?php foreach ($selected["requirements"] as $req): ?>
                  <li><?php echo htmlspecialchars($req); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>

            <hr class="border-dashed border-[#d3defe] my-3">

            <!-- File inputs (one per requirement) with CLEAR button -->
            <?php foreach ($selected["requirements"] as $index => $req):
              $inputId = 'file-' . $index;
            ?>
              <div class="space-y-1">
                <label class="font-semibold text-[#052c6a] text-xs sm:text-sm block">
                  <?php echo htmlspecialchars($req); ?>
                </label>

                <!-- Hidden requirement label so backend knows kung para asa ni nga file -->
                <input type="hidden" name="requirements[]" value="<?php echo htmlspecialchars($req); ?>">

                <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                  <input
                    id="<?php echo $inputId; ?>"
                    type="file"
                    name="files[]"
                    required
                    class="mt-1 block w-full border rounded-xl px-3 py-2 text-xs sm:text-sm border-[#b7c7ff]
                           focus:outline-none focus:ring-2 focus:ring-[#0d8ddb] focus:border-[#0d8ddb]
                           file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0
                           file:text-xs file:sm:text-sm file:bg-[#0d8ddb] file:text-white"
                    accept=".pdf,.jpg,.jpeg,.png"
                  >

                  <button
                    type="button"
                    onclick="clearFileInput('<?php echo $inputId; ?>')"
                    class="inline-flex items-center justify-center px-3 py-1.5 text-[11px] sm:text-xs
                           rounded-full border border-red-400 text-red-600 bg-red-50
                           hover:bg-red-100 hover:border-red-500">
                    Clear File
                  </button>
                </div>
              </div>
            <?php endforeach; ?>

            <!-- Submit -->
            <div class="pt-2">
              <button
                 id="uploadBtn"
                type="submit"
                class="w-full inline-flex items-center justify-center gap-2 bg-[#fcdc2f]
                       text-[#052c6a] font-bold text-sm sm:text-base py-2.5 sm:py-3 rounded-full
                       shadow-md hover:bg-[#ffe45c] active:scale-[0.99] transition">
                Submit Requirements
              </button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <script>
    function clearFileInput(id) {
      const input = document.getElementById(id);
      if (!input) return;
      input.value = "";
    }

   
  const uploadForm = document.getElementById("uploadForm");
  let confirmedSubmit = false;

  if (uploadForm) {
    uploadForm.addEventListener("submit", (event) => {
      if (confirmedSubmit) {
        return;
      }

      event.preventDefault();
      Swal.fire({
        title: "Are you sure?",
        text: "Do you want to submit your application?",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, proceed",
        cancelButtonText: "Cancel",
        confirmButtonColor: "#fcdc2f",
        cancelButtonColor: "#d33"
      }).then((result) => {
        if (result.isConfirmed) {
          confirmedSubmit = true;
          uploadForm.submit();
        }
      });
    });
  }


  </script>
</body>
</html>
