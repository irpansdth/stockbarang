<?php
// ===== LAPORAN BARANG MASUK — FINAL (auto-detect PK) =====
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_permission('view_reports');

$conn = isset($koneksi) && $koneksi instanceof mysqli ? $koneksi : null;

function has_column($conn, $table, $column){
  $tbl = $conn->real_escape_string($table);
  $col = $conn->real_escape_string($column);
  $res = $conn->query("SHOW COLUMNS FROM `{$tbl}` LIKE '{$col}'");
  return ($res && $res->num_rows > 0);
}
function detect_qty_col($conn, $table) {
  foreach (['qty','jumlah','quantity','volume','jumlah_barang'] as $c) {
    if (has_column($conn, $table, $c)) return $c;
  }
  return 'qty';
}
function detect_pk_col($conn, $table, $candidates = []) {
  if (!$candidates) $candidates = ['id','id_masuk','idbm','id_bm','id_transaksi'];
  foreach ($candidates as $c) {
    if (has_column($conn, $table, $c)) return $c;
  }
  return null; // tidak ada kolom PK yang dikenali
}

$qtyIn   = detect_qty_col($conn, 'barang_masuk');
$pkInCol = detect_pk_col($conn, 'barang_masuk'); // <— DETEKSI PK

$hasKode = has_column($conn, 'stock', 'kode_barang');
$hasSat  = has_column($conn, 'stock', 'satuan');
$hasLok  = has_column($conn, 'stock', 'lokasi');
$hasDesc = has_column($conn, 'stock', 'deskripsi');

$hasSuppId   = has_column($conn, 'barang_masuk', 'supplier_id');
$hasSuppText = has_column($conn, 'barang_masuk', 'supplier');
$hasSuppliersTbl = false;
if ($hasSuppId) {
  $r = $conn->query("SHOW TABLES LIKE 'suppliers'");
  $hasSuppliersTbl = ($r && $r->num_rows > 0);
}

$today = (new DateTime('today'))->format('Y-m-d');
$from  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']   ?? '') ? $_GET['to']   : $today;
$q     = trim($_GET['q'] ?? '');

/* ---------- Query data ---------- */
$select = "";

// Kolom ID (aman jika tidak ada PK)
if ($pkInCol) $select .= "bm.`{$pkInCol}` AS id";
else          $select .= "0 AS id";

$select .= ", bm.tanggal, bm.id_barang, s.nama_barang, bm.$qtyIn AS qty";
if ($hasKode) $select .= ", s.kode_barang";
if ($hasSat)  $select .= ", s.satuan";
if ($hasLok)  $select .= ", s.lokasi";
if ($hasDesc) $select .= ", s.deskripsi";
if ($hasSuppId && $hasSuppliersTbl) $select .= ", sup.nama AS supplier_name";
elseif ($hasSuppText)               $select .= ", bm.supplier AS supplier_name";

$sql = "SELECT $select
        FROM barang_masuk bm
        JOIN stock s ON s.id_barang = bm.id_barang";
if ($hasSuppId && $hasSuppliersTbl) $sql .= " LEFT JOIN suppliers sup ON sup.id = bm.supplier_id";
$sql .= " WHERE DATE(bm.tanggal) BETWEEN ? AND ?";

$params = [$from, $to];
$types  = 'ss';

if ($q !== '') {
  $sql .= " AND (s.nama_barang LIKE CONCAT('%',?,'%')";
  $params[]=$q; $types.='s';
  if ($hasKode) { $sql .= " OR s.kode_barang LIKE CONCAT('%',?,'%')"; $params[]=$q; $types.='s'; }
  if ($hasDesc) { $sql .= " OR s.deskripsi LIKE CONCAT('%',?,'%')";  $params[]=$q; $types.='s'; }
  if ($hasSuppId && $hasSuppliersTbl) { $sql .= " OR sup.nama LIKE CONCAT('%',?,'%')"; $params[]=$q; $types.='s'; }
  if ($hasSuppText) { $sql .= " OR bm.supplier LIKE CONCAT('%',?,'%')"; $params[]=$q; $types.='s'; }
  $sql .= ")";
}

// Urutkan: pakai PK jika ada, jika tidak pakai tanggal
if ($pkInCol) $sql .= " ORDER BY bm.tanggal ASC, bm.`{$pkInCol}` ASC";
else          $sql .= " ORDER BY bm.tanggal ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while($r=$res->fetch_assoc()) $rows[]=$r;

/* ---------- EXPORT CSV (SEBELUM HTML) ---------- */
if (($_GET['export'] ?? '') === 'csv') {
  $filename = 'laporan_masuk_'.$from.'_sd_'.$to.'.csv';
  while (ob_get_level()) ob_end_clean();
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.preg_replace('~[^\\w\\-\\.]+~','_',$filename).'"');
  echo "\xEF\xBB\xBF"; echo "sep=;\r\n";

  $out = fopen('php://output','w');
  fputcsv($out, ['Periode', $from.' s/d '.$to], ';');
  if ($q!=='') fputcsv($out, ['Filter', $q], ';');
  fputcsv($out, [], ';');

  $head = ['ID','Tanggal'];
  if ($hasKode) $head[]='Kode';
  $head[]='Nama Barang';
  if ($hasSat)  $head[]='Satuan';
  if ($hasLok)  $head[]='Lokasi';
  if ($hasDesc) $head[]='Deskripsi';
  if ($hasSuppId || $hasSuppText) $head[]='Supplier';
  $head[]='Qty';
  fputcsv($out, $head, ';');

  foreach($rows as $r){
    $line = [];
    $line[] = (int)$r['id']; // akan 0 jika tidak punya PK
    $line[] = $r['tanggal'];
    if ($hasKode) $line[] = $r['kode_barang'] ?? '';
    $line[] = $r['nama_barang'] ?? '';
    if ($hasSat)  $line[] = $r['satuan'] ?? '';
    if ($hasLok)  $line[] = $r['lokasi'] ?? '';
    if ($hasDesc) $line[] = $r['deskripsi'] ?? '';
    if ($hasSuppId || $hasSuppText) $line[] = $r['supplier_name'] ?? '';
    $line[] = (int)$r['qty'];
    fputcsv($out, $line, ';');
  }
  fclose($out); exit;
}
/* ---------- END EXPORT ---------- */

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid px-3 px-xl-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="fw-bold mb-2">Laporan Barang Masuk</h1>
    <div class="d-flex gap-2">
      <a class="btn btn-success" href="<?= url('pages/laporan_masuk.php').'?from='.urlencode($from).'&to='.urlencode($to).($q!==''?('&q='.urlencode($q)):'').'&export=csv' ?>">
        <i class="fa-solid fa-file-csv"></i> Export CSV
      </a>
      <button class="btn btn-outline-secondary" id="btnPrint"><i class="fa-solid fa-print"></i> Print</button>
    </div>
  </div>

  <form class="row g-2 mb-3" method="get" action="<?= url('pages/laporan_masuk.php') ?>">
    <div class="col-auto">
      <label class="form-label mb-1">Dari</label>
      <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="col-auto">
      <label class="form-label mb-1">Sampai</label>
      <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div class="col-sm-4 col-lg-3">
      <label class="form-label mb-1">Cari</label>
      <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nama/Kode/Deskripsi/Supplier">
    </div>
    <div class="col-auto align-self-end">
      <button class="btn btn-primary">Terapkan</button>
    </div>
  </form>

  <div class="card">
    <div class="card-body" id="printArea">
      <div class="mb-2">
        <small class="text-muted">Periode: <b><?= htmlspecialchars($from) ?> s/d <?= htmlspecialchars($to) ?></b></small>
        <?php if ($q!==''): ?><br><small class="text-muted">Filter: <b><?= htmlspecialchars($q) ?></b></small><?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th style="width:70px">#</th>
              <th>Tanggal</th>
              <?php if ($hasKode): ?><th style="width:160px">Kode</th><?php endif; ?>
              <th>Nama Barang</th>
              <?php if ($hasSat): ?><th style="width:120px">Satuan</th><?php endif; ?>
              <?php if ($hasLok): ?><th style="width:160px">Lokasi</th><?php endif; ?>
              <?php if ($hasDesc): ?><th>Deskripsi</th><?php endif; ?>
              <?php if ($hasSuppId || $hasSuppText): ?><th>Supplier</th><?php endif; ?>
              <th class="text-end" style="width:120px">Qty</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="10" class="text-center text-muted">Tidak ada data.</td></tr>
            <?php else: $i=1; foreach($rows as $r): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars(substr($r['tanggal'],0,19)) ?></td>
                <?php if ($hasKode): ?><td><?= htmlspecialchars($r['kode_barang'] ?? '') ?></td><?php endif; ?>
                <td><?= htmlspecialchars($r['nama_barang'] ?? '') ?></td>
                <?php if ($hasSat): ?><td><?= htmlspecialchars($r['satuan'] ?? '') ?></td><?php endif; ?>
                <?php if ($hasLok): ?><td><?= htmlspecialchars($r['lokasi'] ?? '') ?></td><?php endif; ?>
                <?php if ($hasDesc): ?><td><?= htmlspecialchars($r['deskripsi'] ?? '') ?></td><?php endif; ?>
                <?php if ($hasSuppId || $hasSuppText): ?><td><?= htmlspecialchars($r['supplier_name'] ?? '') ?></td><?php endif; ?>
                <td class="text-end"><?= number_format((int)$r['qty'],0,',','.') ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
document.getElementById('btnPrint')?.addEventListener('click', function(){
  const html = document.getElementById('printArea').innerHTML;
  const w = window.open('', '_blank', 'width=1024,height=768');
  if(!w){ alert('Popup diblokir.'); return; }
  w.document.open();
  w.document.write(`
    <html><head><meta charset="utf-8">
    <title>Cetak Laporan Barang Masuk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>@page{size:auto;margin:10mm} body{padding:10px}</style>
    </head><body>
      <h5>Laporan Barang Masuk</h5>
      <div class="mb-2">
        <small class="text-muted">Periode: <b><?= htmlspecialchars($from) ?> s/d <?= htmlspecialchars($to) ?></b></small>
        <?= $q!=='' ? '<br><small class="text-muted">Filter: <b>'.htmlspecialchars($q).'</b></small>' : '' ?>
      </div>
      ${html}
    </body></html>
  `);
  w.document.close(); w.focus(); w.print();
});
</script>
