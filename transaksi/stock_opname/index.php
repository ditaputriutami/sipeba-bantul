<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus','kepala','superadmin']);
$pageTitle = 'Input Stock Opname';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

// Handle store
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal    = $_POST['tanggal'] ?? date('Y-m-d');
    $id_barang  = (int)($_POST['id_barang'] ?? 0);
    $stok_fisik = (int)($_POST['stok_fisik'] ?? 0);
    $keterangan = sanitize($_POST['keterangan'] ?? '');
    $bag        = ($role === 'superadmin') ? (int)$_POST['id_bagian'] : $id_bagian;
    $id_user    = getUserId();

    // Ambil stok sistem
    $stok_sistem = (int)$conn->query("SELECT COALESCE(stok,0) FROM stok_current WHERE id_barang=$id_barang AND id_bagian=$bag")->fetch_row()[0];

    $stmt = $conn->prepare("INSERT INTO stock_opname (tanggal,id_barang,id_bagian,stok_sistem,stok_fisik,keterangan,id_user,status) VALUES(?,?,?,?,?,?,?,'pending')");
    $stmt->bind_param('siiiiis',$tanggal,$id_barang,$bag,$stok_sistem,$stok_fisik,$keterangan,$id_user);
    if($stmt->execute()) setFlash('success','Stock Opname berhasil disimpan. Menunggu persetujuan Kepala.');
    else setFlash('error','Gagal: '.$conn->error);
    $stmt->close();
    header('Location: index.php'); exit;
}

$filterBagian = ($role === 'superadmin') ? '' : "AND so.id_bagian=$id_bagian";
$list = $conn->query("
    SELECT so.*, b.nama_barang, b.satuan, bg.nama as nama_bagian
    FROM stock_opname so JOIN barang b ON so.id_barang=b.id JOIN bagian bg ON so.id_bagian=bg.id
    WHERE 1=1 $filterBagian ORDER BY so.tanggal DESC LIMIT 20
");

$barangList = $conn->query("SELECT id,kode_barang,nama_barang,satuan FROM barang ORDER BY nama_barang");
$bagianList = ($role==='superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-clipboard-check me-2"></i>Input Stock Opname</div>
  </div>
  <div class="page-content">
    <?php $flash=getFlash(); if($flash): ?>
      <div class="alert alert-<?=$flash['type']==='error'?'danger':$flash['type']?> auto-dismiss alert-dismissible fade show">
        <?=htmlspecialchars($flash['message'])?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header"><i class="bi bi-plus-lg me-2"></i>Form Stock Opname</div>
          <div class="card-body p-3">
            <form method="POST">
              <div class="mb-3">
                <label class="form-label">Tanggal SO</label>
                <input type="date" name="tanggal" class="form-control" value="<?=date('Y-m-d')?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Barang</label>
                <select name="id_barang" class="form-select" id="soBarang" required onchange="getStokSistem(this.value)">
                  <option value="">-- Pilih Barang --</option>
                  <?php while($b=$barangList->fetch_assoc()): ?>
                    <option value="<?=$b['id']?>">[<?=htmlspecialchars($b['kode_barang'])?>] <?=htmlspecialchars($b['nama_barang'])?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <?php if($role==='superadmin'): ?>
              <div class="mb-3">
                <label class="form-label">Bagian</label>
                <select name="id_bagian" class="form-select" required>
                  <option value="">-- Pilih Bagian --</option>
                  <?php while($bg=$bagianList->fetch_assoc()): ?>
                    <option value="<?=$bg['id']?>"><?=htmlspecialchars($bg['nama'])?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <?php endif; ?>
              <div class="mb-3">
                <label class="form-label">Stok Sistem <small class="text-muted">(otomatis)</small></label>
                <input type="number" id="stokSistemDisplay" class="form-control" readonly value="0">
              </div>
              <div class="mb-3">
                <label class="form-label">Stok Fisik <span class="text-danger">*</span></label>
                <input type="number" name="stok_fisik" class="form-control" min="0" required placeholder="0">
              </div>
              <div class="mb-3">
                <label class="form-label">Keterangan</label>
                <textarea name="keterangan" class="form-control" rows="2" placeholder="Alasan selisih (jika ada)"></textarea>
              </div>
              <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Simpan Stock Opname</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header"><i class="bi bi-list-ul me-2"></i>Riwayat Stock Opname (20 terbaru)</div>
          <div class="table-wrapper">
            <table class="table table-sm">
              <thead><tr><th>Tanggal</th><th>Barang</th><th>Stok Sistem</th><th>Stok Fisik</th><th>Selisih</th><th>Status</th></tr></thead>
              <tbody>
                <?php while($s=$list->fetch_assoc()): ?>
                <tr>
                  <td><?=formatTanggal($s['tanggal'])?></td>
                  <td><?=htmlspecialchars($s['nama_barang'])?></td>
                  <td class="text-center"><?=number_format($s['stok_sistem'])?></td>
                  <td class="text-center"><?=number_format($s['stok_fisik'])?></td>
                  <td class="text-center fw-bold <?=$s['selisih']>0?'text-success':($s['selisih']<0?'text-danger':'')?>">
                    <?=($s['selisih']>0?'+':'').number_format($s['selisih'])?>
                  </td>
                  <td>
                    <?php $sc=['pending'=>'badge-pending','disetujui'=>'badge-approved','ditolak'=>'badge-rejected']; ?>
                    <span class="badge-sipeba <?=$sc[$s['status']]??''?>"><?=ucfirst($s['status'])?></span>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
async function getStokSistem(idBarang) {
  if (!idBarang) { document.getElementById('stokSistemDisplay').value = 0; return; }
  const bagian = <?=$id_bagian ?? 'null'?>;
  const params = new URLSearchParams({ id_barang: idBarang });
  if (bagian) params.append('id_bagian', bagian);
  const resp = await fetch('<?=BASE_URL?>/api/get_stok.php?' + params.toString());
  const data = await resp.json();
  document.getElementById('stokSistemDisplay').value = data.stok || 0;
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
