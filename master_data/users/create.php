<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['superadmin']);
$pageTitle = 'Tambah User';

$bagianList = $conn->query("SELECT * FROM bagian ORDER BY nama");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = sanitize($_POST['nama'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';
    $id_bagian= in_array($role, ['kepala','pengurus']) ? (int)$_POST['id_bagian'] : null;

    if (empty($nama))     $errors[] = 'Nama wajib diisi.';
    if (empty($username)) $errors[] = 'Username wajib diisi.';
    if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';
    if (!in_array($role, ['superadmin','kepala','pengurus'])) $errors[] = 'Role tidak valid.';
    if (in_array($role, ['kepala','pengurus']) && !$id_bagian) $errors[] = 'Bagian wajib dipilih.';

    // Cek username unik
    $chk = $conn->prepare("SELECT id FROM users WHERE username=?");
    $chk->bind_param('s', $username); $chk->execute();
    if ($chk->get_result()->num_rows > 0) $errors[] = 'Username sudah digunakan.';
    $chk->close();

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (nama,username,password,role,id_bagian) VALUES(?,?,?,?,?)");
        $stmt->bind_param('ssssi', $nama, $username, $hash, $role, $id_bagian);
        $stmt->execute(); $stmt->close();
        setFlash('success', "User '$nama' berhasil ditambahkan.");
        header('Location: index.php'); exit;
    }
}

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-person-plus me-2"></i>Tambah User</div>
  </div>
  <div class="page-content">
    <?php if($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?=htmlspecialchars($e)?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <div class="card" style="max-width:600px">
      <div class="card-header"><i class="bi bi-person-plus me-2"></i>Form Tambah User</div>
      <div class="card-body p-4">
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
            <input type="text" name="nama" class="form-control" value="<?=htmlspecialchars($_POST['nama']??'')?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control" value="<?=htmlspecialchars($_POST['username']??'')?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" required minlength="6">
            <div class="form-text">Minimal 6 karakter.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Role <span class="text-danger">*</span></label>
            <select name="role" class="form-select" id="roleSelect" required onchange="toggleBagian(this.value)">
              <option value="">-- Pilih Role --</option>
              <option value="superadmin">Super Admin</option>
              <option value="kepala">Kepala Bagian</option>
              <option value="pengurus">Pengurus Barang</option>
            </select>
          </div>
          <div class="mb-3" id="bagianDiv" style="display:none">
            <label class="form-label">Bagian <span class="text-danger">*</span></label>
            <select name="id_bagian" class="form-select">
              <option value="">-- Pilih Bagian --</option>
              <?php while($b=$bagianList->fetch_assoc()): ?>
                <option value="<?=$b['id']?>"><?=htmlspecialchars($b['nama'])?></option>
              <?php endwhile; ?>
            </select>
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
  const req = ['kepala','pengurus'].includes(role);
  div.style.display = req ? 'block' : 'none';
  div.querySelector('select').required = req;
}
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>
