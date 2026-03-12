<?php

/**
 * Kepala Bagian - Approval Transaksi (Penerimaan & Pengurangan)
 * 
 * Workflow Persetujuan:
 * - Disetujui PENERIMAAN → UPDATE stok_current (tambah)
 * - Disetujui PENGURANGAN → UPDATE stok_current (kurang) + UPDATE sisa_stok per batch FIFO
 * - Ditolak → hanya ubah status, tidak ada perubahan stok
 */
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['kepala', 'superadmin']);
$pageTitle = 'Approval Transaksi';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

// ---- Handle Approval/Reject ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
  if ($role === 'superadmin') {
    setFlash('error', 'Superadmin hanya memiliki hak akses Read-Only pada Approval.');
    header('Location: index.php');
    exit;
  }

  $jenis    = $_POST['jenis'] ?? '';  // 'penerimaan' or 'pengurangan' or 'pengurangan_batch'
  $tx_id    = (int)($_POST['tx_id'] ?? 0);
  $detail_id = (int)($_POST['detail_id'] ?? 0); // For batch-level approval
  $action   = $_POST['action_type']; // 'setujui' or 'tolak'
  $catatan  = sanitize($_POST['catatan_approval'] ?? '');
  $now      = date('Y-m-d H:i:s');
  $approver = getUserId();

  $conn->begin_transaction();
  try {
    if ($jenis === 'penerimaan') {
      // Ambil data penerimaan
      $tx = $conn->query("SELECT * FROM penerimaan WHERE id=$tx_id AND status='pending'")->fetch_assoc();
      if (!$tx) throw new Exception('Transaksi tidak ditemukan atau sudah diproses.');

      if ($action === 'setujui') {
        // Update status penerimaan DAN set sisa_stok = jumlah (FIFO initialization)
        $conn->query("UPDATE penerimaan SET status='disetujui', sisa_stok=jumlah, id_approver=$approver, approved_at='$now', catatan_approval='" . mysqli_real_escape_string($conn, $catatan) . "' WHERE id=$tx_id");

        // Update stok_current (INSERT ON DUPLICATE KEY UPDATE)
        $stmt = $conn->prepare("
                    INSERT INTO stok_current (id_barang, id_bagian, stok) VALUES(?,?,?)
                    ON DUPLICATE KEY UPDATE stok = stok + ?
                ");
        $stmt->bind_param('iiii', $tx['id_barang'], $tx['id_bagian'], $tx['jumlah'], $tx['jumlah']);
        $stmt->execute();
        $stmt->close();
      } else { // tolak
        $conn->query("UPDATE penerimaan SET status='ditolak', id_approver=$approver, approved_at='$now', catatan_approval='" . mysqli_real_escape_string($conn, $catatan) . "', sisa_stok=0 WHERE id=$tx_id");
      }
    } elseif ($jenis === 'pengurangan_batch') {
      // BATCH-LEVEL APPROVAL
      $detail = $conn->query("SELECT pd.*, p.id_barang, p.id_bagian FROM pengurangan_detail pd JOIN pengurangan p ON p.id=pd.id_pengurangan WHERE pd.id=$detail_id AND pd.status='pending'")->fetch_assoc();
      if (!$detail) throw new Exception('Batch tidak ditemukan atau sudah diproses.');

      if ($action === 'setujui') {
        // Update batch status
        $conn->query("UPDATE pengurangan_detail SET status='disetujui', id_approver=$approver, approved_at='$now', catatan_approval='" . mysqli_real_escape_string($conn, $catatan) . "' WHERE id=$detail_id");

        // Update sisa_stok penerimaan untuk batch ini
        $conn->query("UPDATE penerimaan SET sisa_stok = sisa_stok - {$detail['jumlah_dipotong']} WHERE id={$detail['id_penerimaan']}");

        // Update stok_current (kurangi)
        $stmt = $conn->prepare("UPDATE stok_current SET stok = stok - ? WHERE id_barang=? AND id_bagian=?");
        $stmt->bind_param('iii', $detail['jumlah_dipotong'], $detail['id_barang'], $detail['id_bagian']);
        $stmt->execute();
        $stmt->close();
      } else {
        // Tolak batch ini
        $conn->query("UPDATE pengurangan_detail SET status='ditolak', id_approver=$approver, approved_at='$now', catatan_approval='" . mysqli_real_escape_string($conn, $catatan) . "' WHERE id=$detail_id");
      }

      // Check if all batches are processed (approved or rejected)
      $checkAll = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending FROM pengurangan_detail WHERE id_pengurangan={$detail['id_pengurangan']}")->fetch_assoc();

      if ($checkAll['pending'] == 0) {
        // All batches processed, update parent transaction
        $approvedBatches = $conn->query("SELECT SUM(jumlah_dipotong) as total FROM pengurangan_detail WHERE id_pengurangan={$detail['id_pengurangan']} AND status='disetujui'")->fetch_row()[0];

        if ($approvedBatches > 0) {
          // At least some batches approved
          $allApproved = $conn->query("SELECT COUNT(*) FROM pengurangan_detail WHERE id_pengurangan={$detail['id_pengurangan']} AND status='ditolak'")->fetch_row()[0];
          $finalStatus = ($allApproved > 0) ? 'disetujui sebagian' : 'disetujui';
          $conn->query("UPDATE pengurangan SET status='$finalStatus', id_approver=$approver, approved_at='$now' WHERE id={$detail['id_pengurangan']}");
        } else {
          // All rejected
          $conn->query("UPDATE pengurangan SET status='ditolak', id_approver=$approver, approved_at='$now' WHERE id={$detail['id_pengurangan']}");
        }
      }
    } elseif ($jenis === 'pengurangan') {
      // FULL TRANSACTION APPROVAL (kept for compatibility)
      $tx = $conn->query("SELECT * FROM pengurangan WHERE id=$tx_id AND status='pending'")->fetch_assoc();
      if (!$tx) throw new Exception('Transaksi tidak ditemukan atau sudah diproses.');

      if ($action === 'setujui') {
        // Approve all batches at once
        $details = $conn->query("SELECT * FROM pengurangan_detail WHERE id_pengurangan=$tx_id")->fetch_all(MYSQLI_ASSOC);

        foreach ($details as $d) {
          $conn->query("UPDATE pengurangan_detail SET status='disetujui', id_approver=$approver, approved_at='$now' WHERE id={$d['id']}");
          $conn->query("UPDATE penerimaan SET sisa_stok = sisa_stok - {$d['jumlah_dipotong']} WHERE id={$d['id_penerimaan']}");
        }

        $stmt = $conn->prepare("UPDATE stok_current SET stok = stok - ? WHERE id_barang=? AND id_bagian=?");
        $stmt->bind_param('iii', $tx['jumlah'], $tx['id_barang'], $tx['id_bagian']);
        $stmt->execute();
        $stmt->close();

        $conn->query("UPDATE pengurangan SET status='disetujui', id_approver=$approver, approved_at='$now', catatan_approval='" . mysqli_real_escape_string($conn, $catatan) . "' WHERE id=$tx_id");
      } else {
        $conn->query("UPDATE pengurangan_detail SET status='ditolak', id_approver=$approver, approved_at='$now' WHERE id_pengurangan=$tx_id");
        $conn->query("UPDATE pengurangan SET status='ditolak', id_approver=$approver, approved_at='$now', catatan_approval='" . mysqli_real_escape_string($conn, $catatan) . "' WHERE id=$tx_id");
      }
    }

    $conn->commit();
    $actLabel = $action === 'setujui' ? 'disetujui' : 'ditolak';
    setFlash('success', "Transaksi berhasil $actLabel.");
  } catch (Exception $e) {
    $conn->rollback();
    setFlash('error', 'Gagal: ' . $e->getMessage());
  }
  header('Location: index.php');
  exit;
}

// ---- Tampilkan daftar pending ----
$bagianFilter = ($role === 'superadmin') ? '' : "AND p.id_bagian=$id_bagian";

$penPending = $conn->query("
    SELECT p.id, p.no_faktur, p.tanggal, p.jumlah, p.harga_satuan, p.jumlah_harga,
       p.dari, p.keterangan, j.nama_jenis,
       b.nama_barang, b.satuan, bg.nama as nama_bagian, u.nama as nama_user
    FROM penerimaan p
  JOIN barang b ON p.id_barang=b.id
  JOIN jenis_barang j ON b.id_jenis_barang=j.id
  JOIN bagian bg ON p.id_bagian=bg.id
  JOIN users u ON p.id_user=u.id
    WHERE p.status='pending' $bagianFilter ORDER BY p.created_at
");

// Hitung jumlah penerimaan pending
$penPendingArray = [];
while ($row = $penPending->fetch_assoc()) {
  $penPendingArray[] = $row;
}
$jumlahPenPending = count($penPendingArray);

// Query pengurangan dengan detail batch - show transactions with any pending batches
$pengPending = $conn->query("
    SELECT p.id, p.no_permintaan, p.tanggal, p.jumlah, p.status as parent_status,
       p.keterangan, j.nama_jenis,
       b.nama_barang, b.satuan, bg.nama as nama_bagian, u.nama as nama_user,
       pd.id as detail_id, pd.status as batch_status, pd.jumlah_dipotong, pd.harga_satuan as batch_harga_satuan,
           pen.no_faktur as batch_no_faktur, pen.tanggal as batch_tanggal,
           (SELECT SUM(pd2.jumlah_dipotong * pd2.harga_satuan) 
            FROM pengurangan_detail pd2 
            WHERE pd2.id_pengurangan = p.id) as jumlah_harga_total
    FROM pengurangan p
  JOIN barang b ON p.id_barang=b.id
  JOIN jenis_barang j ON b.id_jenis_barang=j.id
    JOIN bagian bg ON p.id_bagian=bg.id 
    JOIN users u ON p.id_user=u.id
    LEFT JOIN pengurangan_detail pd ON pd.id_pengurangan = p.id
    LEFT JOIN penerimaan pen ON pen.id = pd.id_penerimaan
    WHERE (p.status='pending' OR p.status='disetujui sebagian') $bagianFilter 
    ORDER BY p.created_at, pd.id ASC
");

// Hitung jumlah pengurangan pending (unique id)
$pengPendingArray = [];
$seenIds = [];
while ($row = $pengPending->fetch_assoc()) {
  if (!in_array($row['id'], $seenIds)) {
    $seenIds[] = $row['id'];
  }
  $pengPendingArray[] = $row;
}
$jumlahPengPending = count($seenIds);

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<style>
  .batch-row {
    background-color: #f8f9fa;
  }

  .batch-row:hover {
    background-color: #e9ecef !important;
  }

  .table tbody tr.batch-row td {
    border-top: 2px solid #dee2e6;
  }
</style>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-check2-square me-2"></i>Approval Transaksi</div>
  </div>
  <div class="page-content">
    <?php $flash = getFlash();
    if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> auto-dismiss alert-dismissible fade show">
        <?= htmlspecialchars($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- PENERIMAAN PENDING -->
    <h6 class="fw-bold text-primary mb-2">
      <i class="bi bi-box-arrow-in-down me-1"></i>Penerimaan — Menunggu Persetujuan
      <?php if ($jumlahPenPending > 0): ?>
        <span class="badge-header"><?= $jumlahPenPending ?></span>
      <?php endif; ?>
    </h6>
    <div class="card mb-4">
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>No.Faktur</th>
              <th>Tanggal</th>
              <th>Jenis Barang</th>
              <th>Barang</th>
              <th>Jumlah</th>
              <th>Harga Sat.</th>
              <th>Total</th>
              <th>Dari</th>
              <th>Sumber Dana</th>
              <th>Oleh</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $no = 1;
            $found = false;
            foreach ($penPendingArray as $p): $found = true; ?>
              <tr>
                <td><?= $no++ ?></td>
                <td><code><?= htmlspecialchars($p['no_faktur']) ?></code></td>
                <td><?= formatTanggal($p['tanggal']) ?></td>
                <td><?= htmlspecialchars($p['nama_jenis']) ?></td>
                <td><?= htmlspecialchars($p['nama_barang']) ?></td>
                <td><?= number_format($p['jumlah']) ?> <?= $p['satuan'] ?></td>
                <td><?= formatRupiah($p['harga_satuan']) ?></td>
                <td><?= formatRupiah($p['jumlah_harga']) ?></td>
                <td><?= htmlspecialchars($p['dari'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['keterangan'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['nama_user']) ?></td>
                <td>
                  <?php if ($role !== 'superadmin'): ?>
                    <form method="POST" class="d-flex flex-column gap-1" style="min-width:120px">
                      <input type="hidden" name="jenis" value="penerimaan">
                      <input type="hidden" name="tx_id" value="<?= $p['id'] ?>">
                      <input type="hidden" name="catatan_approval" value="">
                      <div class="d-flex gap-1 justify-content-center">
                        <button type="submit" name="action_type" value="setujui" class="btn btn-sm btn-success btn-icon w-50" title="Setujui"><i class="bi bi-check-lg"></i></button>
                        <button type="submit" name="action_type" value="tolak" class="btn btn-sm btn-danger btn-icon w-50" title="Tolak"><i class="bi bi-x-lg"></i></button>
                      </div>
                    </form>
                  <?php else: ?>
                    <span class="text-muted"><i class="bi bi-lock"></i> Hanya Kepala</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach;
            if (!$found): ?>
              <tr>
                <td colspan="12" class="text-center text-muted py-3"><i class="bi bi-inbox me-2"></i>Tidak ada penerimaan pending.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PENGURANGAN PENDING -->
    <h6 class="fw-bold text-warning mb-2">
      <i class="bi bi-box-arrow-up me-1"></i>Pengurangan — Menunggu Persetujuan
      <?php if (count($seenIds) > 0): ?>
        <span class="badge-header"><?= count($seenIds) ?></span>
      <?php endif; ?>
    </h6>
    <div class="card">
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>No.Permintaan</th>
              <th>Tanggal</th>
              <th>Jenis Barang</th>
              <th>Barang</th>
              <th>Batch Info</th>
              <th>Jumlah</th>
              <th>Harga Satuan</th>
              <th>Subtotal</th>
              <th>Total Harga</th>
              <th>Sumber Dana</th>
              <th>Oleh</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no = 1;
            $prevId = null;
            $rowspanData = [];

            // Hitung rowspan untuk setiap pengurangan
            foreach ($pengPendingArray as $row) {
              if (!isset($rowspanData[$row['id']])) {
                // Count all batches (including non-pending for display)
                $allBatchCount = $conn->query("SELECT COUNT(*) FROM pengurangan_detail WHERE id_pengurangan={$row['id']}")->fetch_row()[0];
                $rowspanData[$row['id']] = $allBatchCount;
              }
            }

            $batchNo = [];
            $found2 = false;

            foreach ($pengPendingArray as $p):
              $found2 = true;
              $isFirstRow = ($prevId !== $p['id']);
              $rowspan = $rowspanData[$p['id']] ?? 1;

              if ($isFirstRow) {
                $batchNo[$p['id']] = 1;
              } else {
                $batchNo[$p['id']]++;
              }

              $subtotal = ($p['jumlah_dipotong'] ?? 0) * ($p['batch_harga_satuan'] ?? 0);
            ?>
              <tr <?= $rowspan > 1 ? 'class="batch-row"' : '' ?>>
                <?php if ($isFirstRow): ?>
                  <td rowspan="<?= $rowspan ?>"><?= $no++ ?></td>
                  <td rowspan="<?= $rowspan ?>">
                    <code><?= htmlspecialchars($p['no_permintaan']) ?></code>
                    <?php if ($p['parent_status'] === 'disetujui sebagian'): ?>
                      <br><span class="badge bg-warning text-dark mt-1"><i class="bi bi-hourglass-split"></i> Sebagian</span>
                    <?php endif; ?>
                  </td>
                  <td rowspan="<?= $rowspan ?>"><?= formatTanggal($p['tanggal']) ?></td>
                  <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($p['nama_jenis']) ?></td>
                  <td rowspan="<?= $rowspan ?>">
                    <strong><?= htmlspecialchars($p['nama_barang']) ?></strong><br>
                    <small class="text-muted">Total: <?= number_format($p['jumlah']) ?> <?= $p['satuan'] ?></small>
                  </td>
                <?php endif; ?>

                <!-- Batch Info -->
                <td>
                  <?php if ($rowspan > 1): ?>
                    <span class="badge bg-primary">Batch #<?= $batchNo[$p['id']] ?></span><br>
                  <?php else: ?>
                    <span class="badge bg-secondary">Batch Tunggal</span><br>
                  <?php endif; ?>
                  <small class="text-muted">
                    <?= htmlspecialchars($p['batch_no_faktur'] ?? '-') ?><br>
                    <?= $p['batch_tanggal'] ? formatTanggal($p['batch_tanggal']) : '-' ?>
                  </small>
                </td>

                <!-- Jumlah per batch -->
                <td class="text-end">
                  <strong><?= number_format($p['jumlah_dipotong'] ?? $p['jumlah']) ?></strong> <?= $p['satuan'] ?>
                </td>

                <!-- Harga Satuan per batch -->
                <td class="text-end">
                  <strong><?= formatRupiah($p['batch_harga_satuan'] ?? 0) ?></strong>
                </td>

                <!-- Subtotal per batch -->
                <td class="text-end">
                  <?= formatRupiah($subtotal) ?>
                </td>

                <?php if ($isFirstRow): ?>
                  <td class="text-end" rowspan="<?= $rowspan ?>">
                    <strong class="text-primary fs-6"><?= formatRupiah($p['jumlah_harga_total']) ?></strong>
                  </td>
                  <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($p['keterangan'] ?? '-') ?></td>
                  <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($p['nama_user']) ?></td>
                <?php endif; ?>

                <!-- Aksi per batch -->
                <td class="text-center">
                  <?php if (($p['batch_status'] ?? 'pending') === 'pending'): ?>
                    <?php if ($role !== 'superadmin'): ?>
                      <button class="btn btn-sm btn-success" onclick="openBatchModal(<?= $p['detail_id'] ?>,<?= $p['id'] ?>,'setujui')" title="Setujui Batch Ini">
                        <i class="bi bi-check-lg"></i>
                      </button>
                      <button class="btn btn-sm btn-danger" onclick="openBatchModal(<?= $p['detail_id'] ?>,<?= $p['id'] ?>,'tolak')" title="Tolak Batch Ini">
                        <i class="bi bi-x-lg"></i>
                      </button>
                    <?php else: ?>
                      <span class="text-muted"><i class="bi bi-lock"></i></span>
                    <?php endif; ?>
                  <?php elseif (($p['batch_status'] ?? '') === 'disetujui'): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Disetujui</span>
                  <?php elseif (($p['batch_status'] ?? '') === 'ditolak'): ?>
                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Ditolak</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">-</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php
              $prevId = $p['id'];
            endforeach;

            if (!$found2): ?>
              <tr>
                <td colspan="13" class="text-center text-muted py-3"><i class="bi bi-inbox me-2"></i>Tidak ada pengurangan pending.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal konfirmasi -->
<div class="modal fade" id="approvalModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" id="modalHeader">
        <h5 class="modal-title" id="modalTitle">Konfirmasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="jenis" id="modalJenis">
          <input type="hidden" name="tx_id" id="modalTxId">
          <input type="hidden" name="detail_id" id="modalDetailId">
          <input type="hidden" name="action_type" id="modalAction">
          <input type="hidden" name="catatan_approval" value="">
          <div class="alert" id="modalAlert"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn" id="modalSubmitBtn">Konfirmasi</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
  function openBatchModal(detailId, txId, action) {
    const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
    document.getElementById('modalJenis').value = 'pengurangan_batch';
    document.getElementById('modalTxId').value = txId;
    document.getElementById('modalDetailId').value = detailId;
    document.getElementById('modalAction').value = action;

    const isSetujui = action === 'setujui';
    document.getElementById('modalTitle').textContent = isSetujui ? '✅ Konfirmasi Persetujuan Batch' : '❌ Konfirmasi Penolakan Batch';
    document.getElementById('modalHeader').className = 'modal-header ' + (isSetujui ? 'bg-success text-white' : 'bg-danger text-white');
    document.getElementById('modalAlert').className = 'alert ' + (isSetujui ? 'alert-success' : 'alert-danger');
    document.getElementById('modalAlert').innerHTML = isSetujui ?
      '<i class="bi bi-info-circle me-2"></i><strong>Menyetujui batch ini akan:</strong><br>' +
      '• Mengurangi stok untuk jumlah batch ini saja<br>' +
      '• Memotong stok dari penerimaan terkait<br>' +
      '• Batch lain masih menunggu persetujuan terpisah' :
      '<i class="bi bi-exclamation-triangle me-2"></i><strong>Menolak batch ini akan:</strong><br>' +
      '• Membatalkan pengurangan untuk batch ini saja<br>' +
      '• Batch lain tidak terpengaruh';

    document.getElementById('modalSubmitBtn').className = 'btn ' + (isSetujui ? 'btn-success' : 'btn-danger');
    document.getElementById('modalSubmitBtn').innerHTML = isSetujui ? '<i class="bi bi-check-lg me-1"></i>Setujui Batch' : '<i class="bi bi-x-lg me-1"></i>Tolak Batch';

    modal.show();
  }

  function openModal(jenis, txId, action) {
    const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
    document.getElementById('modalJenis').value = jenis;
    document.getElementById('modalTxId').value = txId;
    document.getElementById('modalDetailId').value = '';
    document.getElementById('modalAction').value = action;

    const isSetujui = action === 'setujui';
    document.getElementById('modalTitle').textContent = isSetujui ? '✅ Konfirmasi Persetujuan' : '❌ Konfirmasi Penolakan';
    document.getElementById('modalHeader').className = 'modal-header ' + (isSetujui ? 'bg-success text-white' : 'bg-danger text-white');
    document.getElementById('modalAlert').className = 'alert ' + (isSetujui ? 'alert-success' : 'alert-danger');

    if (jenis === 'pengurangan') {
      document.getElementById('modalAlert').innerHTML = isSetujui ?
        '<i class="bi bi-info-circle me-2"></i><strong>Menyetujui transaksi pengurangan ini akan:</strong><br>' +
        '• Mengurangi stok utama sesuai jumlah yang diminta<br>' +
        '• Memotong stok dari batch penerimaan yang dipilih (FIFO)<br>' +
        '• Menghitung nilai persediaan berdasarkan harga batch yang digunakan' :
        '<i class="bi bi-exclamation-triangle me-2"></i><strong>Menolak transaksi ini akan:</strong><br>' +
        '• Membatalkan pengurangan stok<br>' +
        '• Tidak mengubah stok barang';
    } else {
      document.getElementById('modalAlert').textContent = isSetujui ?
        'Menyetujui transaksi ini akan langsung menambah stok utama.' :
        'Menolak transaksi ini tidak akan mengubah stok.';
    }

    document.getElementById('modalSubmitBtn').className = 'btn ' + (isSetujui ? 'btn-success' : 'btn-danger');
    document.getElementById('modalSubmitBtn').innerHTML = isSetujui ? '<i class="bi bi-check-lg me-1"></i>Setujui' : '<i class="bi bi-x-lg me-1"></i>Tolak';

    modal.show();
  }
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>