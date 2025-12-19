<?php
// ==========================================
// CONFIG.PHP — FINAL & STABIL untuk stockbarang.test di root
// ==========================================

define('BASE_URL', '/'); // karena app di root: http://stockbarang.test/

// Session Global (HARUS di paling atas & hanya sekali)
if (session_status() === PHP_SESSION_NONE) {
  session_name('pjt_session'); // konsisten di semua halaman
  session_set_cookie_params([
    'path'     => '/',   // root domain
    'httponly' => true,
    'samesite' => 'Lax',
    // 'secure' => true, // gunakan jika memakai HTTPS
  ]);
  session_start();
}

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Helper URL absolut
function url(string $path = ''): string {
  $base = rtrim(BASE_URL, '/');
  $path = ltrim($path, '/');
  return $base . '/' . $path;
}

// Helper asset
function asset(string $path = ''): string {
  return url('assets/' . ltrim($path, '/'));
}

// Error display (matikan di production)
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');
