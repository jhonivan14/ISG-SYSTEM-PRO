<?php
session_start();
require_once '../db.php';

$categoryDefinitions = [
  [
    "label" => "Student Assistant",
    "slug" => "student-assistant",
    "keywords" => ["student assistant"],
  ],
  [
    "label" => "Kabayani Scholarship",
    "slug" => "kabayani",
    "keywords" => ["kabayani"],
  ],
  [
    "label" => "Academic Scholar",
    "slug" => "academic",
    "keywords" => ["academic"],
  ],
  [
    "label" => "Others",
    "slug" => "others",
    "keywords" => [],
  ],
];

$grantLabels = [
  1 => "Student Assistant",
  2 => "Academic Scholarship Program",
  3 => "Executive Student Government (ESG) President Scholarship Program",
  4 => "Kabayani Scholarship Program",
  5 => "Kabayani Loyalty Grant",
  6 => "Discount for Persons with Disability (PWD)",
  7 => "Discount for Children of Employees",
  8 => "Discount for Sibling of Employees",
  9 => "Sibling Discount",
  10 => "DXSM-FM Grant",
  11 => "Michaelinian Mirror Grant (Editor-in-Chief)",
  12 => "Grant for the Dependents of a Lot Donor",
  13 => "Grant for the Dependents of a Board of Trustees (BOT) Member",
  14 => "SMCC Alumni Discount",
];

$currentYear = (int)date("Y");
$currentMonth = (int)date("n");
$currentSchoolYearStart = $currentMonth < 6 ? $currentYear - 1 : $currentYear;
$currentSchoolYear = $currentSchoolYearStart . "-" . ($currentSchoolYearStart + 1);
$schoolYearOptions = [];

$schoolYearResult = $conn->query("SELECT DISTINCT school_year FROM applications WHERE school_year IS NOT NULL AND TRIM(school_year) <> ''");
if ($schoolYearResult) {
  while ($row = $schoolYearResult->fetch_assoc()) {
    $value = trim((string)($row["school_year"] ?? ""));
    if ($value !== "") {
      $schoolYearOptions[] = $value;
    }
  }
  $schoolYearResult->free();
}

if (!in_array($currentSchoolYear, $schoolYearOptions, true)) {
  $schoolYearOptions[] = $currentSchoolYear;
}

$schoolYearOptions = array_values(array_unique($schoolYearOptions));
usort($schoolYearOptions, function ($a, $b) {
  $aYear = (int)substr($a, 0, 4);
  $bYear = (int)substr($b, 0, 4);
  if ($aYear === $bYear) {
    return strcmp($a, $b);
  }
  return $aYear <=> $bYear;
});
$semesterOptions = ["1st Semester", "2nd Semester"];

$selectedSchoolYear = isset($_GET["school_year"]) ? trim((string)$_GET["school_year"]) : "";
$selectedSemester = isset($_GET["semester"]) ? trim((string)$_GET["semester"]) : "";
if ($selectedSchoolYear !== "" && !in_array($selectedSchoolYear, $schoolYearOptions, true)) {
  array_unshift($schoolYearOptions, $selectedSchoolYear);
}
if ($selectedSemester !== "" && !in_array($selectedSemester, $semesterOptions, true)) {
  array_unshift($semesterOptions, $selectedSemester);
}

$filterClauses = [];
$filterParams = [];
$filterTypes = "";
if ($selectedSchoolYear !== "") {
  $filterClauses[] = "school_year = ?";
  $filterParams[] = $selectedSchoolYear;
  $filterTypes .= "s";
}
if ($selectedSemester !== "") {
  $filterClauses[] = "semester = ?";
  $filterParams[] = $selectedSemester;
  $filterTypes .= "s";
}

$approvedApplicants = [];
$approvedQuery = "SELECT id, applicant_name, email_address, grant_id, status FROM applications WHERE status = 'Approved'";
if (!empty($filterClauses)) {
  $approvedQuery .= " AND " . implode(" AND ", $filterClauses);
}
$approvedQuery .= " ORDER BY created_at DESC";
if ($stmt = $conn->prepare($approvedQuery)) {
  if (!empty($filterParams)) {
    $stmt->bind_param($filterTypes, ...$filterParams);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $grantId = (int)($row["grant_id"] ?? 0);
      $grantLabel = $grantLabels[$grantId] ?? "Others";
    $status = isset($row["status"]) ? trim((string)$row["status"]) : "Approved";
    if ($status === "") {
      $status = "Approved";
    }

    $approvedApplicants[] = [
      "id" => (int)($row["id"] ?? 0),
      "name" => $row["applicant_name"] ?? "",
      "email" => $row["email_address"] ?? "",
      "grant_id" => $grantId,
      "grant" => $grantLabel,
      "status" => $status,
    ];
  }
    $result->free();
  }
$stmt->close();
}

$panelists = [];
$panelistError = "";
$panelistResult = $conn->query("SELECT username, full_name, status FROM panelists ORDER BY full_name ASC");
if ($panelistResult) {
  while ($row = $panelistResult->fetch_assoc()) {
    $status = strtolower(trim((string)($row["status"] ?? "active")));
    if ($status !== "inactive") {
      $panelists[] = [
        "username" => $row["username"] ?? "",
        "full_name" => $row["full_name"] ?? "",
      ];
    }
  }
  $panelistResult->free();
} else {
  $panelistError = "Panelist accounts table is not available.";
}

$categories = [];
foreach ($categoryDefinitions as $definition) {
  $categories[$definition["slug"]] = [
    "label" => $definition["label"],
    "slug" => $definition["slug"],
    "count" => 0,
  ];
}

foreach ($approvedApplicants as &$applicant) {
  $grant = strtolower($applicant["grant"]);
  $matchedSlug = "others";

  foreach ($categoryDefinitions as $definition) {
    foreach ($definition["keywords"] as $keyword) {
      if (stripos($grant, strtolower($keyword)) !== false) {
        $matchedSlug = $definition["slug"];
        break 2;
      }
    }
  }

  $applicant["category_slug"] = $matchedSlug;
  if (isset($categories[$matchedSlug])) {
    $categories[$matchedSlug]["count"]++;
  }
}
unset($applicant);
?>
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
      .filter-button.active {
        background-color: #0d8ddb;
        color: #ffffff;
        border-color: #0d8ddb;
        box-shadow: 0 10px 20px rgba(13, 141, 219, 0.18);
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

        <div class="mt-12"></div>

        <!-- Academic Year / Semester Filters -->
        <section class="px-4 sm:px-6 mt-4">
          <?php if (isset($_GET["message_status"])): ?>
            <?php
              $status = $_GET["message_status"];
              $isSuccess = $status === "sent";
              $isError = $status === "error";
              $errorMessage = $_SESSION["message_error"] ?? "";
              unset($_SESSION["message_error"]);
            ?>
            <?php if ($isSuccess || $isError): ?>
              <div class="mb-3 rounded-lg border px-4 py-3 text-xs font-semibold <?php echo $isSuccess ? "border-green-400 bg-green-50 text-green-700" : "border-red-400 bg-red-50 text-red-700"; ?>">
                <?php
                  if ($isSuccess) {
                    echo "Message sent successfully.";
                  } else {
                    $fallback = "Failed to send message. Please try again.";
                    echo $errorMessage !== "" ? htmlspecialchars($errorMessage) : $fallback;
                  }
                ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (isset($_GET["panelist_status"])): ?>
            <?php
              $status = $_GET["panelist_status"];
              $isSuccess = $status === "sent";
              $isError = $status === "error";
              $errorMessage = $_SESSION["panelist_error"] ?? "";
              $sentCount = (int)($_SESSION["panelist_sent_count"] ?? 0);
              unset($_SESSION["panelist_sent_count"]);
              unset($_SESSION["panelist_error"]);
            ?>
            <?php if ($isSuccess || $isError): ?>
              <div class="mb-3 rounded-lg border px-4 py-3 text-xs font-semibold <?php echo $isSuccess ? "border-green-400 bg-green-50 text-green-700" : "border-red-400 bg-red-50 text-red-700"; ?>">
                <?php
                  if ($isSuccess) {
                    echo $sentCount > 0
                      ? "Sent to panelist successfully (" . htmlspecialchars((string)$sentCount) . ")."
                      : "Sent to panelist successfully.";
                  } else {
                    $fallback = "Failed to send to panelist. Please try again.";
                    echo $errorMessage !== "" ? htmlspecialchars($errorMessage) : $fallback;
                  }
                ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
          <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[#0d8ddb] bg-white p-3 shadow-sm">
            <span class="text-sm font-semibold text-[#052c6a]">Approved Applicants</span>
            <form class="flex flex-wrap items-center gap-2" method="get" action="approved.php">
              <div class="flex items-center gap-2 rounded-full border border-[#0d8ddb] bg-white px-3 py-2 shadow-sm">
                <i class="fas fa-search text-[#7c8191] text-xs"></i>
                <input
                  id="approvedSearch"
                  type="text"
                  class="w-40 bg-transparent text-xs font-semibold text-[#052c6a] outline-none placeholder:text-[#7c8191]"
                  placeholder="Search approved..."
                  aria-label="Search approved applicants"
                />
              </div>
              <select
                class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                name="school_year"
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
                class="rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm focus:outline-none"
                name="semester"
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
              <?php if ($selectedSchoolYear !== "" || $selectedSemester !== ""): ?>
                <a
                  href="approved.php"
                  class="inline-flex items-center rounded-full border border-[#0d8ddb] bg-white px-3 py-2 text-xs font-semibold text-[#052c6a] shadow-sm"
                >
                  Clear
                </a>
              <?php endif; ?>
            </form>
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($categories as $category): ?>
              <button
                type="button"
                class="filter-button rounded-full border border-[#0d8ddb] px-3 py-2 text-xs font-semibold text-[#0d8ddb] transition"
                data-filter-category="<?= htmlspecialchars($category["slug"]) ?>"
                data-filter-label="<?= htmlspecialchars($category["label"]) ?>"
              >
                <?= htmlspecialchars($category["label"]) ?>
                <span class="ml-1 rounded-full bg-[#e5f1ff] px-2 py-0.5 text-[10px] font-bold text-[#052c6a]">
                  <?= htmlspecialchars($category["count"]) ?>
                </span>
              </button>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- Approved table -->
        <section class="px-4 sm:px-6 pb-10 mt-4">
          <div class="border border-[#0d8ddb] rounded-lg shadow-sm overflow-hidden">
            <div class="bg-[#0d8ddb] bg-opacity-5 px-4 py-3">
              <p id="approvedTableTitle" class="text-xs sm:text-sm font-semibold text-[#052c6a]">
                All Approved Applicants
              </p>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full border-t border-[#0d8ddb] text-xs text-center">
                <thead>
                  <tr class="bg-white border-b border-[#0d8ddb]">
                    <th class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]">Applicant Name</th>
                    <th class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]">ISG Grant</th>
                    <th class="border-r border-[#0d8ddb] py-2 px-2 font-semibold text-[#fcdc2f]">Status</th>
                    <th class="py-2 px-2 font-semibold text-[#fcdc2f]">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($approvedApplicants)): ?>
                    <tr>
                      <td colspan="4" class="py-3 text-center text-[#052c6a]">No approved applicants.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($approvedApplicants as $applicant): ?>
                      <?php
                        $searchText = strtolower($applicant["name"] . " " . $applicant["grant"] . " " . $applicant["status"]);
                      ?>
                      <tr
                        class="border-b border-[#0d8ddb]"
                        data-approved-row
                        data-search-text="<?= htmlspecialchars($searchText) ?>"
                        data-category="<?= htmlspecialchars($applicant["category_slug"]) ?>"
                      >
                        <td class="border-r border-[#0d8ddb] py-2 text-left px-2 text-[#052c6a]">
                          <?= htmlspecialchars($applicant["name"]) ?>
                        </td>
                        <td class="border-r border-[#0d8ddb] py-2 text-left px-2 text-[#052c6a]">
                          <?= htmlspecialchars($applicant["grant"]) ?>
                        </td>
                        <td class="border-r border-[#0d8ddb] py-2">
                          <span class="bg-green-500 text-white rounded px-2 py-0.5 inline-block">
                            <?= htmlspecialchars($applicant["status"]) ?>
                          </span>
                        </td>
                        <td class="py-2">
                          <div class="flex items-center justify-center gap-2">
                            <button
                              class="bg-[#0d8ddb] text-white rounded px-3 py-1 text-xs"
                              type="button"
                              onclick="window.location.href='view-application.php?id=<?= htmlspecialchars((string)$applicant['id']) ?>'"
                            >
                              View Details
                            </button>
                            <button
                              class="bg-[#052c6a] text-white rounded px-3 py-1 text-xs hover:bg-[#031f4d]"
                              type="button"
                              data-send-message
                              data-applicant-name="<?= htmlspecialchars($applicant["name"]) ?>"
                              data-applicant-id="<?= htmlspecialchars((string)$applicant["id"]) ?>"
                              data-applicant-email="<?= htmlspecialchars($applicant["email"]) ?>"
                            >
                              Send Message
                            </button>
                            <?php if ((int)$applicant["grant_id"] === 1): ?>
                              <button
                                class="border border-[#0d8ddb] text-[#0d8ddb] rounded px-3 py-1 text-xs hover:bg-[#0d8ddb] hover:text-white"
                                type="button"
                                data-send-panelist
                                data-applicant-name="<?= htmlspecialchars($applicant["name"]) ?>"
                                data-applicant-id="<?= htmlspecialchars((string)$applicant["id"]) ?>"
                              >
                                Send to Panelist
                              </button>
                            <?php endif; ?>
                            <button class="border border-[#f44336] text-[#f44336] rounded px-3 py-1 text-xs hover:bg-[#f44336] hover:text-white" type="button">
                              Remove
                            </button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <tr data-approved-empty class="hidden">
                      <td colspan="4" class="py-3 text-center text-[#052c6a]">No matching approved applicants.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Send Message Modal -->
        <div
          id="sendMessageModal"
          class="fixed inset-0 z-40 hidden items-center justify-center bg-black/40 px-4"
          aria-hidden="true"
        >
          <div class="w-full max-w-md rounded-lg bg-white shadow-lg">
            <div class="flex items-center justify-between border-b border-[#0d8ddb] px-4 py-3">
              <h2 class="text-sm font-semibold text-[#052c6a]">Send Message</h2>
              <button id="sendMessageClose" class="text-[#052c6a] hover:text-[#0d8ddb]" type="button">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <form action="send_message.php" method="post" class="px-4 py-3 space-y-3">
              <input type="hidden" name="applicant_id" id="recipientId" value="" />
              <div>
                <label class="block text-xs font-semibold text-[#052c6a] mb-1">Recipient</label>
                <input
                  type="text"
                  id="recipientName"
                  class="w-full rounded border border-[#0d8ddb] px-3 py-2 text-xs text-[#052c6a] bg-gray-50"
                  readonly
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#052c6a] mb-1">Recipient Email</label>
                <input
                  type="text"
                  id="recipientEmail"
                  name="recipient_email"
                  class="w-full rounded border border-[#0d8ddb] px-3 py-2 text-xs text-[#052c6a] bg-gray-50"
                  readonly
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-[#052c6a] mb-1" for="messageBody">Message</label>
                <textarea
                  id="messageBody"
                  name="message_body"
                  rows="5"
                  required
                  class="w-full rounded border border-[#0d8ddb] px-3 py-2 text-xs text-[#052c6a] focus:outline-none"
                  placeholder="Type your message here..."
                ></textarea>
              </div>
              <div class="flex justify-end gap-2">
                <button
                  type="button"
                  id="sendMessageCancel"
                  class="rounded border border-[#0d8ddb] px-3 py-2 text-xs font-semibold text-[#052c6a]"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  class="rounded bg-[#0d8ddb] px-3 py-2 text-xs font-semibold text-white hover:bg-[#0b7cc0]"
                >
                  Send
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Send to Panelist Modal -->
        <div
          id="sendPanelistModal"
          class="fixed inset-0 z-40 hidden items-center justify-center bg-black/40 px-4"
          aria-hidden="true"
        >
          <div class="w-full max-w-md rounded-lg bg-white shadow-lg">
            <div class="flex items-center justify-between border-b border-[#0d8ddb] px-4 py-3">
              <h2 class="text-sm font-semibold text-[#052c6a]">Send to Panelist</h2>
              <button id="sendPanelistClose" class="text-[#052c6a] hover:text-[#0d8ddb]" type="button">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <form action="send_to_panelist.php" method="post" class="px-4 py-3 space-y-3">
              <input type="hidden" name="applicant_id" id="panelistApplicantId" value="" />
              <div>
                <label class="block text-xs font-semibold text-[#052c6a] mb-1">Applicant</label>
                <input
                  type="text"
                  id="panelistApplicantName"
                  class="w-full rounded border border-[#0d8ddb] px-3 py-2 text-xs text-[#052c6a] bg-gray-50"
                  readonly
                />
              </div>
              <div>
                <div class="flex items-center justify-between">
                  <label class="block text-xs font-semibold text-[#052c6a] mb-1">Panelists</label>
                  <div class="flex items-center gap-2 text-[10px] text-[#0d8ddb]">
                    <button type="button" id="panelistSelectAll" class="underline">Select all</button>
                    <button type="button" id="panelistClearAll" class="underline">Clear</button>
                  </div>
                </div>
                <?php if ($panelistError !== ""): ?>
                  <div class="rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    <?= htmlspecialchars($panelistError) ?>
                  </div>
                <?php elseif (empty($panelists)): ?>
                  <div class="rounded border border-yellow-200 bg-yellow-50 px-3 py-2 text-xs text-yellow-800">
                    No active panelist accounts found.
                  </div>
                <?php else: ?>
                  <div class="max-h-40 space-y-2 overflow-y-auto rounded border border-[#0d8ddb] px-3 py-2 text-xs text-[#052c6a]">
                    <?php foreach ($panelists as $panelist): ?>
                      <label class="flex items-center gap-2">
                        <input
                          type="checkbox"
                          class="panelist-checkbox"
                          name="panelist_usernames[]"
                          value="<?= htmlspecialchars($panelist["username"]) ?>"
                        />
                        <span>
                          <?= htmlspecialchars($panelist["full_name"] !== "" ? $panelist["full_name"] : $panelist["username"]) ?>
                        </span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <p id="panelistSelectError" class="mt-2 text-[10px] text-red-600 hidden">
                  Please select at least one panelist.
                </p>
              </div>
              <div class="flex justify-end gap-2">
                <button
                  type="button"
                  id="sendPanelistCancel"
                  class="rounded border border-[#0d8ddb] px-3 py-2 text-xs font-semibold text-[#052c6a]"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  id="sendPanelistSubmit"
                  class="rounded bg-[#0d8ddb] px-3 py-2 text-xs font-semibold text-white hover:bg-[#0b7cc0]"
                  <?php echo (!empty($panelistError) || empty($panelists)) ? "disabled" : ""; ?>
                >
                  Send
                </button>
              </div>
            </form>
          </div>
        </div>
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

      // Search + category filter for approved applicants
      document.addEventListener("DOMContentLoaded", () => {
        const searchInput = document.getElementById("approvedSearch");
        const filterButtons = document.querySelectorAll("[data-filter-category]");
        const rows = document.querySelectorAll("[data-approved-row]");
        const emptyRow = document.querySelector("[data-approved-empty]");
        let activeCategory = "";

        const approvedTitle = document.getElementById("approvedTableTitle");

        const applyFilters = () => {
          const query = (searchInput?.value || "").trim().toLowerCase();
          let visible = 0;

          rows.forEach((row) => {
            const text = row.dataset.searchText || "";
            const category = row.dataset.category || "";
            const matchesCategory = activeCategory === "" || category === activeCategory;
            const matchesSearch = query === "" || text.includes(query);
            const matches = matchesCategory && matchesSearch;
            row.style.display = matches ? "table-row" : "none";
            if (matches) visible++;
          });

          if (emptyRow) {
            emptyRow.style.display = visible === 0 ? "table-row" : "none";
          }

          filterButtons.forEach((button) => {
            const isActive = button.dataset.filterCategory === activeCategory;
            button.classList.toggle("active", isActive);
          });

          if (approvedTitle) {
            if (activeCategory === "") {
              approvedTitle.textContent = "All Approved Applicants";
            } else {
              const activeButton = document.querySelector(
                `[data-filter-category="${activeCategory}"]`
              );
              const label = activeButton?.dataset.filterLabel || "Filtered Approved Applicants";
              approvedTitle.textContent = label.toUpperCase();
            }
          }
        };

        filterButtons.forEach((button) => {
          button.addEventListener("click", () => {
            const slug = button.dataset.filterCategory || "";
            activeCategory = activeCategory === slug ? "" : slug;
            applyFilters();
          });
        });

        if (searchInput) {
          searchInput.addEventListener("input", applyFilters);
        }

        applyFilters();
      });

      // Send message modal
      document.addEventListener("DOMContentLoaded", () => {
        const modal = document.getElementById("sendMessageModal");
        const closeBtn = document.getElementById("sendMessageClose");
        const cancelBtn = document.getElementById("sendMessageCancel");
        const recipientName = document.getElementById("recipientName");
        const recipientEmail = document.getElementById("recipientEmail");
        const recipientId = document.getElementById("recipientId");
        const messageBody = document.getElementById("messageBody");

        const closeModal = () => {
          if (modal) {
            modal.classList.add("hidden");
            modal.classList.remove("flex");
          }
          if (messageBody) {
            messageBody.value = "";
          }
        };

        document.querySelectorAll("[data-send-message]").forEach((button) => {
          button.addEventListener("click", () => {
            const name = button.getAttribute("data-applicant-name") || "";
            const id = button.getAttribute("data-applicant-id") || "";
            const email = button.getAttribute("data-applicant-email") || "";

            if (recipientName) recipientName.value = name;
            if (recipientEmail) recipientEmail.value = email;
            if (recipientId) recipientId.value = id;
            if (modal) {
              modal.classList.remove("hidden");
              modal.classList.add("flex");
            }
          });
        });

        [closeBtn, cancelBtn].forEach((btn) => {
          if (btn) {
            btn.addEventListener("click", closeModal);
          }
        });

        if (modal) {
          modal.addEventListener("click", (event) => {
            if (event.target === modal) {
              closeModal();
            }
          });
        }
      });

      // Send to panelist modal
      document.addEventListener("DOMContentLoaded", () => {
        const modal = document.getElementById("sendPanelistModal");
        const closeBtn = document.getElementById("sendPanelistClose");
        const cancelBtn = document.getElementById("sendPanelistCancel");
        const applicantName = document.getElementById("panelistApplicantName");
        const applicantId = document.getElementById("panelistApplicantId");
        const selectAllBtn = document.getElementById("panelistSelectAll");
        const clearAllBtn = document.getElementById("panelistClearAll");
        const checkboxes = () => Array.from(document.querySelectorAll(".panelist-checkbox"));
        const errorEl = document.getElementById("panelistSelectError");
        const form = modal ? modal.querySelector("form") : null;

        const closeModal = () => {
          if (modal) {
            modal.classList.add("hidden");
            modal.classList.remove("flex");
          }
        };

        document.querySelectorAll("[data-send-panelist]").forEach((button) => {
          button.addEventListener("click", () => {
            const name = button.getAttribute("data-applicant-name") || "";
            const id = button.getAttribute("data-applicant-id") || "";
            if (applicantName) applicantName.value = name;
            if (applicantId) applicantId.value = id;
            if (errorEl) {
              errorEl.classList.add("hidden");
            }
            if (modal) {
              modal.classList.remove("hidden");
              modal.classList.add("flex");
            }
          });
        });

        if (selectAllBtn) {
          selectAllBtn.addEventListener("click", () => {
            checkboxes().forEach((box) => {
              box.checked = true;
            });
            if (errorEl) errorEl.classList.add("hidden");
          });
        }

        if (clearAllBtn) {
          clearAllBtn.addEventListener("click", () => {
            checkboxes().forEach((box) => {
              box.checked = false;
            });
            if (errorEl) errorEl.classList.add("hidden");
          });
        }

        if (form) {
          form.addEventListener("submit", (event) => {
            const hasSelection = checkboxes().some((box) => box.checked);
            if (!hasSelection) {
              event.preventDefault();
              if (errorEl) errorEl.classList.remove("hidden");
            }
          });
        }

        [closeBtn, cancelBtn].forEach((btn) => {
          if (btn) {
            btn.addEventListener("click", closeModal);
          }
        });

        if (modal) {
          modal.addEventListener("click", (event) => {
            if (event.target === modal) {
              closeModal();
            }
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

