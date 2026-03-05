<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus','kepala','superadmin']);
$pageTitle = 'Tambah Penerimaan Barang';
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
    <div class="topbar-title"><i class="bi bi-plus-circle me-2"></i>Tambah Penerimaan Barang</div>
  </div>
  <div class="page-content">
    <div class="card" style="max-width:700px">
      <div class="card-header">
        <i class="bi bi-file-earmark-plus me-2"></i>Form Buku Penerimaan Barang Persediaan
        <span class="badge bg-warning text-dark ms-2">Status: Pending (menunggu persetujuan)</span>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="store.php">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">No. Faktur / Dokumen <span class="text-danger">*</span></label>
              <input type="text" name="no_faktur" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tanggal Faktur <span class="text-danger">*</span></label>
              <input type="date" name="tanggal" class="form-control" value="<?=date('Y-m-d')?>" required>
            </div>
            <div class="col-12">
              <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
              <select name="id_barang" class="form-select" id="barangSelect" required onchange="setSatuan(this)">
                <option value="">-- Pilih Barang --</option>
                <?php while($b=$barangList->fetch_assoc()): ?>
                  <option value="<?=$b['id']?>" data-satuan="<?=htmlspecialchars($b['satuan'])?>">
                    [<?=htmlspecialchars($b['kode_barang'])?>] <?=htmlspecialchars($b['nama_barang'])?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Jumlah / Banyaknya <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="number" name="jumlah" class="form-control" min="1" required placeholder="0">
                <span class="input-group-text" id="satuanLabel">—</span>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Harga Satuan (Rp) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" name="harga_satuan" class="form-control" min="0" step="1" required placeholder="0">
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">No. Bukti Penerimaan</label>
              <input type="text" name="no_bukti_penerimaan" class="form-control" placeholder="Contoh: BP/2024/001">
            </div>
            <div class="col-md-6">
              <label class="form-label">Tanggal Bukti Penerimaan</label>
              <input type="date" name="tanggal_bukti_penerimaan" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Sumber Penerimaan <span class="text-danger">*</span></label>
              <select name="sumber" class="form-select" required>
                <option value="">-- Pilih Sumber --</option>
                <option value="belanja_modal">Belanja Modal</option>
                <option value="belanja_barang_jasa">Belanja Barang/Jasa</option>
                <option value="dropping">Dropping</option>
                <option value="hibah">Hibah</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">DARI (Pemasok/Sumber)</label>
              <input type="text" name="dari" class="form-control" placeholder="Nama supplier/dinas">
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
            <div class="col-12">
              <label class="form-label">Keterangan</label>
              <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan tambahan (opsional)"></textarea>
            </div>
          </div>
          <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Penerimaan</button>
            <a href="index.php" class="btn btn-outline-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
    <div class="alert alert-info mt-3" style="max-width:700px">
      <i class="bi bi-info-circle me-2"></i>
      <strong>Informasi:</strong> Transaksi akan tersimpan dengan status <strong>Pending</strong>.
      Stok tidak akan berubah sebelum disetujui oleh Kepala Bagian.
    </div>
  </div>
</div>
<script>
function setSatuan(sel) {
  const opt = sel.options[sel.selectedIndex];
  document.getElementById('satuanLabel').textContent = opt.dataset.satuan || '—';
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
