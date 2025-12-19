<?php
// includes/sidebar.php — FINAL (role-aware + active state)

// pastikan config & session sudah jalan via include dari halaman pemanggil
$role = $_SESSION['role'] ?? 'staff';
$companyName = $companyName ?? 'PT Mandiri Jaya Top';

$uri = $_SERVER['REQUEST_URI'] ?? '';
$active = function(string $needle) use ($uri) {
  return (strpos($uri, '/'.$needle) !== false) ? 'active' : '';
};
?>
<div class="sidebar">
  <div class="brand">
    <img src="<?= asset('img/logo.png') ?>" alt="Logo"
         class="brand-logo"
         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
    <div class="brand-fallback">PT</div>
    <div class="fw-bold text-white text-truncate" title="<?= htmlspecialchars($companyName) ?>">
      <?= htmlspecialchars($companyName) ?>
    </div>
  </div>

  <div class="nav flex-column">
    <a class="nav-link <?= $active('pages/index.php') ?>" href="<?= url('pages/index.php') ?>">
      <i class="fa-solid fa-chart-line"></i> Dashboard
    </a>

    <div class="nav-section-title">Master Data</div>
    <a class="nav-link <?= $active('pages/stok.php') ?>" href="<?= url('pages/stok.php') ?>">
      <i class="fa-solid fa-box"></i> Stok Barang
    </a>
    <a class="nav-link <?= $active('pages/suppliers.php') ?>" href="<?= url('pages/suppliers.php') ?>">
      <i class="fa-solid fa-truck-field"></i> Supplier
    </a>
    <a class="nav-link <?= $active('pages/customers.php') ?>" href="<?= url('pages/customers.php') ?>">
      <i class="fa-solid fa-people-group"></i> Customer
    </a>

    <div class="nav-section-title">Transaksi</div>
    <?php if ($role !== 'viewer'): ?>
      <a class="nav-link <?= $active('pages/masuk.php') ?>" href="<?= url('pages/masuk.php') ?>">
        <i class="fa-solid fa-arrow-down"></i> Barang Masuk
      </a>
      <a class="nav-link <?= $active('pages/keluar.php') ?>" href="<?= url('pages/keluar.php') ?>">
        <i class="fa-solid fa-arrow-up"></i> Barang Keluar
      </a>
    <?php else: ?>
      <span class="nav-link disabled" aria-disabled="true" title="Hubungi admin untuk akses transaksi">
        <i class="fa-solid fa-lock"></i> Transaksi (terkunci)
      </span>
    <?php endif; ?>

    <div class="nav-section-title">Analitik</div>
    <a class="nav-link <?= $active('pages/laporan.php') ?>" href="<?= url('pages/laporan.php') ?>">
      <i class="fa-solid fa-file-lines"></i> Laporan
    </a>

    <?php if ($role === 'admin'): ?>
      <div class="nav-section-title">Admin</div>
      <a class="nav-link <?= $active('pages/users.php') ?>" href="<?= url('pages/users.php') ?>">
        <i class="fa-solid fa-user-gear"></i> Users
      </a>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    <a href="<?= url('logout.php') ?>" class="btn btn-outline-light w-100">
      <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
    </a>
    
  </div>
</div>

<main class="content">
