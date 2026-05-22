<?php

function isg_program_option_categories(): array
{
  return [
    "senior_high" => "Senior High Strand",
    "college" => "College Program",
    "student_assistant" => "Student Assistant Program",
  ];
}

function isg_default_student_assistant_programs(): array
{
  return [
    "Bachelor of Arts in English Language (AB English)",
    "Bachelor of Technical Vocational Teacher Education (BTVTED)",
    "Bachelor of Secondary Education - major in Mathematics",
    "Bachelor of Secondary Education - major in Social Studies",
    "Bachelor of Science in Computer Science (BSCS)",
    "Bachelor of Library and Information Science (BLIS)",
    "Bachelor of Science in Information Systems (BSIS)",
    "Bachelor of Public Administration (BPA)",
    "Bachelor of Science in Entrepreneurship (BSE)",
    "Bachelor of Science in Accounting Information System (BSAIS)",
    "Bachelor of Science in Business Administration (BSBA) - major in Operations Management",
    "Bachelor of Science in Business Administration (BSBA) - major in Business Economics",
    "Bachelor in Human Services (BHumserv)",
  ];
}

function isg_normalize_program_option_category(string $category): string
{
  $category = strtolower(trim($category));
  return array_key_exists($category, isg_program_option_categories()) ? $category : "";
}

function isg_ensure_program_options_table(mysqli $conn): bool
{
  $sql = "
    CREATE TABLE IF NOT EXISTS scholarship_program_options (
      id INT AUTO_INCREMENT PRIMARY KEY,
      category VARCHAR(40) NOT NULL,
      name VARCHAR(255) NOT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      sort_order INT NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_program_option (category, name)
    )
  ";

  if ($conn->query($sql) !== true) {
    return false;
  }

  $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM scholarship_program_options WHERE category = 'student_assistant'");
  if (!$countStmt) {
    return true;
  }

  $countStmt->execute();
  $countResult = $countStmt->get_result();
  $countRow = $countResult ? $countResult->fetch_assoc() : null;
  $studentAssistantCount = (int)($countRow["total"] ?? 0);
  $countStmt->close();

  if ($studentAssistantCount === 0) {
    $insertStmt = $conn->prepare("
      INSERT INTO scholarship_program_options (category, name, sort_order)
      VALUES ('student_assistant', ?, ?)
      ON DUPLICATE KEY UPDATE is_active = 1
    ");
    if ($insertStmt) {
      foreach (isg_default_student_assistant_programs() as $index => $programName) {
        $sortOrder = $index + 1;
        $insertStmt->bind_param("si", $programName, $sortOrder);
        $insertStmt->execute();
      }
      $insertStmt->close();
    }
  }

  return true;
}

function isg_load_program_options(mysqli $conn, string $category): array
{
  $category = isg_normalize_program_option_category($category);
  if ($category === "" || !isg_ensure_program_options_table($conn)) {
    return [];
  }

  $options = [];
  $stmt = $conn->prepare("
    SELECT id, category, name, is_active
    FROM scholarship_program_options
    WHERE category = ? AND is_active = 1
    ORDER BY sort_order ASC, name ASC
  ");
  if (!$stmt) {
    return $options;
  }

  $stmt->bind_param("s", $category);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($result && ($row = $result->fetch_assoc())) {
    $options[] = $row;
  }
  $stmt->close();

  return $options;
}

function isg_load_program_option_names(mysqli $conn, string $category): array
{
  return array_map(function ($row) {
    return (string)($row["name"] ?? "");
  }, isg_load_program_options($conn, $category));
}

function isg_add_program_option(mysqli $conn, string $category, string $name, ?string &$error = null): bool
{
  $category = isg_normalize_program_option_category($category);
  $name = trim($name);

  if ($category === "") {
    $error = "Invalid program category.";
    return false;
  }
  if ($name === "") {
    $error = "Program or strand name is required.";
    return false;
  }
  if (!isg_ensure_program_options_table($conn)) {
    $error = "Unable to prepare program settings.";
    return false;
  }

  $sortOrder = 1;
  $sortStmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM scholarship_program_options WHERE category = ?");
  if ($sortStmt) {
    $sortStmt->bind_param("s", $category);
    $sortStmt->execute();
    $sortResult = $sortStmt->get_result();
    $sortRow = $sortResult ? $sortResult->fetch_assoc() : null;
    $sortOrder = (int)($sortRow["next_sort"] ?? 1);
    $sortStmt->close();
  }

  $stmt = $conn->prepare("
    INSERT INTO scholarship_program_options (category, name, is_active, sort_order)
    VALUES (?, ?, 1, ?)
    ON DUPLICATE KEY UPDATE is_active = 1
  ");
  if (!$stmt) {
    $error = "Unable to save program option.";
    return false;
  }

  $stmt->bind_param("ssi", $category, $name, $sortOrder);
  $ok = $stmt->execute();
  $stmt->close();

  if (!$ok) {
    $error = "Unable to save program option.";
    return false;
  }

  return true;
}

function isg_deactivate_program_option(mysqli $conn, int $id, ?string &$error = null): bool
{
  if ($id <= 0 || !isg_ensure_program_options_table($conn)) {
    $error = "Invalid program option.";
    return false;
  }

  $stmt = $conn->prepare("UPDATE scholarship_program_options SET is_active = 0 WHERE id = ? LIMIT 1");
  if (!$stmt) {
    $error = "Unable to remove program option.";
    return false;
  }

  $stmt->bind_param("i", $id);
  $ok = $stmt->execute();
  $stmt->close();

  if (!$ok) {
    $error = "Unable to remove program option.";
    return false;
  }

  return true;
}

function isg_update_program_option(mysqli $conn, int $id, string $name, ?string &$error = null): bool
{
  $name = trim($name);

  if ($id <= 0) {
    $error = "Invalid program option.";
    return false;
  }
  if ($name === "") {
    $error = "Program or strand name is required.";
    return false;
  }
  if (!isg_ensure_program_options_table($conn)) {
    $error = "Unable to prepare program settings.";
    return false;
  }

  $stmt = $conn->prepare("UPDATE scholarship_program_options SET name = ?, is_active = 1 WHERE id = ? LIMIT 1");
  if (!$stmt) {
    $error = "Unable to update program option.";
    return false;
  }

  $stmt->bind_param("si", $name, $id);
  $ok = $stmt->execute();
  $stmt->close();

  if (!$ok) {
    $error = "Unable to update program option. The same name may already exist in this category.";
    return false;
  }

  return true;
}
