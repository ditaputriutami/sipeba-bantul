<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['superadmin', 'kepala', 'pengurus']);
$pageTitle = 'Jenis Barang';

// Inline create/edit/delete handler
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $kode = sanitize($_POST['kode_jenis'] ?? '');
  $nama = sanitize($_POST['nama_jenis'] ?? '');

  if ($action === 'create') {
    if ($kode && $nama) {
      $stmt = $conn->prepare("INSERT INTO jenis_barang (kode_jenis,nama_jenis) VALUES(?,?)");
      $stmt->bind_param('ss', $kode, $nama);
      $stmt->execute();
      $stmt->close();
      setFlash('success', "Jenis barang '$nama' berhasil ditambahkan.");
    }
    header('Location: index.php');
    exit;
  } elseif ($action === 'edit' && $id) {
    $stmt = $conn->prepare("UPDATE jenis_barang SET kode_jenis=?,nama_jenis=? WHERE id=?");
    $stmt->bind_param('ssi', $kode, $nama, $id);
    $stmt->execute();
    $stmt->close();
    setFlash('success', "Jenis barang berhasil diperbarui.");
    header('Location: index.php');
    exit;
  } elseif ($action === 'delete' && $id) {
    // Cek apakah ada barang yang pakai jenis ini
    $chk = $conn->query("SELECT COUNT(*) FROM barang WHERE id_jenis_barang=$id")->fetch_row()[0];
    if ($chk > 0) {
      setFlash('error', 'Tidak bisa hapus — ada barang yang menggunakan jenis ini.');
    } else {
      $conn->query("DELETE FROM jenis_barang WHERE id=$id");
      setFlash('success', 'Jenis barang berhasil dihapus.');
    }
    header('Location: index.php');
    exit;
  }
}

// Fetch for edit modal prefill
$editItem = null;
if ($action === 'edit' && $id) {
  $stmt = $conn->prepare("SELECT * FROM jenis_barang WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $editItem = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

$list = $conn->query("SELECT j.*, (SELECT COUNT(*) FROM barang b WHERE b.id_jenis_barang=j.id) as jml_barang FROM jenis_barang j ORDER BY j.kode_jenis");

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-tags me-2"></i>Master Jenis Barang</div>
  </div>
  <div class="page-content">
    <?php $flash = getFlash();
    if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> auto-dismiss alert-dismissible fade show">
        <?= htmlspecialchars($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-3">
      <!-- Form Panel -->
      <div class="col-lg-6" id="formPanel" style="display: <?= $editItem ? 'block' : 'none' ?>">
        <div class="card">
          <div class="card-header"><i class="bi bi-<?= $editItem ? 'pencil' : 'plus-lg' ?> me-2"></i><?= $editItem ? 'Edit' : 'Tambah' ?> Jenis Barang</div>
          <div class="card-body p-3">
            <form method="POST">
              <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'create' ?>">
              <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>
              <div class="mb-3">
                <label class="form-label">Kode Jenis <span class="text-danger">*</span></label>
                <input type="text" name="kode_jenis" class="form-control" value="<?= htmlspecialchars($editItem['kode_jenis'] ?? '') ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Nama Jenis <span class="text-danger">*</span></label>
                <input type="text" name="nama_jenis" class="form-control" value="<?= htmlspecialchars($editItem['nama_jenis'] ?? '') ?>" required>
              </div>
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Simpan</button>
                <?php if ($editItem): ?>
                  <a href="index.php" class="btn btn-outline-secondary btn-sm">Batal</a>
                <?php else: ?>
                  <button type="button" class="btn btn-outline-secondary btn-sm" id="btnBatalJenis">Batal</button>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>
      </div>
      <!-- List -->
      <div class="<?= $editItem ? 'col-lg-6' : 'col-lg-12' ?>" id="listPanel">
        <div class="card">
          <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-list-ul me-2"></i>Daftar Jenis Barang</span>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-primary btn-sm" id="btnTambahJenis" style="display: <?= $editItem ? 'none' : 'block' ?>">
                <i class="bi bi-plus-lg me-1"></i>Tambah Jenis Barang
              </button>
              <input type="text" class="form-control form-control-sm" style="width:180px" data-table-search="jenisTable" placeholder="Cari...">
            </div>
          </div>
          <div class="table-wrapper">
            <table class="table" id="jenisTable">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Kode</th>
                  <th>Nama Jenis</th>
                  <th>Jml Barang</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php $no = 1;
                while ($j = $list->fetch_assoc()): ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><span><?= htmlspecialchars($j['kode_jenis']) ?></span></td>
                    <td><?= htmlspecialchars($j['nama_jenis']) ?></td>
                    <td><span><?= $j['jml_barang'] ?></span></td>
                    <td>
                      <a href="?action=edit&id=<?= $j['id'] ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-pencil"></i></a>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $j['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" data-confirm="Hapus jenis barang ini?"><i class="bi bi-trash"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const btnTambah = document.getElementById('btnTambahJenis');
    const btnBatal = document.getElementById('btnBatalJenis');
    const formPanel = document.getElementById('formPanel');
    const listPanel = document.getElementById('listPanel');

    if (btnTambah) {
      btnTambah.addEventListener('click', function() {
        formPanel.style.display = 'block';
        listPanel.style.display = 'none';
      });
    }

    if (btnBatal) {
      btnBatal.addEventListener('click', function() {
        formPanel.style.display = 'none';
        listPanel.style.display = 'block';
        // Reset form
        formPanel.querySelector('form').reset();
      });
    }
  });
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>