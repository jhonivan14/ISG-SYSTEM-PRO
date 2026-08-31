<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once dirname(__DIR__) . "/includes/evaluator-users.php";

if (!function_exists("headExpectedRole")) {
  function headExpectedRole(): string
  {
    return isgEvaluatorRoleFromFolder(basename(__DIR__));
  }
}

if (!function_exists("headBasePath")) {
  function headBasePath(): string
  {
    $scriptName = str_replace("\\", "/", (string)($_SERVER["SCRIPT_NAME"] ?? ""));
    $needle = "/" . basename(__DIR__);
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
    $username = trim((string)($_SESSION["head_username"] ?? ""));
    $role = isgNormalizeEvaluatorRole((string)($_SESSION["evaluator_role"] ?? ""));
    return $username !== "" && $role === headExpectedRole();
  }
}

if (!function_exists("headLoginFile")) {
  function headLoginFile(): string
  {
    $config = isgEvaluatorRoleConfig(headExpectedRole());
    return (string)$config["login"];
  }
}

if (!function_exists("headDashboardFile")) {
  function headDashboardFile(): string
  {
    $config = isgEvaluatorRoleConfig(headExpectedRole());
    return (string)$config["dashboard"];
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

    header("Location: " . headPath(headLoginFile()));
    exit();
  }
}

if (!function_exists("headConsumeRedirectTarget")) {
  function headConsumeRedirectTarget(string $fallbackFile = ""): string
  {
    if ($fallbackFile === "") {
      $fallbackFile = headDashboardFile();
    }

    $redirectTarget = trim((string)($_SESSION["head_redirect_after_login"] ?? ""));
    unset($_SESSION["head_redirect_after_login"]);

    $basePath = headBasePath();
    if ($redirectTarget !== "" && $basePath !== "" && strpos($redirectTarget, $basePath . "/") === 0) {
      return $redirectTarget;
    }

    return headPath($fallbackFile);
  }
}

