<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['superadmin']);
$pageTitle = 'Manajemen User';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $did = (int)$_POST['delete_id'];
    if ($did !== getUserId()) { // jangan hapus diri sendiri
        $conn->query("DELETE FROM users WHERE id=$did AND role!='superadmin'");
        setFlash('success', 'User berhasil dihapus.');
    } else {
        setFlash('error', 'Tidak bisa menghapus akun Anda sendiri.');
    }
    header('Location: index.php'); exit;
}

$users = $conn->query("
    SELECT u.*, b.nama AS nama_bagian
    FROM users u LEFT JOIN bagian b ON u.id_bagian=b.id
    ORDER BY u.role, u.nama
");

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-people me-2"></i>Manajemen User</div>
  </div>
  <div class="page-content">
    <?php $flash=getFlash(); if($flash): ?>
      <div class="alert alert-<?=$flash['type']==='error'?'danger':$flash['type']?> auto-dismiss alert-dismissible fade show">
        <?=htmlspecialchars($flash['message'])?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-people me-2"></i>Daftar User</span>
        <div class="d-flex gap-2">
          <input type="text" class="form-control form-control-sm" style="width:200px" data-table-search="userTable" placeholder="Cari...">
          <a href="create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah User</a>
        </div>
      </div>
      <div class="table-wrapper">
        <table class="table" id="userTable">
          <thead><tr><th>#</th><th>Nama</th><th>Username</th><th>Role</th><th>Bagian</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php $no=1; while($u=$users->fetch_assoc()): ?>
            <tr>
              <td><?=$no++?></td>
              <td><?=htmlspecialchars($u['nama'])?></td>
              <td><code><?=htmlspecialchars($u['username'])?></code></td>
              <td>
                <?php
                $rc=['superadmin'=>['bg-dark','bi-shield-fill-check'],'kepala'=>['bg-primary','bi-briefcase'],'pengurus'=>['bg-success','bi-person-workspace']];
                $rl=['superadmin'=>'Super Admin','kepala'=>'Kepala Bagian','pengurus'=>'Pengurus Barang'];
                [$rbg,$ric]=$rc[$u['role']]??['bg-secondary','bi-person'];
                ?>
                <span class="badge <?=$rbg?>"><i class="bi <?=$ric?> me-1"></i><?=$rl[$u['role']]??$u['role']?></span>
              </td>
              <td><?=htmlspecialchars($u['nama_bagian']??'—')?></td>
              <td><span class="badge-sipeba <?=$u['is_active']?'badge-approved':'badge-rejected'?>"><?=$u['is_active']?'Aktif':'Non-Aktif'?></span></td>
              <td>
                <a href="edit.php?id=<?=$u['id']?>" class="btn btn-sm btn-outline-primary btn-icon" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                <?php if($u['id']!==getUserId()&&$u['role']!=='superadmin'): ?>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="delete_id" value="<?=$u['id']?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" data-confirm="Hapus user <?=htmlspecialchars($u['nama'])?>?" data-bs-toggle="tooltip" title="Hapus"><i class="bi bi-trash"></i></button>
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
