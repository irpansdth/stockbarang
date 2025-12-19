<?php
// ===== LAPORAN BARANG KELUAR =====
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_permission('view_reports');

$conn = isset($koneksi) && $koneksi instanceof mysqli ? $koneksi : null;

function has_column($conn, $table, $column) {
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

$hasKode = has_column($conn,'stock','kode_barang');
$hasSat  = has_column($conn,'stock','satuan');
$hasLok  = has_column($conn,'stock','lokasi');
$hasDesc = has_column($conn,'stock','deskripsi');
$qtyCol  = detect_qty_col($conn, 'barang_keluar');

$today      = (new DateTime('today'))->format('Y-m-d');
$from       = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to         = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']   ?? '') ? $_GET['to']   : $today;
$idBarang   = (int)($_GET['barang'] ?? 0);

$where  = "DATE(k.tanggal) BETWEEN ? AND ?";
$params = [$from, $to];
$types  = 'ss';
if ($idBarang > 0) { $where .= " AND k.id_barang = ?"; $params[]=$idBarang; $types.='i'; }

$sql = "SELECT k.id_keluar AS id_keluar, k.tanggal,
               s.nama_barang,
               ".($hasKode?'s.kode_barang,':'')."
               ".($hasSat?'s.satuan,':'')."
               ".($hasLok?'s.lokasi,':'')."
               ".($hasDesc?'s.deskripsi,':'')."
               k.$qtyCol AS qty
        FROM barang_keluar k
        JOIN stock s ON s.id_barang = k.id_barang
        WHERE $where
        ORDER BY k.tanggal ASC, k.id_keluar ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;

// ===== EXPORT CSV =====
if (($_GET['export'] ?? '') === 'csv') {
  $filename = 'laporan_keluar_'.$from.'_sd_'.$to.'.csv';
  while (ob_get_level()) ob_end_clean();

  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');

  echo "\xEF\xBB\xBF";
  echo "sep=;\r\n";

  $out = fopen('php://output', 'w');

  $head = ['ID','Tanggal'];
  if ($hasKode) $head[]='Kode';
  $head[]='Nama Barang';
  if ($hasSat)  $head[]='Satuan';
  if ($hasLok)  $head[]='Lokasi';
  if ($hasDesc) $head[]='Deskripsi';
  $head[]='Qty';
  fputcsv($out, $head, ';');

  foreach ($rows as $r) {
    $tgl = '="'.($r['tanggal'] ?? '').'"';
    $line = [(int)$r['id_keluar'], $tgl];
    if ($hasKode) $line[] = $r['kode_barang'] ?? '';
    $line[] = $r['nama_barang'] ?? '';
    if ($hasSat)  $line[] = $r['satuan'] ?? '';
    if ($hasLok)  $line[] = $r['lokasi'] ?? '';
    if ($hasDesc) $line[] = $r['deskripsi'] ?? '';
    $line[] = (int)$r['qty'];
    fputcsv($out, $line, ';');
  }
  fclose($out);
  exit;
}
// ===== END EXPORT =====

// dropdown barang
$barangList = [];
$brs = $conn->query("SELECT id_barang, nama_barang FROM stock ORDER BY nama_barang ASC");
while($b = $brs->fetch_assoc()) $barangList[] = $b;

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid px-3 px-xl-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="fw-bold mb-2">Laporan Barang Keluar</h1>
    <div class="d-flex gap-2">
      <a class="btn btn-success" href="<?= url('pages/laporan_keluar.php').'?from='.urlencode($from).'&to='.urlencode($to).($idBarang?('&barang='.$idBarang):'').'&export=csv' ?>">
        <i class="fa-solid fa-file-csv"></i> Export CSV
      </a>
      <button class="btn btn-outline-secondary" id="btnPrint"><i class="fa-solid fa-print"></i> Print</button>
    </div>
  </div>

  <form class="row g-2 mb-3" method="get" action="<?= url('pages/laporan_keluar.php') ?>">
    <div class="col-auto">
      <label class="form-label mb-1">Dari</label>
      <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="col-auto">
      <label class="form-label mb-1">Sampai</label>
      <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div class="col-auto">
      <label class="form-label mb-1">Barang</label>
      <select name="barang" class="form-select">
        <option value="0">Semua</option>
        <?php foreach($barangList as $b): ?>
          <option value="<?= (int)$b['id_barang'] ?>" <?= $idBarang==$b['id_barang']?'selected':'' ?>>
            <?= htmlspecialchars($b['nama_barang']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto align-self-end">
      <button class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Tampilkan</button>
    </div>
  </form>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive" id="printArea">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Tanggal</th>
              <?php if ($hasKode): ?><th>Kode</th><?php endif; ?>
              <th>Nama Barang</th>
              <?php if ($hasSat):  ?><th>Satuan</th><?php endif; ?>
              <?php if ($hasLok):  ?><th>Lokasi</th><?php endif; ?>
              <?php if ($hasDesc): ?><th>Deskripsi</th><?php endif; ?>
              <th class="text-end" style="width:120px;">Qty</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="9" class="text-center text-muted">Tidak ada data</td></tr>
            <?php else: $i=1; foreach($rows as $r): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars(substr($r['tanggal'],0,10)) ?></td>
                <?php if ($hasKode): ?><td><?= htmlspecialchars($r['kode_barang'] ?? '') ?></td><?php endif; ?>
                <td><?= htmlspecialchars($r['nama_barang'] ?? '') ?></td>
                <?php if ($hasSat):  ?><td><?= htmlspecialchars($r['satuan'] ?? '') ?></td><?php endif; ?>
                <?php if ($hasLok):  ?><td><?= htmlspecialchars($r['lokasi'] ?? '') ?></td><?php endif; ?>
                <?php if ($hasDesc): ?><td><?= htmlspecialchars($r['deskripsi'] ?? '') ?></td><?php endif; ?>
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
  const tableHTML = document.getElementById('printArea').innerHTML;
  const w = window.open('', '_blank', 'width=1024,height=768');
  if(!w){ alert('Popup diblokir. Izinkan pop-up untuk mencetak.'); return; }
  w.document.open();
  w.document.write(`
    <html>
      <head>
        <meta charset="utf-8">
        <title>Cetak Laporan Keluar</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>@page{size:auto;margin:10mm} body{padding:10px}</style>
      </head>
      <body>
        <h5>Laporan Barang Keluar (<?= htmlspecialchars($from) ?> s/d <?= htmlspecialchars($to) ?>)</h5>
        ${tableHTML}
      </body>
    </html>
  `);
  w.document.close();
  w.focus();
  w.print();
});
</script>
