<?php
// =============== USERS (ADMIN) — FINAL ===============
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_permission('manage_users'); // pastikan permission ini ada di auth-mu

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

$conn = (isset($koneksi) && $koneksi instanceof mysqli) ? $koneksi : null;
if (!$conn) {
  echo '<div class="alert alert-danger m-3">Koneksi database tidak tersedia.</div>';
  include __DIR__ . '/../includes/footer.php';
  exit;
}

$msg = null; $type = 'success';

// helper
function trimx($v){ return trim((string)$v); }
function only_role($s){
  $s = strtolower(trim($s));
  return in_array($s, ['admin','staff','viewer'], true) ? $s : 'viewer';
}
function to_bool01($v){ return (int) (!!$v); }

// CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['action'] ?? '';
  try {
    if ($act === 'create') {
      $username = trimx($_POST['username'] ?? '');
      $nama     = trimx($_POST['nama'] ?? '');
      $role     = only_role($_POST['role'] ?? 'viewer');
      $aktif    = to_bool01($_POST['is_active'] ?? 1);
      $pwd      = (string)($_POST['password'] ?? '');

      if ($username === '' || $pwd === '') throw new Exception('Username & Password wajib diisi');

      // cek unik username
      $st = $conn->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1");
      $st->bind_param('s', $username);
      $st->execute();
      if ($st->get_result()->fetch_row()) throw new Exception('Username sudah digunakan');

      $hash = password_hash($pwd, PASSWORD_BCRYPT);
      $now  = date('Y-m-d H:i:s');

      $st = $conn->prepare("INSERT INTO users (username,password_hash,nama,role,is_active,created_at) VALUES (?,?,?,?,?,?)");
      $st->bind_param('ssssss', $username, $hash, $nama, $role, $aktif, $now);
      $st->execute();

      $msg = 'User berhasil ditambahkan.';
    }
    elseif ($act === 'update') {
      $id       = (int)($_POST['id'] ?? 0);
      $username = trimx($_POST['username'] ?? '');
      $nama     = trimx($_POST['nama'] ?? '');
      $role     = only_role($_POST['role'] ?? 'viewer');
      $aktif    = to_bool01($_POST['is_active'] ?? 1);
      $pwd_new  = (string)($_POST['password_new'] ?? '');

      if ($id <= 0) throw new Exception('ID tidak valid');
      if ($username === '') throw new Exception('Username wajib diisi');

      // cek unik username kecuali dirinya
      $st = $conn->prepare("SELECT 1 FROM users WHERE username = ? AND id <> ? LIMIT 1");
      $st->bind_param('si', $username, $id);
      $st->execute();
      if ($st->get_result()->fetch_row()) throw new Exception('Username sudah digunakan');

      if ($pwd_new !== '') {
        $hash = password_hash($pwd_new, PASSWORD_BCRYPT);
        $st = $conn->prepare("UPDATE users SET username=?, nama=?, role=?, is_active=?, password_hash=? WHERE id=?");
        $st->bind_param('sssssi', $username, $nama, $role, $aktif, $hash, $id);
      } else {
        $st = $conn->prepare("UPDATE users SET username=?, nama=?, role=?, is_active=? WHERE id=?");
        $st->bind_param('sssii', $username, $nama, $role, $aktif, $id);
      }
      $st->execute();

      $msg = 'User berhasil diperbarui.';
    }
    elseif ($act === 'resetpwd') {
      $id  = (int)($_POST['id'] ?? 0);
      $pwd = (string)($_POST['password_new'] ?? '');
      if ($id <= 0 || $pwd === '') throw new Exception('ID/Password tidak valid');

      $hash = password_hash($pwd, PASSWORD_BCRYPT);
      $st = $conn->prepare("UPDATE users SET password_hash=? WHERE id=?");
      $st->bind_param('si', $hash, $id);
      $st->execute();

      $msg = 'Password berhasil direset.';
    }
    elseif ($act === 'toggle') {
      $id = (int)($_POST['id'] ?? 0);
      $v  = to_bool01($_POST['value'] ?? 0);
      if ($id <= 0) throw new Exception('ID tidak valid');

      $st = $conn->prepare("UPDATE users SET is_active=? WHERE id=?");
      $st->bind_param('ii', $v, $id);
      $st->execute();

      $msg = 'Status aktif diperbarui.';
    }
    elseif ($act === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new Exception('ID tidak valid');

      // lindungi akun sendiri
      if ((int)($_SESSION['user_id'] ?? 0) === $id) throw new Exception('Tidak boleh menghapus akun yang sedang login.');

      $st = $conn->prepare("DELETE FROM users WHERE id=?");
      $st->bind_param('i', $id);
      $st->execute();

      $msg = 'User dihapus.';
    }
  } catch (Throwable $e) {
    $msg = $e->getMessage();
    $type = 'danger';
  }
}

// Ambil data
$q = trim($_GET['q'] ?? '');
$sql = "SELECT id, username, nama, role, is_active, created_at FROM users";
$bind = false;

if ($q !== '') {
  $sql .= " WHERE (username LIKE CONCAT('%',?,'%') OR nama LIKE CONCAT('%',?,'%') OR role LIKE CONCAT('%',?,'%'))";
  $bind = true;
}
$sql .= " ORDER BY created_at DESC, id DESC";

$st = $conn->prepare($sql);
if ($bind) { $st->bind_param('sss', $q, $q, $q); }
$st->execute();
$res = $st->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;

?>
<div class="container-fluid px-3 px-xl-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="fw-bold mb-2">Users</h1>
    <div class="d-flex gap-2">
      <form class="d-flex" method="get" action="<?= url('pages/users.php') ?>">
        <input class="form-control" name="q" placeholder="Cari username/nama/role…" value="<?= htmlspecialchars($q) ?>">
        <button class="btn btn-outline-secondary ms-2" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Cari</button>
      </form>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate"><i class="fa-solid fa-user-plus"></i> Tambah</button>
    </div>
  </div>

  <ol class="breadcrumb mb-4">
    <li class="breadcrumb-item"><a href="<?= url('pages/index.php') ?>">Home</a></li>
    <li class="breadcrumb-item active">Users</li>
  </ol>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $type ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fa-solid fa-table me-1"></i> Data Users</span>
      <small class="text-muted">Total: <?= number_format(count($rows), 0, ',', '.') ?> akun</small>
    </div>
    <div class="card-body">
      <?php if (empty($rows)): ?>
        <div class="empty-state">
          <i class="fa-regular fa-circle-user"></i>
          <h6 class="mt-2 mb-1">Belum ada pengguna</h6>
          <p class="text-muted mb-0">Klik tombol <b>Tambah</b> untuk membuat akun baru.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th style="width:80px;">#</th>
                <th>Username</th>
                <th>Nama</th>
                <th style="width:140px;">Role</th>
                <th style="width:140px;">Aktif</th>
                <th style="width:200px;">Dibuat</th>
                <th style="width:260px;" class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php $no = 1; foreach ($rows as $r): ?>
                <tr>
                  <td><?= $no++ ?></td>
                  <td class="fw-medium"><?= htmlspecialchars($r['username']) ?></td>
                  <td><?= htmlspecialchars($r['nama'] ?? '') ?></td>
                  <td><span class="badge text-bg-secondary text-uppercase"><?= htmlspecialchars($r['role']) ?></span></td>
                  <td>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="value" value="<?= (int)!$r['is_active'] ?>">
                      <button class="btn btn-sm <?= $r['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>" type="submit">
                        <i class="fa-solid fa-power-off"></i> <?= $r['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                      </button>
                    </form>
                  </td>
                  <td><code><?= htmlspecialchars($r['created_at']) ?></code></td>
                  <td class="text-center">
                    <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal"
                            data-bs-target="#modalEdit"
                            data-id="<?= (int)$r['id'] ?>"
                            data-username="<?= htmlspecialchars($r['username'], ENT_QUOTES) ?>"
                            data-nama="<?= htmlspecialchars($r['nama'] ?? '', ENT_QUOTES) ?>"
                            data-role="<?= htmlspecialchars($r['role'], ENT_QUOTES) ?>"
                            data-active="<?= (int)$r['is_active'] ?>">
                      <i class="fa-regular fa-pen-to-square"></i> Edit
                    </button>

                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal"
                            data-bs-target="#modalReset"
                            data-id="<?= (int)$r['id'] ?>"
                            data-username="<?= htmlspecialchars($r['username'], ENT_QUOTES) ?>">
                      <i class="fa-solid fa-key"></i> Reset Password
                    </button>

                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus user ini?');">
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

<!-- Modal: Create -->
<div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Tambah User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="create">
        <div class="mb-3">
          <label class="form-label">Username <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="username" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Nama</label>
          <input type="text" class="form-control" name="nama">
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Role</label>
            <select class="form-select" name="role">
              <option value="admin">admin</option>
              <option value="staff">staff</option>
              <option value="viewer" selected>viewer</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <select class="form-select" name="is_active">
              <option value="1" selected>Aktif</option>
              <option value="0">Nonaktif</option>
            </select>
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label">Password <span class="text-danger">*</span></label>
          <input type="password" class="form-control" name="password" required>
          <small class="text-muted">Disimpan menggunakan hash (bcrypt).</small>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal" type="button">Batal</button>
        <button class="btn btn-primary" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" class="form-control" name="username" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Nama</label>
          <input type="text" class="form-control" name="nama">
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Role</label>
            <select class="form-select" name="role">
              <option value="admin">admin</option>
              <option value="staff">staff</option>
              <option value="viewer">viewer</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <select class="form-select" name="is_active">
              <option value="1">Aktif</option>
              <option value="0">Nonaktif</option>
            </select>
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label">Password Baru <small class="text-muted">(opsional)</small></label>
          <input type="password" class="form-control" name="password_new">
          <small class="text-muted">Biarkan kosong bila tidak ingin mengubah password.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal" type="button">Batal</button>
        <button class="btn btn-primary" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Reset Password -->
<div class="modal fade" id="modalReset" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reset Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="resetpwd">
        <input type="hidden" name="id" value="">
        <div class="mb-2">
          <div class="small text-muted">User: <code id="resetUser"></code></div>
        </div>
        <div>
          <label class="form-label">Password Baru</label>
          <input type="password" class="form-control" name="password_new" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal" type="button">Batal</button>
        <button class="btn btn-primary" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
/* Isi modal edit */
document.getElementById('modalEdit')?.addEventListener('show.bs.modal', function (ev) {
  const btn  = ev.relatedTarget;
  const form = this.querySelector('form');
  form.id.value        = btn.dataset.id || '';
  form.username.value  = btn.dataset.username || '';
  form.nama.value      = btn.dataset.nama || '';
  form.role.value      = btn.dataset.role || 'viewer';
  form.is_active.value = btn.dataset.active === '1' ? '1' : '0';
  form.password_new.value = '';
});

/* Isi modal reset */
document.getElementById('modalReset')?.addEventListener('show.bs.modal', function (ev) {
  const btn  = ev.relatedTarget;
  const form = this.querySelector('form');
  form.id.value = btn.dataset.id || '';
  document.getElementById('resetUser').textContent = btn.dataset.username || '';
});
</script>
