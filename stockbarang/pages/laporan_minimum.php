<?php
// ====== PERINGATAN STOK MINIMUM (FINAL) ======
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_permission('view_reports');

$conn = $koneksi;

// helper
function has_column($conn,$table,$column){
  $t=$conn->real_escape_string($table); $c=$conn->real_escape_string($column);
  $r=$conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'"); return ($r && $r->num_rows>0);
}

$hasMin  = has_column($conn,'stock','min_stock');
$hasSat  = has_column($conn,'stock','satuan');
$hasLok  = has_column($conn,'stock','lokasi');
$hasDesc = has_column($conn,'stock','deskripsi');
$hasKode = has_column($conn,'stock','kode_barang');

$threshold = (int)($_GET['threshold'] ?? 5);
if ($threshold < 0) $threshold = 0;

if ($hasMin) {
  $sql = "SELECT id_barang,nama_barang,stock,min_stock".
         ($hasSat?  ",satuan":"").
         ($hasLok?  ",lokasi":"").
         ($hasDesc? ",deskripsi":"").
         ($hasKode? ",kode_barang":"").
         " FROM stock WHERE stock <= min_stock ORDER BY stock ASC";
  $res = $conn->query($sql);
} else {
  $sql = "SELECT id_barang,nama_barang,stock".
         ($hasSat?  ",satuan":"").
         ($hasLok?  ",lokasi":"").
         ($hasDesc? ",deskripsi":"").
         ($hasKode? ",kode_barang":"").
         " FROM stock WHERE stock <= $threshold ORDER BY stock ASC";
  $res = $conn->query($sql);
}
$rows = [];
while($r=$res->fetch_assoc()) $rows[]=$r;

// EXPORT
if (isset($_GET['export']) && $_GET['export']==='csv') {
  $filename = 'stok_minimum_'.date('Y-m-d_His').'.csv';
  header('Content-Type: text/csv; charset=UTF-8');
  header("Content-Disposition: attachment; filename=\"$filename\"");
  $out=fopen('php://output','w'); fprintf($out,"\xEF\xBB\xBF");
  $hdr = ['ID','Kode','Nama Barang','Satuan','Lokasi','Deskripsi','Stok','Min/Threshold'];
  fputcsv($out,$hdr);
  foreach($rows as $r){
    fputcsv($out,[
      $r['id_barang'],
      $hasKode?($r['kode_barang']??''):'',
      $r['nama_barang'],
      $hasSat?($r['satuan']??''):'',
      $hasLok?($r['lokasi']??''):'',
      $hasDesc?($r['deskripsi']??''):'',
      (int)$r['stock'],
      $hasMin? (int)($r['min_stock']??0) : $threshold
    ]);
  }
  fclose($out); exit;
}

// PRINT
if (isset($_GET['print']) && $_GET['print']==='1') {
  ?><!doctype html><html><head><meta charset="utf-8"><title>Cetak Stok Minimum</title>
  <style>body{font-family:system-ui;margin:20px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:6px 8px;font-size:13px}th{background:#f2f2f2}</style>
  </head><body onload="window.print()">
  <h3>Peringatan Stok Minimum</h3>
  <table><thead><tr>
    <th>ID</th><?php if($hasKode):?><th>Kode</th><?php endif;?><th>Nama Barang</th>
    <?php if($hasSat):?><th>Satuan</th><?php endif;?><?php if($hasLok):?><th>Lokasi</th><?php endif;?>
    <?php if($hasDesc):?><th>Deskripsi</th><?php endif;?><th>Stok</th><th>Min/Threshold</th>
  </tr></thead><tbody>
  <?php foreach($rows as $r):?>
  <tr>
    <td><?= (int)$r['id_barang']?></td>
    <?php if($hasKode):?><td><?= htmlspecialchars($r['kode_barang']??'')?></td><?php endif;?>
    <td><?= htmlspecialchars($r['nama_barang'])?></td>
    <?php if($hasSat):?><td><?= htmlspecialchars($r['satuan']??'')?></td><?php endif;?>
    <?php if($hasLok):?><td><?= htmlspecialchars($r['lokasi']??'')?></td><?php endif;?>
    <?php if($hasDesc):?><td><?= htmlspecialchars($r['deskripsi']??'')?></td><?php endif;?>
    <td><?= (int)$r['stock']?></td>
    <td><?= $hasMin? (int)($r['min_stock']??0) : $threshold?></td>
  </tr>
  <?php endforeach; ?></tbody></table></body></html><?php
  exit;
}

// VIEW
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid px-3 px-xl-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="fw-bold">Peringatan Stok Minimum</h1>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="<?= url('pages/laporan_minimum.php') ?>?print=1" target="_blank"><i class="fa-solid fa-print"></i> Print</a>
      <a class="btn btn-primary" href="<?= url('pages/laporan_minimum.php') ?>?export=csv"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
    </div>
  </div>

  <?php if(!$hasMin):?>
    <div class="alert alert-info">Tabel <b>stock</b> tidak memiliki kolom <code>min_stock</code>. Menggunakan threshold: <b><?= (int)$threshold ?></b>. Ubah via parameter <code>?threshold=angka</code>.</div>
  <?php endif;?>

  <div class="card">
    <div class="card-body">
      <?php if(empty($rows)):?>
        <div class="text-center text-muted py-4">Tidak ada barang di bawah batas.</div>
      <?php else:?>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead><tr>
              <th>ID</th><?php if($hasKode):?><th>Kode</th><?php endif;?><th>Nama</th>
              <?php if($hasSat):?><th>Satuan</th><?php endif;?>
              <?php if($hasLok):?><th>Lokasi</th><?php endif;?>
              <?php if($hasDesc):?><th>Deskripsi</th><?php endif;?>
              <th class="text-end">Stok</th><th class="text-end">Min/Threshold</th>
            </tr></thead>
            <tbody>
              <?php foreach($rows as $r):?>
                <tr>
                  <td><?= (int)$r['id_barang']?></td>
                  <?php if($hasKode):?><td><?= htmlspecialchars($r['kode_barang']??'')?></td><?php endif;?>
                  <td><?= htmlspecialchars($r['nama_barang'])?></td>
                  <?php if($hasSat):?><td><?= htmlspecialchars($r['satuan']??'')?></td><?php endif;?>
                  <?php if($hasLok):?><td><?= htmlspecialchars($r['lokasi']??'')?></td><?php endif;?>
                  <?php if($hasDesc):?><td><?= htmlspecialchars($r['deskripsi']??'')?></td><?php endif;?>
                  <td class="text-end"><?= (int)$r['stock']?></td>
                  <td class="text-end"><?= $hasMin? (int)($r['min_stock']??0) : $threshold?></td>
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
