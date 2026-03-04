<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus','kepala','superadmin']);
$pageTitle = 'Tambah Pengurangan Barang';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

$barangList = $conn->query("SELECT id, kode_barang, nama_barang, satuan FROM barang ORDER BY nama_barang");
$bagianList = ($role === 'superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-dash-circle me-2"></i>Tambah Pengurangan Barang</div>
  </div>
  <div class="page-content">
    <div id="stokInfo" class="alert alert-info" style="display:none;max-width:700px">
      <i class="bi bi-info-circle me-2"></i>Stok tersedia: <strong id="stokValue">0</strong>
    </div>
    <div id="stokWarning" class="alert alert-danger" style="display:none;max-width:700px">
      <i class="bi bi-exclamation-triangle me-2"></i>Stok tidak mencukupi!
    </div>
    <div class="card" style="max-width:700px">
      <div class="card-header">
        <i class="bi bi-file-earmark-minus me-2"></i>Form Pengurangan Barang Persediaan
        <span class="badge bg-warning text-dark ms-2">Status: Pending</span>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="store.php" id="pengForm">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">No. Permintaan / Nomor</label>
              <input type="text" name="no_permintaan" class="form-control" placeholder="Contoh: PERM/2024/001">
            </div>
            <div class="col-md-6">
              <label class="form-label">Tanggal <span class="text-danger">*</span></label>
              <input type="date" name="tanggal" class="form-control" value="<?=date('Y-m-d')?>" required>
            </div>
            <div class="col-12">
              <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
              <select name="id_barang" class="form-select" id="barangSelect" required onchange="checkStok(this)">
                <option value="">-- Pilih Barang --</option>
                <?php while($b=$barangList->fetch_assoc()): ?>
                  <option value="<?=$b['id']?>" data-satuan="<?=htmlspecialchars($b['satuan'])?>">
                    [<?=htmlspecialchars($b['kode_barang'])?>] <?=htmlspecialchars($b['nama_barang'])?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Jumlah <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="number" name="jumlah" id="jumlahInput" class="form-control" min="1" required placeholder="0" onchange="validateJumlah()">
                <span class="input-group-text" id="satuanLabel">—</span>
              </div>
            </div>
            <?php if($role==='superadmin'): ?>
            <div class="col-md-6">
              <label class="form-label">Bagian <span class="text-danger">*</span></label>
              <select name="id_bagian" class="form-select" required>
                <option value="">-- Pilih Bagian --</option>
                <?php while($bg=$bagianList->fetch_assoc()): ?>
                  <option value="<?=$bg['id']?>"><?=htmlspecialchars($bg['nama'])?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <?php else: ?>
              <input type="hidden" name="id_bagian" value="<?=$id_bagian?>">
            <?php endif; ?>
            <div class="col-md-6">
              <label class="form-label">Penerima Barang</label>
              <input type="text" name="penerima" class="form-control" placeholder="Nama penerima">
            </div>
            <div class="col-md-6">
              <label class="form-label">Tanggal Penyerahan</label>
              <input type="date" name="tanggal_penyerahan" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Keterangan</label>
              <textarea name="keterangan" class="form-control" rows="2" placeholder="Tujuan penggunaan barang (opsional)"></textarea>
            </div>
          </div>
          <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-warning" id="submitBtn"><i class="bi bi-save me-1"></i>Simpan Pengurangan</button>
            <a href="index.php" class="btn btn-outline-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
    <div class="alert alert-info mt-3" style="max-width:700px">
      <i class="bi bi-info-circle me-2"></i>
      <strong>FIFO:</strong> Pengurangan stok akan memotong dari batch penerimaan tertua terlebih dahulu secara otomatis saat disetujui.
    </div>
  </div>
</div>
<script>
let stokTersedia = 0;
const bagianId = <?=$id_bagian ?? 'null'?>;

async function checkStok(sel) {
  const idBarang = sel.value;
  const opt = sel.options[sel.selectedIndex];
  document.getElementById('satuanLabel').textContent = opt.dataset.satuan || '—';

  if (!idBarang) { hideStokInfo(); return; }

  // Fetch stok via hidden endpoint
  const params = new URLSearchParams({ id_barang: idBarang });
  <?php if($role !== 'superadmin'): ?>params.append('id_bagian', <?=$id_bagian?>);<?php endif; ?>

  const resp = await fetch('<?=BASE_URL?>/api/get_stok.php?' + params.toString());
  const data = await resp.json();
  stokTersedia = data.stok || 0;

  document.getElementById('stokInfo').style.display = 'block';
  document.getElementById('stokValue').textContent = stokTersedia + ' ' + (opt.dataset.satuan || '');
  document.getElementById('stokWarning').style.display = 'none';
  validateJumlah();
}

function validateJumlah() {
  const jumlah = parseInt(document.getElementById('jumlahInput').value) || 0;
  const warn = document.getElementById('stokWarning');
  const btn  = document.getElementById('submitBtn');
  if (stokTersedia > 0 && jumlah > stokTersedia) {
    warn.style.display = 'block';
    btn.disabled = true;
  } else {
    warn.style.display = 'none';
    btn.disabled = false;
  }
}

function hideStokInfo() {
  document.getElementById('stokInfo').style.display = 'none';
  document.getElementById('stokWarning').style.display = 'none';
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
