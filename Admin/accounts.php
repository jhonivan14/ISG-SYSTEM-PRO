<?php

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
require_once "../db.php";
require_once __DIR__ . "/includes/school-term-filter.php";
require_once __DIR__ . "/includes/applicant-sidebar-badge.php";
require_once "../scholarship-program-options.php";

$resetMessage = "";
$resetError = "";
$accountMessage = "";
$accountError = "";
$schoolTermSettingsMessage = "";
$schoolTermSettingsError = "";
$programSettingsMessage = "";
$programSettingsError = "";
$panelistAccounts = [];
$headOfficeAccounts = [];
$programOptionGroups = [
  "senior_high" => [],
  "college" => [],
  "student_assistant" => [],
];
$panelistError = "";
$headOfficeError = "";
$panelistFormError = "";
$headOfficeFormError = "";
$activeModal = "";
$panelistFormData = [
  "username" => "",
  "full_name" => "",
  "status" => "active",
];
$headOfficeFormData = [
  "username" => "",
  "name" => "",
  "lastname" => "",
  "office" => "",
  "status" => "active",
];

// Handle password resets, account updates, and account creation requests before loading the account tables.

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (isset($_POST["reset_account_password"])) {
    $accountType = trim((string)($_POST["account_type"] ?? ""));
    $resetUsername = trim((string)($_POST["reset_username"] ?? ""));
    $resetPassword = (string)($_POST["reset_password"] ?? "");

    $tableMap = [
      "panelist" => "panelists",
      "head_office" => "head_offices",
    ];

    if (!isset($tableMap[$accountType])) {
      $resetError = "Invalid account type.";
    } elseif ($resetUsername === "" || $resetPassword === "") {
      $resetError = "Please complete all fields.";
    } else {
      $tableName = $tableMap[$accountType];
      $checkStmt = $conn->prepare("SELECT id FROM {$tableName} WHERE username = ? LIMIT 1");
      if ($checkStmt) {
        $checkStmt->bind_param("s", $resetUsername);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows === 0) {
          $resetError = "Username not found.";
        } else {
          $passwordHash = password_hash($resetPassword, PASSWORD_DEFAULT);
          if ($passwordHash === false) {
            $resetError = "Unable to reset password. Please try again.";
          } else {
            $updateStmt = $conn->prepare("UPDATE {$tableName} SET password_hash = ? WHERE username = ? LIMIT 1");
            if ($updateStmt) {
              $updateStmt->bind_param("ss", $passwordHash, $resetUsername);
              if ($updateStmt->execute()) {
                $resetMessage = "Password reset successful.";
              } else {
                $resetError = "Unable to update the password.";
              }
              $updateStmt->close();
            } else {
              $resetError = "Unable to update the password.";
            }
          }
        }
        $checkStmt->close();
      } else {
        $resetError = "Unable to reset the password.";
      }
    }
  } elseif (isset($_POST["add_school_year"])) {
    $newSchoolYear = trim((string)($_POST["new_school_year"] ?? ""));
    $updatedBy = trim((string)($_SESSION["admin_username"] ?? $_SESSION["admin_name"] ?? "Admin"));
    $openResult = function_exists("schoolTermOpenSchoolYear")
      ? schoolTermOpenSchoolYear($conn, $newSchoolYear, $updatedBy)
      : "error";

    if ($openResult === "opened") {
      $normalizedNewSchoolYear = schoolTermNormalizeSchoolYear($newSchoolYear);
      $schoolTermSettingsMessage = "School year " . $normalizedNewSchoolYear . " added.";
      if ($normalizedNewSchoolYear !== "" && !in_array($normalizedNewSchoolYear, $schoolYearOptions, true)) {
        $schoolYearOptions[] = $normalizedNewSchoolYear;
        usort($schoolYearOptions, function ($a, $b) {
          $aYear = (int)substr((string)$a, 0, 4);
          $bYear = (int)substr((string)$b, 0, 4);
          if ($aYear === $bYear) {
            return strcmp((string)$a, (string)$b);
          }
          return $aYear <=> $bYear;
        });
      }
    } elseif ($openResult === "exists") {
      $schoolTermSettingsMessage = "School year already exists in the list.";
    } elseif ($openResult === "invalid") {
      $schoolTermSettingsError = "Use a valid school year format like 2026-2027.";
    } else {
      $schoolTermSettingsError = "Unable to add the school year right now.";
    }
  } elseif (isset($_POST["save_school_term_settings"])) {
    $termSchoolYear = trim((string)($_POST["active_school_year"] ?? ""));
    $termSemester = trim((string)($_POST["active_semester"] ?? ""));
    $updatedBy = trim((string)($_SESSION["admin_username"] ?? $_SESSION["admin_name"] ?? "Admin"));
    $saveResult = function_exists("schoolTermSaveSettings")
      ? schoolTermSaveSettings($conn, $termSchoolYear, $termSemester, $updatedBy)
      : "error";

    if ($saveResult === "saved") {
      $normalizedTermSchoolYear = schoolTermNormalizeSchoolYear($termSchoolYear);
      $schoolTermSettingsMessage = "Active school term updated.";
      $currentSchoolYear = $normalizedTermSchoolYear;
      $currentSemester = $termSemester;
      $displaySchoolYear = $normalizedTermSchoolYear;
      $displaySemester = $termSemester;
      $activeSchoolYearFilter = $normalizedTermSchoolYear;
      $activeSemesterFilter = "";
      $configuredSchoolTerm = [
        "active_school_year" => $normalizedTermSchoolYear,
        "active_semester" => $termSemester,
      ];
      if ($normalizedTermSchoolYear !== "" && !in_array($normalizedTermSchoolYear, $schoolYearOptions, true)) {
        $schoolYearOptions[] = $normalizedTermSchoolYear;
        usort($schoolYearOptions, function ($a, $b) {
          $aYear = (int)substr((string)$a, 0, 4);
          $bYear = (int)substr((string)$b, 0, 4);
          if ($aYear === $bYear) {
            return strcmp((string)$a, (string)$b);
          }
          return $aYear <=> $bYear;
        });
      }
    } elseif ($saveResult === "invalid_school_year") {
      $schoolTermSettingsError = "Use a valid school year format like 2026-2027.";
    } elseif ($saveResult === "invalid_semester") {
      $schoolTermSettingsError = "Select a valid semester.";
    } else {
      $schoolTermSettingsError = "Unable to update the active school term right now.";
    }
  } elseif (isset($_POST["add_program_option"])) {
    $programCategory = trim((string)($_POST["program_category"] ?? ""));
    $programName = trim((string)($_POST["program_name"] ?? ""));

    if (isg_add_program_option($conn, $programCategory, $programName, $programSettingsError)) {
      $categoryLabels = isg_program_option_categories();
      $programSettingsMessage = ($categoryLabels[$programCategory] ?? "Program option") . " added.";
    }
  } elseif (isset($_POST["remove_program_option"])) {
    $programOptionId = (int)($_POST["program_option_id"] ?? 0);

    if (isg_deactivate_program_option($conn, $programOptionId, $programSettingsError)) {
      $programSettingsMessage = "Program option removed.";
    }
  } elseif (isset($_POST["update_program_option"])) {
    $programOptionId = (int)($_POST["program_option_id"] ?? 0);
    $programName = trim((string)($_POST["program_name"] ?? ""));

    if (isg_update_program_option($conn, $programOptionId, $programName, $programSettingsError)) {
      $programSettingsMessage = "Program option updated.";
    }
  } elseif (isset($_POST["update_account"])) {
    $updateAccountType = trim((string)($_POST["update_account_type"] ?? ""));
    $originalUsername = trim((string)($_POST["original_username"] ?? ""));
    $newUsername = trim((string)($_POST["username"] ?? ""));
    $status = strtolower(trim((string)($_POST["status"] ?? "active")));
    $status = in_array($status, ["active", "inactive"], true) ? $status : "active";

    if ($updateAccountType === "panelist") {
      $fullName = trim((string)($_POST["full_name"] ?? ""));

      if ($originalUsername === "" || $newUsername === "" || $fullName === "") {
        $accountError = "Please complete all panelist fields.";
      } else {
        $oldFullName = "";
        $oldFullNameCount = 0;
        $checkStmt = $conn->prepare("SELECT full_name FROM panelists WHERE username = ? LIMIT 1");
        if ($checkStmt) {
          $checkStmt->bind_param("s", $originalUsername);
          $checkStmt->execute();
          $checkResult = $checkStmt->get_result();
          $checkRow = $checkResult ? $checkResult->fetch_assoc() : null;
          if ($checkRow) {
            $oldFullName = trim((string)($checkRow["full_name"] ?? ""));
          } else {
            $accountError = "Panelist account not found.";
          }
          $checkStmt->close();
        } else {
          $accountError = "Unable to update the panelist account.";
        }

        if ($accountError === "" && $oldFullName !== "") {
          $fullNameCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM panelists WHERE full_name = ?");
          if ($fullNameCountStmt) {
            $fullNameCountStmt->bind_param("s", $oldFullName);
            $fullNameCountStmt->execute();
            $fullNameCountResult = $fullNameCountStmt->get_result();
            $fullNameCountRow = $fullNameCountResult ? $fullNameCountResult->fetch_assoc() : null;
            $oldFullNameCount = (int)($fullNameCountRow["total"] ?? 0);
            $fullNameCountStmt->close();
          }
        }

        if ($accountError === "" && strcasecmp($newUsername, $originalUsername) !== 0) {
          $duplicateStmt = $conn->prepare("SELECT id FROM panelists WHERE username = ? LIMIT 1");
          if ($duplicateStmt) {
            $duplicateStmt->bind_param("s", $newUsername);
            $duplicateStmt->execute();
            $duplicateStmt->store_result();
            if ($duplicateStmt->num_rows > 0) {
              $accountError = "Panelist username already exists.";
            }
            $duplicateStmt->close();
          } else {
            $accountError = "Unable to update the panelist account.";
          }
        }

        if ($accountError === "") {
          $updateStmt = $conn->prepare("UPDATE panelists SET username = ?, full_name = ?, status = ? WHERE username = ? LIMIT 1");
          if ($updateStmt) {
            $updateStmt->bind_param("ssss", $newUsername, $fullName, $status, $originalUsername);
            if ($updateStmt->execute()) {
              if ($newUsername !== $originalUsername) {
                $queueTableResult = $conn->query("SHOW TABLES LIKE 'panelist_queue'");
                if ($queueTableResult instanceof mysqli_result && $queueTableResult->num_rows > 0) {
                  $queueUpdateStmt = $conn->prepare("UPDATE panelist_queue SET panelist_username = ? WHERE panelist_username = ?");
                  if ($queueUpdateStmt) {
                    $queueUpdateStmt->bind_param("ss", $newUsername, $originalUsername);
                    $queueUpdateStmt->execute();
                    $queueUpdateStmt->close();
                  }
                }
                if ($queueTableResult instanceof mysqli_result) {
                  $queueTableResult->free();
                }
              }

              if ($oldFullName !== "" && $oldFullName !== $fullName && $oldFullNameCount === 1) {
                $evaluationTableResult = $conn->query("SHOW TABLES LIKE 'interview_evaluations'");
                if ($evaluationTableResult instanceof mysqli_result && $evaluationTableResult->num_rows > 0) {
                  $evaluationUpdateStmt = $conn->prepare("UPDATE interview_evaluations SET interviewer_name = ? WHERE interviewer_name = ?");
                  if ($evaluationUpdateStmt) {
                    $evaluationUpdateStmt->bind_param("ss", $fullName, $oldFullName);
                    $evaluationUpdateStmt->execute();
                    $evaluationUpdateStmt->close();
                  }
                }
                if ($evaluationTableResult instanceof mysqli_result) {
                  $evaluationTableResult->free();
                }
              }

              $accountMessage = "Panelist account updated.";
            } else {
              $accountError = "Unable to update the panelist account.";
            }
            $updateStmt->close();
          } else {
            $accountError = "Unable to update the panelist account.";
          }
        }
      }
    } elseif ($updateAccountType === "head_office") {
      $name = trim((string)($_POST["name"] ?? ""));
      $lastname = trim((string)($_POST["lastname"] ?? ""));
      $office = trim((string)($_POST["office"] ?? ""));

      if ($originalUsername === "" || $newUsername === "" || $name === "" || $lastname === "" || $office === "") {
        $accountError = "Please complete all head of office fields.";
      } else {
        $checkStmt = $conn->prepare("SELECT id FROM head_offices WHERE username = ? LIMIT 1");
        if ($checkStmt) {
          $checkStmt->bind_param("s", $originalUsername);
          $checkStmt->execute();
          $checkStmt->store_result();
          if ($checkStmt->num_rows === 0) {
            $accountError = "Head of office account not found.";
          }
          $checkStmt->close();
        } else {
          $accountError = "Unable to update the head of office account.";
        }

        if ($accountError === "" && strcasecmp($newUsername, $originalUsername) !== 0) {
          $duplicateStmt = $conn->prepare("SELECT id FROM head_offices WHERE username = ? LIMIT 1");
          if ($duplicateStmt) {
            $duplicateStmt->bind_param("s", $newUsername);
            $duplicateStmt->execute();
            $duplicateStmt->store_result();
            if ($duplicateStmt->num_rows > 0) {
              $accountError = "Head of office username already exists.";
            }
            $duplicateStmt->close();
          } else {
            $accountError = "Unable to update the head of office account.";
          }
        }

        if ($accountError === "") {
          $updateStmt = $conn->prepare("UPDATE head_offices SET username = ?, name = ?, lastname = ?, office = ?, status = ? WHERE username = ? LIMIT 1");
          if ($updateStmt) {
            $updateStmt->bind_param("ssssss", $newUsername, $name, $lastname, $office, $status, $originalUsername);
            if ($updateStmt->execute()) {
              if ($newUsername !== $originalUsername) {
                $evaluationTableResult = $conn->query("SHOW TABLES LIKE 'department_head_evaluations'");
                if ($evaluationTableResult instanceof mysqli_result && $evaluationTableResult->num_rows > 0) {
                  $evaluationUpdateStmt = $conn->prepare("UPDATE department_head_evaluations SET head_username = ? WHERE head_username = ?");
                  if ($evaluationUpdateStmt) {
                    $evaluationUpdateStmt->bind_param("ss", $newUsername, $originalUsername);
                    $evaluationUpdateStmt->execute();
                    $evaluationUpdateStmt->close();
                  }
                }
                if ($evaluationTableResult instanceof mysqli_result) {
                  $evaluationTableResult->free();
                }
              }

              $accountMessage = "Head of office account updated.";
            } else {
              $accountError = "Unable to update the head of office account.";
            }
            $updateStmt->close();
          } else {
            $accountError = "Unable to update the head of office account.";
          }
        }
      }
    } else {
      $accountError = "Invalid account type.";
    }
  } elseif (isset($_POST["create_account"])) {
    $createAccountType = trim((string)($_POST["create_account_type"] ?? ""));
    $status = strtolower(trim((string)($_POST["status"] ?? "active")));
    $status = in_array($status, ["active", "inactive"], true) ? $status : "active";

    if ($createAccountType === "panelist") {
      $activeModal = "panelistModal";
      $panelistFormData = [
        "username" => trim((string)($_POST["username"] ?? "")),
        "full_name" => trim((string)($_POST["full_name"] ?? "")),
        "status" => $status,
      ];
      $password = (string)($_POST["password"] ?? "");

      if ($panelistFormData["username"] === "" || $panelistFormData["full_name"] === "" || $password === "") {
        $panelistFormError = "Please complete all fields.";
      } else {
        $checkStmt = $conn->prepare("SELECT id FROM panelists WHERE username = ? LIMIT 1");
        if ($checkStmt) {
          $checkStmt->bind_param("s", $panelistFormData["username"]);
          $checkStmt->execute();
          $checkStmt->store_result();
          if ($checkStmt->num_rows > 0) {
            $panelistFormError = "Username already exists.";
          }
          $checkStmt->close();
        } else {
          $panelistFormError = "Unable to save the account.";
        }

        if ($panelistFormError === "") {
          $passwordHash = password_hash($password, PASSWORD_DEFAULT);
          if ($passwordHash === false) {
            $panelistFormError = "Unable to save the account.";
          } else {
            $insertStmt = $conn->prepare("INSERT INTO panelists (username, full_name, password_hash, status) VALUES (?, ?, ?, ?)");
            if ($insertStmt) {
              $insertStmt->bind_param("ssss", $panelistFormData["username"], $panelistFormData["full_name"], $passwordHash, $panelistFormData["status"]);
              if ($insertStmt->execute()) {
                $accountMessage = "Panelist account created.";
                $activeModal = "";
                $panelistFormData = [
                  "username" => "",
                  "full_name" => "",
                  "status" => "active",
                ];
              } else {
                $panelistFormError = "Unable to save the account.";
              }
              $insertStmt->close();
            } else {
              $panelistFormError = "Unable to save the account.";
            }
          }
        }
      }
    } elseif ($createAccountType === "head_office") {
      $activeModal = "headOfficeModal";
      $headOfficeFormData = [
        "username" => trim((string)($_POST["username"] ?? "")),
        "name" => trim((string)($_POST["name"] ?? "")),
        "lastname" => trim((string)($_POST["lastname"] ?? "")),
        "office" => trim((string)($_POST["office"] ?? "")),
        "status" => $status,
      ];
      $password = (string)($_POST["password"] ?? "");

      if ($headOfficeFormData["username"] === "" || $headOfficeFormData["name"] === "" || $headOfficeFormData["lastname"] === "" || $headOfficeFormData["office"] === "" || $password === "") {
        $headOfficeFormError = "Please complete all fields.";
      } else {
        $checkStmt = $conn->prepare("SELECT id FROM head_offices WHERE username = ? LIMIT 1");
        if ($checkStmt) {
          $checkStmt->bind_param("s", $headOfficeFormData["username"]);
          $checkStmt->execute();
          $checkStmt->store_result();
          if ($checkStmt->num_rows > 0) {
            $headOfficeFormError = "Username already exists.";
          }
          $checkStmt->close();
        } else {
          $headOfficeFormError = "Unable to save the account.";
        }

        if ($headOfficeFormError === "") {
          $passwordHash = password_hash($password, PASSWORD_DEFAULT);
          if ($passwordHash === false) {
            $headOfficeFormError = "Unable to save the account.";
          } else {
            $insertStmt = $conn->prepare("INSERT INTO head_offices (username, name, lastname, office, password_hash, status) VALUES (?, ?, ?, ?, ?, ?)");
            if ($insertStmt) {
              $insertStmt->bind_param("ssssss", $headOfficeFormData["username"], $headOfficeFormData["name"], $headOfficeFormData["lastname"], $headOfficeFormData["office"], $passwordHash, $headOfficeFormData["status"]);
              if ($insertStmt->execute()) {
                $accountMessage = "Head of office account created.";
                $activeModal = "";
                $headOfficeFormData = [
                  "username" => "",
                  "name" => "",
                  "lastname" => "",
                  "office" => "",
                  "status" => "active",
                ];
              } else {
                $headOfficeFormError = "Unable to save the account.";
              }
              $insertStmt->close();
            } else {
              $headOfficeFormError = "Unable to save the account.";
            }
          }
        }
      }
    }
  }
}

isg_ensure_program_options_table($conn);
foreach (array_keys($programOptionGroups) as $categoryKey) {
  $programOptionGroups[$categoryKey] = isg_load_program_options($conn, $categoryKey);
}

$panelistResult = $conn->query("SELECT username, full_name, password_hash, status FROM panelists ORDER BY username ASC");
if ($panelistResult) {
  while ($row = $panelistResult->fetch_assoc()) {
    $panelistAccounts[] = $row;
  }
  $panelistResult->free();
} else {
  $panelistError = "Panelist accounts table is not available.";
}

$headOfficeResult = $conn->query("SELECT username, name, lastname, office, password_hash, status FROM head_offices ORDER BY username ASC");
if ($headOfficeResult) {
  while ($row = $headOfficeResult->fetch_assoc()) {
    $headOfficeAccounts[] = $row;
  }
  $headOfficeResult->free();
} else {
  $headOfficeError = "Head of office accounts table is not available.";
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Accounts</title>
    <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        background: linear-gradient(180deg, #93d7ff 0%, #2e9bd7 100%);
        border-radius: 999px;
      }
          #sidebar > nav > ul {
        padding: 0.35rem 0.5rem 5.5rem;
      }
      #sidebar li[data-nav] {
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
      #sidebar li[data-nav]:hover {
        transform: translateX(2px);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.16);
      }
    </style>
  </head>
  <body class="bg-white font-sans">
    <div class="min-h-screen">
      <!-- Sidebar -->
      <aside
        id="sidebar"
        class="flex flex-col bg-gradient-to-b from-[#031f4f] via-[#0a4b86] to-[#0f9ad8] text-white w-64 h-screen fixed left-0 top-0 z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out overflow-y-auto shadow-[12px_0_28px_-12px_rgba(4,31,79,0.65)]"
      >
        <div
          class="mx-3 mt-3 rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm"
        >
          <div class="flex items-center gap-3">
            <div class="relative shrink-0">
              <span class="absolute -inset-1 rounded-full bg-white/15 blur-sm"></span>
              <img
                src="../img/SMCCNEWLOGO.png"
                class="relative rounded-full w-14 h-14 object-cover ring-2 ring-white/45"
                alt="SMCC Logo"
              />
            </div>
            <div class="min-w-0">
              <p class="text-[10px] uppercase tracking-[0.14em] text-blue-100/85">
                SMCC Scholarship
              </p>
              <p class="text-sm font-semibold leading-tight text-white">
                Admission and Scholarship Office
              </p>
              <p class="text-[10px] text-blue-100/80 mt-1">
                Admin Management Portal
              </p>
            </div>
          </div>
        </div>

        <nav class="flex-1 mt-2">
          <ul class="text-xs font-semibold">
<li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
               data-nav="adminDashboard.php" onclick="window.location.href='adminDashboard.php'"
            >
              <i class="fas fa-home w-5"></i>
              <span>Home</span>
            </li>
            <li class="mb-1">
              <details class="group">
                <summary
                  class="flex cursor-pointer list-none items-center gap-2 rounded-[0.85rem] px-4 py-3 text-left hover:bg-white/15"
                  style="list-style: none;"
                  data-nav="applicant.php"
                >
                  <i class="fas fa-user-graduate w-5"></i>
                  <span class="flex-1">Applicants</span>
                  <i class="fas fa-chevron-down text-[10px] transition group-open:rotate-180"></i>
                </summary>
                <ul class="ml-8 mt-1 space-y-1 border-l border-white/20 pl-3 text-[11px] font-semibold">
                  <li>
                    <a href="applicant.php" class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-blue-50 hover:bg-white/15">
                      <span>Pending Applicants</span>
                      <span class="inline-flex min-w-[1.65rem] items-center justify-center rounded-full bg-gradient-to-r from-[#fcdc2f] to-[#ffe889] px-2 py-0.5 text-[10px] font-extrabold leading-none text-[#052c6a] shadow-[0_0_0_1px_rgba(255,255,255,0.35),0_6px_14px_rgba(252,220,47,0.28)]">
                        <?= htmlspecialchars($sidebarPendingApplicantBadge ?? '0') ?>
                      </span>
                    </a>
                  </li>
                  <li>
                    <a href="declined-applicants.php" class="block rounded-lg px-3 py-2 text-blue-50 hover:bg-white/15">
                      Declined Applicants
                    </a>
                  </li>
                  <li>
                    <a href="summary-of-applicants.php" class="block rounded-lg px-3 py-2 text-blue-50 hover:bg-white/15">
                      Summary of Applicants
                    </a>
                  </li>
                </ul>
              </details>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="approved.php" onclick="window.location.href='approved.php'"
            >
              <i class="fas fa-thumbs-up w-5"></i>
              <span>Approved Applications</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="interviewEvaluation.php" onclick="window.location.href='interviewEvaluation.php'"
            >
              <i class="fas fa-check-circle w-5"></i>
              <span>Interview Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="ranks.php" onclick="window.location.href='ranks.php'"
            >
              <i class="fas fa-star w-5"></i>
              <span>Applicant Ranks</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="list-of-qualified.php" onclick="window.location.href='list-of-qualified.php'"
            >
              <i class="fas fa-list w-5"></i>
              <span>List of Qualified</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="department-evaluation-list.php" onclick="window.location.href='department-evaluation-list.php'"
            >
              <i class="fas fa-building w-5"></i>
              <span>Departmental Evaluation</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="summary-report.php" onclick="window.location.href='summary-report.php'"
            >
              <i class="fas fa-flag w-5"></i>
              <span>Summary Evaluation Report</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="institutional-scholars.php" onclick="window.location.href='institutional-scholars.php'"
            >
              <i class="fas fa-chart-line w-5"></i>
              <span>Institutional Scholars</span>
            </li>

            <li
              class="flex items-center gap-2 px-4 py-3 hover:bg-white/15 cursor-pointer"
              data-nav="accounts.php" onclick="window.location.href='accounts.php'"
            >
              <i class="fas fa-user-circle w-5"></i>
              <span>Settings</span>
            </li>
          </ul>
        </nav>
       
                <div class="absolute bottom-0 left-0 w-full p-2">
          <div class="rounded-xl border border-white/20 bg-white/10 backdrop-blur-sm overflow-hidden">
            <div class="h-px w-full bg-gradient-to-r from-transparent via-[#8bcfff] to-transparent opacity-80"></div>

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
                type="button"
              >
                <i class="fas fa-sign-out-alt text-xs"></i>
                <span>Logout</span>
              </button>
            </div>
          </div>
        </div>
      </aside>

      <!-- Main content -->
      <main class="ml-0 md:ml-64 flex flex-col min-h-screen bg-[#eef2f7] pt-14">
        <!-- Top bar -->
        <header
          class="hidden fixed top-0 left-0 md:left-64 right-0 z-20 flex items-center justify-between bg-[#052c6a] text-white text-xs px-4 py-2"
        >
          <div class="flex items-center gap-2">
            <!-- Mobile menu button -->
            <button
              id="sidebarToggleTop"
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

        <!-- Dashboard header -->
        <section
          class="page-header fixed top-0 left-0 md:left-64 right-0 z-20 bg-white border-b border-slate-200 px-4 sm:px-6 py-3 shadow-sm"
        >
                    <div class="flex items-center gap-2">
            <button
              id="sidebarToggle"
              class="md:hidden inline-flex items-center justify-center p-2 rounded bg-slate-700 text-white hover:bg-slate-800 focus:outline-none transition-colors"
              type="button"
            >
              <i class="fas fa-bars"></i>
            </button>
            <h2 class="text-slate-800 text-lg font-semibold flex items-center gap-2">
            <i class="fas fa-flag"></i>
            SETTINGS
          </h2>
          </div>
        </section>

        <section class="order-1 px-4 sm:px-6 pt-6">
          <div class="rounded-xl border border-[#0d8ddb] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <p class="text-[#0d8ddb] text-sm font-semibold">School Term Settings</p>
                <p class="text-xs text-[#052c6a]">
                  Set the active school year and semester used by Institutional Scholars and admin term filters.
                </p>
              </div>
              <div class="inline-flex w-fit items-center gap-2 rounded-full border border-[#0d8ddb]/25 bg-[#eef7ff] px-3 py-2 text-[11px] font-semibold text-[#052c6a]">
                <i class="fas fa-calendar-alt text-[#0d8ddb]"></i>
                <span><?= htmlspecialchars($displaySchoolYear) ?> / <?= htmlspecialchars($displaySemester) ?></span>
              </div>
            </div>

            <?php if ($schoolTermSettingsMessage !== ""): ?>
              <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <?= htmlspecialchars($schoolTermSettingsMessage) ?>
              </div>
            <?php endif; ?>
            <?php if ($schoolTermSettingsError !== ""): ?>
              <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($schoolTermSettingsError) ?>
              </div>
            <?php endif; ?>

            <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,0.75fr)_minmax(0,1fr)]">
              <form method="POST" class="rounded-xl border border-[#0d8ddb]/30 bg-[#f8fbff] p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#052c6a]">Add School Year</p>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                  <input
                    id="new-school-year"
                    name="new_school_year"
                    type="text"
                    placeholder="2026-2027"
                    pattern="[0-9]{4}\s*-\s*[0-9]{4}"
                    class="min-w-0 flex-1 rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                  <button
                    type="submit"
                    name="add_school_year"
                    value="1"
                    class="rounded-full bg-[#052c6a] px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-[#0b3d86]"
                  >
                    Add Year
                  </button>
                </div>
                <p class="mt-2 text-[11px] text-[#052c6a]/70">Added years remain available for previous records and filters.</p>
              </form>

              <form method="POST" class="rounded-xl border border-[#0d8ddb]/30 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#052c6a]">Active Term</p>
                <div class="mt-3 grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(180px,0.65fr)_auto] md:items-end">
                  <div>
                    <label class="text-xs font-semibold text-[#052c6a]" for="active-school-year">School Year</label>
                    <select
                      id="active-school-year"
                      name="active_school_year"
                      class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                      required
                    >
                      <?php foreach ($schoolYearOptions as $option): ?>
                        <option value="<?= htmlspecialchars($option) ?>" <?= $displaySchoolYear === $option ? "selected" : "" ?>>
                          <?= htmlspecialchars($option) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="text-xs font-semibold text-[#052c6a]" for="active-semester">Semester</label>
                    <select
                      id="active-semester"
                      name="active_semester"
                      class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                      required
                    >
                      <?php foreach ($semesterOptions as $option): ?>
                        <option value="<?= htmlspecialchars($option) ?>" <?= $displaySemester === $option ? "selected" : "" ?>>
                          <?= htmlspecialchars($option) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <button
                    type="submit"
                    name="save_school_term_settings"
                    value="1"
                    class="rounded-full bg-[#0d8ddb] px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-[#0b7bbf]"
                  >
                    Save Active
                  </button>
                </div>
              </form>
            </div>
          </div>
        </section>

        <section class="order-4 px-4 sm:px-6 pt-6 pb-6">
          <div class="rounded-xl border border-[#0d8ddb] bg-white p-5 shadow-sm">
            <div class="mb-4">
              <p class="text-[#0d8ddb] text-sm font-semibold">Application Program Settings</p>
              <p class="text-xs text-[#052c6a]">
                Manage the dropdown choices shown in the applicant form for Senior High, College, and Student Assistant applicants.
              </p>
            </div>

            <?php if ($programSettingsMessage !== ""): ?>
              <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <?= htmlspecialchars($programSettingsMessage) ?>
              </div>
            <?php endif; ?>
            <?php if ($programSettingsError !== ""): ?>
              <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($programSettingsError) ?>
              </div>
            <?php endif; ?>

            <div class="grid gap-4 xl:grid-cols-3">
              <?php
                $programSettingCards = [
                  "senior_high" => [
                    "title" => "Senior High Strands",
                    "placeholder" => "e.g. STEM",
                    "empty" => "No Senior High strands added yet.",
                  ],
                  "college" => [
                    "title" => "College Programs",
                    "placeholder" => "e.g. Bachelor of Science in Computer Science",
                    "empty" => "No College programs added yet.",
                  ],
                  "student_assistant" => [
                    "title" => "Student Assistant Programs",
                    "placeholder" => "e.g. Bachelor in Human Services",
                    "empty" => "No Student Assistant programs added yet.",
                  ],
                ];
              ?>
              <?php foreach ($programSettingCards as $categoryKey => $settingCard): ?>
                <div class="rounded-xl border border-[#0d8ddb]/40 bg-[#f9fbff] p-4">
                  <p class="text-xs font-semibold uppercase tracking-wide text-[#052c6a]">
                    <?= htmlspecialchars($settingCard["title"]) ?>
                  </p>
                  <form method="POST" class="mt-3 flex flex-col gap-2 sm:flex-row xl:flex-col 2xl:flex-row">
                    <input type="hidden" name="program_category" value="<?= htmlspecialchars($categoryKey) ?>" />
                    <input
                      type="text"
                      name="program_name"
                      class="min-w-0 flex-1 rounded-lg border border-[#0d8ddb]/40 px-3 py-2 text-xs text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring-2 focus:ring-[#0d8ddb]/20"
                      placeholder="<?= htmlspecialchars($settingCard["placeholder"]) ?>"
                      required
                    />
                    <button
                      type="submit"
                      name="add_program_option"
                      value="1"
                      class="rounded-full bg-[#0d8ddb] px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-white hover:bg-[#0b7bbf]"
                    >
                      Add
                    </button>
                  </form>

                  <div class="mt-4 space-y-2">
                    <?php if (empty($programOptionGroups[$categoryKey])): ?>
                      <div class="rounded-lg border border-dashed border-[#0d8ddb]/40 bg-white px-3 py-3 text-xs text-[#052c6a]/70">
                        <?= htmlspecialchars($settingCard["empty"]) ?>
                      </div>
                    <?php else: ?>
                      <?php foreach ($programOptionGroups[$categoryKey] as $programOption): ?>
                        <div class="flex flex-col gap-2 rounded-lg border border-[#0d8ddb]/25 bg-white px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                          <span class="text-xs font-medium text-[#052c6a]">
                            <?= htmlspecialchars((string)($programOption["name"] ?? "")) ?>
                          </span>
                          <div class="flex gap-2">
                            <button
                              type="button"
                              class="rounded-full bg-[#052c6a] px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-white hover:bg-[#0b3d86]"
                              data-edit-program-option
                              data-program-option-id="<?= htmlspecialchars((string)($programOption["id"] ?? "")) ?>"
                              data-program-option-name="<?= htmlspecialchars((string)($programOption["name"] ?? "")) ?>"
                              data-program-option-category="<?= htmlspecialchars($categoryKey) ?>"
                            >
                              Edit
                            </button>
                            <form method="POST">
                              <input type="hidden" name="program_option_id" value="<?= htmlspecialchars((string)($programOption["id"] ?? "")) ?>" />
                              <button
                                type="submit"
                                name="remove_program_option"
                                value="1"
                                class="rounded-full bg-red-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-red-700 hover:bg-red-100"
                              >
                                Remove
                              </button>
                            </form>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>

        <section class="order-2 px-4 sm:px-6 pt-6">
          <div class="rounded-xl border border-[#0d8ddb] bg-white p-5 shadow-sm">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="text-[#0d8ddb] text-sm font-semibold">Account Management</p>
                <p class="text-xs text-[#052c6a]">
                  Manage profile details, status, and password resets for panelists and head of offices.
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  class="rounded-full bg-[#052c6a] px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-white hover:bg-[#0b3d86]"
                  data-open-modal="panelistModal"
                >
                  Add Panelist
                </button>
                <button
                  type="button"
                  class="rounded-full bg-[#0d8ddb] px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-white hover:bg-[#0b7bbf]"
                  data-open-modal="headOfficeModal"
                >
                  Add Head of Office
                </button>
              </div>
            </div>

            <div class="space-y-6">
              <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-[#052c6a]">Panelists</p>
            <?php if ($panelistError !== ""): ?>
              <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($panelistError) ?>
              </div>
            <?php else: ?>
              <div class="mt-3 overflow-x-auto">
                <table class="min-w-full border border-[#0d8ddb] text-xs text-left">
                  <thead class="bg-[#052c6a] text-white">
                    <tr>
                      <th class="border-r border-white/10 px-3 py-2">Username</th>
                      <th class="border-r border-white/10 px-3 py-2">Full Name</th>
                      <th class="border-r border-white/10 px-3 py-2">Password Hash</th>
                      <th class="border-r border-white/10 px-3 py-2">Status</th>
                      <th class="px-3 py-2 text-center">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($panelistAccounts)): ?>
                      <tr>
                        <td colspan="5" class="px-3 py-3 text-center text-[#052c6a]">
                          No panelist accounts found.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($panelistAccounts as $account): ?>
                        <tr class="border-b border-[#0d8ddb]">
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["username"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["full_name"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 font-mono text-[10px] text-[#052c6a] break-all">
                            <?= htmlspecialchars((string)($account["password_hash"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["status"] ?? "")) ?>
                          </td>
                          <td class="px-3 py-2">
                            <form method="POST" class="flex flex-wrap items-center justify-center gap-2">
                              <input type="hidden" name="account_type" value="panelist" />
                              <input
                                type="hidden"
                                name="reset_username"
                                value="<?= htmlspecialchars((string)($account["username"] ?? "")) ?>"
                              />
                              <input
                                type="password"
                                name="reset_password"
                                class="w-36 rounded border border-[#0d8ddb]/40 px-2 py-1 text-[11px]"
                                placeholder="New password"
                                required
                              />
                              <button
                                type="submit"
                                name="reset_account_password"
                                value="1"
                                class="rounded-full bg-[#0d8ddb] px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-white hover:bg-[#0b7bbf]"
                              >
                                Reset
                              </button>
                              <button
                                type="button"
                                class="rounded-full bg-[#052c6a] px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-white hover:bg-[#0b3d86]"
                                data-edit-panelist
                                data-username="<?= htmlspecialchars((string)($account["username"] ?? "")) ?>"
                                data-full-name="<?= htmlspecialchars((string)($account["full_name"] ?? "")) ?>"
                                data-status="<?= htmlspecialchars((string)($account["status"] ?? "active")) ?>"
                              >
                                Edit
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
              </div>

              <div class="border-t border-[#0d8ddb]/20 pt-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-[#052c6a]">Head of Offices</p>
            <?php if ($headOfficeError !== ""): ?>
              <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($headOfficeError) ?>
              </div>
            <?php else: ?>
              <div class="mt-3 overflow-x-auto">
                <table class="min-w-full border border-[#0d8ddb] text-xs text-left">
                  <thead class="bg-[#052c6a] text-white">
                    <tr>
                      <th class="border-r border-white/10 px-3 py-2">Username</th>
                      <th class="border-r border-white/10 px-3 py-2">Name</th>
                      <th class="border-r border-white/10 px-3 py-2">Last Name</th>
                      <th class="border-r border-white/10 px-3 py-2">Office</th>
                      <th class="border-r border-white/10 px-3 py-2">Password Hash</th>
                      <th class="border-r border-white/10 px-3 py-2">Status</th>
                      <th class="px-3 py-2 text-center">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($headOfficeAccounts)): ?>
                      <tr>
                        <td colspan="7" class="px-3 py-3 text-center text-[#052c6a]">
                          No head of office accounts found.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($headOfficeAccounts as $account): ?>
                        <tr class="border-b border-[#0d8ddb]">
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["username"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["name"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["lastname"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["office"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 font-mono text-[10px] text-[#052c6a] break-all">
                            <?= htmlspecialchars((string)($account["password_hash"] ?? "")) ?>
                          </td>
                          <td class="border-r border-[#0d8ddb] px-3 py-2 text-[#052c6a]">
                            <?= htmlspecialchars((string)($account["status"] ?? "")) ?>
                          </td>
                          <td class="px-3 py-2">
                            <form method="POST" class="flex flex-wrap items-center justify-center gap-2">
                              <input type="hidden" name="account_type" value="head_office" />
                              <input
                                type="hidden"
                                name="reset_username"
                                value="<?= htmlspecialchars((string)($account["username"] ?? "")) ?>"
                              />
                              <input
                                type="password"
                                name="reset_password"
                                class="w-36 rounded border border-[#0d8ddb]/40 px-2 py-1 text-[11px]"
                                placeholder="New password"
                                required
                              />
                              <button
                                type="submit"
                                name="reset_account_password"
                                value="1"
                                class="rounded-full bg-[#0d8ddb] px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-white hover:bg-[#0b7bbf]"
                              >
                                Reset
                              </button>
                              <button
                                type="button"
                                class="rounded-full bg-[#052c6a] px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-white hover:bg-[#0b3d86]"
                                data-edit-head-office
                                data-username="<?= htmlspecialchars((string)($account["username"] ?? "")) ?>"
                                data-name="<?= htmlspecialchars((string)($account["name"] ?? "")) ?>"
                                data-lastname="<?= htmlspecialchars((string)($account["lastname"] ?? "")) ?>"
                                data-office="<?= htmlspecialchars((string)($account["office"] ?? "")) ?>"
                                data-status="<?= htmlspecialchars((string)($account["status"] ?? "active")) ?>"
                              >
                                Edit
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
              </div>
            </div>
          </div>
        </section>
         

        <!-- Panelist Modal -->
        <div
          id="panelistModal"
          class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-950/60 px-4 py-6"
          role="dialog"
          aria-modal="true"
          aria-labelledby="panelist-modal-title"
        >
          <div class="absolute inset-0" data-close-modal="panelistModal"></div>
          <div class="relative z-10 w-full max-w-2xl rounded-2xl border border-[#0d8ddb]/20 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#0d8ddb]">Accounts</p>
                <h3 id="panelist-modal-title" class="text-lg font-semibold text-[#052c6a]">Create Panelist Account</h3>
              </div>
              <button
                type="button"
                class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700"
                data-close-modal="panelistModal"
              >
                Close
              </button>
            </div>
            <div class="px-6 py-5">
              <p class="text-xs text-[#052c6a]">
                Provide the account details below to add a new panelist without leaving this page.
              </p>

              <?php if ($panelistFormError !== ""): ?>
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                  <?= htmlspecialchars($panelistFormError) ?>
                </div>
              <?php endif; ?>

              <form method="POST" class="mt-6 grid gap-4 md:grid-cols-2">
                <input type="hidden" name="create_account" value="1" />
                <input type="hidden" name="create_account_type" value="panelist" />
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="panelist-username">Username</label>
                  <input
                    id="panelist-username"
                    name="username"
                    type="text"
                    value="<?= htmlspecialchars($panelistFormData["username"]) ?>"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="panelist-full-name">Full Name</label>
                  <input
                    id="panelist-full-name"
                    name="full_name"
                    type="text"
                    value="<?= htmlspecialchars($panelistFormData["full_name"]) ?>"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="panelist-password">Password</label>
                  <input
                    id="panelist-password"
                    name="password"
                    type="password"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="panelist-status">Status</label>
                  <select
                    id="panelist-status"
                    name="status"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                  >
                    <option value="active" <?= $panelistFormData["status"] === "active" ? "selected" : "" ?>>Active</option>
                    <option value="inactive" <?= $panelistFormData["status"] === "inactive" ? "selected" : "" ?>>Inactive</option>
                  </select>
                </div>
                <div class="md:col-span-2 flex flex-wrap justify-end gap-2 pt-2">
                  <button
                    type="button"
                    class="rounded-full border border-slate-300 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:border-slate-400 hover:text-slate-700"
                    data-close-modal="panelistModal"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    class="rounded-full bg-[#0d8ddb] px-6 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:bg-[#0b7bbf]"
                  >
                    Save Panelist
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Head of Office Modal -->
        <div
          id="headOfficeModal"
          class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-950/60 px-4 py-6"
          role="dialog"
          aria-modal="true"
          aria-labelledby="head-office-modal-title"
        >
          <div class="absolute inset-0" data-close-modal="headOfficeModal"></div>
          <div class="relative z-10 w-full max-w-2xl rounded-2xl border border-[#0d8ddb]/20 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#0d8ddb]">Accounts</p>
                <h3 id="head-office-modal-title" class="text-lg font-semibold text-[#052c6a]">Create Department Head Account</h3>
              </div>
              <button
                type="button"
                class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700"
                data-close-modal="headOfficeModal"
              >
                Close
              </button>
            </div>
            <div class="px-6 py-5">
              <p class="text-xs text-[#052c6a]">
                Provide the account details below to add a new head of office without leaving this page.
              </p>

              <?php if ($headOfficeFormError !== ""): ?>
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                  <?= htmlspecialchars($headOfficeFormError) ?>
                </div>
              <?php endif; ?>

              <form method="POST" class="mt-6 grid gap-4 md:grid-cols-2">
                <input type="hidden" name="create_account" value="1" />
                <input type="hidden" name="create_account_type" value="head_office" />
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="head-office-username">Username</label>
                  <input
                    id="head-office-username"
                    name="username"
                    type="text"
                    value="<?= htmlspecialchars($headOfficeFormData["username"]) ?>"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="head-office-full-name">Name</label>
                  <input
                    id="head-office-full-name"
                    name="name"
                    type="text"
                    value="<?= htmlspecialchars($headOfficeFormData["name"]) ?>"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="head-office-last-name">Last Name</label>
                  <input
                    id="head-office-last-name"
                    name="lastname"
                    type="text"
                    value="<?= htmlspecialchars($headOfficeFormData["lastname"]) ?>"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="head-office-office">Office</label>
                  <input
                    id="head-office-office"
                    name="office"
                    type="text"
                    value="<?= htmlspecialchars($headOfficeFormData["office"]) ?>"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="head-office-password">Password</label>
                  <input
                    id="head-office-password"
                    name="password"
                    type="password"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="head-office-status">Status</label>
                  <select
                    id="head-office-status"
                    name="status"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                  >
                    <option value="active" <?= $headOfficeFormData["status"] === "active" ? "selected" : "" ?>>Active</option>
                    <option value="inactive" <?= $headOfficeFormData["status"] === "inactive" ? "selected" : "" ?>>Inactive</option>
                  </select>
                </div>
                <div class="md:col-span-2 flex flex-wrap justify-end gap-2 pt-2">
                  <button
                    type="button"
                    class="rounded-full border border-slate-300 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:border-slate-400 hover:text-slate-700"
                    data-close-modal="headOfficeModal"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    class="rounded-full bg-[#0d8ddb] px-6 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:bg-[#0b7bbf]"
                  >
                    Save Head of Office
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Edit Panelist Modal -->
        <div
          id="editPanelistModal"
          class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-950/60 px-4 py-6"
          role="dialog"
          aria-modal="true"
          aria-labelledby="edit-panelist-modal-title"
        >
          <div class="absolute inset-0" data-close-modal="editPanelistModal"></div>
          <div class="relative z-10 w-full max-w-2xl rounded-2xl border border-[#0d8ddb]/20 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#0d8ddb]">Accounts</p>
                <h3 id="edit-panelist-modal-title" class="text-lg font-semibold text-[#052c6a]">Edit Panelist Account</h3>
              </div>
              <button
                type="button"
                class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700"
                data-close-modal="editPanelistModal"
              >
                Close
              </button>
            </div>
            <div class="px-6 py-5">
              <form method="POST" class="grid gap-4 md:grid-cols-2">
                <input type="hidden" name="update_account" value="1" />
                <input type="hidden" name="update_account_type" value="panelist" />
                <input type="hidden" name="original_username" id="edit-panelist-original-username" />
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="edit-panelist-username">Username</label>
                  <input
                    id="edit-panelist-username"
                    name="username"
                    type="text"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="edit-panelist-full-name">Full Name</label>
                  <input
                    id="edit-panelist-full-name"
                    name="full_name"
                    type="text"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="edit-panelist-status">Status</label>
                  <select
                    id="edit-panelist-status"
                    name="status"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                  >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
                <div class="md:col-span-2 flex flex-wrap justify-end gap-2 pt-2">
                  <button
                    type="button"
                    class="rounded-full border border-slate-300 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:border-slate-400 hover:text-slate-700"
                    data-close-modal="editPanelistModal"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    class="rounded-full bg-[#0d8ddb] px-6 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:bg-[#0b7bbf]"
                  >
                    Save Changes
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Edit Head of Office Modal -->
        <div
          id="editHeadOfficeModal"
          class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-950/60 px-4 py-6"
          role="dialog"
          aria-modal="true"
          aria-labelledby="edit-head-office-modal-title"
        >
          <div class="absolute inset-0" data-close-modal="editHeadOfficeModal"></div>
          <div class="relative z-10 w-full max-w-2xl rounded-2xl border border-[#0d8ddb]/20 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#0d8ddb]">Accounts</p>
                <h3 id="edit-head-office-modal-title" class="text-lg font-semibold text-[#052c6a]">Edit Head of Office Account</h3>
              </div>
              <button
                type="button"
                class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700"
                data-close-modal="editHeadOfficeModal"
              >
                Close
              </button>
            </div>
            <div class="px-6 py-5">
              <form method="POST" class="grid gap-4 md:grid-cols-2">
                <input type="hidden" name="update_account" value="1" />
                <input type="hidden" name="update_account_type" value="head_office" />
                <input type="hidden" name="original_username" id="edit-head-office-original-username" />
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="edit-head-office-username">Username</label>
                  <input
                    id="edit-head-office-username"
                    name="username"
                    type="text"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="edit-head-office-name">Name</label>
                  <input
                    id="edit-head-office-name"
                    name="name"
                    type="text"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="edit-head-office-lastname">Last Name</label>
                  <input
                    id="edit-head-office-lastname"
                    name="lastname"
                    type="text"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="edit-head-office-office">Office</label>
                  <input
                    id="edit-head-office-office"
                    name="office"
                    type="text"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="edit-head-office-status">Status</label>
                  <select
                    id="edit-head-office-status"
                    name="status"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                  >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
                <div class="md:col-span-2 flex flex-wrap justify-end gap-2 pt-2">
                  <button
                    type="button"
                    class="rounded-full border border-slate-300 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:border-slate-400 hover:text-slate-700"
                    data-close-modal="editHeadOfficeModal"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    class="rounded-full bg-[#0d8ddb] px-6 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:bg-[#0b7bbf]"
                  >
                    Save Changes
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Edit Program Option Modal -->
        <div
          id="editProgramOptionModal"
          class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-950/60 px-4 py-6"
          role="dialog"
          aria-modal="true"
          aria-labelledby="edit-program-option-modal-title"
        >
          <div class="absolute inset-0" data-close-modal="editProgramOptionModal"></div>
          <div class="relative z-10 w-full max-w-xl rounded-2xl border border-[#0d8ddb]/20 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#0d8ddb]">Program Settings</p>
                <h3 id="edit-program-option-modal-title" class="text-lg font-semibold text-[#052c6a]">Edit Program Option</h3>
              </div>
              <button
                type="button"
                class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700"
                data-close-modal="editProgramOptionModal"
              >
                Close
              </button>
            </div>
            <div class="px-6 py-5">
              <form method="POST" class="grid gap-4">
                <input type="hidden" name="program_option_id" id="edit-program-option-id" />
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="edit-program-option-category">Category</label>
                  <input
                    id="edit-program-option-category"
                    type="text"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-slate-50 px-4 py-2 text-sm text-[#052c6a]"
                    readonly
                  />
                </div>
                <div>
                  <label class="text-xs font-semibold text-[#052c6a]" for="edit-program-option-name">Program / Strand Name</label>
                  <input
                    id="edit-program-option-name"
                    name="program_name"
                    type="text"
                    class="mt-2 w-full rounded-lg border border-[#0d8ddb]/40 bg-white px-4 py-2 text-sm text-[#052c6a] focus:border-[#0d8ddb] focus:outline-none focus:ring focus:ring-[#0d8ddb]/20"
                    required
                  />
                </div>
                <div class="flex flex-wrap justify-end gap-2 pt-2">
                  <button
                    type="button"
                    class="rounded-full border border-slate-300 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:border-slate-400 hover:text-slate-700"
                    data-close-modal="editProgramOptionModal"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    name="update_program_option"
                    value="1"
                    class="rounded-full bg-[#0d8ddb] px-6 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:bg-[#0b7bbf]"
                  >
                    Save
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </main>
    </div>

    <script>
      const accountToastMessages = [
        {
          message: <?= json_encode($accountMessage, JSON_UNESCAPED_SLASHES) ?>,
          type: "success",
        },
        {
          message: <?= json_encode($resetMessage, JSON_UNESCAPED_SLASHES) ?>,
          type: "success",
        },
        {
          message: <?= json_encode($resetError, JSON_UNESCAPED_SLASHES) ?>,
          type: "error",
        },
        {
          message: <?= json_encode($accountError, JSON_UNESCAPED_SLASHES) ?>,
          type: "error",
        },
      ].filter((item) => item.message);

      function showAccountToast(message, type) {
        if (!message) {
          return;
        }

        if (typeof Swal === "undefined") {
          window.alert(message);
          return;
        }

        Swal.fire({
          toast: true,
          position: "top-end",
          showConfirmButton: false,
          icon: type === "error" ? "error" : "success",
          title: message,
          timer: type === "error" ? 4200 : 3200,
          timerProgressBar: true,
          background: type === "error" ? "#fef2f2" : "#f0fdf4",
          color: type === "error" ? "#991b1b" : "#166534",
        });
      }

      // Sidebar toggle for mobile
      document.addEventListener("DOMContentLoaded", () => {
        accountToastMessages.forEach((item, index) => {
          window.setTimeout(() => {
            showAccountToast(item.message, item.type);
          }, index * 250);
        });

        const sidebar = document.getElementById("sidebar");
        const toggleBtn = document.getElementById("sidebarToggle");
        // Restore modal state after validation errors and wire sidebar plus modal interactions.
        const initialModal = <?= json_encode($activeModal) ?>;

        const setModalState = (modalId, isOpen) => {
          const modal = document.getElementById(modalId);
          if (!modal) {
            return;
          }

          modal.classList.toggle("hidden", !isOpen);
          modal.classList.toggle("flex", isOpen);
          document.body.classList.toggle("overflow-hidden", isOpen);
        };

        if (toggleBtn && sidebar) {
          toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
          });

          // Close sidebar when clicking any nav item on small screens
          sidebar.querySelectorAll("li").forEach((item) => {
            item.addEventListener("click", (event) => {
              if (event.target.closest("summary")) {
                return;
              }
              if (window.innerWidth < 768) {
                sidebar.classList.add("-translate-x-full");
              }
            });
          });
        }

        document.querySelectorAll("[data-open-modal]").forEach((button) => {
          button.addEventListener("click", () => {
            const modalId = button.getAttribute("data-open-modal");
            if (modalId) {
              setModalState(modalId, true);
            }
          });
        });

        document.querySelectorAll("[data-edit-panelist]").forEach((button) => {
          button.addEventListener("click", () => {
            const originalUsernameInput = document.getElementById("edit-panelist-original-username");
            const usernameInput = document.getElementById("edit-panelist-username");
            const fullNameInput = document.getElementById("edit-panelist-full-name");
            const statusSelect = document.getElementById("edit-panelist-status");

            if (!originalUsernameInput || !usernameInput || !fullNameInput || !statusSelect) {
              return;
            }

            originalUsernameInput.value = button.dataset.username || "";
            usernameInput.value = button.dataset.username || "";
            fullNameInput.value = button.dataset.fullName || "";
            statusSelect.value = button.dataset.status || "active";
            setModalState("editPanelistModal", true);
          });
        });

        document.querySelectorAll("[data-edit-head-office]").forEach((button) => {
          button.addEventListener("click", () => {
            const originalUsernameInput = document.getElementById("edit-head-office-original-username");
            const usernameInput = document.getElementById("edit-head-office-username");
            const nameInput = document.getElementById("edit-head-office-name");
            const lastnameInput = document.getElementById("edit-head-office-lastname");
            const officeInput = document.getElementById("edit-head-office-office");
            const statusSelect = document.getElementById("edit-head-office-status");

            if (!originalUsernameInput || !usernameInput || !nameInput || !lastnameInput || !officeInput || !statusSelect) {
              return;
            }

            originalUsernameInput.value = button.dataset.username || "";
            usernameInput.value = button.dataset.username || "";
            nameInput.value = button.dataset.name || "";
            lastnameInput.value = button.dataset.lastname || "";
            officeInput.value = button.dataset.office || "";
            statusSelect.value = button.dataset.status || "active";
            setModalState("editHeadOfficeModal", true);
          });
        });

        document.querySelectorAll("[data-edit-program-option]").forEach((button) => {
          button.addEventListener("click", () => {
            const optionIdInput = document.getElementById("edit-program-option-id");
            const optionNameInput = document.getElementById("edit-program-option-name");
            const optionCategoryInput = document.getElementById("edit-program-option-category");

            if (!optionIdInput || !optionNameInput || !optionCategoryInput) {
              return;
            }

            const categoryLabels = {
              senior_high: "Senior High Strand",
              college: "College Program",
              student_assistant: "Student Assistant Program",
            };
            const category = button.dataset.programOptionCategory || "";

            optionIdInput.value = button.dataset.programOptionId || "";
            optionNameInput.value = button.dataset.programOptionName || "";
            optionCategoryInput.value = categoryLabels[category] || "Program Option";
            setModalState("editProgramOptionModal", true);
          });
        });

        document.querySelectorAll("[data-close-modal]").forEach((button) => {
          button.addEventListener("click", () => {
            const modalId = button.getAttribute("data-close-modal");
            if (modalId) {
              setModalState(modalId, false);
            }
          });
        });

        document.addEventListener("keydown", (event) => {
          if (event.key !== "Escape") {
            return;
          }

          document.querySelectorAll("[id$='Modal']").forEach((modal) => {
            if (!modal.classList.contains("hidden")) {
              setModalState(modal.id, false);
            }
          });
        });

        if (initialModal) {
          setModalState(initialModal, true);
        }
      });

    </script>
  <script>
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("sidebar");
  if (!sidebar) {
    return;
  }

  // Highlight the current admin page in the shared sidebar menu.

  const currentPage = window.location.pathname.split("/").pop().toLowerCase();
  const sidebarAliases = {
    "summary-of-applicants.php": "applicant.php",
    "declined-applicants.php": "applicant.php",
    "view-application.php": "applicant.php",
    "department-evaluation-indi.php": "department-evaluation-list.php",
    "summary-reports.php": "summary-report.php",
    "list-0f-qualified.php": "list-of-qualified.php"
  };
  const activePage = sidebarAliases[currentPage] || currentPage;

  sidebar.querySelectorAll("[data-nav]").forEach((item) => {
    const target = (item.dataset.nav || "").toLowerCase();
    const isActive = target === activePage;
    item.classList.toggle("bg-[#fcdc2f]", isActive);
    item.classList.toggle("bg-opacity-90", isActive);
    item.classList.toggle("text-[#052c6a]", isActive);
    item.classList.toggle("hover:bg-white/15", !isActive);
  });
});
</script>
    <script>
      document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar");
        if (!sidebar) return;

        const currentPage = window.location.pathname.split("/").pop().toLowerCase();
        const applicantPages = new Set([
          "applicant.php",
          "declined-applicants.php",
          "summary-of-applicants.php",
          "view-application.php"
        ]);
        const applicantMenuTrigger = sidebar.querySelector('summary[data-nav="applicant.php"]');
        const applicantMenu = applicantMenuTrigger ? applicantMenuTrigger.closest("details") : null;
        if (applicantMenu) {
          applicantMenu.open = applicantPages.has(currentPage);
        }

        const applicantSubmenuAliases = {
          "view-application.php": "applicant.php"
        };
        const activeApplicantSubmenu = applicantSubmenuAliases[currentPage] || currentPage;
        sidebar.querySelectorAll('details a[href]').forEach((link) => {
          const linkPage = link.getAttribute("href").split("?")[0].split("#")[0].split("/").pop().toLowerCase();
          const isActive = linkPage === activeApplicantSubmenu;
          link.classList.toggle("bg-white/15", isActive);
          link.classList.toggle("text-white", isActive);
          link.classList.toggle("font-bold", isActive);
          link.classList.toggle("text-blue-50", !isActive);
          link.classList.toggle("hover:bg-white/15", !isActive);
        });
      });
    </script>  </body>
</html>
















