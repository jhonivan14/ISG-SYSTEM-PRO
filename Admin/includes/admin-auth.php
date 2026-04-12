<?php
// Guide: Shared admin authentication, base path, and redirect helpers.
// Trace: start session -> expose path helpers -> enforce login -> resolve post-login redirect target.

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// These helpers are wrapped in function_exists guards so multiple admin includes stay safe.

if (!function_exists("adminBasePath")) {
  function adminBasePath(): string
  {
    $scriptName = str_replace("\\", "/", (string)($_SERVER["SCRIPT_NAME"] ?? ""));
    $scriptDirectory = rtrim(dirname($scriptName), "/");

    if ($scriptDirectory === "." || $scriptDirectory === "\\") {
      $scriptDirectory = "";
    }

    if (substr($scriptDirectory, -9) === "/includes") {
      $scriptDirectory = substr($scriptDirectory, 0, -9);
    }

    return $scriptDirectory;
  }
}

if (!function_exists("adminPath")) {
  function adminPath(string $fileName): string
  {
    $basePath = adminBasePath();
    return ($basePath !== "" ? $basePath : "") . "/" . ltrim($fileName, "/");
  }
}

if (!function_exists("adminIsAuthenticated")) {
  function adminIsAuthenticated(): bool
  {
    return isset($_SESSION["admin_username"]) && trim((string)$_SESSION["admin_username"]) !== "";
  }
}

if (!function_exists("adminRequireLogin")) {
  function adminRequireLogin(): void
  {
    if (adminIsAuthenticated()) {
      return;
    }

    $requestUri = trim((string)($_SERVER["REQUEST_URI"] ?? ""));
    if ($requestUri !== "") {
      $_SESSION["admin_redirect_after_login"] = $requestUri;
    }

    header("Location: " . adminPath("adminLogin.php"));
    exit();
  }
}

if (!function_exists("adminConsumeRedirectTarget")) {
  function adminConsumeRedirectTarget(string $fallbackFile = "adminDashboard.php"): string
  {
    $redirectTarget = trim((string)($_SESSION["admin_redirect_after_login"] ?? ""));
    unset($_SESSION["admin_redirect_after_login"]);

    if ($redirectTarget !== "" && strpos($redirectTarget, "/") === 0) {
      return $redirectTarget;
    }

    return adminPath($fallbackFile);
  }
}
