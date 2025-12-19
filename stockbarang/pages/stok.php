<?php
// =============== MASTER STOK BARANG (CRUD) ===============
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
if (function_exists('require_permission')) { require_permission('view_stock'); }

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

/* ---------------- Helpers ---------------- */
$conn = isset($koneksi) && $koneksi instanceof mysqli ? $koneksi : null;

function has_column(mysqli $db, string $table, string $col): bool {
  $tbl = $db->real_escape_string($table);
  $c   = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `{$tbl}` LIKE '{$c}'");
  return ($res && $res->num_rows > 0);
}
function s($v){ return trim((string)($v ?? '')); }
function i($v){ return (int)($v ?? 0); }

/* --------- Deteksi kolom opsional di tabel stock --------- */
$hasMin   = has_column($conn,'stock','min_stock');
$hasSat   = has_column($conn,'stock','satuan');
$hasLok   = has_column($conn,'stock','lokasi');
$hasKode  = has_column($conn,'stock','kode_barang');
$hasDesc  = has_column($conn,'stock','deskripsi');

/* ---------------- Handle POST (CRUD) ---------------- */
$msg=null; $msgType='success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'create') {
      $nama   = s($_POST['nama_barang'] ?? '');
      $stock  = max(0, i($_POST['stock'] ?? 0));
      $min    = $hasMin ? max(0, i($_POST['min_stock'] ?? 0)) : null;
      $satuan = $hasSat ? s($_POST['satuan'] ?? '') : null;
      $lok    = $hasLok ? s($_POST['lokasi'] ?? '') : null;
      $kode   = $hasKode? s($_POST['kode_barang'] ?? '') : null;
      $desc   = $hasDesc? s($_POST['deskripsi'] ?? '') : null;

      if ($nama==='') throw new Exception('Nama barang wajib diisi.');

      $cols=['nama_barang','stock']; $qs=['?','?']; $vals=[$nama,$stock]; $types='si';
      if ($hasKode){ $cols[]='kode_barang'; $qs[]='?'; $vals[]=$kode; $types.='s'; }
      if ($hasMin){  $cols[]='min_stock';   $qs[]='?'; $vals[]=$min;  $types.='i'; }
      if ($hasSat){  $cols[]='satuan';      $qs[]='?'; $vals[]=$satuan; $types.='s'; }
      if ($hasLok){  $cols[]='lokasi';      $qs[]='?'; $vals[]=$lok;    $types.='s'; }
      if ($hasDesc){ $cols[]='deskripsi';   $qs[]='?'; $vals[]=$desc;   $types.='s'; }

      $sql = "INSERT INTO stock (".implode(',',$cols).") VALUES (".implode(',',$qs).")";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param($types, ...$vals);
      $stmt->execute();

      $msg='Barang berhasil ditambahkan.';
    } elseif ($action === 'update') {
      $id     = i($_POST['id_barang'] ?? 0);
      $nama   = s($_POST['nama_barang'] ?? '');
      $stock  = max(0, i($_POST['stock'] ?? 0));
      $min    = $hasMin ? max(0, i($_POST['min_stock'] ?? 0)) : null;
      $satuan = $hasSat ? s($_POST['satuan'] ?? '') : null;
      $lok    = $hasLok ? s($_POST['lokasi'] ?? '') : null;
      $kode   = $hasKode? s($_POST['kode_barang'] ?? '') : null;
      $desc   = $hasDesc? s($_POST['deskripsi'] ?? '') : null;

      if ($id<=0)    throw new Exception('ID tidak valid.');
      if ($nama==='') throw new Exception('Nama barang wajib diisi.');

      $set=['nama_barang=?','stock=?']; $vals=[$nama,$stock]; $types='si';
      if ($hasKode){ $set[]='kode_barang=?'; $vals[]=$kode;   $types.='s'; }
      if ($hasMin){  $set[]='min_stock=?';   $vals[]=$min;    $types.='i'; }
      if ($hasSat){  $set[]='satuan=?';      $vals[]=$satuan; $types.='s'; }
      if ($hasLok){  $set[]='lokasi=?';      $vals[]=$lok;    $types.='s'; }
      if ($hasDesc){ $set[]='deskripsi=?';   $vals[]=$desc;   $types.='s'; }

      $vals[]=$id; $types.='i';
      $sql = "UPDATE stock SET ".implode(', ',$set)." WHERE id_barang=?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param($types, ...$vals);
      $stmt->execute();

      $msg='Barang berhasil diupdate.';
    } elseif ($action === 'delete') {
      $id = i($_POST['id_barang'] ?? 0);
      if ($id<=0) throw new Exception('ID tidak valid.');
      $stmt = $conn->prepare("DELETE FROM stock WHERE id_barang=?");
      $stmt->bind_param('i',$id);
      $stmt->execute();
      $msg='Barang berhasil dihapus.';
    }
  } catch (Throwable $e) {
    $msgType='danger'; $msg=$e->getMessage();
  }
}

/* ---------------- Filter & List ---------------- */
$q      = s($_GET['q'] ?? '');
$page   = max(1, i($_GET['page'] ?? 1));
$perPage= 10;
$offset = ($page-1)*$perPage;

$where="1=1"; $params=[]; $types='';
if ($q!=='') {
  $where.=" AND (nama_barang LIKE CONCAT('%',?,'%')";
  $params[]=$q; $types.='s';
  if ($hasKode){ $where.=" OR kode_barang LIKE CONCAT('%',?,'%')"; $params[]=$q; $types.='s'; }
  if ($hasDesc){ $where.=" OR deskripsi LIKE CONCAT('%',?,'%')";   $params[]=$q; $types.='s'; }
  $where.=")";
}
$sqlCount="SELECT COUNT(*) FROM stock WHERE $where";
$stmt=$conn->prepare($sqlCount);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalRows=(int)$stmt->get_result()->fetch_row()[0];
$totalPages=max(1,(int)ceil($totalRows/$perPage));

$sqlList = "SELECT id_barang, nama_barang, stock"
          .($hasKode?", kode_barang":"")
          .($hasMin ?", min_stock":"")
          .($hasSat ?", satuan":"")
          .($hasLok ?", lokasi":"")
          .($hasDesc?", deskripsi":"")
          ." FROM stock WHERE $where
             ORDER BY nama_barang ASC LIMIT ? OFFSET ?";
$params2=$params; $types2=$types.'ii'; $params2[]=$perPage; $params2[]=$offset;
$stmt=$conn->prepare($sqlList);
if ($params) $stmt->bind_param($types2, ...$params2);
else $stmt->bind_param('ii',$perPage,$offset);
$stmt->execute();
$res=$stmt->get_result();
$rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
?>
<div class="container-fluid px-3 px-xl-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="fw-bold mb-2">Stok Barang</h1>
    <div class="d-flex gap-2">
      <form class="d-flex" method="get" action="<?= url('pages/stok.php') ?>">
        <input type="text" class="form-control" name="q" placeholder="Cari nama / kode / deskripsi..."
               value="<?= htmlspecialchars($q) ?>">
        <button class="btn btn-outline-secondary ms-2" type="submit">
          <i class="fa-solid fa-magnifying-glass"></i> Cari
        </button>
      </form>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalForm">
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

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fa-solid fa-table me-1"></i> Data Stok</span>
      <small class="text-muted">Total: <?= number_format($totalRows,0,',','.') ?> item</small>
    </div>
    <div class="card-body">
      <?php if (empty($rows)): ?>
        <div class="empty-state">
          <i class="fa-regular fa-box"></i>
          <h6 class="mt-2 mb-1">Belum ada data</h6>
          <p class="text-muted mb-0">Klik tombol <b>Tambah</b> untuk menambahkan stok.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th style="width:70px;">#</th>
                <?php if ($hasKode): ?>
                  <th style="width:150px;">Kode</th>
                  <th style="width:180px;">Barcode</th>
                <?php endif; ?>
                <th>Nama Barang</th>
                <?php if ($hasSat): ?><th style="width:140px;">Satuan</th><?php endif; ?>
                <?php if ($hasLok): ?><th style="width:160px;">Lokasi</th><?php endif; ?>
                <?php if ($hasDesc): ?><th>Deskripsi</th><?php endif; ?>
                <th style="width:130px;" class="text-end">Stok</th>
                <?php if ($hasMin): ?><th style="width:130px;" class="text-end">Min</th><?php endif; ?>
                <th style="width:200px;" class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php $no=$offset+1; foreach($rows as $r): $isLow=$hasMin?((int)$r['stock'] <= (int)$r['min_stock']):false; ?>
              <tr class="<?= $isLow?'table-warning':'' ?>">
                <td><?= $no++ ?></td>

                <?php if ($hasKode): ?>
                  <td><?= htmlspecialchars($r['kode_barang'] ?? '') ?></td>
                  <td>
                    <?php if (!empty($r['kode_barang'])): ?>
                      <svg class="barcode" data-code="<?= htmlspecialchars($r['kode_barang'],ENT_QUOTES) ?>"
                           style="width:170px;height:44px;"></svg>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>

                <td><?= htmlspecialchars($r['nama_barang']) ?></td>
                <?php if ($hasSat): ?><td><?= htmlspecialchars($r['satuan'] ?? '') ?></td><?php endif; ?>
                <?php if ($hasLok): ?><td><?= htmlspecialchars($r['lokasi'] ?? '') ?></td><?php endif; ?>
                <?php if ($hasDesc): ?><td><?= htmlspecialchars($r['deskripsi'] ?? '') ?></td><?php endif; ?>
                <td class="text-end"><?= number_format((int)$r['stock'],0,',','.') ?></td>
                <?php if ($hasMin): ?><td class="text-end"><?= number_format((int)$r['min_stock'],0,',','.') ?></td><?php endif; ?>

                <td class="text-center">
                  <!-- EDIT -->
                  <button class="btn btn-sm btn-outline-secondary me-1"
                          data-bs-toggle="modal" data-bs-target="#modalForm"
                          data-mode="edit"
                          data-id="<?= (int)$r['id_barang'] ?>"
                          data-nama="<?= htmlspecialchars($r['nama_barang'],ENT_QUOTES) ?>"
                          data-stock="<?= (int)$r['stock'] ?>"
                          <?php if ($hasMin):  ?> data-min="<?= (int)$r['min_stock'] ?>" <?php endif; ?>
                          <?php if ($hasSat):  ?> data-satuan="<?= htmlspecialchars($r['satuan'] ?? '',ENT_QUOTES) ?>" <?php endif; ?>
                          <?php if ($hasLok):  ?> data-lokasi="<?= htmlspecialchars($r['lokasi'] ?? '',ENT_QUOTES) ?>" <?php endif; ?>
                          <?php if ($hasKode): ?> data-kode="<?= htmlspecialchars($r['kode_barang'] ?? '',ENT_QUOTES) ?>" <?php endif; ?>
                          <?php if ($hasDesc): ?> data-deskripsi="<?= htmlspecialchars($r['deskripsi'] ?? '',ENT_QUOTES) ?>" <?php endif; ?>
                  ><i class="fa-regular fa-pen-to-square"></i> Edit</button>

                  <!-- CETAK BARCODE -->
                  <?php if ($hasKode && !empty($r['kode_barang'])): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-print"
                            data-code="<?= htmlspecialchars($r['kode_barang'],ENT_QUOTES) ?>">
                      <i class="fa-solid fa-barcode"></i> Cetak
                    </button>
                  <?php endif; ?>

                  <!-- HAPUS -->
                  <form class="d-inline" method="post" onsubmit="return confirm('Hapus barang ini?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_barang" value="<?= (int)$r['id_barang'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                      <i class="fa-regular fa-trash-can"></i> Hapus
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages>1): ?>
          <nav aria-label="Halaman">
            <ul class="pagination justify-content-end">
              <?php $qs= http_build_query(array_filter(['q'=>$q])); for($i=1;$i<=$totalPages;$i++): ?>
                <li class="page-item <?= $i===$page?'active':'' ?>">
                  <a class="page-link" href="<?= url('pages/stok.php').'?'.$qs.'&page='.$i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalForm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Tambah Barang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="id_barang" value="">

        <?php if ($hasKode): ?>
          <div class="mb-3">
            <label class="form-label">Kode Barang</label>
            <input type="text" class="form-control" name="kode_barang" placeholder="(opsional)">
          </div>
          <div class="mb-3">
            <label class="form-label d-block">Preview Barcode</label>
            <svg id="previewBarcode" style="width:260px;height:70px;"></svg>
            <small class="text-muted">Barcode otomatis mengikuti nilai Kode Barang.</small>
          </div>
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="nama_barang" required>
        </div>

        <?php if ($hasDesc): ?>
          <div class="mb-3">
            <label class="form-label">Deskripsi</label>
            <textarea class="form-control" name="deskripsi" rows="2" placeholder="Keterangan singkat"></textarea>
          </div>
        <?php endif; ?>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Stok</label>
            <input type="number" class="form-control" name="stock" min="0" value="0">
          </div>
          <?php if ($hasMin): ?>
          <div class="col-md-4">
            <label class="form-label">Min</label>
            <input type="number" class="form-control" name="min_stock" min="0" value="0">
          </div>
          <?php endif; ?>
          <?php if ($hasSat): ?>
          <div class="col-md-4">
            <label class="form-label">Satuan</label>
            <input type="text" class="form-control" name="satuan" placeholder="pcs, box, dll">
          </div>
          <?php endif; ?>
        </div>

        <?php if ($hasLok): ?>
          <div class="mt-3">
            <label class="form-label">Lokasi</label>
            <input type="text" class="form-control" name="lokasi" placeholder="Gudang A / Rak 1">
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- ========= SCRIPTS: Diletakkan sebelum footer (agar pasti jalan) ========= -->

<!-- JsBarcode untuk render barcode -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

<script>
(function () {
  // Render semua barcode pada tabel
  function renderBarcodes() {
    document.querySelectorAll('svg.barcode').forEach(function (svg) {
      var code = (svg.getAttribute('data-code') || '').trim();
      if (!code) { svg.innerHTML = ''; return; }
      try {
        JsBarcode(svg, code, { format: 'CODE128', height: 40, displayValue: false, margin: 0 });
      } catch (e) { console.error('Barcode render error:', e); }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', renderBarcodes);
  else renderBarcodes();

  // Preview barcode di modal saat user mengetik kode_barang
  const modal = document.getElementById('modalForm');
  if (modal) {
    modal.addEventListener('show.bs.modal', function (ev) {
      const btn   = ev.relatedTarget;
      const form  = modal.querySelector('form');
      const title = modal.querySelector('.modal-title');

      // Reset lebih dulu supaya tidak bawa sisa nilai
      form.reset();
      if (form.stock) form.stock.value = 0;
      if (form.min_stock) form.min_stock.value = 0;

      // Default: tambah
      form.action.value = 'create';
      if (form.id_barang) form.id_barang.value = '';

      if (btn && btn.dataset.mode === 'edit') {
        title.textContent = 'Edit Barang';
        form.action.value = 'update';
        if (form.id_barang) form.id_barang.value = btn.dataset.id || '';
        if (form.nama_barang) form.nama_barang.value = btn.dataset.nama || '';
        if (form.stock) form.stock.value = btn.dataset.stock || 0;
        if (form.min_stock) form.min_stock.value = btn.dataset.min || 0;
        if (form.satuan) form.satuan.value = btn.dataset.satuan || '';
        if (form.lokasi) form.lokasi.value = btn.dataset.lokasi || '';
        if (form.kode_barang) form.kode_barang.value = btn.dataset.kode || '';
        if (form.deskripsi) form.deskripsi.value = btn.dataset.deskripsi || '';
      } else {
        title.textContent = 'Tambah Barang';
      }

      // Render preview barcode (kalau ada kolom kode)
      const prev = document.getElementById('previewBarcode');
      if (prev) {
        const code = (form.kode_barang?.value || '').trim();
        prev.innerHTML = '';
        if (code) JsBarcode(prev, code, { format: 'CODE128', height: 60, displayValue: false, margin: 0 });
      }
    });

    // Update preview saat user mengetik kode
    modal.addEventListener('input', function (ev) {
      if (ev.target && ev.target.name === 'kode_barang') {
        const prev = document.getElementById('previewBarcode');
        if (!prev) return;
        const code = (ev.target.value || '').trim();
        prev.innerHTML = '';
        if (!code) return;
        try { JsBarcode(prev, code, { format: 'CODE128', height: 60, displayValue: false, margin: 0 }); }
        catch(e){ console.warn(e); }
      }
    });
  }

  // Tombol cetak barcode pada tabel
  document.addEventListener('click', function (ev) {
    const btn = ev.target.closest('.btn-print');
    if (!btn) return;
    const code = (btn.dataset.code || '').trim();
    if (!code) return;
    openPrintWindow(code);
  });

  function openPrintWindow(code) {
    const w = window.open('', '_blank', 'width=520,height=320');
    if (!w) { alert('Popup diblokir. Izinkan pop-up untuk situs ini.'); return; }
    const html = `
<!doctype html><html><head><meta charset="utf-8"><title>Barcode ${escapeHtml(code)}</title>
<style>@page{size:auto;margin:10mm}body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial}.wrap{display:flex;align-items:center;justify-content:center;height:100vh}.box{text-align:center}.code{font-weight:600;margin-bottom:6px}</style>
</head><body>
<div class="wrap"><div class="box"><div class="code">${escapeHtml(code)}</div><svg id="printBC" style="width:380px;height:100px;"></svg></div></div>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"><\/script>
<script>JsBarcode("#printBC", ${JSON.stringify(code)}, {format:"CODE128",height:80,displayValue:false,margin:0});window.onload=function(){setTimeout(function(){window.print()},200)};<\/script>
</body></html>`;
    w.document.open(); w.document.write(html); w.document.close();
  }
  function escapeHtml(s){return s.replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
