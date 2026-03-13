<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (!function_exists("panelistBasePath")) {
  function panelistBasePath(): string
  {
    $scriptName = str_replace("\\", "/", (string)($_SERVER["SCRIPT_NAME"] ?? ""));
    $needle = "/Panelist";
    $position = stripos($scriptName, $needle);

    if ($position !== false) {
      return substr($scriptName, 0, $position + strlen($needle));
    }

    $scriptDirectory = rtrim(dirname($scriptName), "/");
    return $scriptDirectory === "." ? "" : $scriptDirectory;
  }
}

if (!function_exists("panelistPath")) {
  function panelistPath(string $fileName): string
  {
    $basePath = panelistBasePath();
    return ($basePath !== "" ? $basePath : "") . "/" . ltrim($fileName, "/");
  }
}

if (!function_exists("panelistIsAuthenticated")) {
  function panelistIsAuthenticated(): bool
  {
    return isset($_SESSION["panelist_username"]) && trim((string)$_SESSION["panelist_username"]) !== "";
  }
}

if (!function_exists("panelistRequireLogin")) {
  function panelistRequireLogin(): void
  {
    if (panelistIsAuthenticated()) {
      return;
    }

    $requestUri = trim((string)($_SERVER["REQUEST_URI"] ?? ""));
    if ($requestUri !== "") {
      $_SESSION["panelist_redirect_after_login"] = $requestUri;
    }

    header("Location: " . panelistPath("panelLogin.php"));
    exit();
  }
}

if (!function_exists("panelistConsumeRedirectTarget")) {
  function panelistConsumeRedirectTarget(string $fallbackFile = "panelistDashboard.php"): string
  {
    $redirectTarget = trim((string)($_SESSION["panelist_redirect_after_login"] ?? ""));
    unset($_SESSION["panelist_redirect_after_login"]);

    $basePath = panelistBasePath();
    if ($redirectTarget !== "" && $basePath !== "" && strpos($redirectTarget, $basePath . "/") === 0) {
      return $redirectTarget;
    }

    return panelistPath($fallbackFile);
  }
}
