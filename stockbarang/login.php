<?php
// =================== LOGIN (FINAL & FLEXIBLE) ===================
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Kalau sudah login, langsung ke dashboard
if (!empty($_SESSION['user_id'])) {
  header('Location: ' . url('pages/index.php'));
  exit;
}

/* ====== KONFIG MASA DEPAN (kalau struktur berubah) ======
   Default sudah sesuai dengan tabel users kamu:
   id, username, password_hash, nama, role, is_active
*/
$USERS_TABLE  = 'users';
$COL_ID       = 'id';
$COL_USERNAME = 'username';
$COL_PASSWORD = 'password_hash';
$COL_NAME     = 'nama';
$COL_ROLE     = 'role';
$COL_ACTIVE   = 'is_active';
$ACTIVE_TRUE  = 1;

// Utility kecil
function table_exists(mysqli $conn, string $table): bool {
  $t = $conn->real_escape_string($table);
  $res = $conn->query("SHOW TABLES LIKE '{$t}'");
  return ($res && $res->num_rows > 0);
}

$msg = null; $msgType = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = (string)($_POST['password'] ?? '');

  if ($username === '' || $password === '') {
    $msg = 'Username dan password wajib diisi.';
  } elseif (!table_exists($koneksi, $USERS_TABLE)) {
    $msg = "Tabel '{$USERS_TABLE}' tidak ditemukan.";
  } else {
    // Ambil user (wajib include kolom id)
    $sql = "SELECT `{$COL_ID}` AS uid,
                   `{$COL_USERNAME}` AS uname,
                   `{$COL_PASSWORD}` AS phash,
                   " . ($COL_NAME   ? "`{$COL_NAME}`   AS rname," : "NULL AS rname,") . "
                   " . ($COL_ROLE   ? "`{$COL_ROLE}`   AS rrole," : "NULL AS rrole,") . "
                   " . ($COL_ACTIVE ? "`{$COL_ACTIVE}` AS ractive" : "NULL AS ractive") . "
            FROM `{$USERS_TABLE}`
            WHERE `{$COL_USERNAME}` = ?
            LIMIT 1";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res  = $stmt->get_result();
    $user = $res->fetch_assoc();

    if (!$user) {
      $msg = 'Username tidak ditemukan.';
    } else {
      // Cek aktif kalau kolom ada
      if ($COL_ACTIVE && $user['ractive'] !== null && (string)$user['ractive'] !== (string)$ACTIVE_TRUE) {
        $msg = 'Akun dinonaktifkan.';
      } else {
        $stored = (string)($user['phash'] ?? '');
        $ok = false;
        $needUpgrade = false;

        // Cek apakah hash bcrypt (mengandung "$2")
        if ($stored !== '' && strpos($stored, '$2') === 0) {
        $ok = password_verify($password, $stored);
          // Optional: upgrade cost jika perlu (abaikan jika belum butuh)
        }

        // 2) Kompat data lama: plaintext
        if (!$ok && $stored !== '' && $stored === $password) {
          $ok = true;
          $needUpgrade = true;
        }

        // 3) Kompat data lama: MD5
        if (!$ok && $stored !== '' && strtolower($stored) === md5($password)) {
          $ok = true;
          $needUpgrade = true;
        }

        if ($ok) {
          // Auto-upgrade ke bcrypt jika sebelumnya plaintext/md5
          if ($needUpgrade) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $up = $koneksi->prepare("UPDATE `{$USERS_TABLE}` SET `{$COL_PASSWORD}`=? WHERE `{$COL_ID}`=?");
            $up->bind_param('si', $newHash, $user['uid']);
            $up->execute();
          }

          // Set session
          session_regenerate_id(true);
          $_SESSION['user_id']  = (int)$user['uid'];
          $_SESSION['username'] = $user['uname'] ?? $username;
          $_SESSION['nama']     = $user['rname'] ?? ($user['uname'] ?? $username);
          $_SESSION['role']     = $user['rrole'] ?? 'staff';
          $_SESSION['logged_in']= true;

          header('Location: ' . url('pages/index.php'));
          exit;
        } else {
          $msg = 'Password yang anda masukkan salah.';
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — PT Mandiri Jaya Top</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <style>
    body{background:#f2f5fb;}
    .login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
    .card{border-radius:.75rem;box-shadow:0 6px 20px rgba(0,0,0,.08);}
  </style>
</head>
<body>
  <div class="login-wrap">
    <div class="card" style="width:100%;max-width:420px;">
      <div class="card-body">
        <div class="text-center mb-3">
          <img src="<?= url('assets/img/logo.png') ?>" alt="Logo" style="width:56px;height:56px;border-radius:12px;background:#fff;object-fit:cover">
          <h4 class="mt-2 mb-0">PT Mandiri Jaya Top</h4>
          <small class="text-muted">Sistem Pengadaan, Persediaan & Laporan</small>
        </div>

        <?php if ($msg): ?>
          <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" required autofocus>
          </div>
          <div class="mb-4">
            <label class="form-label d-flex justify-content-between">
              <span>Password</span>
              <a href="#" class="small text-muted" onclick="togglePwd();return false;">Tampilkan</a>
            </label>
            <input type="password" class="form-control" name="password" id="pwd" required>
          </div>
          <button class="btn btn-primary w-100" type="submit">
            <i class="fa-solid fa-right-to-bracket me-1"></i> Masuk
          </button>
        </form>

        <div class="text-center mt-3">
          <small class="text-muted">© <?= date('Y') ?> PT Mandiri Jaya Top</small>
        </div>
      </div>
    </div>
  </div>

  <script>
    function togglePwd(){
      const el = document.getElementById('pwd');
      el.type = (el.type === 'password') ? 'text' : 'password';
    }
  </script>
</body>
</html>
