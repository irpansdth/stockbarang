<?php
// =============== BARANG KELUAR (Transaksi) ===============
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
  foreach (['id','id_keluar','id_transaksi','id_barang_keluar','no','nomor'] as $c) {
    $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '".$db->real_escape_string($c)."'");
    if ($res && $res->num_rows > 0) return $c;
  }
  return null;
}
/** Cek apakah kolom PK auto_increment */
function is_auto_increment(mysqli $db, string $table, string $column): bool {
  $sql = "SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
  $stmt = $db->prepare($sql);
  $stmt->bind_param('ss', $table, $column);
  $stmt->execute();
  $extra = (string)($stmt->get_result()->fetch_row()[0] ?? '');
  return stripos($extra, 'auto_increment') !== false;
}
/** Ambil nilai PK berikutnya (MAX+1) untuk tabel yang PK-nya bukan auto_increment */
function next_pk_value(mysqli $db, string $table, string $pkColumn): int {
  $sql = "SELECT COALESCE(MAX(`$pkColumn`),0) + 1 FROM `$table`";
  $res = $db->query($sql);
  return (int)($res->fetch_row()[0] ?? 1);
}

function fetch_options(mysqli $db, string $table, string $idCol='id', string $nameCol='nama'): array {
  $rows = [];
  $sql = "SELECT `$idCol` AS id, `$nameCol` AS nama FROM `$table` ORDER BY `$nameCol` ASC";
  if ($res = $db->query($sql)) while ($r = $res->fetch_assoc()) $rows[] = $r;
  return $rows;
}
function fetch_barang_options(mysqli $db): array {
  $rows = [];
  $sql = "SELECT id_barang, kode_barang, nama_barang, stock, satuan FROM stock ORDER BY nama_barang ASC";
  if ($res = $db->query($sql)) while ($r = $res->fetch_assoc()) $rows[] = $r;
  return $rows;
}

/* ============== Detect schema ============== */
$qtyColKeluar  = detect_qty_column($koneksi, 'barang_keluar') ?: 'qty';
$fkCustomerCol = detect_fk_col($koneksi, 'barang_keluar', ['customer_id','id_customer']);
$hasKeterangan = !!$koneksi->query("SHOW COLUMNS FROM `barang_keluar` LIKE 'keterangan'")?->num_rows;
$hasPenerima   = !!$koneksi->query("SHOW COLUMNS FROM `barang_keluar` LIKE 'penerima'")?->num_rows; // << NEW
$pkKeluar      = detect_pk_col($koneksi, 'barang_keluar') ?: 'id';
$pkAuto        = is_auto_increment($koneksi, 'barang_keluar', $pkKeluar);

$barangList   = fetch_barang_options($koneksi);
$customerList = fetch_options($koneksi, 'customers','id','nama');

$msg=null; $msgType='success';

/* ============== Handle POST ============== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create') {
    $tanggal    = $_POST['tanggal'] ?? date('Y-m-d');
    $id_barang  = (int)($_POST['id_barang'] ?? 0);
    $qty        = (int)($_POST['qty'] ?? 0);
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $ket        = trim($_POST['keterangan'] ?? '');
    $penerima   = trim($_POST['penerima'] ?? ''); // << NEW

    try {
      if ($id_barang <= 0) throw new Exception('Barang wajib dipilih.');
      if ($qty <= 0)       throw new Exception('Qty harus lebih dari 0.');

      // cek stok cukup
      $stmtX = $koneksi->prepare("SELECT COALESCE(stock,0) FROM stock WHERE id_barang=?");
      $stmtX->bind_param('i',$id_barang);
      $stmtX->execute();
      $stokNow = (int)$stmtX->get_result()->fetch_row()[0];
      if ($stokNow < $qty) throw new Exception('Stok tidak mencukupi.');

      $koneksi->begin_transaction();

      // siapkan kolom insert
      $cols = ['tanggal','id_barang', $qtyColKeluar];
      $vals = [$tanggal, $id_barang, $qty];
      $types= 'sii';

      if ($fkCustomerCol && $customerId > 0) { $cols[]=$fkCustomerCol; $vals[]=$customerId; $types.='i'; }
      if ($hasKeterangan) { $cols[]='keterangan'; $vals[]=$ket; $types.='s'; }
      if ($hasPenerima)   { $cols[]='penerima';   $vals[]=$penerima; $types.='s'; } // << NEW (isi '' jika kosong)

      // jika PK bukan auto_increment, sertakan PK manual (MAX+1)
      if (!$pkAuto) {
        $newId = next_pk_value($koneksi, 'barang_keluar', $pkKeluar);
        array_unshift($cols, $pkKeluar);
        array_unshift($vals, $newId);
        $types = 'i'.$types;
      }

      $sql = "INSERT INTO barang_keluar (`".implode('`,`',$cols)."`)
              VALUES (".rtrim(str_repeat('?,',count($cols)),',').")";
      $stmt= $koneksi->prepare($sql);
      $stmt->bind_param($types, ...$vals);
      $stmt->execute();

      // update stok
      $stmt2= $koneksi->prepare("UPDATE stock SET stock = GREATEST(COALESCE(stock,0) - ?, 0) WHERE id_barang=?");
      $stmt2->bind_param('ii',$qty,$id_barang);
      $stmt2->execute();

      $koneksi->commit();
      $msgType='success';
      $msg='Transaksi keluar berhasil disimpan & stok diperbarui.';
    } catch (Throwable $e) {
      $koneksi->rollback();
      $msgType='danger'; $msg=$e->getMessage();
    }
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    try {
      if ($id <= 0) throw new Exception('ID tidak valid.');

      $sqlGet = "SELECT id_barang, COALESCE(`$qtyColKeluar`,0) AS qty FROM barang_keluar WHERE `$pkKeluar`=?";
      $stmt   = $koneksi->prepare($sqlGet);
      $stmt->bind_param('i',$id);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      if (!$row) throw new Exception('Data tidak ditemukan.');

      $koneksi->begin_transaction();

      $stmt2 = $koneksi->prepare("DELETE FROM barang_keluar WHERE `$pkKeluar`=?");
      $stmt2->bind_param('i',$id);
      $stmt2->execute();

      $stmt3 = $koneksi->prepare("UPDATE stock SET stock = COALESCE(stock,0) + ? WHERE id_barang=?");
      $stmt3->bind_param('ii',$row['qty'],$row['id_barang']);
      $stmt3->execute();

      $koneksi->commit();
      $msgType='success';
      $msg='Transaksi keluar dihapus & stok dikoreksi.';
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
$custFilter   = (int)($_GET['customer'] ?? 0);

$where  = ["bk.tanggal BETWEEN ? AND ?"];
$params = [$from,$to];
$types  = "ss";
if ($barangFilter>0){ $where[]="bk.id_barang=?"; $params[]=$barangFilter; $types.='i'; }
if ($fkCustomerCol && $custFilter>0){ $where[]="bk.`$fkCustomerCol`=?"; $params[]=$custFilter; $types.='i'; }

$sqlList = "
  SELECT bk.`$pkKeluar` AS id, bk.tanggal, bk.id_barang, s.nama_barang, s.kode_barang,
         COALESCE(bk.`$qtyColKeluar`,0) AS qty,
         ".($fkCustomerCol ? "cst.nama AS customer," : "NULL AS customer,")."
         ".($hasKeterangan ? "bk.keterangan," : "NULL AS keterangan,")."
         ".($hasPenerima   ? "bk.penerima" : "NULL AS penerima")."
  FROM barang_keluar bk
  JOIN stock s ON s.id_barang = bk.id_barang
  ".($fkCustomerCol ? "LEFT JOIN customers cst ON cst.id = bk.`$fkCustomerCol`" : "")."
  WHERE ".implode(' AND ',$where)."
  ORDER BY bk.tanggal DESC, id DESC
  LIMIT 200
";
$stmtL = $koneksi->prepare($sqlList);
$stmtL->bind_param($types, ...$params);
$stmtL->execute();
$list = $stmtL->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="container-fluid px-3 px-xl-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="fw-bold mb-2">Barang Keluar</h1>
    <div class="text-muted">Qty column: <code><?= htmlspecialchars($qtyColKeluar) ?></code> | PK: <code><?= htmlspecialchars($pkKeluar) ?></code></div>
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
          <label class="form-label">Customer</label>
          <select class="form-select" name="customer_id" <?= $fkCustomerCol ? '' : 'disabled title="Kolom customer tidak ada di tabel barang_keluar"' ?>>
            <option value="">- pilih -</option>
            <?php foreach ($customerList as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($hasKeterangan): ?>
        <div class="col-md-6">
          <label class="form-label">Keterangan</label>
          <input type="text" class="form-control" name="keterangan" maxlength="255">
        </div>
        <?php endif; ?>
        <?php if ($hasPenerima): ?>
        <div class="col-md-6">
          <label class="form-label">Penerima</label>
          <input type="text" class="form-control" name="penerima" maxlength="100" placeholder="Nama penerima (opsional)">
        </div>
        <?php endif; ?>
        <div class="col-12">
          <button class="btn btn-primary" <?= empty($barangList)?'disabled':'' ?>><i class="fa-solid fa-save me-1"></i> Simpan</button>
        </div>
      </form>
      <small class="text-muted d-block mt-2">Stok akan otomatis berkurang sesuai Qty.</small>
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
        <?php if ($fkCustomerCol): ?>
        <div class="col-12">
          <select class="form-select" name="customer">
            <option value="0">Semua Customer</option>
            <?php foreach ($customerList as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $custFilter==$c['id']?'selected':'' ?>>
                <?= htmlspecialchars($c['nama']) ?>
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
                <?php if ($fkCustomerCol): ?><th>Customer</th><?php endif; ?>
                <?php if ($hasKeterangan): ?><th>Keterangan</th><?php endif; ?>
                <?php if ($hasPenerima): ?><th>Penerima</th><?php endif; ?>
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
                <?php if ($fkCustomerCol): ?><td><?= htmlspecialchars($r['customer'] ?? '-') ?></td><?php endif; ?>
                <?php if ($hasKeterangan): ?><td><?= htmlspecialchars($r['keterangan'] ?? '') ?></td><?php endif; ?>
                <?php if ($hasPenerima): ?><td><?= htmlspecialchars($r['penerima'] ?? '') ?></td><?php endif; ?>
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
