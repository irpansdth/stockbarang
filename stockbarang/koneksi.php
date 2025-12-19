<?php
// ========== koneksi.php ==========

// KREDENSIAL DATABASE
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';                 // ganti kalau MySQL kamu ada password
$DB_NAME = 'mandirijayatop';   // <- sesuai yang kamu gunakan

// Koneksi MySQLi
$koneksi = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($koneksi->connect_errno) {
  die('Gagal konek ke database: ' . $koneksi->connect_error);
}
$koneksi->set_charset('utf8mb4');

// Kalau kamu ingin pakai PDO nanti, ini sudah siap tinggal aktifkan:
/*
try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER,
    $DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (Throwable $e) {
  die('Gagal konek DB (PDO): ' . $e->getMessage());
}
*/
