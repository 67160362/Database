<?php
// *** แก้ค่าตามเครื่องคุณ ***
const DB_HOST = '';
const DB_NAME = '';
const DB_USER = '';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

// ตั้งค่า session ให้ปลอดภัยขึ้น
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
  ]);
  session_start();
}

// ให้ mysqli โยน exception เวลา error
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $mysqli->set_charset(DB_CHARSET);
} catch (Throwable $e) {
  http_response_code(500);
  exit('Database connection failed.');
}
