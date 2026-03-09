<?php

/**
 * Kepala Bagian - Approval Stock Opname
 */
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['kepala', 'superadmin']);
$pageTitle = 'Approval Stock Opname';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

// Handle approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['so_id'])) {
  $so_id   = (int)$_POST['so_id'];
  $action  = $_POST['action'] ?? '';
  $catatan = sanitize($_POST['catatan'] ?? '');
  $now     = date('Y-m-d H:i:s');
  $approver = getUserId();

  if ($action === 'setujui') {
    $conn->query("UPDATE stock_opname SET status='disetujui', id_approver=$approver, approved_at='$now' WHERE id=$so_id");
    setFlash('success', 'Stock Opname disetujui.');
  } elseif ($action === 'tolak') {
    $conn->query("UPDATE stock_opname SET status='ditolak', id_approver=$approver, approved_at='$now' WHERE id=$so_id");
    setFlash('success', 'Stock Opname ditolak.');
  }
  header('Location: index.php');
  exit;
}

$bagianFilter = ($role === 'superadmin') ? '' : "AND so.id_bagian=$id_bagian";
$list = $conn->query("
    SELECT so.*, b.nama_barang, b.satuan, bg.nama as nama_bagian, u.nama as nama_input
    FROM stock_opname so JOIN barang b ON so.id_barang=b.id JOIN bagian bg ON so.id_bagian=bg.id JOIN users u ON so.id_user=u.id
    WHERE so.status='pending' $bagianFilter ORDER BY so.tanggal DESC
");

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-clipboard2-pulse me-2"></i>Approval Stock Opname</div>
  </div>
  <div class="page-content">
    <?php $flash = getFlash();
    if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> auto-dismiss alert-dismissible fade show">
        <?= htmlspecialchars($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <div class="card">
      <div class="card-header"><i class="bi bi-list me-2"></i>Daftar Stock Opname — Menunggu Persetujuan</div>
      <div class="table-wrapper">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>#</th>
              <th>Tanggal</th>
              <th>Barang</th>
              <th>Stok Sistem</th>
              <th>Stok Fisik</th>
              <th>Selisih</th>
              <th>Keterangan</th>
              <th>Oleh</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no = 1;
            $hasData = false;
            while ($s = $list->fetch_assoc()):
              $hasData = true;
            ?>
              <tr>
                <td><?= $no++ ?></td>
                <td><?= formatTanggal($s['tanggal']) ?></td>
                <td><?= htmlspecialchars($s['nama_barang']) ?></td>
                <td class="text-center"><?= number_format($s['stok_sistem']) ?></td>
                <td class="text-center"><?= number_format($s['stok_fisik']) ?></td>
                <td class="text-center fw-bold <?= $s['selisih'] > 0 ? 'text-success' : ($s['selisih'] < 0 ? 'text-danger' : '') ?>">
                  <?= ($s['selisih'] > 0 ? '+' : '') . number_format($s['selisih']) ?>
                </td>
                <td><small><?= htmlspecialchars($s['keterangan']) ?></small></td>
                <td><?= htmlspecialchars($s['nama_input']) ?></td>
                <td>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="so_id" value="<?= $s['id'] ?>">
                    <button type="submit" name="action" value="setujui" class="btn btn-sm btn-success" data-confirm="Setujui stock opname ini?"><i class="bi bi-check-lg"></i></button>
                    <button type="submit" name="action" value="tolak" class="btn btn-sm btn-danger" data-confirm="Tolak stock opname ini?"><i class="bi bi-x-lg"></i></button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
            <?php if (!$hasData): ?>
              <tr>
                <td colspan="9" class="text-center py-4 text-muted">
                  <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                  Tidak ada stock opname pending.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>