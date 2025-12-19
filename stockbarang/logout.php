<?php
// logout.php — bersihkan sesi dan kembali ke login
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

require_once __DIR__ . '/config.php';

// Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Hapus semua data session
$_SESSION = [];

// Hapus cookie session (pakai path dari BASE_URL agar yakin kehapus)
$cookiePath = rtrim(defined('BASE_URL') ? BASE_URL : '/', '/');
if ($cookiePath === '') $cookiePath = '/';

if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  // Pakai path kita agar tidak nyangkut karena mismatch path
  setcookie(session_name(), '', time() - 42000, $cookiePath, $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
}

// Destroy session di server
if (session_status() === PHP_SESSION_ACTIVE) {
  session_destroy();
}

// (opsional) cegah cache halaman sebelumnya saat tombol back
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Redirect ke login
require_once __DIR__ . '/config.php';
$loginUrl = url('login.php');
header('Location: ' . $loginUrl);
exit;
