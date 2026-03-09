<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus','kepala','superadmin']);
$pageTitle = 'Pengurangan Barang';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

$filterBagian = ($role === 'superadmin') ? '' : "AND p.id_bagian=$id_bagian";
$filterStatus = $_GET['status'] ?? '';
$where = "WHERE 1=1 $filterBagian";
if ($filterStatus) $where .= " AND p.status='".mysqli_real_escape_string($conn,$filterStatus)."'";

$list = $conn->query("
    SELECT p.*, b.nama_barang, b.satuan, bg.nama as nama_bagian, u.nama as nama_user, ap.nama as nama_approver
    FROM pengurangan p
    JOIN barang b ON p.id_barang=b.id
    JOIN bagian bg ON p.id_bagian=bg.id
    JOIN users u ON p.id_user=u.id
    LEFT JOIN users ap ON p.id_approver=ap.id
    $where
    ORDER BY p.created_at DESC
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
    <?php $flash=getFlash(); if($flash): ?>
      <div class="alert alert-<?=$flash['type']==='error'?'danger':$flash['type']?> auto-dismiss alert-dismissible fade show">
        <?=htmlspecialchars($flash['message'])?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
          <div class="col-auto">
            <select name="status" class="form-select form-select-sm">
              <option value="">Semua Status</option>
              <option value="pending" <?=$filterStatus==='pending'?'selected':''?>>Pending</option>
              <option value="disetujui" <?=$filterStatus==='disetujui'?'selected':''?>>Disetujui</option>
              <option value="ditolak" <?=$filterStatus==='ditolak'?'selected':''?>>Ditolak</option>
            </select>
          </div>
          <div class="col-auto"><button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-funnel"></i> Filter</button></div>
          <div class="col-auto ms-auto">
            <?php if(in_array($role,['pengurus','kepala'])): ?>
            <a href="create.php" class="btn btn-warning btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah Pengurangan</a>
            <?php endif; ?>
          </div>
        </form>
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
              <th>#</th><th>No. Permintaan</th><th>Tanggal</th><th>Nama Barang</th><th>Jumlah</th>
              <th>Penerima</th><th>Tgl Penyerahan</th>
              <?php if($role==='superadmin'): ?><th>Bagian</th><?php endif; ?>
              <th>Status</th><th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $no=1; while($p=$list->fetch_assoc()): ?>
            <tr>
              <td><?=$no++?></td>
              <td><code><?=htmlspecialchars($p['no_permintaan'])?></code></td>
              <td><?=formatTanggal($p['tanggal'])?></td>
              <td><?=htmlspecialchars($p['nama_barang'])?></td>
              <td><?=number_format($p['jumlah'])?> <?=htmlspecialchars($p['satuan'])?></td>
              <td><?=htmlspecialchars($p['penerima']??'—')?></td>
              <td><?=$p['tanggal_penyerahan']?formatTanggal($p['tanggal_penyerahan']):'—'?></td>
              <?php if($role==='superadmin'): ?><td><?=htmlspecialchars($p['nama_bagian'])?></td><?php endif; ?>
              <td>
                <?php
                $sc=['pending'=>'badge-pending','disetujui'=>'badge-approved','ditolak'=>'badge-rejected'];
                $si=['pending'=>'bi-clock','disetujui'=>'bi-check-circle','ditolak'=>'bi-x-circle'];
                ?>
                <span class="badge-sipeba <?=$sc[$p['status']]??''?>">
                  <i class="bi <?=$si[$p['status']]??''?>"></i> <?=ucfirst($p['status'])?>
                </span>
              </td>
              <td>
                <?php if($p['status']==='pending'&&in_array($role,['pengurus','kepala'])): ?>
                  <form method="POST" action="delete.php" class="d-inline">
                    <input type="hidden" name="id" value="<?=$p['id']?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" data-confirm="Hapus pengurangan ini?" title="Hapus"><i class="bi bi-trash"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
