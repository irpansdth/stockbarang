<?php
// =============== KARTU STOK (FINAL: FIX COMMANDS OUT OF SYNC) ===============
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors','1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_permission('view_reports');

$conn = $koneksi;

// Helpers
function has_column($conn,$table,$column){
  $t=$conn->real_escape_string($table); $c=$conn->real_escape_string($column);
  $r=$conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return ($r && $r->num_rows>0);
}
function detect_qty($conn,$table){
  foreach(['qty','jumlah','kuantitas','quantity','volume','jumlah_barang'] as $c){
    if(has_column($conn,$table,$c)) return $c;
  }
  return null;
}

$qtyIn  = detect_qty($conn,'barang_masuk');
$qtyOut = detect_qty($conn,'barang_keluar');

$hasKode = has_column($conn,'stock','kode_barang');
$hasSat  = has_column($conn,'stock','satuan');
$hasLok  = has_column($conn,'stock','lokasi');

// Dropdown barang
$items=[];
$colItems = "id_barang,nama_barang".($hasKode?",kode_barang":"");
$res = $conn->query("SELECT $colItems FROM stock ORDER BY nama_barang ASC");
if ($res){ while($o=$res->fetch_assoc()) $items[]=$o; $res->free(); }

$idBarang = (int)($_GET['barang'] ?? ($items[0]['id_barang'] ?? 0));
$from     = $_GET['from'] ?? date('Y-m-01');
$to       = $_GET['to']   ?? date('Y-m-d');

$rows = [];
$saldoAwal = 0.0;
$info = [];

// Info barang (untuk header cetak/CSV)
if ($idBarang > 0) {
  $infoCols = "id_barang,nama_barang".($hasKode?",kode_barang":"").($hasSat?",satuan":"").($hasLok?",lokasi":"");
  $st = $conn->prepare("SELECT $infoCols FROM stock WHERE id_barang=?");
  $st->bind_param('i',$idBarang);
  $st->execute();
  $r = $st->get_result();
  $info = $r ? ($r->fetch_assoc() ?: []) : [];
  if ($r) $r->free();
  $st->close();
}

if ($idBarang > 0) {
  // Saldo awal (s/d hari sebelum from)
  $fromPrev = date('Y-m-d', strtotime($from.' -1 day'));
  $in0 = 0.0; $out0 = 0.0;

  if ($qtyIn) {
    $st = $conn->prepare("SELECT COALESCE(SUM($qtyIn),0) as total FROM barang_masuk WHERE id_barang=? AND tanggal <= ?");
    $st->bind_param('is', $idBarang, $fromPrev);
    $st->execute();
    $r = $st->get_result();
    if ($r) { $row = $r->fetch_assoc(); $in0 = (float)($row['total'] ?? 0); $r->free(); }
    $st->close();
  }

  if ($qtyOut) {
    $st = $conn->prepare("SELECT COALESCE(SUM($qtyOut),0) as total FROM barang_keluar WHERE id_barang=? AND tanggal <= ?");
    $st->bind_param('is', $idBarang, $fromPrev);
    $st->execute();
    $r = $st->get_result();
    if ($r) { $row = $r->fetch_assoc(); $out0 = (float)($row['total'] ?? 0); $r->free(); }
    $st->close();
  }

  $saldoAwal = $in0 - $out0;

  // Mutasi IN dalam periode
  if ($qtyIn) {
    $st = $conn->prepare("SELECT tanggal as tgl, $qtyIn as qty, 'IN' as tipe FROM barang_masuk WHERE id_barang=? AND tanggal BETWEEN ? AND ?");
    $st->bind_param('iss', $idBarang, $from, $to);
    $st->execute();
    $r = $st->get_result();
    if ($r) {
      while ($row = $r->fetch_assoc()) $rows[] = $row;
      $r->free();
    }
    $st->close();
  }

  // Mutasi OUT dalam periode
  if ($qtyOut) {
    $st = $conn->prepare("SELECT tanggal as tgl, $qtyOut as qty, 'OUT' as tipe FROM barang_keluar WHERE id_barang=? AND tanggal BETWEEN ? AND ?");
    $st->bind_param('iss', $idBarang, $from, $to);
    $st->execute();
    $r = $st->get_result();
    if ($r) {
      while ($row = $r->fetch_assoc()) $rows[] = $row;
      $r->free();
    }
    $st->close();
  }

  // Urutkan berdasarkan tanggal
  usort($rows, function($a,$b){ return strcmp($a['tgl'],$b['tgl']); });
}

// ========== EXPORT CSV ==========
if (isset($_GET['export']) && $_GET['export']==='csv') {
  $filename = 'kartu_stok_'.$idBarang.'_'.str_replace('-','',$from).'_sd_'.str_replace('-','',$to).'.csv';
  header('Content-Type: text/csv; charset=UTF-8');
  header("Content-Disposition: attachment; filename=\"$filename\"");
  $out = fopen('php://output','w');
  fprintf($out, "\xEF\xBB\xBF"); // BOM

  // Header informasi
  fputcsv($out, ['Barang', $info['nama_barang'] ?? '']);
  if ($hasKode) fputcsv($out, ['Kode', $info['kode_barang'] ?? '']);
  if ($hasSat)  fputcsv($out, ['Satuan', $info['satuan'] ?? '']);
  if ($hasLok)  fputcsv($out, ['Lokasi', $info['lokasi'] ?? '']);
  fputcsv($out, ['Periode', "$from s/d $to"]);
  fputcsv($out, []); // spacer

  // Tabel kartu stok
  fputcsv($out, ['Tanggal','Tipe','Qty','Saldo']);
  $saldo = $saldoAwal;
  fputcsv($out, [$from, 'Saldo Awal', '', $saldo]);
  foreach ($rows as $row) {
    $qty = (float) $row['qty'];
    $saldo += ($row['tipe'] === 'IN' ? $qty : -$qty);
    fputcsv($out, [$row['tgl'], $row['tipe']==='IN'?'Masuk':'Keluar', $qty, $saldo]);
  }

  fclose($out);
  exit;
}

// ========== PRINT ==========
if (isset($_GET['print']) && $_GET['print']==='1') {
  ?><!doctype html><html><head><meta charset="utf-8"><title>Cetak Kartu Stok</title>
  <style>
    body{font-family:system-ui,Segoe UI,Arial;margin:20px}
    table{border-collapse:collapse;width:100%}
    th,td{border:1px solid #ccc;padding:6px 8px;font-size:13px}
    th{background:#f2f2f2}
  </style>
  </head><body onload="window.print()">
  <h3>Kartu Stok</h3>
  <p>
    <b>Barang:</b> <?= htmlspecialchars($info['nama_barang'] ?? '-') ?>
    <?php if($hasKode && !empty($info['kode_barang'])):?>
      (<?= htmlspecialchars($info['kode_barang'])?>)
    <?php endif; ?>
    <?php if($hasSat && !empty($info['satuan'])):?> — <b>Satuan:</b> <?= htmlspecialchars($info['satuan'])?><?php endif; ?>
    <?php if($hasLok && !empty($info['lokasi'])):?> — <b>Lokasi:</b> <?= htmlspecialchars($info['lokasi'])?><?php endif; ?>
    <br><b>Periode:</b> <?= htmlspecialchars("$from s/d $to") ?>
  </p>
  <table>
    <thead><tr><th>Tanggal</th><th>Tipe</th><th>Qty</th><th>Saldo</th></tr></thead>
    <tbody>
      <tr><td><?= htmlspecialchars($from) ?></td><td><i>Saldo Awal</i></td><td></td><td><?= $saldoAwal ?></td></tr>
      <?php $saldo=$saldoAwal; foreach($rows as $row): $q=(float)$row['qty']; $saldo += ($row['tipe']==='IN'?$q:-$q); ?>
        <tr>
          <td><?= htmlspecialchars($row['tgl']) ?></td>
          <td><?= $row['tipe']==='IN'?'Masuk':'Keluar' ?></td>
          <td><?= $q ?></td>
          <td><?= $saldo ?></td>
        </tr>
      <?php endforeach; ?>
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
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="fw-bold mb-2">Kartu Stok</h1>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary"
         href="<?= url('pages/kartu_stok.php') ?>?barang=<?= (int)$idBarang ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&print=1"
         target="_blank"><i class="fa-solid fa-print"></i> Print</a>
      <a class="btn btn-primary"
         href="<?= url('pages/kartu_stok.php') ?>?barang=<?= (int)$idBarang ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=csv">
         <i class="fa-solid fa-file-csv"></i> Export CSV</a>
    </div>
  </div>

  <form class="row g-2 mb-3" method="get" action="<?= url('pages/kartu_stok.php') ?>">
    <div class="col-md-5">
      <select class="form-select" name="barang" required>
        <?php foreach($items as $it): ?>
          <option value="<?= (int)$it['id_barang'] ?>" <?= $it['id_barang']==$idBarang ? 'selected':'' ?>>
            <?= $hasKode && !empty($it['kode_barang']) ? '['.htmlspecialchars($it['kode_barang']).'] ' : '' ?>
            <?= htmlspecialchars($it['nama_barang']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3"><input type="date" class="form-control" name="from" value="<?= htmlspecialchars($from) ?>"></div>
    <div class="col-md-3"><input type="date" class="form-control" name="to"   value="<?= htmlspecialchars($to) ?>"></div>
    <div class="col-md-1"><button class="btn btn-outline-secondary w-100" type="submit"><i class="fa-solid fa-rotate"></i></button></div>
  </form>

  <div class="card">
    <div class="card-body">
      <?php if ($idBarang<=0): ?>
        <div class="text-muted text-center py-4">Pilih barang terlebih dahulu.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>Tanggal</th><th>Tipe</th><th class="text-end">Qty</th><th class="text-end">Saldo</th></tr></thead>
            <tbody>
              <tr><td><?= htmlspecialchars($from) ?></td><td><em>Saldo Awal</em></td><td class="text-end">—</td><td class="text-end"><?= $saldoAwal ?></td></tr>
              <?php $saldo=$saldoAwal; foreach($rows as $row): $q=(float)$row['qty']; $saldo += ($row['tipe']==='IN'?$q:-$q); ?>
                <tr>
                  <td><?= htmlspecialchars($row['tgl']) ?></td>
                  <td><?= $row['tipe']==='IN' ? 'Masuk':'Keluar' ?></td>
                  <td class="text-end"><?= $q ?></td>
                  <td class="text-end"><?= $saldo ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
