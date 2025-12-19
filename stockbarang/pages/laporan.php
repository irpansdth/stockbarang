<?php
// =============== LAPORAN — HUB ===============
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_permission('view_reports');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

$cards = [
  [
    'title' => 'Laporan Barang Masuk',
    'icon'  => 'fa-arrow-down',
    'desc'  => 'Daftar transaksi barang masuk dengan filter tanggal, barang, dan supplier. Bisa export CSV/print.',
    'href'  => url('pages/laporan_masuk.php'),
    'badge' => 'Transaksi',
  ],
  [
    'title' => 'Laporan Barang Keluar',
    'icon'  => 'fa-arrow-up',
    'desc'  => 'Daftar transaksi barang keluar dengan filter tanggal, barang, dan customer. Bisa export CSV/print.',
    'href'  => url('pages/laporan_keluar.php'),
    'badge' => 'Transaksi',
  ],
  [
    'title' => 'Laporan Stok Saat Ini',
    'icon'  => 'fa-boxes-stacked',
    'desc'  => 'Snapshot stok per barang (kode, nama, stok, satuan, lokasi, min stock).',
    'href'  => url('pages/laporan_stok.php'),
    'badge' => 'Stok',
  ],
  [
    'title' => 'Peringatan Stok Minimum',
    'icon'  => 'fa-triangle-exclamation',
    'desc'  => 'Barang di bawah ambang minimum. Cocok untuk kebutuhan restock cepat.',
    'href'  => url('pages/laporan_minimum.php'),
    'badge' => 'Stok',
  ],
  [
    'title' => 'Kartu Stok (Per Barang)',
    'icon'  => 'fa-clipboard-list',
    'desc'  => 'Mutasi per barang dalam periode: saldo awal, masuk, keluar, saldo akhir.',
    'href'  => url('pages/kartu_stok.php'),
    'badge' => 'Analitik',
  ],
  [
    'title' => 'Ringkasan Bulanan',
    'icon'  => 'fa-chart-column',
    'desc'  => 'Rekap bulanan: total masuk/keluar, dan barang teratas yang paling banyak keluar.',
    'href'  => url('pages/laporan_ringkasan_bulanan.php'),
    'badge' => 'Analitik',
  ],
];
?>
<div class="container-fluid px-3 px-xl-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="fw-bold mb-2">Laporan</h1>
  </div>

  <ol class="breadcrumb mb-4">
    <li class="breadcrumb-item"><a href="<?= url('pages/index.php') ?>">Home</a></li>
    <li class="breadcrumb-item active">Laporan</li>
  </ol>

  <div class="row g-3">
    <?php foreach ($cards as $c): ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-start justify-content-between mb-2">
              <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-secondary"><?= htmlspecialchars($c['badge']) ?></span>
              </div>
              <i class="fa-solid <?= htmlspecialchars($c['icon']) ?> opacity-50"></i>
            </div>
            <h5 class="card-title mb-1"><?= htmlspecialchars($c['title']) ?></h5>
            <p class="text-muted mb-4" style="min-height: 60px;"><?= htmlspecialchars($c['desc']) ?></p>
            <div class="mt-auto d-flex gap-2">
              <a class="btn btn-primary" href="<?= $c['href'] ?>"><i class="fa-solid fa-folder-open me-1"></i> Buka</a>
              <a class="btn btn-outline-secondary" href="<?= $c['href'] ?>"><i class="fa-regular fa-eye me-1"></i> Lihat</a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="alert alert-info mt-4" role="alert">
    <i class="fa-regular fa-circle-question me-1"></i>
    Pilih salah satu tipe laporan di atas. Jika kamu butuh format khusus (mis. rekap per supplier/customer), kita bisa tambahkan submenu lanjutan.
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
