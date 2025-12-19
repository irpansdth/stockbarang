<?php
// ========== DASHBOARD LOGIC ==========
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';



date_default_timezone_set('Asia/Jakarta');

// ---- INPUT FILTER ----
$ym_default = (new DateTime('now'))->format('Y-m');
$selectedYm = preg_match('/^\d{4}\-\d{2}$/', $_GET['m'] ?? '') ? $_GET['m'] : $ym_default;
$topLimit = (int) ($_GET['top'] ?? 10);
if ($topLimit < 3)
  $topLimit = 3;
if ($topLimit > 30)
  $topLimit = 30;

// Ambang stok minimum fallback (dipakai jika kolom min_stock belum ada)
$minThreshold = (int) ($_GET['threshold'] ?? 5);
if ($minThreshold < 1)
  $minThreshold = 1;

// Bulan lalu
$dt = DateTime::createFromFormat('Y-m', $selectedYm)->setDate(substr($selectedYm, 0, 4), substr($selectedYm, 5, 2), 1);
$prev = (clone $dt)->modify('-1 month')->format('Y-m');

// ---- KONEKSI ----
$conn = null;
if (isset($koneksi) && $koneksi instanceof mysqli)
  $conn = $koneksi;
if (!$conn && isset($pdo) && $pdo instanceof PDO)
  $conn = $pdo;

// ---- HELPERS DB ----
function db_fetch_value($conn, $sql, $params = [])
{
  if ($conn instanceof mysqli) {
    $stmt = $conn->prepare($sql);
    if ($params) {
      $types = str_repeat('s', count($params));
      $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_row() : null;
    return $row ? (float) $row[0] : 0;
  } elseif ($conn instanceof PDO) {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return (float) $stmt->fetchColumn();
  }
  return 0;
}
function db_fetch_all($conn, $sql, $params = [])
{
  if ($conn instanceof mysqli) {
    $stmt = $conn->prepare($sql);
    if ($params) {
      $types = str_repeat('s', count($params));
      $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
      while ($r = $res->fetch_assoc())
        $rows[] = $r;
    }
    return $rows;
  } elseif ($conn instanceof PDO) {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  return [];
}
function has_column($conn, $table, $column)
{
  if ($conn instanceof mysqli) {
    // SHOW tidak bisa di-prepare -> pakai query biasa + escape aman
    $tbl = $conn->real_escape_string($table);
    $col = $conn->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `{$tbl}` LIKE '{$col}'";
    $res = $conn->query($sql);
    return ($res && $res->num_rows > 0);
  } elseif ($conn instanceof PDO) {
    // Pakai INFORMATION_SCHEMA (bisa di-prepare)
    $stmt = $conn->prepare("
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
      LIMIT 1
    ");
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
  }
  return false;
}


function detect_qty_col($conn, $table)
{
  foreach (['qty', 'jumlah', 'kuantitas', 'quantity', 'volume', 'jumlah_barang'] as $c) {
    if (has_column($conn, $table, $c))
      return $c;
  }
  return null;
}
function delta_info($curr, $prev)
{
  $diff = $curr - $prev;
  if ($prev == 0) {
    $pct = $curr > 0 ? 100 : 0;
  } else {
    $pct = ($diff / $prev) * 100;
  }
  $icon = $diff > 0 ? 'fa-arrow-up' : ($diff < 0 ? 'fa-arrow-down' : 'fa-minus');
  $cls = $diff > 0 ? 'bg-success' : ($diff < 0 ? 'bg-danger' : 'bg-secondary');
  $aria = $diff > 0 ? 'Naik' : ($diff < 0 ? 'Turun' : 'Tetap');
  return [$diff, $pct, $icon, $cls, $aria];
}

// ---- DETECT SCHEMA ----
$qtyMasukCol = detect_qty_col($conn, 'barang_masuk');
$qtyKeluarCol = detect_qty_col($conn, 'barang_keluar');
$hasMinStock = has_column($conn, 'stock', 'min_stock');

// ---- KPI MASUK/KELUAR ----
if ($qtyMasukCol) {
  $in_curr = db_fetch_value($conn, "SELECT COALESCE(SUM($qtyMasukCol),0) FROM barang_masuk WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?", [$selectedYm]);
  $in_prev = db_fetch_value($conn, "SELECT COALESCE(SUM($qtyMasukCol),0) FROM barang_masuk WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?", [$prev]);
} else {
  $in_curr = db_fetch_value($conn, "SELECT COUNT(*) FROM barang_masuk WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?", [$selectedYm]);
  $in_prev = db_fetch_value($conn, "SELECT COUNT(*) FROM barang_masuk WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?", [$prev]);
}
if ($qtyKeluarCol) {
  $out_curr = db_fetch_value($conn, "SELECT COALESCE(SUM($qtyKeluarCol),0) FROM barang_keluar WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?", [$selectedYm]);
  $out_prev = db_fetch_value($conn, "SELECT COALESCE(SUM($qtyKeluarCol),0) FROM barang_keluar WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?", [$prev]);
} else {
  $out_curr = db_fetch_value($conn, "SELECT COUNT(*) FROM barang_keluar WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?", [$selectedYm]);
  $out_prev = db_fetch_value($conn, "SELECT COUNT(*) FROM barang_keluar WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?", [$prev]);
}

// ---- TOTAL STOK ----
$totalStok = db_fetch_value($conn, "SELECT COALESCE(SUM(stock),0) FROM stock");

// ---- TOP N KELUAR (bawa id_barang untuk link) ----
if ($qtyKeluarCol) {
  $topKeluar = db_fetch_all(
    $conn,
    "SELECT s.id_barang AS id, s.nama_barang AS label, COALESCE(SUM(k.$qtyKeluarCol),0) AS total
     FROM barang_keluar k
     JOIN stock s ON s.id_barang = k.id_barang
     WHERE DATE_FORMAT(k.tanggal, '%Y-%m') = ?
     GROUP BY s.id_barang, s.nama_barang
     ORDER BY total DESC
     LIMIT $topLimit",
    [$selectedYm]
  );
} else {
  $topKeluar = db_fetch_all(
    $conn,
    "SELECT s.id_barang AS id, s.nama_barang AS label, COUNT(*) AS total
     FROM barang_keluar k
     JOIN stock s ON s.id_barang = k.id_barang
     WHERE DATE_FORMAT(k.tanggal, '%Y-%m') = ?
     GROUP BY s.id_barang, s.nama_barang
     ORDER BY total DESC
     LIMIT $topLimit",
    [$selectedYm]
  );
}
$chartLabels = array_map(fn($r) => $r['label'], $topKeluar);
$chartData = array_map(fn($r) => (int) $r['total'], $topKeluar);

// ---- EMPTY STATE FLAGS (chart/tabel Top-N) ----
$hasTopRows = !empty($topKeluar);
$hasTopValue = false;
foreach ($chartData as $v) {
  if ((int) $v > 0) {
    $hasTopValue = true;
    break;
  }
}
$showChart = $hasTopRows && $hasTopValue;

// ---- LOW STOCK (peringatan stok minimum) ----
if ($hasMinStock) {
  $lowStock = db_fetch_all(
    $conn,
    "SELECT id_barang, nama_barang, stock, min_stock
     FROM stock
     WHERE stock <= min_stock
     ORDER BY stock ASC, nama_barang ASC
     LIMIT 10"
  );
  $lowTitle = "Peringatan Stok Minimum";
  $lowSub = "Barang dengan stok ≤ min_stock";
} else {
  $lowStock = db_fetch_all(
    $conn,
    "SELECT id_barang, nama_barang, stock
     FROM stock
     WHERE stock <= ?
     ORDER BY stock ASC, nama_barang ASC
     LIMIT 10",
    [$minThreshold]
  );
  $lowTitle = "Peringatan Stok Rendah (threshold ≤ {$minThreshold})";
  $lowSub = "Ganti ambang via parameter ?threshold=angka";
}
$hasLowStock = !empty($lowStock);

// ---- DELTA INFO ----
[$in_diff, $in_pct, $in_icon, $in_badge, $in_aria] = delta_info($in_curr, $in_prev);
[$out_diff, $out_pct, $out_icon, $out_badge, $out_aria] = delta_info($out_curr, $out_prev);

// ========== VIEW ==========
?>


<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <h1 class="mt-2 mb-0">Dashboard</h1>

  <!-- FILTER BULAN + TOP-N (+ threshold kalau min_stock belum ada) -->
  <form class="d-flex gap-2 align-items-center" method="get" action="<?= url('pages/index.php') ?>">
    <div class="input-group">
      <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
      <input type="month" class="form-control" name="m" value="<?= htmlspecialchars($selectedYm) ?>">
    </div>
    <div class="input-group" style="max-width: 160px;">
      <span class="input-group-text">Top</span>
      <input type="number" min="3" max="30" class="form-control" name="top" value="<?= (int) $topLimit ?>">
    </div>
    <?php if (!$hasMinStock): ?>
      <div class="input-group" style="max-width: 200px;">
        <span class="input-group-text">Threshold</span>
        <input type="number" min="1" class="form-control" name="threshold" value="<?= (int) $minThreshold ?>">
      </div>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-rotate"></i> Terapkan</button>
  </form>
</div>

<ol class="breadcrumb mb-4">
  <li class="breadcrumb-item"><a href="<?= url('pages/index.php') ?>">Home</a></li>
  <li class="breadcrumb-item active">Dashboard</li>
</ol>

<div class="row">
  <!-- Masuk Bulanan -->
  <div class="col-xl-4 col-md-6 mb-4">
    <div class="card text-white bg-primary h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="small opacity-75">Barang Masuk (<?= htmlspecialchars($selectedYm) ?>)</div>
            <div class="fs-2 fw-bold"><?= number_format((int) $in_curr, 0, ',', '.') ?></div>
          </div>
          <i class="fas fa-arrow-down fa-2x opacity-75"></i>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center justify-content-between">
        <?php
        $badgeText = sprintf(
          '%s %s (%.1f%%)',
          $in_diff > 0 ? '+' : ($in_diff < 0 ? '' : '±'),
          number_format((int) $in_diff, 0, ',', '.'),
          $in_pct
        );
        ?>
        <span class="badge kpi-delta <?= $in_badge ?>" title="<?= $in_aria ?> dibanding <?= htmlspecialchars($prev) ?>">
          <i class="fa-solid <?= $in_icon ?>"></i> <?= $badgeText ?>
        </span>
        <a class="small text-white" href="<?= url('pages/masuk.php') ?>">Detail <i class="fas fa-angle-right"></i></a>
      </div>
    </div>
  </div>

  <!-- Keluar Bulanan -->
  <div class="col-xl-4 col-md-6 mb-4">
    <div class="card text-white bg-danger h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="small opacity-75">Barang Keluar (<?= htmlspecialchars($selectedYm) ?>)</div>
            <div class="fs-2 fw-bold"><?= number_format((int) $out_curr, 0, ',', '.') ?></div>
          </div>
          <i class="fas fa-arrow-up fa-2x opacity-75"></i>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center justify-content-between">
        <?php
        $badgeText = sprintf(
          '%s %s (%.1f%%)',
          $out_diff > 0 ? '+' : ($out_diff < 0 ? '' : '±'),
          number_format((int) $out_diff, 0, ',', '.'),
          $out_pct
        );
        ?>
        <span class="badge kpi-delta <?= $out_badge ?>"
          title="<?= $out_aria ?> dibanding <?= htmlspecialchars($prev) ?>">
          <i class="fa-solid <?= $out_icon ?>"></i> <?= $badgeText ?>
        </span>
        <a class="small text-white" href="<?= url('pages/keluar.php') ?>">Detail <i class="fas fa-angle-right"></i></a>
      </div>
    </div>
  </div>

  <!-- Total Stok -->
  <div class="col-xl-4 col-md-12 mb-4">
    <div class="card text-white bg-success h-100">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="small opacity-75">Total Stok (Keseluruhan)</div>
          <div class="fs-2 fw-bold"><?= number_format((int) $totalStok, 0, ',', '.') ?></div>
        </div>
        <i class="fas fa-boxes-stacked fa-2x opacity-75"></i>
      </div>
      <div class="card-footer d-flex align-items-center justify-content-between">
        <span class="small">∑ stok saat ini</span>
        <a class="small text-white" href="<?= url('pages/laporan.php') ?>">Laporan <i
            class="fas fa-angle-right"></i></a>
      </div>
    </div>
  </div>
</div>

<!-- PERINGATAN STOK MINIMUM -->
<div class="card mb-4 border-danger">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span class="text-danger"><i class="fas fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($lowTitle) ?></span>
    <small class="text-muted"><?= htmlspecialchars($lowSub) ?></small>
  </div>
  <div class="card-body">
    <?php if ($hasLowStock): ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:80px">#</th>
              <th>Barang</th>
              <th style="width:140px" class="text-end">Stok</th>
              <?php if ($hasMinStock): ?>
                <th style="width:140px" class="text-end">Min</th><?php endif; ?>
              <th style="width:160px" class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1;
            foreach ($lowStock as $row): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                <td class="text-end"><?= number_format((int) $row['stock'], 0, ',', '.') ?></td>
                <?php if ($hasMinStock): ?>
                  <td class="text-end"><?= number_format((int) $row['min_stock'], 0, ',', '.') ?></td>
                <?php endif; ?>
                <td class="text-center">
                  <a class="btn btn-sm btn-outline-primary"
                    href="<?= url('pages/masuk.php') . '?barang=' . urlencode($row['id_barang']) ?>">
                    Tambah Stok
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state" aria-live="polite"
        style="border:1px dashed #c9ced6;background:#fff;border-radius:.75rem;padding:2rem 1rem;text-align:center;">
        <i class="fa-regular fa-square-check" style="font-size:2rem;opacity:.7;"></i>
        <h6 class="mb-1">Semua stok aman</h6>
        <p class="text-muted mb-0">Tidak ada barang di bawah batas minimum saat ini.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Chart: Top N Barang Paling Banyak Keluar -->
<div class="card mb-3">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span><i class="fas fa-chart-bar me-1"></i> Top <?= (int) $topLimit ?> Barang Paling Banyak Keluar
      (<?= htmlspecialchars($selectedYm) ?>)</span>
    <small class="text-muted">Bandingkan bulan lain via filter di atas</small>
  </div>
  <div class="card-body">
    <?php if ($showChart): ?>
      <div class="chart-container" style="min-height:360px;">
        <canvas id="chartTopKeluar" aria-label="Grafik barang paling banyak keluar" role="img"></canvas>
      </div>
    <?php else: ?>
      <div class="empty-state" aria-live="polite"
        style="border:1px dashed #c9ced6;background:#fff;border-radius:.75rem;padding:2rem 1rem;text-align:center;">
        <i class="fa-regular fa-chart-bar" style="font-size:2rem;opacity:.7;"></i>
        <h6 class="mb-1">Belum ada data untuk periode ini</h6>
        <p class="text-muted mb-0">Pilih bulan lain atau masukkan transaksi di menu <strong>Barang Masuk/Keluar</strong>.
        </p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- TABEL RINGKAS: Top N Barang Keluar -->
<div class="card mb-4">
  <div class="card-header">
    <i class="fas fa-table me-1"></i> Tabel Ringkas — Top <?= (int) $topLimit ?> Barang Keluar
    (<?= htmlspecialchars($selectedYm) ?>)
  </div>
  <div class="card-body">
    <?php if ($hasTopRows): ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:80px">#</th>
              <th>Barang</th>
              <th style="width:180px" class="text-end">Total Keluar</th>
              <th style="width:140px" class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1;
            foreach ($topKeluar as $row): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['label']) ?></td>
                <td class="text-end"><?= number_format((int) $row['total'], 0, ',', '.') ?></td>
                <td class="text-center">
                  <a class="btn btn-sm btn-outline-primary"
                    href="<?= url('pages/keluar.php') . '?barang=' . urlencode($row['id']) . '&m=' . urlencode($selectedYm) ?>">
                    Lihat
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state" aria-live="polite"
        style="border:1px dashed #c9ced6;background:#fff;border-radius:.75rem;padding:2rem 1rem;text-align:center;">
        <i class="fa-regular fa-clipboard" style="font-size:2rem;opacity:.7;"></i>
        <h6 class="mb-1">Tabel kosong</h6>
        <p class="text-muted mb-0">Belum ada data barang keluar untuk periode ini.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php if ($showChart): ?>
  <script>
    const topLabels = <?= json_encode(array_values($chartLabels), JSON_UNESCAPED_UNICODE) ?>;
    const topData = <?= json_encode(array_values($chartData)) ?>;

    const ctx = document.getElementById('chartTopKeluar').getContext('2d');
    if (window._chartTopKeluar) window._chartTopKeluar.destroy();

    window._chartTopKeluar = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: topLabels,
        datasets: [{ label: 'Qty Keluar', data: topData, borderWidth: 1 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 } },
          x: { ticks: { autoSkip: false, maxRotation: 45, minRotation: 0 } }
        },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (c) => ' ' + (c.parsed.y ?? 0).toLocaleString('id-ID') } }
        }
      }
    });
  </script>
<?php endif; ?>