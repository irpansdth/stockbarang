<?php
// cek.php - memastikan user sudah login

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Periksa apakah session login sudah diset oleh login.php
if (empty($_SESSION['user_id']) && empty($_SESSION['logged_in'])) {
    // Jika belum login, arahkan ke halaman login
    header('Location: login.php');
    exit;
}
?>
