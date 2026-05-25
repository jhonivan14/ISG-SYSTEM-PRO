<?php
// Guide: Shared support for recording and displaying application approval timestamps.
// Trace: ensure column exists -> format stored timestamp for admin tables.

if (!function_exists("adminEnsureApplicationApprovedAtColumn")) {
  function adminEnsureApplicationApprovedAtColumn(mysqli $conn): bool
  {
    $columnResult = $conn->query("SHOW COLUMNS FROM applications LIKE 'approved_at'");
    if ($columnResult instanceof mysqli_result) {
      $exists = $columnResult->num_rows > 0;
      $columnResult->free();
      if ($exists) {
        return true;
      }
    }

    return (bool)$conn->query("ALTER TABLE applications ADD COLUMN approved_at DATETIME DEFAULT NULL AFTER status");
  }
}

if (!function_exists("adminFormatApplicationApprovalTimestamp")) {
  function adminFormatApplicationApprovalTimestamp(string $timestamp): string
  {
    $timestamp = trim($timestamp);
    if ($timestamp === "") {
      return "Not recorded";
    }

    $time = strtotime($timestamp);
    if ($time === false) {
      return $timestamp;
    }

    return date("M d, Y h:i A", $time);
  }
}
