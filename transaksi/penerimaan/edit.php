<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus', 'kepala', 'superadmin']);
$pageTitle = 'Edit Penerimaan Barang';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('ID tidak valid', 'error');
    header('Location: index.php');
    exit;
}

// Ambil data penerimaan
$stmt = $conn->prepare("SELECT p.*, b.id_jenis_barang FROM penerimaan p JOIN barang b ON p.id_barang=b.id WHERE p.id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$penerimaan = $stmt->get_result()->fetch_assoc();

if (!$penerimaan) {
    setFlash('Data tidak ditemukan', 'error');
    header('Location: index.php');
    exit;
}

// Cek akses: hanya bisa edit jika status pending dan dari bagian sendiri (kecuali superadmin)
if ($penerimaan['status'] !== 'pending') {
    setFlash('Tidak dapat mengedit transaksi yang sudah disetujui atau ditolak', 'error');
    header('Location: index.php');
    exit;
}

if ($role !== 'superadmin' && $penerimaan['id_bagian'] != $id_bagian) {
    setFlash('Anda tidak memiliki akses untuk mengedit transaksi ini', 'error');
    header('Location: index.php');
    exit;
}

$jenisList = $conn->query("SELECT id, kode_jenis, nama_jenis FROM jenis_barang ORDER BY nama_jenis");
$barangList = $conn->query("SELECT id, kode_barang, nama_barang, satuan, id_jenis_barang FROM barang ORDER BY nama_barang");
$bagianList = ($role === 'superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
        <div class="topbar-title"><i class="bi bi-pencil me-2"></i>Edit Penerimaan Barang</div>
    </div>
    <div class="page-content">
        <div class="card" style="max-width:700px">
            <div class="card-header">
                <i class="bi bi-file-earmark-text me-2"></i>Form Edit Penerimaan Barang
                <span class="badge bg-warning text-dark ms-2">Status: Pending</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="update.php">
                    <input type="hidden" name="id" value="<?= $penerimaan['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">No. Faktur / Dokumen <span class="text-danger">*</span></label>
                            <input type="text" name="no_faktur" class="form-control" value="<?= htmlspecialchars($penerimaan['no_faktur']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Faktur <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal" class="form-control" value="<?= $penerimaan['tanggal'] ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Jenis Barang <span class="text-danger">*</span></label>
                            <select class="form-select" id="jenisFilter" onchange="filterBarang(this.value)">
                                <option value="">-- Semua Jenis --</option>
                                <?php while ($j = $jenisList->fetch_assoc()): ?>
                                    <option value="<?= $j['id'] ?>" <?= $j['id'] == $penerimaan['id_jenis_barang'] ? 'selected' : '' ?>>[<?= htmlspecialchars($j['kode_jenis']) ?>] <?= htmlspecialchars($j['nama_jenis']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
                            <select name="id_barang" class="form-select" id="barangSelect" required onchange="setSatuan(this)">
                                <option value="" data-jenis="">-- Pilih Barang --</option>
                                <?php while ($b = $barangList->fetch_assoc()): ?>
                                    <option value="<?= $b['id'] ?>" data-satuan="<?= htmlspecialchars($b['satuan']) ?>" data-jenis="<?= $b['id_jenis_barang'] ?>" <?= $b['id'] == $penerimaan['id_barang'] ? 'selected' : '' ?>>
                                        [<?= htmlspecialchars($b['kode_barang']) ?>] <?= htmlspecialchars($b['nama_barang']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jumlah / Banyaknya <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="jumlah" class="form-control" min="1" value="<?= $penerimaan['jumlah'] ?>" required placeholder="0">
                                <span class="input-group-text" id="satuanLabel">—</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Harga Satuan (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" name="harga_satuan" class="form-control" min="0" step="1" value="<?= $penerimaan['harga_satuan'] ?>" required placeholder="0">
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">DARI</label>
                            <input type="text" name="dari" class="form-control" value="<?= htmlspecialchars($penerimaan['dari'] ?? '') ?>" placeholder="Nama supplier/dinas">
                        </div>
                        <?php if ($role === 'superadmin'): ?>
                            <div class="col-md-6">
                                <label class="form-label">Bagian <span class="text-danger">*</span></label>
                                <select name="id_bagian" class="form-select" required>
                                    <option value="">-- Pilih Bagian --</option>
                                    <?php while ($bg = $bagianList->fetch_assoc()): ?>
                                        <option value="<?= $bg['id'] ?>" <?= $bg['id'] == $penerimaan['id_bagian'] ? 'selected' : '' ?>><?= htmlspecialchars($bg['nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="id_bagian" value="<?= $id_bagian ?>">
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan tambahan (opsional)"><?= htmlspecialchars($penerimaan['keterangan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update Penerimaan</button>
                        <a href="index.php" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    // Set satuan saat halaman load
    document.addEventListener('DOMContentLoaded', function() {
        setSatuan(document.getElementById('barangSelect'));
    });

    function setSatuan(sel) {
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('satuanLabel').textContent = opt.dataset.satuan || '—';
    }

    function filterBarang(jenisId) {
        const select = document.getElementById('barangSelect');
        const options = select.options;

        for (let i = 0; i < options.length; i++) {
            const opt = options[i];
            if (opt.value === "") continue;

            if (jenisId === "" || opt.dataset.jenis === jenisId) {
                opt.style.display = "";
            } else {
                opt.style.display = "none";
            }
        }
    }
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>