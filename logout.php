<?php
session_start();

// Resolve where to send the user before clearing session data.
$redirect = "index.php";

$requestedRole = strtolower(trim((string)($_GET["role"] ?? "")));
if ($requestedRole === "panel" || $requestedRole === "panelist") {
    $redirect = "Panelist/panelLogin.php";
} elseif ($requestedRole === "head" || $requestedRole === "departmenthead") {
    $redirect = "DepartmentHead/headLogin.php";
} elseif ($requestedRole === "admin") {
    $redirect = "Admin/adminLogin.php";
} elseif (!empty($_SESSION["panelist_username"])) {
    $redirect = "Panelist/panelLogin.php";
} elseif (!empty($_SESSION["head_username"])) {
    $redirect = "DepartmentHead/headLogin.php";
} elseif (!empty($_SESSION["admin_username"]) || !empty($_SESSION["admin_name"])) {
    $redirect = "Admin/adminLogin.php";
} else {
    // Fallback when no role session exists (e.g., admin pages without role session key).
    $refPath = (string)parse_url((string)($_SERVER["HTTP_REFERER"] ?? ""), PHP_URL_PATH);
    if (stripos($refPath, "/Panelist/") !== false) {
        $redirect = "Panelist/panelLogin.php";
    } elseif (stripos($refPath, "/DepartmentHead/") !== false) {
        $redirect = "DepartmentHead/headLogin.php";
    } elseif (stripos($refPath, "/Admin/") !== false) {
        $redirect = "Admin/adminLogin.php";
    }
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("Location: " . $redirect);
exit;
