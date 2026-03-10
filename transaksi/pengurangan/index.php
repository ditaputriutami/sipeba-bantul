<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus', 'kepala', 'superadmin']);
$pageTitle = 'Pengurangan Barang';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

$filterBagian = ($role === 'superadmin') ? '' : "AND p.id_bagian=$id_bagian";
$filterStatus = $_GET['status'] ?? '';
$where = "WHERE 1=1 $filterBagian";
if ($filterStatus) $where .= " AND p.status='" . mysqli_real_escape_string($conn, $filterStatus) . "'";

// Query untuk mendapatkan detail batch per pengurangan
$list = $conn->query("
    SELECT p.*, b.nama_barang, b.satuan, bg.nama as nama_bagian, u.nama as nama_user, ap.nama as nama_approver,
           j.nama_jenis, j.kode_jenis,
           pd.id as detail_id, pd.jumlah_dipotong, pd.harga_satuan as batch_harga_satuan,
           pen.tanggal as batch_tanggal, pen.no_faktur as batch_no_faktur,
           (SELECT SUM(pd2.jumlah_dipotong * pd2.harga_satuan) 
            FROM pengurangan_detail pd2 
            WHERE pd2.id_pengurangan = p.id) as jumlah_harga_total,
           (SELECT COUNT(*) 
            FROM pengurangan_detail pd3 
            WHERE pd3.id_pengurangan = p.id) as batch_count
    FROM pengurangan p
    JOIN barang b ON p.id_barang=b.id
    JOIN jenis_barang j ON b.id_jenis_barang=j.id
    JOIN bagian bg ON p.id_bagian=bg.id
    JOIN users u ON p.id_user=u.id
    LEFT JOIN users ap ON p.id_approver=ap.id
    LEFT JOIN pengurangan_detail pd ON pd.id_pengurangan = p.id
    LEFT JOIN penerimaan pen ON pen.id = pd.id_penerimaan
    $where
    ORDER BY p.created_at DESC, pd.id ASC
");

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-box-arrow-up me-2"></i>Pengurangan Barang</div>
  </div>
  <div class="page-content">
    <?php $flash = getFlash();
    if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> auto-dismiss alert-dismissible fade show">
        <?= htmlspecialchars($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <div class="card mb-3">
      <div class="card-body py-2">
        <div class="d-flex justify-content-between align-items-center">
          <form method="GET" class="d-flex gap-2 align-items-center">
            <select name="status" class="form-select form-select-sm" style="width: auto;">
              <option value="">Semua Status</option>
              <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="disetujui" <?= $filterStatus === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
              <option value="ditolak" <?= $filterStatus === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-funnel"></i> Filter</button>
          </form>
          <?php if (in_array($role, ['pengurus', 'kepala', 'superadmin'])): ?>
            <a href="create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah Pengurangan</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-list-ul me-2"></i>Daftar Transaksi Pengurangan</span>
        <input type="text" class="form-control form-control-sm" style="width:200px" data-table-search="pengTable" placeholder="Cari...">
      </div>
      <div class="table-wrapper">
        <table class="table" id="pengTable">
          <thead>
            <tr>
              <th>#</th>
              <th>No. Permintaan</th>
              <th>Tanggal</th>
              <th>Jenis Barang</th>
              <th>Nama Barang</th>
              <th>Jumlah</th>
              <th>Harga Satuan</th>
              <th>Jumlah Harga</th>
              <th>Keterangan</th>
              <?php if ($role === 'superadmin'): ?><th>Bagian</th><?php endif; ?>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no = 1;
            $prevId = null;
            $rowspanData = [];

            // Hitung rowspan untuk setiap pengurangan
            $list->data_seek(0);
            while ($row = $list->fetch_assoc()) {
              if (!isset($rowspanData[$row['id']])) {
                $rowspanData[$row['id']] = $row['batch_count'] ?? 1;
              }
            }

            // Reset pointer dan tampilkan data
            $list->data_seek(0);
            $batchNo = [];

            while ($p = $list->fetch_assoc()):
              $isFirstRow = ($prevId !== $p['id']);
              $rowspan = $rowspanData[$p['id']] ?? 1;

              if ($isFirstRow) {
                $batchNo[$p['id']] = 1;
              } else {
                $batchNo[$p['id']]++;
              }
            ?>
              <tr>
                <?php if ($isFirstRow): ?>
                  <td rowspan="<?= $rowspan ?>"><?= $no++ ?></td>
                  <td rowspan="<?= $rowspan ?>"><code><?= htmlspecialchars($p['no_permintaan']) ?></code></td>
                  <td rowspan="<?= $rowspan ?>"><?= formatTanggal($p['tanggal']) ?></td>
                  <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($p['nama_jenis']) ?></td>
                  <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($p['nama_barang']) ?></td>
                <?php endif; ?>

                <!-- Jumlah & Harga Satuan per batch -->
                <td><?= number_format($p['jumlah_dipotong'] ?? $p['jumlah']) ?> <?= htmlspecialchars($p['satuan']) ?>
                  <?php if ($rowspan > 1): ?>
                    <small class="text-muted">(Batch <?= $batchNo[$p['id']] ?>)</small>
                  <?php endif; ?>
                </td>
                <td class="text-end"><?= formatRupiah($p['batch_harga_satuan'] ?? 0) ?></td>

                <?php if ($isFirstRow): ?>
                  <td class="text-end" rowspan="<?= $rowspan ?>"><strong><?= formatRupiah($p['jumlah_harga_total']) ?></strong></td>
                  <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($p['keterangan'] ?? '-') ?></td>
                  <?php if ($role === 'superadmin'): ?>
                    <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($p['nama_bagian']) ?></td>
                  <?php endif; ?>
                  <td rowspan="<?= $rowspan ?>">
                    <?php
                    $sc = ['pending' => 'badge-pending', 'disetujui' => 'badge-approved', 'ditolak' => 'badge-rejected'];
                    $si = ['pending' => 'bi-clock', 'disetujui' => 'bi-check-circle', 'ditolak' => 'bi-x-circle'];
                    ?>
                    <span class="badge-sipeba <?= $sc[$p['status']] ?? '' ?>">
                      <i class="bi <?= $si[$p['status']] ?? '' ?>"></i> <?= ucfirst($p['status']) ?>
                    </span>
                  </td>
                  <td rowspan="<?= $rowspan ?>">
                    <?php if ($p['status'] === 'pending' && in_array($role, ['pengurus', 'kepala', 'superadmin'])): ?>
                      <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary btn-icon me-1" title="Edit"><i class="bi bi-pencil"></i></a>
                      <form method="POST" action="delete.php" class="d-inline">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" data-confirm="Hapus pengurangan ini?" title="Hapus"><i class="bi bi-trash"></i></button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted" title="Tidak dapat diedit/dihapus karena sudah disetujui atau ditolak"><i class="bi bi-lock"></i></span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php
              $prevId = $p['id'];
            endwhile;
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>