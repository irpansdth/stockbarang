<?php
// =============== BARANG MASUK (Transaksi) ===============
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_permission('transact');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

/* ================= Helpers ================= */
function detect_qty_column(mysqli $db, string $table): ?string {
  foreach (['qty','jumlah','quantity','volume','jumlah_barang'] as $c) {
    $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '".$db->real_escape_string($c)."'");
    if ($res && $res->num_rows > 0) return $c;
  }
  return null;
}
function detect_fk_col(mysqli $db, string $table, array $cands): ?string {
  foreach ($cands as $c) {
    $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '".$db->real_escape_string($c)."'");
    if ($res && $res->num_rows > 0) return $c;
  }
  return null;
}
function detect_pk_col(mysqli $db, string $table): ?string {
  if ($res = $db->query("SHOW KEYS FROM `$table` WHERE Key_name='PRIMARY'")) {
    if ($row = $res->fetch_assoc()) return $row['Column_name'] ?? null;
  }
  foreach (['id','id_masuk','id_transaksi','id_barang_masuk','no','nomor'] as $c) {
    $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '".$db->real_escape_string($c)."'");
    if ($res && $res->num_rows > 0) return $c;
  }
  return null;
}
function fetch_options(mysqli $db, string $table, string $idCol='id', string $nameCol='nama'): array {
  $rows = [];
  $sql = "SELECT `$idCol` AS id, `$nameCol` AS nama FROM `$table` ORDER BY `$nameCol` ASC";
  if ($res = $db->query($sql)) while ($r = $res->fetch_assoc()) $rows[] = $r;
  return $rows;
}
function fetch_barang_options(mysqli $db): array {
  $rows = [];
  $sql = "SELECT id_barang, kode_barang, nama_barang, satuan FROM stock ORDER BY nama_barang ASC";
  if ($res = $db->query($sql)) while ($r = $res->fetch_assoc()) $rows[] = $r;
  return $rows;
}

/* ============== Detect schema ============== */
$qtyColMasuk  = detect_qty_column($koneksi, 'barang_masuk') ?: 'qty';
$fkSupplierCol= detect_fk_col($koneksi, 'barang_masuk', ['supplier_id','id_supplier']);
$hasKeterangan= !!$koneksi->query("SHOW COLUMNS FROM `barang_masuk` LIKE 'keterangan'")?->num_rows;
$pkMasuk      = detect_pk_col($koneksi, 'barang_masuk') ?: 'id';

$barangList   = fetch_barang_options($koneksi);
$supplierList = fetch_options($koneksi, 'suppliers','id','nama');

$msg=null; $msgType='success';

/* ============== Handle POST ============== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create') {
    $tanggal    = $_POST['tanggal'] ?? date('Y-m-d');
    $id_barang  = (int)($_POST['id_barang'] ?? 0);
    $qty        = (int)($_POST['qty'] ?? 0);
    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $ket        = trim($_POST['keterangan'] ?? '');

    try {
      if ($id_barang <= 0) throw new Exception('Barang wajib dipilih.');
      if ($qty <= 0)       throw new Exception('Qty harus lebih dari 0.');

      $koneksi->begin_transaction();

      $cols = ['tanggal','id_barang', $qtyColMasuk];
      $vals = [$tanggal, $id_barang, $qty];
      $types= 'sii';

      if ($fkSupplierCol && $supplierId > 0) { $cols[]=$fkSupplierCol; $vals[]=$supplierId; $types.='i'; }
      if ($hasKeterangan) { $cols[]='keterangan'; $vals[]=$ket; $types.='s'; }

      $sql = "INSERT INTO barang_masuk (`".implode('`,`',$cols)."`) VALUES (".rtrim(str_repeat('?,',count($cols)),',').")";
      $stmt= $koneksi->prepare($sql);
      $stmt->bind_param($types, ...$vals);
      $stmt->execute();

      $stmt2= $koneksi->prepare("UPDATE stock SET stock = COALESCE(stock,0) + ? WHERE id_barang=?");
      $stmt2->bind_param('ii',$qty,$id_barang);
      $stmt2->execute();

      $koneksi->commit();
      $msg='Transaksi masuk berhasil disimpan & stok diperbarui.';
    } catch (Throwable $e) {
      $koneksi->rollback();
      $msgType='danger'; $msg=$e->getMessage();
    }
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    try {
      if ($id <= 0) throw new Exception('ID tidak valid.');

      $sqlGet = "SELECT id_barang, COALESCE(`$qtyColMasuk`,0) AS qty FROM barang_masuk WHERE `$pkMasuk`=?";
      $stmt   = $koneksi->prepare($sqlGet);
      $stmt->bind_param('i',$id);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      if (!$row) throw new Exception('Data tidak ditemukan.');

      $koneksi->begin_transaction();

      $stmt2 = $koneksi->prepare("DELETE FROM barang_masuk WHERE `$pkMasuk`=?");
      $stmt2->bind_param('i',$id);
      $stmt2->execute();

      $stmt3 = $koneksi->prepare("UPDATE stock SET stock = GREATEST(COALESCE(stock,0) - ?, 0) WHERE id_barang=?");
      $stmt3->bind_param('ii',$row['qty'],$row['id_barang']);
      $stmt3->execute();

      $koneksi->commit();
      $msg='Transaksi masuk dihapus & stok dikoreksi.';
    } catch (Throwable $e) {
      $koneksi->rollback();
      $msgType='danger'; $msg=$e->getMessage();
    }
  }
}

/* ============== Filters & List ============== */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$barangFilter = (int)($_GET['barang'] ?? 0);
$suppFilter   = (int)($_GET['supplier'] ?? 0);

$where  = ["bm.tanggal BETWEEN ? AND ?"];
$params = [$from,$to];
$types  = "ss";
if ($barangFilter>0){ $where[]="bm.id_barang=?"; $params[]=$barangFilter; $types.='i'; }
if ($fkSupplierCol && $suppFilter>0){ $where[]="bm.`$fkSupplierCol`=?"; $params[]=$suppFilter; $types.='i'; }

$sqlList = "
  SELECT bm.`$pkMasuk` AS id, bm.tanggal, bm.id_barang, s.nama_barang, s.kode_barang,
         COALESCE(bm.`$qtyColMasuk`,0) AS qty,
         ".($fkSupplierCol ? "sup.nama AS supplier," : "NULL AS supplier,")."
         ".($hasKeterangan ? "bm.keterangan" : "NULL AS keterangan")."
  FROM barang_masuk bm
  JOIN stock s ON s.id_barang = bm.id_barang
  ".($fkSupplierCol ? "LEFT JOIN suppliers sup ON sup.id = bm.`$fkSupplierCol`" : "")."
  WHERE ".implode(' AND ',$where)."
  ORDER BY bm.tanggal DESC, id DESC
  LIMIT 200
";
$stmtL = $koneksi->prepare($sqlList);
$stmtL->bind_param($types, ...$params);
$stmtL->execute();
$list = $stmtL->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="container-fluid px-3 px-xl-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="fw-bold mb-2">Barang Masuk</h1>
    <div class="text-muted">Qty column: <code><?= htmlspecialchars($qtyColMasuk) ?></code> | PK: <code><?= htmlspecialchars($pkMasuk) ?></code></div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-header"><i class="fa-solid fa-plus me-1"></i> Tambah Transaksi</div>
    <div class="card-body">
      <?php if (empty($barangList)): ?>
        <div class="alert alert-warning">Data <b>stock</b> kosong. Tambahkan barang di Master Stok dulu.</div>
      <?php endif; ?>
      <form method="post" class="row g-3">
        <input type="hidden" name="action" value="create">
        <div class="col-md-3">
          <label class="form-label">Tanggal</label>
          <input type="date" class="form-control" name="tanggal" value="<?= htmlspecialchars(date('Y-m-d')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Barang</label>
          <select class="form-select" name="id_barang" <?= empty($barangList)?'disabled':'' ?>>
            <option value="">- pilih -</option>
            <?php foreach ($barangList as $b): ?>
              <option value="<?= (int)$b['id_barang'] ?>">
                <?= htmlspecialchars(($b['kode_barang']? $b['kode_barang'].' - ' : '').$b['nama_barang']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Qty</label>
          <input type="number" class="form-control" name="qty" min="1" value="1">
        </div>
        <div class="col-md-3">
          <label class="form-label">Supplier</label>
          <select class="form-select" name="supplier_id" <?= $fkSupplierCol ? '' : 'disabled title="Kolom supplier tidak ada di tabel barang_masuk"' ?>>
            <option value="">- pilih -</option>
            <?php foreach ($supplierList as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($hasKeterangan): ?>
        <div class="col-12">
          <label class="form-label">Keterangan</label>
          <input type="text" class="form-control" name="keterangan" maxlength="255">
        </div>
        <?php endif; ?>
        <div class="col-12">
          <button class="btn btn-primary" <?= empty($barangList)?'disabled':'' ?>><i class="fa-solid fa-save me-1"></i> Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-table me-1"></i> Transaksi Terbaru
      <form class="row row-cols-lg-auto g-2 align-items-center float-end" method="get">
        <div class="col-12"><input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control"></div>
        <div class="col-12"><input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control"></div>
        <div class="col-12">
          <select class="form-select" name="barang">
            <option value="0">Semua Barang</option>
            <?php foreach ($barangList as $b): ?>
              <option value="<?= (int)$b['id_barang'] ?>" <?= $barangFilter==$b['id_barang']?'selected':'' ?>>
                <?= htmlspecialchars($b['nama_barang']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($fkSupplierCol): ?>
        <div class="col-12">
          <select class="form-select" name="supplier">
            <option value="0">Semua Supplier</option>
            <?php foreach ($supplierList as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $suppFilter==$s['id']?'selected':'' ?>>
                <?= htmlspecialchars($s['nama']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="col-12"><button class="btn btn-outline-secondary"><i class="fa-solid fa-rotate"></i></button></div>
      </form>
    </div>
    <div class="card-body">
      <?php if (empty($list)): ?>
        <div class="empty-state"><i class="fa-regular fa-inbox"></i><h6 class="mt-2 mb-1">Belum ada transaksi</h6><p class="text-muted mb-0">Tambah transaksi melalui form di atas.</p></div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:80px">#</th>
                <th>Tanggal</th>
                <th>Kode</th>
                <th>Barang</th>
                <th class="text-end">Qty</th>
                <?php if ($fkSupplierCol): ?><th>Supplier</th><?php endif; ?>
                <?php if ($hasKeterangan): ?><th>Keterangan</th><?php endif; ?>
                <th style="width:120px" class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php $i=1; foreach ($list as $r): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($r['tanggal']) ?></td>
                <td><?= htmlspecialchars($r['kode_barang'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['nama_barang']) ?></td>
                <td class="text-end"><?= number_format((int)$r['qty'],0,',','.') ?></td>
                <?php if ($fkSupplierCol): ?><td><?= htmlspecialchars($r['supplier'] ?? '-') ?></td><?php endif; ?>
                <?php if ($hasKeterangan): ?><td><?= htmlspecialchars($r['keterangan'] ?? '') ?></td><?php endif; ?>
                <td class="text-end">
                  <form method="post" class="d-inline" onsubmit="return confirm('Hapus transaksi ini? Stok akan dikoreksi.');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="fa-regular fa-trash-can"></i> Hapus</button>
                  </form>
                </td>
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
