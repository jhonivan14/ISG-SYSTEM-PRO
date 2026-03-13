<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (!function_exists("headBasePath")) {
  function headBasePath(): string
  {
    $scriptName = str_replace("\\", "/", (string)($_SERVER["SCRIPT_NAME"] ?? ""));
    $needle = "/DepartmentHead";
    $position = stripos($scriptName, $needle);

    if ($position !== false) {
      return substr($scriptName, 0, $position + strlen($needle));
    }

    $scriptDirectory = rtrim(dirname($scriptName), "/");
    return $scriptDirectory === "." ? "" : $scriptDirectory;
  }
}

if (!function_exists("headPath")) {
  function headPath(string $fileName): string
  {
    $basePath = headBasePath();
    return ($basePath !== "" ? $basePath : "") . "/" . ltrim($fileName, "/");
  }
}

if (!function_exists("headIsAuthenticated")) {
  function headIsAuthenticated(): bool
  {
    return isset($_SESSION["head_username"]) && trim((string)$_SESSION["head_username"]) !== "";
  }
}

if (!function_exists("headRequireLogin")) {
  function headRequireLogin(): void
  {
    if (headIsAuthenticated()) {
      return;
    }

    $requestUri = trim((string)($_SERVER["REQUEST_URI"] ?? ""));
    if ($requestUri !== "") {
      $_SESSION["head_redirect_after_login"] = $requestUri;
    }

    header("Location: " . headPath("headLogin.php"));
    exit();
  }
}

if (!function_exists("headConsumeRedirectTarget")) {
  function headConsumeRedirectTarget(string $fallbackFile = "headDashboard.php"): string
  {
    $redirectTarget = trim((string)($_SESSION["head_redirect_after_login"] ?? ""));
    unset($_SESSION["head_redirect_after_login"]);

    $basePath = headBasePath();
    if ($redirectTarget !== "" && $basePath !== "" && strpos($redirectTarget, $basePath . "/") === 0) {
      return $redirectTarget;
    }

    return headPath($fallbackFile);
  }
}
