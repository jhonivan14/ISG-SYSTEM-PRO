<?php

if (!function_exists("projectRootPath")) {
  function projectRootPath(): string
  {
    $rootPath = realpath(__DIR__);
    return $rootPath !== false ? $rootPath : __DIR__;
  }
}

if (!function_exists("uploadStorageBasePath")) {
  function uploadStorageBasePath(): string
  {
    $configuredBasePath = trim((string)getenv("ISG_UPLOAD_BASE"));
    if ($configuredBasePath !== "") {
      return rtrim($configuredBasePath, "\\/");
    }

    $projectRoot = projectRootPath();
    $xamppRoot = dirname(dirname($projectRoot));

    return $xamppRoot . DIRECTORY_SEPARATOR . "isg-system-storage";
  }
}

if (!function_exists("applicationUploadDirectory")) {
  function applicationUploadDirectory(int $applicationId): string
  {
    return uploadStorageBasePath() . DIRECTORY_SEPARATOR . "applications" . DIRECTORY_SEPARATOR . $applicationId;
  }
}

if (!function_exists("storedUploadDbPath")) {
  function storedUploadDbPath(int $applicationId, string $storedFileName): string
  {
    $normalizedFileName = ltrim(str_replace("\\", "/", $storedFileName), "/");
    return "applications/" . $applicationId . "/" . $normalizedFileName;
  }
}

if (!function_exists("resolveStoredUploadPath")) {
  function resolveStoredUploadPath(string $storedPath): string
  {
    $normalizedPath = ltrim(str_replace("\\", "/", trim($storedPath)), "/");
    if ($normalizedPath === "") {
      return "";
    }

    if (strpos($normalizedPath, "uploads/") === 0) {
      return projectRootPath() . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $normalizedPath);
    }

    return uploadStorageBasePath() . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $normalizedPath);
  }
}
