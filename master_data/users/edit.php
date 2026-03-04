<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['superadmin']);
$pageTitle = 'Edit User';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $id); $stmt->execute();
$user_edit = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$user_edit) { setFlash('error','User tidak ditemukan.'); header('Location: index.php'); exit; }

$bagianList = $conn->query("SELECT * FROM bagian ORDER BY nama");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama      = sanitize($_POST['nama'] ?? '');
    $username  = sanitize($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = $_POST['role'] ?? '';
    $id_bagian = in_array($role,['kepala','pengurus']) ? (int)$_POST['id_bagian'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($nama))     $errors[] = 'Nama wajib diisi.';
    if (empty($username)) $errors[] = 'Username wajib diisi.';
    if (!empty($password) && strlen($password)<6) $errors[] = 'Password minimal 6 karakter.';

    // Cek username unik (kecuali user ini)
    $chk = $conn->prepare("SELECT id FROM users WHERE username=? AND id!=?");
    $chk->bind_param('si', $username, $id); $chk->execute();
    if ($chk->get_result()->num_rows > 0) $errors[] = 'Username sudah digunakan.';
    $chk->close();

    if (empty($errors)) {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET nama=?,username=?,password=?,role=?,id_bagian=?,is_active=? WHERE id=?");
            $stmt->bind_param('sssssii',$nama,$username,$hash,$role,$id_bagian,$is_active,$id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET nama=?,username=?,role=?,id_bagian=?,is_active=? WHERE id=?");
            $stmt->bind_param('sssiii',$nama,$username,$role,$id_bagian,$is_active,$id);
        }
        $stmt->execute(); $stmt->close();
        setFlash('success',"User '$nama' berhasil diperbarui.");
        header('Location: index.php'); exit;
    }
}

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-pencil me-2"></i>Edit User</div>
  </div>
  <div class="page-content">
    <?php if($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?=htmlspecialchars($e)?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <div class="card" style="max-width:600px">
      <div class="card-header"><i class="bi bi-pencil me-2"></i>Edit: <?=htmlspecialchars($user_edit['nama'])?></div>
      <div class="card-body p-4">
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="nama" class="form-control" value="<?=htmlspecialchars($_POST['nama']??$user_edit['nama'])?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" value="<?=htmlspecialchars($_POST['username']??$user_edit['username'])?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password Baru <small class="text-muted">(kosongkan jika tidak diubah)</small></label>
            <input type="password" name="password" class="form-control" minlength="6">
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select" id="roleSelect" onchange="toggleBagian(this.value)">
              <option value="superadmin" <?=$user_edit['role']==='superadmin'?'selected':''?>>Super Admin</option>
              <option value="kepala"     <?=$user_edit['role']==='kepala'?'selected':''?>>Kepala Bagian</option>
              <option value="pengurus"   <?=$user_edit['role']==='pengurus'?'selected':''?>>Pengurus Barang</option>
            </select>
          </div>
          <div class="mb-3" id="bagianDiv">
            <label class="form-label">Bagian</label>
            <select name="id_bagian" class="form-select">
              <option value="">-- Pilih Bagian --</option>
              <?php $bagianList->data_seek(0); while($b=$bagianList->fetch_assoc()): ?>
                <option value="<?=$b['id']?>" <?=$user_edit['id_bagian']==$b['id']?'selected':''?>><?=htmlspecialchars($b['nama'])?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?=$user_edit['is_active']?'checked':''?>>
              <label class="form-check-label" for="is_active">User Aktif</label>
            </div>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
            <a href="index.php" class="btn btn-outline-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
function toggleBagian(role) {
  const div = document.getElementById('bagianDiv');
  div.style.display = ['kepala','pengurus'].includes(role) ? 'block' : 'none';
}
toggleBagian(document.getElementById('roleSelect').value);
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
