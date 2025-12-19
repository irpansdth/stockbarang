<?php
// =============== LAPORAN STOK SAAT INI (FINAL) ===============
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_permission('view_reports');

$conn = isset($koneksi) && $koneksi instanceof mysqli ? $koneksi : null;

// Helper cek kolom
function has_column($conn, $table, $column) {
  $tbl = $conn->real_escape_string($table);
  $col = $conn->real_escape_string($column);
  $res = $conn->query("SHOW COLUMNS FROM `{$tbl}` LIKE '{$col}'");
  return ($res && $res->num_rows > 0);
}

$hasMin  = has_column($conn, 'stock', 'min_stock');
$hasSat  = has_column($conn, 'stock', 'satuan');
$hasLok  = has_column($conn, 'stock', 'lokasi');
$hasDesc = has_column($conn, 'stock', 'deskripsi');
$hasKode = has_column($conn, 'stock', 'kode_barang');

// Filter
$q = trim($_GET['q'] ?? '');

// Ambil data
$where = "1=1";
$params = []; $types = '';
if ($q !== '') {
  $where .= " AND (nama_barang LIKE CONCAT('%',?,'%')";
  if ($hasKode) $where .= " OR kode_barang LIKE CONCAT('%',?,'%')";
  if ($hasDesc) $where .= " OR deskripsi LIKE CONCAT('%',?,'%')";
  $where .= ")";
  $params[] = $q; $types .= 's';
  if ($hasKode) { $params[] = $q; $types .= 's'; }
  if ($hasDesc) { $params[] = $q; $types .= 's'; }
}

$sql = "SELECT id_barang, nama_barang, stock".
       ($hasMin?  ", min_stock":"").
       ($hasSat?  ", satuan":"").
       ($hasLok?  ", lokasi":"").
       ($hasDesc? ", deskripsi":"").
       ($hasKode? ", kode_barang":"").
       " FROM stock WHERE $where ORDER BY nama_barang ASC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;

// ========== EXPORT CSV ==========
if (isset($_GET['export']) && $_GET['export']==='csv') {
  $filename = 'laporan_stok_'.date('Y-m-d_His').'.csv';
  header('Content-Type: text/csv; charset=UTF-8');
  header("Content-Disposition: attachment; filename=\"$filename\"");
  $out = fopen('php://output', 'w');
  // BOM biar Excel rapi
  fprintf($out, "\xEF\xBB\xBF");

  $hdr = ['ID','Kode','Nama Barang','Satuan','Lokasi','Deskripsi','Stok','Min'];
  fputcsv($out, $hdr);

  foreach ($rows as $r) {
    fputcsv($out, [
      $r['id_barang'],
      $hasKode? ($r['kode_barang'] ?? '') : '',
      $r['nama_barang'],
      $hasSat? ($r['satuan'] ?? '') : '',
      $hasLok? ($r['lokasi'] ?? '') : '',
      $hasDesc? ($r['deskripsi'] ?? '') : '',
      (int)$r['stock'],
      $hasMin? (int)$r['min_stock'] : ''
    ]);
  }
  fclose($out);
  exit;
}

// ========== PRINT ==========
if (isset($_GET['print']) && $_GET['print']==='1') {
  ?><!doctype html><html><head><meta charset="utf-8"><title>Cetak Stok Saat Ini</title>
  <style>
    body{font-family:system-ui,Segoe UI,Arial;margin:20px}
    table{border-collapse:collapse;width:100%}
    th,td{border:1px solid #ccc;padding:6px 8px;font-size:13px}
    th{background:#f2f2f2}
  </style>
  </head><body onload="window.print()">
  <h3>Stok Saat Ini</h3>
  <table>
    <thead><tr>
      <th>ID</th><?php if($hasKode):?><th>Kode</th><?php endif; ?>
      <th>Nama Barang</th><?php if($hasSat):?><th>Satuan</th><?php endif; ?>
      <?php if($hasLok):?><th>Lokasi</th><?php endif; ?>
      <?php if($hasDesc):?><th>Deskripsi</th><?php endif; ?>
      <th>Stok</th><?php if($hasMin):?><th>Min</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach($rows as $r):?>
      <tr>
        <td><?= (int)$r['id_barang'] ?></td>
        <?php if($hasKode):?><td><?= htmlspecialchars($r['kode_barang']??'') ?></td><?php endif; ?>
        <td><?= htmlspecialchars($r['nama_barang']) ?></td>
        <?php if($hasSat):?><td><?= htmlspecialchars($r['satuan']??'') ?></td><?php endif; ?>
        <?php if($hasLok):?><td><?= htmlspecialchars($r['lokasi']??'') ?></td><?php endif; ?>
        <?php if($hasDesc):?><td><?= htmlspecialchars($r['deskripsi']??'') ?></td><?php endif; ?>
        <td><?= (int)$r['stock'] ?></td>
        <?php if($hasMin):?><td><?= (int)($r['min_stock']??0) ?></td><?php endif; ?>
      </tr>
    <?php endforeach;?>
    </tbody>
  </table>
  </body></html><?php
  exit;
}

// ========== VIEW ==========
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid px-3 px-xl-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="fw-bold mb-2">Stok Saat Ini</h1>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="<?= url('pages/laporan_stok.php') ?>?print=1" target="_blank"><i class="fa-solid fa-print"></i> Print</a>
      <a class="btn btn-primary" href="<?= url('pages/laporan_stok.php') ?>?export=csv"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
    </div>
  </div>

  <form class="mb-3 d-flex" method="get" action="<?= url('pages/laporan_stok.php') ?>">
    <input class="form-control" name="q" placeholder="Cari nama/kode/deskripsi..." value="<?= htmlspecialchars($q) ?>">
    <button class="btn btn-outline-secondary ms-2" type="submit"><i class="fa-solid fa-search"></i> Cari</button>
  </form>

  <div class="card">
    <div class="card-body">
      <?php if(empty($rows)):?>
        <div class="text-center text-muted py-4">Tidak ada data.</div>
      <?php else:?>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead><tr>
              <th style="width:80px">ID</th><?php if($hasKode):?><th>Kode</th><?php endif;?>
              <th>Nama Barang</th><?php if($hasSat):?><th>Satuan</th><?php endif;?>
              <?php if($hasLok):?><th>Lokasi</th><?php endif;?>
              <?php if($hasDesc):?><th>Deskripsi</th><?php endif;?>
              <th class="text-end">Stok</th><?php if($hasMin):?><th class="text-end">Min</th><?php endif;?>
            </tr></thead>
            <tbody>
              <?php foreach($rows as $r):?>
                <tr>
                  <td><?= (int)$r['id_barang'] ?></td>
                  <?php if($hasKode):?><td><?= htmlspecialchars($r['kode_barang']??'') ?></td><?php endif;?>
                  <td><?= htmlspecialchars($r['nama_barang']) ?></td>
                  <?php if($hasSat):?><td><?= htmlspecialchars($r['satuan']??'') ?></td><?php endif;?>
                  <?php if($hasLok):?><td><?= htmlspecialchars($r['lokasi']??'') ?></td><?php endif;?>
                  <?php if($hasDesc):?><td><?= htmlspecialchars($r['deskripsi']??'') ?></td><?php endif;?>
                  <td class="text-end"><?= (int)$r['stock'] ?></td>
                  <?php if($hasMin):?><td class="text-end"><?= (int)($r['min_stock']??0) ?></td><?php endif;?>
                </tr>
              <?php endforeach;?>
            </tbody>
          </table>
        </div>
      <?php endif;?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
