<?php
// ============== includes/auth.php ==============
// Dipanggil SETELAH config.php (session sudah aktif)

// Hirarki role (bisa kamu ubah sesuai kebutuhan)
const ROLE_LEVELS = [
  'viewer' => 0,
  'staff'  => 1,
  'admin'  => 2,
];

// Pemetaan permission → role mana yang boleh
const PERMISSIONS = [
  'view_dashboard' => ['viewer','staff','admin'],
  'view_stock'     => ['viewer','staff','admin'],
  'view_reports'   => ['viewer','staff','admin'],

  'transact'       => ['staff','admin'],       // barang masuk/keluar
  'edit_master'    => ['staff','admin'],       // supplier & customer CRUD
  'manage_users'   => ['admin'],               // halaman users
];

// Info user saat ini
function current_user_id(): int {
  return (int)($_SESSION['user_id'] ?? 0);
}
function current_role(): string {
  $r = $_SESSION['role'] ?? 'staff';
  return array_key_exists($r, ROLE_LEVELS) ? $r : 'staff';
}
function is_logged_in(): bool {
  return current_user_id() > 0;
}

// Wajib login
function require_login(): void {
  if (!is_logged_in()) {
    header('Location: ' . url('login.php'));
    exit;
  }
}

// Cek minimal role (opsional jika mau pakai hirarki)
function role_at_least(string $minRole): bool {
  $cur = current_role();
  return (ROLE_LEVELS[$cur] ?? -1) >= (ROLE_LEVELS[$minRole] ?? PHP_INT_MIN);
}

// Cek permission
function can(string $permission): bool {
  $role = current_role();
  $allowed = PERMISSIONS[$permission] ?? [];
  return in_array($role, $allowed, true);
}

// Blokir bila tak punya izin
function require_permission(string $permission): void {
  if (!can($permission)) {
    http_response_code(403);
    // Bisa diarahkan ke halaman 403 cantik, kalau kamu punya:
    // header('Location: ' . url('pages/403.php')); exit;
    exit('403 Forbidden — Akses ditolak.');
  }
}
