<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['superadmin', 'kepala', 'pengurus']);
$pageTitle = 'Master Barang';

// Inline CRUD
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode   = sanitize($_POST['kode_barang'] ?? '');
    $nama   = sanitize($_POST['nama_barang'] ?? '');
    $satuan = sanitize($_POST['satuan'] ?? '');
    $jenis  = (int)($_POST['id_jenis_barang'] ?? 0);

    if ($action === 'create') {
        $stmt = $conn->prepare("INSERT INTO barang (kode_barang,nama_barang,satuan,id_jenis_barang) VALUES(?,?,?,?)");
        $stmt->bind_param('sssi',$kode,$nama,$satuan,$jenis);
        $stmt->execute(); $stmt->close();
        setFlash('success',"Barang '$nama' berhasil ditambahkan.");
        header('Location: index.php'); exit;
    } elseif ($action === 'edit' && $id) {
        $stmt = $conn->prepare("UPDATE barang SET kode_barang=?,nama_barang=?,satuan=?,id_jenis_barang=? WHERE id=?");
        $stmt->bind_param('sssii',$kode,$nama,$satuan,$jenis,$id);
        $stmt->execute(); $stmt->close();
        setFlash('success','Barang berhasil diperbarui.');
        header('Location: index.php'); exit;
    } elseif ($action === 'delete' && $id) {
        $chk = $conn->query("SELECT COUNT(*) FROM penerimaan WHERE id_barang=$id")->fetch_row()[0];
        if ($chk > 0) {
            setFlash('error','Tidak bisa hapus — barang sudah memiliki transaksi.');
        } else {
            $conn->query("DELETE FROM barang WHERE id=$id");
            setFlash('success','Barang berhasil dihapus.');
        }
        header('Location: index.php'); exit;
    }
}

$editItem = null;
if ($action === 'edit' && $id) {
    $stmt = $conn->prepare("SELECT * FROM barang WHERE id=?");
    $stmt->bind_param('i',$id); $stmt->execute();
    $editItem = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

$jenisList = $conn->query("SELECT * FROM jenis_barang ORDER BY kode_jenis");
$list      = $conn->query("SELECT b.*, j.nama_jenis, j.kode_jenis FROM barang b JOIN jenis_barang j ON b.id_jenis_barang=j.id ORDER BY b.kode_barang");

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-archive me-2"></i>Master Data Barang</div>
  </div>
  <div class="page-content">
    <?php $flash=getFlash(); if($flash): ?>
      <div class="alert alert-<?=$flash['type']==='error'?'danger':$flash['type']?> auto-dismiss alert-dismissible fade show">
        <?=htmlspecialchars($flash['message'])?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header"><i class="bi bi-<?=$editItem?'pencil':'plus-lg'?> me-2"></i><?=$editItem?'Edit':'Tambah'?> Barang</div>
          <div class="card-body p-3">
            <form method="POST">
              <input type="hidden" name="action" value="<?=$editItem?'edit':'create'?>">
              <?php if($editItem): ?><input type="hidden" name="id" value="<?=$editItem['id']?>"><?php endif; ?>
              <div class="mb-3">
                <label class="form-label">Kode Barang <span class="text-danger">*</span></label>
                <input type="text" name="kode_barang" class="form-control" value="<?=htmlspecialchars($editItem['kode_barang']??'')?>" required maxlength="20">
              </div>
              <div class="mb-3">
                <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
                <input type="text" name="nama_barang" class="form-control" value="<?=htmlspecialchars($editItem['nama_barang']??'')?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Satuan <span class="text-danger">*</span></label>
                <input type="text" name="satuan" class="form-control" value="<?=htmlspecialchars($editItem['satuan']??'')?>" placeholder="Rim, Buah, Kotak..." required>
              </div>
              <div class="mb-3">
                <label class="form-label">Jenis Barang <span class="text-danger">*</span></label>
                <select name="id_jenis_barang" class="form-select" required>
                  <option value="">-- Pilih Jenis --</option>
                  <?php while($j=$jenisList->fetch_assoc()): ?>
                    <option value="<?=$j['id']?>" <?=($editItem['id_jenis_barang']??'')==$j['id']?'selected':''?>>
                      [<?=htmlspecialchars($j['kode_jenis'])?>] <?=htmlspecialchars($j['nama_jenis'])?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Simpan</button>
                <?php if($editItem): ?><a href="index.php" class="btn btn-outline-secondary btn-sm">Batal</a><?php endif; ?>
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-list-ul me-2"></i>Daftar Barang</span>
            <input type="text" class="form-control form-control-sm" style="width:200px" data-table-search="barangTable" placeholder="Cari...">
          </div>
          <div class="table-wrapper">
            <table class="table" id="barangTable">
              <thead><tr><th>#</th><th>Kode</th><th>Nama Barang</th><th>Satuan</th><th>Jenis</th><th>Aksi</th></tr></thead>
              <tbody>
                <?php $no=1; while($b=$list->fetch_assoc()): ?>
                <tr>
                  <td><?=$no++?></td>
                  <td><code><?=htmlspecialchars($b['kode_barang'])?></code></td>
                  <td><?=htmlspecialchars($b['nama_barang'])?></td>
                  <td><span class="badge bg-secondary"><?=htmlspecialchars($b['satuan'])?></span></td>
                  <td><?=htmlspecialchars($b['nama_jenis'])?></td>
                  <td>
                    <a href="?action=edit&id=<?=$b['id']?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-pencil"></i></a>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$b['id']?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" data-confirm="Hapus barang <?=htmlspecialchars($b['nama_barang'])?>?"><i class="bi bi-trash"></i></button>
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
<?php include BASE_PATH . '/includes/footer.php'; ?>
