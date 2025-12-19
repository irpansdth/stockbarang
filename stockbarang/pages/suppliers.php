<?php
// =============== MASTER SUPPLIERS (CRUD) ===============
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_permission('view_stock'); // semua role boleh lihat

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';


$msg = null; $msgType = 'success';

// Handle ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act   = $_POST['action'] ?? '';
  $id    = (int)($_POST['id'] ?? 0);
  $nama  = trim($_POST['nama'] ?? '');
  $alamat= trim($_POST['alamat'] ?? '');
  $telp  = trim($_POST['telp'] ?? '');
  $pic   = trim($_POST['pic'] ?? '');

  try {
    if ($act === 'create') {
      if ($nama === '') throw new Exception('Nama supplier wajib diisi.');
      $stmt = $koneksi->prepare("INSERT INTO suppliers (nama, alamat, telp, pic) VALUES (?, ?, ?, ?)");
      $stmt->bind_param('ssss', $nama, $alamat, $telp, $pic);
      $stmt->execute();
      $msg = 'Supplier berhasil ditambahkan.';
    } elseif ($act === 'update') {
      if ($id <= 0) throw new Exception('ID tidak valid.');
      if ($nama === '') throw new Exception('Nama supplier wajib diisi.');
      $stmt = $koneksi->prepare("UPDATE suppliers SET nama=?, alamat=?, telp=?, pic=? WHERE id=?");
      $stmt->bind_param('ssssi', $nama, $alamat, $telp, $pic, $id);
      $stmt->execute();
      $msg = 'Supplier berhasil diperbarui.';
    } elseif ($act === 'delete') {
      if ($id <= 0) throw new Exception('ID tidak valid.');
      // aman: set NULL di FK transaksi (kalau FK ON DELETE SET NULL dipakai),
      // kalau tidak, hapus tetap bisa (tanpa FK) tapi bisa ada orphan. Sesuaikan kebutuhanmu.
      $stmt = $koneksi->prepare("DELETE FROM suppliers WHERE id=?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $msg = 'Supplier berhasil dihapus.';
    }
  } catch (Throwable $e) {
    $msg = $e->getMessage(); $msgType = 'danger';
  }
}

// Query list + search
$q = trim($_GET['q'] ?? '');
$params = [];
$sql = "SELECT id, nama, alamat, telp, pic, created_at
        FROM suppliers ";
if ($q !== '') {
  $sql .= "WHERE (nama LIKE ? OR alamat LIKE ? OR telp LIKE ? OR pic LIKE ?) ";
  $like = "%{$q}%";
  $params = [$like, $like, $like, $like];
}
$sql .= "ORDER BY nama ASC";
$stmt = $koneksi->prepare($sql);
if ($params) {
  $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while($r = $res->fetch_assoc()) $rows[] = $r;
?>
<div class="container-fluid px-3 px-xl-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="fw-bold mb-2">Supplier</h1>

    <div class="d-flex gap-2">
      <form class="d-flex" method="get" action="">
        <input type="text" class="form-control" name="q" placeholder="Cari nama / telp / PIC / alamat..." value="<?= htmlspecialchars($q) ?>">
        <button class="btn btn-outline-secondary ms-2"><i class="fa-solid fa-magnifying-glass"></i> Cari</button>
      </form>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mdlCreate">
        <i class="fa-solid fa-plus"></i> Tambah
      </button>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-table-list me-1"></i> Data Supplier
      <span class="text-muted">• Total: <?= count($rows) ?> item</span>
    </div>
    <div class="card-body">
      <?php if (empty($rows)): ?>
        <div class="empty-state">
          <i class="fa-regular fa-inbox"></i>
          <h6 class="mt-2 mb-1">Belum ada data</h6>
          <p class="text-muted mb-0">Klik tombol <b>Tambah</b> untuk menambahkan supplier baru.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:80px">#</th>
                <th>Nama</th>
                <th style="width:160px">Telp</th>
                <th style="width:200px">PIC</th>
                <th>Alamat</th>
                <th style="width:180px" class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php $i=1; foreach ($rows as $r): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><?= htmlspecialchars($r['nama']) ?></td>
                  <td><?= htmlspecialchars($r['telp']) ?></td>
                  <td><?= htmlspecialchars($r['pic']) ?></td>
                  <td><?= nl2br(htmlspecialchars($r['alamat'])) ?></td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary me-1"
                            data-bs-toggle="modal"
                            data-bs-target="#mdlEdit"
                            data-id="<?= (int)$r['id'] ?>"
                            data-nama="<?= htmlspecialchars($r['nama'], ENT_QUOTES) ?>"
                            data-alamat="<?= htmlspecialchars($r['alamat'], ENT_QUOTES) ?>"
                            data-telp="<?= htmlspecialchars($r['telp'], ENT_QUOTES) ?>"
                            data-pic="<?= htmlspecialchars($r['pic'], ENT_QUOTES) ?>">
                      <i class="fa-regular fa-pen-to-square"></i> Edit
                    </button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus supplier ini?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger">
                        <i class="fa-regular fa-trash-can"></i> Hapus
                      </button>
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

<!-- Modal Create -->
<div class="modal fade" id="mdlCreate" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="create">
      <div class="modal-header"><h5 class="modal-title">Tambah Supplier</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nama <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="nama" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Telp</label>
          <input type="text" class="form-control" name="telp">
        </div>
        <div class="mb-3">
          <label class="form-label">PIC</label>
          <input type="text" class="form-control" name="pic">
        </div>
        <div class="mb-3">
          <label class="form-label">Alamat</label>
          <textarea class="form-control" name="alamat" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Simpan</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="mdlEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="ed_id">
      <div class="modal-header"><h5 class="modal-title">Edit Supplier</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nama <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="nama" id="ed_nama" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Telp</label>
          <input type="text" class="form-control" name="telp" id="ed_telp">
        </div>
        <div class="mb-3">
          <label class="form-label">PIC</label>
          <input type="text" class="form-control" name="pic" id="ed_pic">
        </div>
        <div class="mb-3">
          <label class="form-label">Alamat</label>
          <textarea class="form-control" name="alamat" id="ed_alamat" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Simpan</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('mdlEdit')?.addEventListener('show.bs.modal', function (ev) {
  const btn = ev.relatedTarget;
  if (!btn) return;
  this.querySelector('#ed_id').value     = btn.getAttribute('data-id');
  this.querySelector('#ed_nama').value   = btn.getAttribute('data-nama') || '';
  this.querySelector('#ed_telp').value   = btn.getAttribute('data-telp') || '';
  this.querySelector('#ed_pic').value    = btn.getAttribute('data-pic') || '';
  this.querySelector('#ed_alamat').value = btn.getAttribute('data-alamat') || '';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
