<?php
// =============== RINGKASAN BULANAN (FINAL) ===============
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors','1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_permission('view_reports');

$conn = $koneksi;

function has_column($conn,$table,$column){
  $t=$conn->real_escape_string($table); $c=$conn->real_escape_string($column);
  $r=$conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'"); return ($r && $r->num_rows>0);
}
function detect_qty($conn,$table){
  foreach(['qty','jumlah','kuantitas','quantity','volume','jumlah_barang'] as $c){
    if(has_column($conn,$table,$c)) return $c;
  } return null;
}
$qtyIn  = detect_qty($conn,'barang_masuk');
$qtyOut = detect_qty($conn,'barang_keluar');

$hasKode = has_column($conn,'stock','kode_barang');

$ym = $_GET['m'] ?? date('Y-m'); // bulan yang dipilih
list($yy,$mm) = explode('-',$ym);
$start = "$ym-01";
$end   = date('Y-m-t', strtotime($start));

// Total masuk/keluar bulan ini
$totIn = 0; $totOut = 0;
if ($qtyIn){
  $st=$conn->prepare("SELECT COALESCE(SUM($qtyIn),0) FROM barang_masuk WHERE tanggal BETWEEN ? AND ?");
  $st->bind_param('ss',$start,$end); $st->execute(); $totIn=(float)$st->get_result()->fetch_row()[0];
}
if ($qtyOut){
  $st=$conn->prepare("SELECT COALESCE(SUM($qtyOut),0) FROM barang_keluar WHERE tanggal BETWEEN ? AND ?");
  $st->bind_param('ss',$start,$end); $st->execute(); $totOut=(float)$st->get_result()->fetch_row()[0];
}

// Top N Barang keluar
$topN = 10;
$top = [];
if ($qtyOut){
  $q = "SELECT s.id_barang, s.nama_barang".($hasKode?", s.kode_barang":"").", COALESCE(SUM(k.$qtyOut),0) total
        FROM barang_keluar k JOIN stock s ON s.id_barang=k.id_barang
        WHERE k.tanggal BETWEEN ? AND ?
        GROUP BY s.id_barang,s.nama_barang".($hasKode?",s.kode_barang":"")."
        ORDER BY total DESC LIMIT $topN";
  $st=$conn->prepare($q); $st->bind_param('ss',$start,$end); $st->execute();
  $rs=$st->get_result(); while($r=$rs->fetch_assoc()) $top[]=$r;
}

// EXPORT CSV
if (isset($_GET['export']) && $_GET['export']==='csv') {
  $filename = "ringkasan_$ym.csv";
  header('Content-Type:text/csv; charset=UTF-8');
  header("Content-Disposition: attachment; filename=\"$filename\"");
  $out=fopen('php://output','w'); fprintf($out,"\xEF\xBB\xBF");
  fputcsv($out,["Ringkasan Bulan",$ym]);
  fputcsv($out,["Total Masuk",$totIn]);
  fputcsv($out,["Total Keluar",$totOut]);
  fputcsv($out,[]);
  fputcsv($out,["Top $topN Barang Keluar"]);
  $hdr=['ID','Kode','Nama','Total Keluar']; fputcsv($out,$hdr);
  foreach($top as $r){
    fputcsv($out,[$r['id_barang'], $hasKode?($r['kode_barang']??''):'', $r['nama_barang'], (float)$r['total']]);
  }
  fclose($out); exit;
}

// PRINT
if (isset($_GET['print']) && $_GET['print']==='1') {
  ?><!doctype html><html><head><meta charset="utf-8"><title>Cetak Ringkasan</title>
  <style>body{font-family:system-ui;margin:20px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:6px 8px;font-size:13px}th{background:#f2f2f2}</style>
  </head><body onload="window.print()">
  <h3>Ringkasan Bulanan (<?= htmlspecialchars($ym) ?>)</h3>
  <p><b>Total Masuk:</b> <?= $totIn ?> &nbsp;&nbsp; <b>Total Keluar:</b> <?= $totOut ?></p>
  <h4>Top <?= (int)$topN ?> Barang Keluar</h4>
  <table><thead><tr><th>ID</th><?php if($hasKode):?><th>Kode</th><?php endif;?><th>Nama</th><th>Total</th></tr></thead><tbody>
    <?php foreach($top as $r):?>
      <tr><td><?= (int)$r['id_barang']?></td><?php if($hasKode):?><td><?= htmlspecialchars($r['kode_barang']??'')?></td><?php endif;?><td><?= htmlspecialchars($r['nama_barang'])?></td><td><?= (float)$r['total']?></td></tr>
    <?php endforeach;?>
  </tbody></table>
  </body></html><?php
  exit;
}

// VIEW
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid px-3 px-xl-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="fw-bold mb-2">Ringkasan Bulanan</h1>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="<?= url('pages/laporan_ringkasan_bulanan.php') ?>?m=<?= urlencode($ym) ?>&print=1" target="_blank"><i class="fa-solid fa-print"></i> Print</a>
      <a class="btn btn-primary" href="<?= url('pages/laporan_ringkasan_bulanan.php') ?>?m=<?= urlencode($ym) ?>&export=csv"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
    </div>
  </div>

  <form class="d-flex gap-2 mb-3" method="get" action="<?= url('pages/laporan_ringkasan_bulanan.php') ?>">
    <input type="month" class="form-control" name="m" value="<?= htmlspecialchars($ym) ?>">
    <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-rotate"></i></button>
  </form>

  <div class="row g-3">
    <div class="col-md-4">
      <div class="card h-100"><div class="card-body">
        <h6 class="text-muted">Total Masuk</h6>
        <div class="display-6"><?= $totIn ?></div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card h-100"><div class="card-body">
        <h6 class="text-muted">Total Keluar</h6>
        <div class="display-6"><?= $totOut ?></div>
      </div></div>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-header"><i class="fa-solid fa-ranking-star me-1"></i> Top <?= (int)$topN ?> Barang Keluar (<?= htmlspecialchars($ym) ?>)</div>
    <div class="card-body">
      <?php if(empty($top)):?>
        <div class="text-center text-muted py-4">Belum ada data.</div>
      <?php else:?>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead><tr><th>ID</th><?php if($hasKode):?><th>Kode</th><?php endif;?><th>Nama</th><th class="text-end">Total</th></tr></thead>
            <tbody>
              <?php foreach($top as $r):?>
                <tr>
                  <td><?= (int)$r['id_barang']?></td>
                  <?php if($hasKode):?><td><?= htmlspecialchars($r['kode_barang']??'')?></td><?php endif;?>
                  <td><?= htmlspecialchars($r['nama_barang'])?></td>
                  <td class="text-end"><?= (float)$r['total']?></td>
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
