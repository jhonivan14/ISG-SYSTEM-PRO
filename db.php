<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "isg_system";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$evaluatorUsersHelper = __DIR__ . "/includes/evaluator-users.php";
if (is_file($evaluatorUsersHelper)) {
    require_once $evaluatorUsersHelper;
    if (function_exists("isgEnsureUsersTable")) {
        isgEnsureUsersTable($conn);
    }
    if (function_exists("isgSyncHeadOfficesToUsers")) {
        isgSyncHeadOfficesToUsers($conn);
    }
    if (function_exists("isgEnsureDepartmentHeadEvaluationsRoleColumn")) {
        isgEnsureDepartmentHeadEvaluationsRoleColumn($conn);
    }
}
?>
