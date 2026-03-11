<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus', 'kepala']);

$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

// Get ID from URL
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('error', 'ID pengurangan tidak valid.');
    header('Location: index.php');
    exit;
}

// Get pengurangan data
$stmt = $conn->prepare("
    SELECT p.*, b.nama_barang, b.satuan, b.id_jenis_barang, b.kode_barang
    FROM pengurangan p
    JOIN barang b ON p.id_barang = b.id
    WHERE p.id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$pengurangan = $result->fetch_assoc();
$stmt->close();

if (!$pengurangan) {
    setFlash('error', 'Data pengurangan tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Validate status
if ($pengurangan['status'] !== 'pending') {
    setFlash('error', 'Hanya pengurangan dengan status pending yang dapat diedit.');
    header('Location: index.php');
    exit;
}

// Validate ownership (pengurus/kepala only edit their own bagian, superadmin can edit all)
if ($role !== 'superadmin' && $pengurangan['id_bagian'] != $id_bagian) {
    setFlash('error', 'Anda tidak memiliki akses untuk mengedit data ini.');
    header('Location: index.php');
    exit;
}

$pageTitle = 'Edit Pengurangan Barang';

$jenisList = $conn->query("SELECT id, nama_jenis FROM jenis_barang ORDER BY nama_jenis");
$barangList = $conn->query("SELECT id, kode_barang, nama_barang, satuan, id_jenis_barang FROM barang ORDER BY nama_barang");
$bagianList = ($role === 'superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
        <div class="topbar-title"><i class="bi bi-pencil-square me-2"></i>Edit Pengurangan Barang</div>
    </div>
    <div class="page-content">
        <div id="stokInfo" class="alert alert-info" style="display:none;max-width:700px">
            <i class="bi bi-info-circle me-2"></i>Stok tersedia: <strong id="stokValue">0</strong>
        </div>
        <div id="stokWarning" class="alert alert-danger" style="display:none;max-width:700px">
            <i class="bi bi-exclamation-triangle me-2"></i>Stok tidak mencukupi!
        </div>

        <!-- Tabel Detail FIFO (muncul jika multiple batch) -->
        <div id="fifoDetailCard" class="card mb-3" style="display:none;">
            <div class="card-header bg-light">
                <i class="bi bi-list-check me-2"></i><strong>Detail FIFO:</strong> Batch yang Akan Dipotong
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal Batch</th>
                                <th>No. Faktur</th>
                                <th class="text-center">Sisa Stok</th>
                                <th class="text-center">Akan Dipotong</th>
                                <th class="text-end">Harga Satuan</th>
                                <th class="text-end">Nilai</th>
                            </tr>
                        </thead>
                        <tbody id="fifoDetailBody">
                            <!-- Filled by JavaScript -->
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3" class="text-end">TOTAL:</th>
                                <th class="text-center" id="totalQty">0</th>
                                <th class="text-end">—</th>
                                <th class="text-end" id="totalNilai">Rp 0</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light text-muted small">
                <i class="bi bi-info-circle me-1"></i>
                <strong>FIFO:</strong> Pengurangan mengambil dari batch penerimaan <strong>tertua</strong> terlebih dahulu.
                Jika batch pertama tidak cukup, akan dilanjutkan ke batch berikutnya.
            </div>
        </div>

        <div class="card" style="max-width:700px">
            <div class="card-header">
                <i class="bi bi-pencil-square me-2"></i>Edit Form Pengurangan Barang
                <span class="badge bg-warning text-dark ms-2">Status: Pending</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="update.php" id="pengForm">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">No. Permintaan / Nomor</label>
                            <input type="text" name="no_permintaan" class="form-control" placeholder="" value="<?= htmlspecialchars($pengurangan['no_permintaan'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($pengurangan['tanggal']) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Jenis Barang <span class="text-danger">*</span></label>
                            <select name="id_jenis_barang" class="form-select" id="jenisSelect" required onchange="filterBarang(this)">
                                <option value="">-- Pilih Jenis Barang --</option>
                                <?php while ($jenis = $jenisList->fetch_assoc()): ?>
                                    <option value="<?= $jenis['id'] ?>" <?= $jenis['id'] == $pengurangan['id_jenis_barang'] ? 'selected' : '' ?>><?= htmlspecialchars($jenis['nama_jenis']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
                            <select name="id_barang" class="form-select" id="barangSelect" required onchange="checkStok(this)">
                                <option value="">-- Pilih Barang --</option>
                                <?php $barangList->data_seek(0);
                                while ($b = $barangList->fetch_assoc()): ?>
                                    <option value="<?= $b['id'] ?>"
                                        data-satuan="<?= htmlspecialchars($b['satuan']) ?>"
                                        data-jenis="<?= $b['id_jenis_barang'] ?>"
                                        style="display:none;"
                                        <?= $b['id'] == $pengurangan['id_barang'] ? 'selected' : '' ?>>
                                        [<?= htmlspecialchars($b['kode_barang']) ?>] <?= htmlspecialchars($b['nama_barang']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jumlah <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="jumlah" id="jumlahInput" class="form-control" min="1" required placeholder="0" value="<?= $pengurangan['jumlah'] ?>" onchange="validateJumlah()" oninput="updateHarga()">
                                <span class="input-group-text" id="satuanLabel"><?= htmlspecialchars($pengurangan['satuan']) ?></span>
                            </div>
                        </div>
                        <?php if ($role === 'superadmin'): ?>
                            <div class="col-md-6">
                                <label class="form-label">Bagian <span class="text-danger">*</span></label>
                                <select name="id_bagian" class="form-select" required onchange="checkStokAfterBagian()">
                                    <option value="">-- Pilih Bagian --</option>
                                    <?php $bagianList->data_seek(0);
                                    while ($bg = $bagianList->fetch_assoc()): ?>
                                        <option value="<?= $bg['id'] ?>" <?= $bg['id'] == $pengurangan['id_bagian'] ? 'selected' : '' ?>><?= htmlspecialchars($bg['nama']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="id_bagian" value="<?= $id_bagian ?>">
                        <?php endif; ?>

                        <!-- Harga Satuan (Auto-calculated dari FIFO) -->
                        <div class="col-md-6">
                            <label class="form-label">Harga Satuan <small class="text-muted" id="hargaLabel">(FIFO)</small></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" id="hargaSatuanDisplay" class="form-control bg-light" readonly value="0">
                            </div>
                            <small class="text-muted" id="hargaBreakdown">Harga dari batch yang digunakan</small>
                        </div>

                        <!-- Jumlah Harga Total -->
                        <div class="col-md-6">
                            <label class="form-label">Jumlah Harga <small class="text-muted">(Total Nilai)</small></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" id="jumlahHargaDisplay" class="form-control bg-light" readonly value="0">
                            </div>
                            <small class="text-muted" id="hargaNote">Total nilai pengurangan</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Sumber Dana</label>
                            <textarea name="keterangan" class="form-control" rows="2" placeholder="Sumber dana pembelian barang persediaan (opsional)"><?= htmlspecialchars($pengurangan['keterangan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="submitBtn"><i class="bi bi-save me-1"></i>Update Pengurangan</button>
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
    let currentIdBarang = <?= $pengurangan['id_barang'] ?>;
    let currentBagianId = <?= $pengurangan['id_bagian'] ?>;
    const bagianId = <?= $id_bagian ?? 'null' ?>;

    // Initialize - show barang options for selected jenis
    window.addEventListener('DOMContentLoaded', () => {
        const jenisSelect = document.getElementById('jenisSelect');
        filterBarang(jenisSelect);

        // Load stok for selected barang
        const barangSelect = document.getElementById('barangSelect');
        if (barangSelect.value) {
            checkStok(barangSelect);
        }
    });

    function filterBarang(sel) {
        const idJenis = sel.value;
        const barangSelect = document.getElementById('barangSelect');
        const options = barangSelect.querySelectorAll('option');
        const currentValue = barangSelect.value; // Preserve current selection

        // Show/hide barang options based on jenis selection
        options.forEach(opt => {
            if (opt.value === '') {
                opt.style.display = 'block'; // Always show the placeholder
            } else {
                opt.style.display = opt.dataset.jenis === idJenis ? 'block' : 'none';
            }
        });

        // Restore selection if it was valid
        if (currentValue && barangSelect.querySelector(`option[value="${currentValue}"]`).style.display !== 'none') {
            barangSelect.value = currentValue;
        }
    }

    async function checkStok(sel) {
        const idBarang = sel.value;
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('satuanLabel').textContent = opt.dataset.satuan || '—';
        currentIdBarang = parseInt(idBarang) || 0;

        if (!idBarang) {
            hideStokInfo();
            return;
        }

        // Get bagian ID
        let bagian = currentBagianId;
        <?php if ($role === 'superadmin'): ?>
            const bagianSelect = document.querySelector('select[name="id_bagian"]');
            if (bagianSelect && bagianSelect.value) {
                bagian = parseInt(bagianSelect.value);
                currentBagianId = bagian;
            }
        <?php endif; ?>

        // Fetch stok via API
        const params = new URLSearchParams({
            id_barang: idBarang
        });
        if (bagian) params.append('id_bagian', bagian);

        const resp = await fetch('<?= BASE_URL ?>/api/get_stok.php?' + params.toString());
        const data = await resp.json();
        stokTersedia = data.stok || 0;

        document.getElementById('stokInfo').style.display = 'block';
        document.getElementById('stokValue').textContent = stokTersedia + ' ' + (opt.dataset.satuan || '');
        document.getElementById('stokWarning').style.display = 'none';
        validateJumlah();

        // Update harga jika jumlah sudah diisi
        const jumlah = parseInt(document.getElementById('jumlahInput').value) || 0;
        if (jumlah > 0) {
            updateHarga();
        }
    }

    function validateJumlah() {
        const jumlah = parseInt(document.getElementById('jumlahInput').value) || 0;
        const warn = document.getElementById('stokWarning');
        const btn = document.getElementById('submitBtn');
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
        document.getElementById('hargaSatuanDisplay').value = '0';
        document.getElementById('jumlahHargaDisplay').value = '0';
        document.getElementById('hargaLabel').textContent = '(FIFO)';
        document.getElementById('hargaBreakdown').textContent = 'Harga dari batch yang digunakan';
        document.getElementById('hargaNote').textContent = 'Total nilai pengurangan';
        document.getElementById('fifoDetailCard').style.display = 'none';
    }

    // For superadmin: re-check stok when bagian changed
    function checkStokAfterBagian() {
        const barangSelect = document.getElementById('barangSelect');
        if (barangSelect && barangSelect.value) {
            checkStok(barangSelect);
        }
    }

    // Update harga berdasarkan FIFO
    let hargaTimer = null;
    async function updateHarga() {
        const jumlah = parseInt(document.getElementById('jumlahInput').value) || 0;

        if (!currentIdBarang || jumlah <= 0) {
            document.getElementById('hargaSatuanDisplay').value = '0';
            document.getElementById('jumlahHargaDisplay').value = '0';
            document.getElementById('fifoDetailCard').style.display = 'none';
            return;
        }

        // Debounce
        if (hargaTimer) clearTimeout(hargaTimer);

        hargaTimer = setTimeout(async () => {
            try {
                let bagian = currentBagianId;
                <?php if ($role === 'superadmin'): ?>
                    const bagianSelect = document.querySelector('select[name="id_bagian"]');
                    if (bagianSelect && bagianSelect.value) {
                        bagian = parseInt(bagianSelect.value);
                    }
                    if (!bagian) return;
                <?php endif; ?>

                const params = new URLSearchParams({
                    id_barang: currentIdBarang,
                    id_bagian: bagian,
                    jumlah: jumlah
                });

                const resp = await fetch('<?= BASE_URL ?>/api/get_harga_fifo.php?' + params.toString());
                const data = await resp.json();

                if (data.success) {
                    // Tampilkan breakdown harga per batch jika multiple batch
                    if (data.multiple_batch && data.details.length > 1) {
                        // Format: "Rp 25.000 (3) + Rp 2.000 (1)"
                        const breakdown = data.details.map(b =>
                            `${formatRupiah(b.harga_satuan)} (${b.qty_dipotong})`
                        ).join(' + ');
                        document.getElementById('hargaSatuanDisplay').value = breakdown;
                        document.getElementById('hargaLabel').textContent = '(Multi-Batch)';
                        document.getElementById('hargaBreakdown').innerHTML = '<span class="text-info"><i class="bi bi-layers"></i> ' + data.details.length + ' batch: ' + data.details.map(b => `${b.qty_dipotong} @ ${formatRupiah(b.harga_satuan)}`).join(', ') + '</span>';
                    } else {
                        // Single batch: tampilkan harga normal
                        document.getElementById('hargaSatuanDisplay').value = formatNumber(data.harga_satuan);
                        document.getElementById('hargaLabel').textContent = '(Batch Tunggal)';
                        document.getElementById('hargaBreakdown').textContent = 'Harga dari 1 batch';
                    }

                    document.getElementById('jumlahHargaDisplay').value = formatNumber(data.jumlah_harga);

                    // Tampilkan detail FIFO jika ada multiple batch
                    if (data.multiple_batch && data.details.length > 1) {
                        renderFifoDetail(data.details);
                        document.getElementById('fifoDetailCard').style.display = 'block';

                        // Update note jika ada perbedaan harga
                        const uniquePrices = [...new Set(data.details.map(d => d.harga_satuan))];
                        if (uniquePrices.length > 1) {
                            document.getElementById('hargaNote').innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle"></i> Mengambil dari ' + data.details.length + ' batch dengan harga berbeda</span>';
                        } else {
                            document.getElementById('hargaNote').innerHTML = '<span class="text-info"><i class="bi bi-info-circle"></i> ' + data.details.length + ' batch dengan harga sama</span>';
                        }
                    } else {
                        document.getElementById('fifoDetailCard').style.display = 'none';
                        document.getElementById('hargaNote').textContent = 'Total nilai pengurangan';
                    }
                } else {
                    document.getElementById('hargaSatuanDisplay').value = '0';
                    document.getElementById('jumlahHargaDisplay').value = '0';
                    document.getElementById('hargaLabel').textContent = '(FIFO)';
                    document.getElementById('hargaBreakdown').textContent = 'Harga dari batch yang digunakan';
                    document.getElementById('fifoDetailCard').style.display = 'none';
                }
            } catch (error) {
                console.error('Error fetching harga:', error);
            }
        }, 500);
    }

    function renderFifoDetail(details) {
        const tbody = document.getElementById('fifoDetailBody');
        tbody.innerHTML = '';

        let totalQty = 0;
        let totalNilai = 0;

        details.forEach((batch, index) => {
            totalQty += batch.qty_dipotong;
            totalNilai += batch.nilai;

            const bgClass = batch.qty_dipotong >= batch.sisa_stok ? 'table-danger' : 'table-warning';

            const row = document.createElement('tr');
            row.className = bgClass;
            row.innerHTML = `
      <td class="small">${batch.tanggal}</td>
      <td class="small"><code>${escapeHtml(batch.no_faktur)}</code></td>
      <td class="text-center small">${batch.sisa_stok}</td>
      <td class="text-center small"><strong>${batch.qty_dipotong}</strong></td>
      <td class="text-end small">${formatRupiah(batch.harga_satuan)}</td>
      <td class="text-end small"><strong>${formatRupiah(batch.nilai)}</strong></td>
    `;
            tbody.appendChild(row);
        });

        document.getElementById('totalQty').innerHTML = `<strong>${totalQty}</strong>`;
        document.getElementById('totalNilai').innerHTML = `<strong>${formatRupiah(totalNilai)}</strong>`;
    }

    function formatNumber(num) {
        return Math.round(num).toLocaleString('id-ID');
    }

    function formatRupiah(angka) {
        return 'Rp ' + Math.round(angka).toLocaleString('id-ID');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>