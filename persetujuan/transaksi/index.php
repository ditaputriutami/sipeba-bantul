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
requireRole(['kepala','superadmin']);
$pageTitle = 'Approval Transaksi';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

// ---- Handle Approval/Reject ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $jenis    = $_POST['jenis'] ?? '';  // 'penerimaan' or 'pengurangan'
    $tx_id    = (int)($_POST['tx_id'] ?? 0);
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
                // Update status penerimaan
                $conn->query("UPDATE penerimaan SET status='disetujui', id_approver=$approver, approved_at='$now', catatan_approval='".mysqli_real_escape_string($conn,$catatan)."' WHERE id=$tx_id");

                // Update stok_current (INSERT ON DUPLICATE KEY UPDATE)
                $stmt = $conn->prepare("
                    INSERT INTO stok_current (id_barang, id_bagian, stok) VALUES(?,?,?)
                    ON DUPLICATE KEY UPDATE stok = stok + ?
                ");
                $stmt->bind_param('iiii', $tx['id_barang'], $tx['id_bagian'], $tx['jumlah'], $tx['jumlah']);
                $stmt->execute(); $stmt->close();

            } else { // tolak
                $conn->query("UPDATE penerimaan SET status='ditolak', id_approver=$approver, approved_at='$now', catatan_approval='".mysqli_real_escape_string($conn,$catatan)."', sisa_stok=0 WHERE id=$tx_id");
            }

        } elseif ($jenis === 'pengurangan') {
            $tx = $conn->query("SELECT * FROM pengurangan WHERE id=$tx_id AND status='pending'")->fetch_assoc();
            if (!$tx) throw new Exception('Transaksi tidak ditemukan atau sudah diproses.');

            if ($action === 'setujui') {
                // Ambil detail FIFO
                $details = $conn->query("SELECT * FROM pengurangan_detail WHERE id_pengurangan=$tx_id")->fetch_all(MYSQLI_ASSOC);

                // Update sisa_stok per batch penerimaan
                foreach ($details as $d) {
                    $conn->query("UPDATE penerimaan SET sisa_stok = sisa_stok - {$d['jumlah_dipotong']} WHERE id={$d['id_penerimaan']}");
                }

                // Update stok_current (kurangi)
                $stmt = $conn->prepare("UPDATE stok_current SET stok = stok - ? WHERE id_barang=? AND id_bagian=?");
                $stmt->bind_param('iii', $tx['jumlah'], $tx['id_barang'], $tx['id_bagian']);
                $stmt->execute(); $stmt->close();

                $conn->query("UPDATE pengurangan SET status='disetujui', id_approver=$approver, approved_at='$now', catatan_approval='".mysqli_real_escape_string($conn,$catatan)."' WHERE id=$tx_id");

            } else { // tolak
                // Hapus detail FIFO (tidak jadi dipakai)
                $conn->query("DELETE FROM pengurangan_detail WHERE id_pengurangan=$tx_id");
                $conn->query("UPDATE pengurangan SET status='ditolak', id_approver=$approver, approved_at='$now', catatan_approval='".mysqli_real_escape_string($conn,$catatan)."' WHERE id=$tx_id");
            }
        }

        $conn->commit();
        $actLabel = $action === 'setujui' ? 'disetujui' : 'ditolak';
        setFlash('success', "Transaksi berhasil $actLabel.");
    } catch (Exception $e) {
        $conn->rollback();
        setFlash('error', 'Gagal: ' . $e->getMessage());
    }
    header('Location: index.php'); exit;
}

// ---- Tampilkan daftar pending ----
$bagianFilter = ($role === 'superadmin') ? '' : "AND p.id_bagian=$id_bagian";

$penPending = $conn->query("
    SELECT p.id, p.no_faktur, p.tanggal, p.jumlah, p.harga_satuan, p.jumlah_harga,
           b.nama_barang, b.satuan, bg.nama as nama_bagian, u.nama as nama_user
    FROM penerimaan p
    JOIN barang b ON p.id_barang=b.id JOIN bagian bg ON p.id_bagian=bg.id JOIN users u ON p.id_user=u.id
    WHERE p.status='pending' $bagianFilter ORDER BY p.created_at
");
$pengPending = $conn->query("
    SELECT p.id, p.no_permintaan, p.tanggal, p.jumlah, p.penerima,
           b.nama_barang, b.satuan, bg.nama as nama_bagian, u.nama as nama_user,
           sc.stok as stok_tersedia
    FROM pengurangan p
    JOIN barang b ON p.id_barang=b.id JOIN bagian bg ON p.id_bagian=bg.id JOIN users u ON p.id_user=u.id
    LEFT JOIN stok_current sc ON sc.id_barang=p.id_barang AND sc.id_bagian=p.id_bagian
    WHERE p.status='pending' $bagianFilter ORDER BY p.created_at
");

// Hitung jumlah pengurangan pending
$pengPendingArray = [];
while($row = $pengPending->fetch_assoc()) {
    $pengPendingArray[] = $row;
}
$jumlahPengPending = count($pengPendingArray);

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-check2-square me-2"></i>Approval Transaksi</div>
  </div>
  <div class="page-content">
    <?php $flash=getFlash(); if($flash): ?>
      <div class="alert alert-<?=$flash['type']==='error'?'danger':$flash['type']?> auto-dismiss alert-dismissible fade show">
        <?=htmlspecialchars($flash['message'])?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- PENERIMAAN PENDING -->
    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-box-arrow-in-down me-1"></i>Penerimaan — Menunggu Persetujuan</h6>
    <div class="card mb-4">
      <div class="table-wrapper">
        <table class="table">
          <thead><tr><th>#</th><th>No.Faktur</th><th>Tanggal</th><th>Barang</th><th>Jumlah</th><th>Harga Sat.</th><th>Total</th><th>Oleh</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php $no=1; $haystack=$penPending; $found=false; while($p=$haystack->fetch_assoc()): $found=true; ?>
            <tr>
              <td><?=$no++?></td>
              <td><code><?=htmlspecialchars($p['no_faktur'])?></code></td>
              <td><?=formatTanggal($p['tanggal'])?></td>
              <td><?=htmlspecialchars($p['nama_barang'])?></td>
              <td><?=number_format($p['jumlah'])?> <?=$p['satuan']?></td>
              <td><?=formatRupiah($p['harga_satuan'])?></td>
              <td><?=formatRupiah($p['jumlah_harga'])?></td>
              <td><?=htmlspecialchars($p['nama_user'])?></td>
              <td>
                <button class="btn btn-sm btn-success" onclick="openModal('penerimaan',<?=$p['id']?>,'setujui')"><i class="bi bi-check-lg"></i></button>
                <button class="btn btn-sm btn-danger" onclick="openModal('penerimaan',<?=$p['id']?>,'tolak')"><i class="bi bi-x-lg"></i></button>
              </td>
            </tr>
            <?php endwhile; if(!$found): ?>
            <tr><td colspan="9" class="text-center text-muted py-3"><i class="bi bi-inbox me-2"></i>Tidak ada penerimaan pending.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PENGURANGAN PENDING -->
    <h6 class="fw-bold text-warning mb-2"><i class="bi bi-box-arrow-up me-1"></i>Pengurangan — Menunggu Persetujuan <?php if($jumlahPengPending > 0): ?><span class="badge bg-danger" style="font-size: 0.75rem;"><?=$jumlahPengPending?></span><?php endif; ?></h6>
    <div class="card">
      <div class="table-wrapper">
        <table class="table">
          <thead><tr><th>#</th><th>No.Permintaan</th><th>Tanggal</th><th>Barang</th><th>Jumlah</th><th>Stok Tersedia</th><th>Penerima</th><th>PENERIMA</th><th>Oleh</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php $no=1; $found2=false; foreach($pengPendingArray as $p): $found2=true; ?>
            <tr>
              <td><?=$no++?></td>
              <td><code><?=htmlspecialchars($p['no_permintaan'])?></code></td>
              <td><?=formatTanggal($p['tanggal'])?></td>
              <td><?=htmlspecialchars($p['nama_barang'])?></td>
              <td><?=number_format($p['jumlah'])?> <?=$p['satuan']?></td>
              <td><span class="badge <?=$p['stok_tersedia']>=$p['jumlah']?'bg-success':'bg-danger'?>"><?=$p['stok_tersedia']??0?></span></td>
              <td><?=htmlspecialchars($p['penerima']??'—')?></td>
              <td><span class="badge bg-warning text-dark">Menunggu</span></td>
              <td><?=htmlspecialchars($p['nama_user'])?></td>
              <td>
                <?php if(($p['stok_tersedia']??0) >= $p['jumlah']): ?>
                <button class="btn btn-sm btn-success" onclick="openModal('pengurangan',<?=$p['id']?>,'setujui')"><i class="bi bi-check-lg"></i></button>
                <?php else: ?>
                <button class="btn btn-sm btn-success" disabled title="Stok tidak cukup"><i class="bi bi-check-lg"></i></button>
                <?php endif; ?>
                <button class="btn btn-sm btn-danger" onclick="openModal('pengurangan',<?=$p['id']?>,'tolak')"><i class="bi bi-x-lg"></i></button>
              </td>
            </tr>
            <?php endforeach; if(!$found2): ?>
            <tr><td colspan="10" class="text-center text-muted py-3"><i class="bi bi-inbox me-2"></i>Tidak ada pengurangan pending.</td></tr>
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
          <input type="hidden" name="action_type" id="modalAction">
          <div class="mb-3">
            <label class="form-label">Catatan (opsional)</label>
            <textarea name="catatan_approval" class="form-control" rows="3" placeholder="Catatan untuk penginput..."></textarea>
          </div>
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
function openModal(jenis, txId, action) {
  const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
  document.getElementById('modalJenis').value = jenis;
  document.getElementById('modalTxId').value = txId;
  document.getElementById('modalAction').value = action;

  const isSetujui = action === 'setujui';
  document.getElementById('modalTitle').textContent = isSetujui ? '✅ Konfirmasi Persetujuan' : '❌ Konfirmasi Penolakan';
  document.getElementById('modalHeader').className = 'modal-header ' + (isSetujui ? 'bg-success text-white' : 'bg-danger text-white');
  document.getElementById('modalAlert').className = 'alert ' + (isSetujui ? 'alert-success' : 'alert-danger');
  document.getElementById('modalAlert').textContent = isSetujui
    ? 'Menyetujui transaksi ini akan langsung mengubah stok utama.'
    : 'Menolak transaksi ini tidak akan mengubah stok.';
  document.getElementById('modalSubmitBtn').className = 'btn ' + (isSetujui ? 'btn-success' : 'btn-danger');
  document.getElementById('modalSubmitBtn').textContent = isSetujui ? 'Setujui' : 'Tolak';

  modal.show();
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
