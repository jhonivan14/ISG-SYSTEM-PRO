<?php
// Guide: Shared evaluator user table bootstrap and role-aware login helpers.
// Trace: ensure users table -> sync legacy head offices -> authenticate evaluator roles.

if (!function_exists("isgEvaluatorRoles")) {
  function isgEvaluatorRoles(): array
  {
    return [
      "department_head" => [
        "label" => "Head of Office",
        "folder" => "DepartmentHead",
        "login" => "headLogin.php",
        "dashboard" => "headDashboard.php",
      ],
      "student_assistant" => [
        "label" => "Student Assistant",
        "folder" => "StudentAssistant",
        "login" => "studentAssistantLogin.php",
        "dashboard" => "studentAssistantDashboard.php",
      ],
      "administrator" => [
        "label" => "Administrator",
        "folder" => "Administrator",
        "login" => "administratorLogin.php",
        "dashboard" => "administratorDashboard.php",
      ],
    ];
  }
}

if (!function_exists("isgNormalizeEvaluatorRole")) {
  function isgNormalizeEvaluatorRole(string $role): string
  {
    $role = strtolower(trim($role));
    $role = str_replace(["-", " "], "_", $role);
    return array_key_exists($role, isgEvaluatorRoles()) ? $role : "";
  }
}

if (!function_exists("isgEvaluatorRoleFromFolder")) {
  function isgEvaluatorRoleFromFolder(string $folderName): string
  {
    $folderKey = strtolower(trim($folderName));
    foreach (isgEvaluatorRoles() as $role => $config) {
      if ($folderKey === strtolower((string)$config["folder"])) {
        return $role;
      }
    }
    return "department_head";
  }
}

if (!function_exists("isgEvaluatorRoleConfig")) {
  function isgEvaluatorRoleConfig(string $role): array
  {
    $roles = isgEvaluatorRoles();
    $role = isgNormalizeEvaluatorRole($role);
    return $roles[$role] ?? $roles["department_head"];
  }
}

if (!function_exists("isgEvaluatorDashboardPath")) {
  function isgEvaluatorDashboardPath(string $role): string
  {
    $config = isgEvaluatorRoleConfig($role);
    return $config["folder"] . "/" . $config["dashboard"];
  }
}

if (!function_exists("isgEvaluatorLoginPath")) {
  function isgEvaluatorLoginPath(string $role): string
  {
    $config = isgEvaluatorRoleConfig($role);
    return $config["folder"] . "/" . $config["login"];
  }
}

if (!function_exists("isgEnsureUsersTable")) {
  function isgEnsureUsersTable(mysqli $conn): bool
  {
    static $ensured = false;
    if ($ensured) {
      return true;
    }

    $createSql = "CREATE TABLE IF NOT EXISTS users (
      id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(100) NOT NULL,
      password_hash VARCHAR(255) NOT NULL,
      role VARCHAR(50) NOT NULL DEFAULT 'department_head',
      name VARCHAR(100) DEFAULT NULL,
      lastname VARCHAR(100) DEFAULT NULL,
      full_name VARCHAR(150) DEFAULT NULL,
      email VARCHAR(255) DEFAULT NULL,
      office VARCHAR(120) DEFAULT NULL,
      scholar_record_id INT DEFAULT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'active',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_username_role (username, role),
      KEY idx_role_status (role, status),
      KEY idx_scholar_record_id (scholar_record_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createSql) !== true) {
      return false;
    }

    $columns = isgTableColumns($conn, "users");
    if (!isset($columns["email"])) {
      $conn->query("ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER full_name");
    }
    if (!isset($columns["scholar_record_id"])) {
      $conn->query("ALTER TABLE users ADD COLUMN scholar_record_id INT DEFAULT NULL AFTER office");
    }
    $indexResult = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'idx_scholar_record_id'");
    $hasScholarIndex = $indexResult instanceof mysqli_result && $indexResult->num_rows > 0;
    if ($indexResult instanceof mysqli_result) {
      $indexResult->free();
    }
    if (!$hasScholarIndex) {
      $conn->query("CREATE INDEX idx_scholar_record_id ON users (scholar_record_id)");
    }

    $ensured = true;
    return true;
  }
}

if (!function_exists("isgTableColumns")) {
  function isgTableColumns(mysqli $conn, string $tableName): array
  {
    $columns = [];
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    if ($safeTable === "") {
      return $columns;
    }

    $result = $conn->query("SHOW COLUMNS FROM {$safeTable}");
    if ($result instanceof mysqli_result) {
      while ($row = $result->fetch_assoc()) {
        $field = trim((string)($row["Field"] ?? ""));
        if ($field !== "") {
          $columns[$field] = true;
        }
      }
      $result->free();
    }
    return $columns;
  }
}

if (!function_exists("isgSyncHeadOfficesToUsers")) {
  function isgSyncHeadOfficesToUsers(mysqli $conn): bool
  {
    static $synced = false;
    if ($synced) {
      return true;
    }
    if (!isgEnsureUsersTable($conn)) {
      return false;
    }

    $tableResult = $conn->query("SHOW TABLES LIKE 'head_offices'");
    $hasHeadOffices = $tableResult instanceof mysqli_result && $tableResult->num_rows > 0;
    if ($tableResult instanceof mysqli_result) {
      $tableResult->free();
    }
    if (!$hasHeadOffices) {
      $synced = true;
      return true;
    }

    $columns = isgTableColumns($conn, "head_offices");
    $nameExpr = isset($columns["name"]) ? "name" : "''";
    $lastnameExpr = isset($columns["lastname"]) ? "lastname" : "''";
    $fullNameExpr = isset($columns["full_name"]) ? "full_name" : "''";
    $emailExpr = isset($columns["email"]) ? "email" : "''";
    $officeExpr = isset($columns["office"]) ? "office" : "''";
    $statusExpr = isset($columns["status"]) ? "status" : "'active'";

    if (!isset($columns["username"]) || !isset($columns["password_hash"])) {
      return false;
    }

    $result = $conn->query("
      SELECT
        username,
        {$nameExpr} AS name,
        {$lastnameExpr} AS lastname,
        {$fullNameExpr} AS full_name,
        {$emailExpr} AS email,
        {$officeExpr} AS office,
        password_hash,
        {$statusExpr} AS status
      FROM head_offices
      WHERE TRIM(COALESCE(username, '')) <> ''
    ");
    if (!$result instanceof mysqli_result) {
      return false;
    }

    $upsert = $conn->prepare(
      "INSERT INTO users (username, password_hash, role, name, lastname, full_name, email, office, status)
       VALUES (?, ?, 'department_head', ?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE
         password_hash = VALUES(password_hash),
         name = VALUES(name),
         lastname = VALUES(lastname),
         full_name = VALUES(full_name),
         email = VALUES(email),
         office = VALUES(office),
         status = VALUES(status)"
    );
    if (!$upsert) {
      $result->free();
      return false;
    }

    while ($row = $result->fetch_assoc()) {
      $username = trim((string)($row["username"] ?? ""));
      $passwordHash = (string)($row["password_hash"] ?? "");
      $name = trim((string)($row["name"] ?? ""));
      $lastname = trim((string)($row["lastname"] ?? ""));
      $fullName = trim((string)($row["full_name"] ?? ""));
      $email = trim((string)($row["email"] ?? ""));
      $office = trim((string)($row["office"] ?? ""));
      $status = strtolower(trim((string)($row["status"] ?? "active")));
      $status = in_array($status, ["active", "inactive"], true) ? $status : "active";

      if ($username === "" || $passwordHash === "") {
        continue;
      }

      $upsert->bind_param("ssssssss", $username, $passwordHash, $name, $lastname, $fullName, $email, $office, $status);
      $upsert->execute();
    }

    $upsert->close();
    $result->free();
    $synced = true;
    return true;
  }
}

if (!function_exists("isgEnsureDepartmentHeadEvaluationsRoleColumn")) {
  function isgEnsureDepartmentHeadEvaluationsRoleColumn(mysqli $conn): bool
  {
    $tableResult = $conn->query("SHOW TABLES LIKE 'department_head_evaluations'");
    $hasTable = $tableResult instanceof mysqli_result && $tableResult->num_rows > 0;
    if ($tableResult instanceof mysqli_result) {
      $tableResult->free();
    }
    if (!$hasTable) {
      return true;
    }

    $columns = isgTableColumns($conn, "department_head_evaluations");
    if (!isset($columns["evaluator_role"])) {
      if ($conn->query("ALTER TABLE department_head_evaluations ADD COLUMN evaluator_role VARCHAR(50) NOT NULL DEFAULT 'department_head' AFTER head_username") !== true) {
        return false;
      }
    }

    $conn->query("UPDATE department_head_evaluations SET evaluator_role = 'department_head' WHERE TRIM(COALESCE(evaluator_role, '')) = ''");

    $indexColumns = [];
    $indexResult = $conn->query("SHOW INDEX FROM department_head_evaluations");
    if ($indexResult instanceof mysqli_result) {
      while ($indexRow = $indexResult->fetch_assoc()) {
        $keyName = trim((string)($indexRow["Key_name"] ?? ""));
        $sequence = (int)($indexRow["Seq_in_index"] ?? 0);
        $columnName = trim((string)($indexRow["Column_name"] ?? ""));
        if ($keyName !== "" && $sequence > 0 && $columnName !== "") {
          if (!isset($indexColumns[$keyName])) {
            $indexColumns[$keyName] = [];
          }
          $indexColumns[$keyName][$sequence] = $columnName;
        }
      }
      $indexResult->free();
    }

    foreach (["uniq_application_head", "uniq_application_head_term"] as $legacyIndexName) {
      if (isset($indexColumns[$legacyIndexName])) {
        if ($conn->query("ALTER TABLE department_head_evaluations DROP INDEX {$legacyIndexName}") !== true) {
          return false;
        }
        unset($indexColumns[$legacyIndexName]);
      }
    }

    $requiredIndex = ["application_id", "head_username", "evaluator_role", "semester", "school_year"];
    $hasRequiredIndex = false;
    if (isset($indexColumns["uniq_application_head_role_term"])) {
      ksort($indexColumns["uniq_application_head_role_term"]);
      $hasRequiredIndex = array_values($indexColumns["uniq_application_head_role_term"]) === $requiredIndex;
      if (!$hasRequiredIndex) {
        if ($conn->query("ALTER TABLE department_head_evaluations DROP INDEX uniq_application_head_role_term") !== true) {
          return false;
        }
      }
    }

    if (!$hasRequiredIndex) {
      $addIndexSql = "ALTER TABLE department_head_evaluations
        ADD UNIQUE KEY uniq_application_head_role_term (application_id, head_username, evaluator_role, semester, school_year)";
      if ($conn->query($addIndexSql) !== true) {
        return false;
      }
    }

    return true;
  }
}

if (!function_exists("isgLoadEvaluatorUser")) {
  function isgLoadEvaluatorUser(mysqli $conn, string $username, string $role): ?array
  {
    $username = trim($username);
    $role = isgNormalizeEvaluatorRole($role);
    if ($username === "" || $role === "") {
      return null;
    }
    if (!isgEnsureUsersTable($conn)) {
      return null;
    }
    isgSyncHeadOfficesToUsers($conn);

    $stmt = $conn->prepare(
      "SELECT id, username, password_hash, role, name, lastname, full_name, email, office, scholar_record_id, status
       FROM users
       WHERE username = ? AND role = ?
       LIMIT 1"
    );
    if (!$stmt) {
      return null;
    }

    $stmt->bind_param("ss", $username, $role);
    $user = null;
    if ($stmt->execute()) {
      $result = $stmt->get_result();
      $row = $result ? $result->fetch_assoc() : null;
      if (is_array($row)) {
        $user = $row;
      }
      if ($result instanceof mysqli_result) {
        $result->free();
      }
    }
    $stmt->close();
    return $user;
  }
}

if (!function_exists("isgEvaluatorDisplayName")) {
  function isgEvaluatorDisplayName(array $user): string
  {
    $fullName = trim((string)($user["full_name"] ?? ""));
    if ($fullName !== "") {
      return $fullName;
    }

    $parts = [];
    $name = trim((string)($user["name"] ?? ""));
    $lastname = trim((string)($user["lastname"] ?? ""));
    if ($name !== "") {
      $parts[] = $name;
    }
    if ($lastname !== "") {
      $parts[] = $lastname;
    }

    $displayName = trim(implode(" ", $parts));
    return $displayName !== "" ? $displayName : trim((string)($user["username"] ?? ""));
  }
}

if (!function_exists("isgLoadEvaluatorScope")) {
  function isgLoadEvaluatorScope(mysqli $conn, string $username, string $role): array
  {
    $role = isgNormalizeEvaluatorRole($role);
    $user = isgLoadEvaluatorUser($conn, $username, $role);
    $office = trim((string)($user["office"] ?? ""));
    $scholarRecordId = (int)($user["scholar_record_id"] ?? 0);
    $scope = [
      "role" => $role,
      "office" => $office,
      "office_key" => strtolower($office),
      "scholar_record_id" => $scholarRecordId,
      "all_student_assistants" => false,
      "self_only" => false,
      "error" => "",
    ];

    if (!$user || trim((string)($user["status"] ?? "")) !== "active") {
      $scope["error"] = "Evaluator account is inactive or not found.";
      return $scope;
    }

    $_SESSION["head_office"] = $office;
    $_SESSION["evaluator_scholar_record_id"] = $scholarRecordId;

    if ($role === "administrator") {
      $scope["all_student_assistants"] = true;
      return $scope;
    }

    if ($role === "student_assistant") {
      $scope["self_only"] = true;
      if ($scholarRecordId <= 0) {
        $scope["error"] = "No student assistant record is linked to this account.";
      }
      return $scope;
    }

    if ($office === "") {
      $scope["error"] = "No office is assigned to this head account.";
    }
    return $scope;
  }
}

if (!function_exists("isgEvaluatorScholarRestriction")) {
  function isgEvaluatorScholarRestriction(array $scope, string $alias = ""): array
  {
    $role = isgNormalizeEvaluatorRole((string)($scope["role"] ?? ""));
    $prefix = trim($alias) !== "" ? trim($alias) . "." : "";

    if ($role === "administrator") {
      return ["sql" => "", "types" => "", "params" => []];
    }

    if ($role === "student_assistant") {
      return [
        "sql" => " AND {$prefix}id = ?",
        "types" => "i",
        "params" => [(int)($scope["scholar_record_id"] ?? 0)],
      ];
    }

    return [
      "sql" => " AND LOWER(TRIM(COALESCE({$prefix}assigned_office, ''))) = ?",
      "types" => "s",
      "params" => [(string)($scope["office_key"] ?? "")],
    ];
  }
}

if (!function_exists("isgEvaluatorEvaluationRestriction")) {
  function isgEvaluatorEvaluationRestriction(array $scope, string $alias = ""): array
  {
    $role = isgNormalizeEvaluatorRole((string)($scope["role"] ?? ""));
    $prefix = trim($alias) !== "" ? trim($alias) . "." : "";

    if ($role === "administrator") {
      return ["sql" => "", "types" => "", "params" => []];
    }

    if ($role === "student_assistant") {
      return [
        "sql" => " AND ABS({$prefix}application_id) = ?",
        "types" => "i",
        "params" => [(int)($scope["scholar_record_id"] ?? 0)],
      ];
    }

    return [
      "sql" => " AND LOWER(TRIM(COALESCE({$prefix}assigned_office, ''))) = ?",
      "types" => "s",
      "params" => [(string)($scope["office_key"] ?? "")],
    ];
  }
}

if (!function_exists("isgVerifyEvaluatorPassword")) {
  function isgVerifyEvaluatorPassword(string $password, string $storedHash): bool
  {
    if ($storedHash === "") {
      return false;
    }
    if (strpos($storedHash, '$2y$') === 0 || strpos($storedHash, '$argon2') === 0) {
      return password_verify($password, $storedHash);
    }
    if (hash("sha256", $password) === $storedHash) {
      return true;
    }
    return hash_equals($storedHash, $password);
  }
}

if (!function_exists("isgSetEvaluatorSession")) {
  function isgSetEvaluatorSession(array $user): void
  {
    $role = isgNormalizeEvaluatorRole((string)($user["role"] ?? ""));
    $config = isgEvaluatorRoleConfig($role);

    $_SESSION["head_username"] = trim((string)($user["username"] ?? ""));
    $_SESSION["head_name"] = isgEvaluatorDisplayName($user);
    $_SESSION["head_office"] = trim((string)($user["office"] ?? ""));
    $_SESSION["evaluator_user_id"] = (int)($user["id"] ?? 0);
    $_SESSION["evaluator_scholar_record_id"] = (int)($user["scholar_record_id"] ?? 0);
    $_SESSION["evaluator_role"] = $role;
    $_SESSION["evaluator_role_label"] = (string)$config["label"];
  }
}

if (!function_exists("isgUpsertEvaluatorUser")) {
  function isgUpsertEvaluatorUser(
    mysqli $conn,
    string $username,
    string $role,
    string $passwordHash,
    string $name = "",
    string $lastname = "",
    string $fullName = "",
    string $office = "",
    string $status = "active",
    int $scholarRecordId = 0,
    string $email = ""
  ): bool {
    $username = trim($username);
    $role = isgNormalizeEvaluatorRole($role);
    $passwordHash = trim($passwordHash);
    $name = trim($name);
    $lastname = trim($lastname);
    $fullName = trim($fullName);
    $email = trim($email);
    $office = trim($office);
    $scholarRecordId = $scholarRecordId > 0 ? $scholarRecordId : 0;
    $status = strtolower(trim($status));
    $status = in_array($status, ["active", "inactive"], true) ? $status : "active";

    if ($username === "" || $role === "" || $passwordHash === "" || !isgEnsureUsersTable($conn)) {
      return false;
    }

    if ($fullName === "") {
      $fullName = trim($name . " " . $lastname);
    }

    $stmt = $conn->prepare(
      "INSERT INTO users (username, password_hash, role, name, lastname, full_name, email, office, scholar_record_id, status)
       VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, 0), ?)
       ON DUPLICATE KEY UPDATE
         password_hash = VALUES(password_hash),
         name = VALUES(name),
         lastname = VALUES(lastname),
         full_name = VALUES(full_name),
         email = VALUES(email),
         office = VALUES(office),
         scholar_record_id = VALUES(scholar_record_id),
         status = VALUES(status)"
    );
    if (!$stmt) {
      return false;
    }

    $stmt->bind_param("ssssssssis", $username, $passwordHash, $role, $name, $lastname, $fullName, $email, $office, $scholarRecordId, $status);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  }
}

if (!function_exists("isgDeleteEvaluatorUser")) {
  function isgDeleteEvaluatorUser(mysqli $conn, string $username, string $role): bool
  {
    $username = trim($username);
    $role = isgNormalizeEvaluatorRole($role);
    if ($username === "" || $role === "" || !isgEnsureUsersTable($conn)) {
      return false;
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE username = ? AND role = ? LIMIT 1");
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param("ss", $username, $role);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  }
}

if (!function_exists("isgUpdateEvaluatorPassword")) {
  function isgUpdateEvaluatorPassword(mysqli $conn, string $username, string $role, string $passwordHash): bool
  {
    $username = trim($username);
    $role = isgNormalizeEvaluatorRole($role);
    if ($username === "" || $role === "" || $passwordHash === "" || !isgEnsureUsersTable($conn)) {
      return false;
    }

    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = ? AND role = ? LIMIT 1");
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param("sss", $passwordHash, $username, $role);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  }
}
