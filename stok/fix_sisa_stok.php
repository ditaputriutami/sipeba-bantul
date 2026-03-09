<?php

/**
 * FIX: Update sisa_stok untuk penerimaan yang sudah disetujui
 * Run sekali untuk data lama yang sisa_stok = 0
 */

require_once __DIR__ . '/../config/bootstrap.php';
requireRole(['superadmin']);

$pageTitle = 'Fix Sisa Stok FIFO';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    $conn->begin_transaction();
    try {
        // Update sisa_stok untuk penerimaan yang sudah disetujui tapi sisa_stok = 0
        $query = "UPDATE penerimaan SET sisa_stok = jumlah WHERE status = 'disetujui' AND (sisa_stok = 0 OR sisa_stok IS NULL)";
        $conn->query($query);
        $affected = $conn->affected_rows;

        $conn->commit();
        $message = "✅ Berhasil! $affected record penerimaan telah diperbaiki.";
        $success = true;
    } catch (Exception $e) {
        $conn->rollback();
        $message = "❌ Gagal: " . $e->getMessage();
        $success = false;
    }
}

// Cek berapa record yang perlu diperbaiki
$check = $conn->query("SELECT COUNT(*) as total, SUM(jumlah) as total_qty FROM penerimaan WHERE status = 'disetujui' AND (sisa_stok = 0 OR sisa_stok IS NULL)")->fetch_assoc();

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
        <div class="topbar-title"><i class="bi bi-wrench me-2"></i>Fix Sisa Stok FIFO</div>
    </div>
    <div class="page-content">

        <?php if (isset($message)): ?>
            <div class="alert alert-<?= $success ? 'success' : 'danger' ?> alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width: 700px;">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-exclamation-triangle me-2"></i><strong>Perbaikan Data FIFO</strong>
            </div>
            <div class="card-body">
                <h6 class="mb-3">Diagnosa:</h6>
                <div class="alert alert-info">
                    <strong>Penerimaan yang perlu diperbaiki:</strong><br>
                    <ul class="mb-0 mt-2">
                        <li>Jumlah record: <strong><?= number_format($check['total']) ?></strong></li>
                        <li>Total kuantitas: <strong><?= number_format($check['total_qty'] ?? 0) ?></strong></li>
                    </ul>
                </div>

                <h6 class="mb-2">Penjelasan:</h6>
                <p>Penerimaan yang disetujui harus memiliki <code>sisa_stok = jumlah</code> agar bisa digunakan dalam sistem FIFO.</p>

                <h6 class="mb-2 mt-3">SQL Query:</h6>
                <pre class="bg-light p-3 border rounded"><code>UPDATE penerimaan 
SET sisa_stok = jumlah 
WHERE status = 'disetujui' 
  AND (sisa_stok = 0 OR sisa_stok IS NULL)</code></pre>

                <?php if ($check['total'] > 0): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Ada <strong><?= $check['total'] ?></strong> record yang akan diperbaiki.
                    </div>

                    <form method="POST" onsubmit="return confirm('Yakin ingin memperbaiki <?= $check['total'] ?> record?')">
                        <input type="hidden" name="confirm" value="1">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-wrench me-1"></i>Jalankan Perbaikan
                        </button>
                        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary">Batal</a>
                    </form>
                <?php else: ?>
                    <div class="alert alert-success mt-3">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Tidak ada data yang perlu diperbaiki.</strong>
                    </div>
                    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary">Kembali</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($check['total'] > 0): ?>
            <div class="card mt-3">
                <div class="card-header">Preview (10 record pertama)</div>
                <div class="table-wrapper">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>No. Faktur</th>
                                <th>Tanggal</th>
                                <th>Barang</th>
                                <th>Jumlah</th>
                                <th>Sisa Stok (Sekarang)</th>
                                <th>Sisa Stok (Akan Jadi)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $preview = $conn->query("
                SELECT p.*, b.nama_barang 
                FROM penerimaan p
                JOIN barang b ON p.id_barang = b.id
                WHERE p.status = 'disetujui' 
                  AND (p.sisa_stok = 0 OR p.sisa_stok IS NULL)
                ORDER BY p.tanggal DESC
                LIMIT 10
              ");
                            while ($row = $preview->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><code><?= htmlspecialchars($row['no_faktur']) ?></code></td>
                                    <td><?= formatTanggal($row['tanggal']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                    <td><?= number_format($row['jumlah']) ?></td>
                                    <td><span class="badge bg-danger"><?= $row['sisa_stok'] ?? 0 ?></span></td>
                                    <td><span class="badge bg-success"><?= $row['jumlah'] ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
