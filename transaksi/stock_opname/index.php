<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus', 'kepala', 'superadmin']);
$pageTitle = 'Input Stock Opname';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

// Handle store / update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_edit    = (int)($_POST['id_edit'] ?? 0);
  $tanggal    = $_POST['tanggal'] ?? date('Y-m-d');
  $id_barang  = (int)($_POST['id_barang'] ?? 0);
  $stok_fisik = (int)($_POST['stok_fisik'] ?? 0);
  $keterangan = sanitize($_POST['keterangan'] ?? '');
  $bag        = ($role === 'superadmin') ? (int)$_POST['id_bagian'] : $id_bagian;
  $id_user    = getUserId();

  // Ambil stok sistem
  $stok_sistem = (int)$conn->query("SELECT COALESCE(stok,0) FROM stok_current WHERE id_barang=$id_barang AND id_bagian=$bag")->fetch_row()[0];

  if ($id_edit > 0) {
    // Cek status sebelum update
    $currentData = $conn->query("SELECT status FROM stock_opname WHERE id=$id_edit")->fetch_assoc();
    if ($currentData && $currentData['status'] === 'disetujui') {
      setFlash('error', 'Stock Opname yang sudah disetujui tidak dapat diedit.');
      header('Location: index.php');
      exit;
    }

    $stmt = $conn->prepare("UPDATE stock_opname SET tanggal=?, id_barang=?, id_bagian=?, stok_sistem=?, stok_fisik=?, keterangan=? WHERE id=?");
    $stmt->bind_param('siiiisi', $tanggal, $id_barang, $bag, $stok_sistem, $stok_fisik, $keterangan, $id_edit);
    if ($stmt->execute()) setFlash('success', 'Stock Opname berhasil diperbarui.');
    else setFlash('error', 'Gagal memperbarui: ' . $conn->error);
  } else {
    $stmt = $conn->prepare("INSERT INTO stock_opname (tanggal,id_barang,id_bagian,stok_sistem,stok_fisik,keterangan,id_user,status) VALUES(?,?,?,?,?,?,?,'pending')");
    $stmt->bind_param('siiiisi', $tanggal, $id_barang, $bag, $stok_sistem, $stok_fisik, $keterangan, $id_user);
    if ($stmt->execute()) setFlash('success', 'Stock Opname berhasil disimpan. Menunggu persetujuan Kepala.');
    else setFlash('error', 'Gagal menyimpan: ' . $conn->error);
  }
  $stmt->close();
  header('Location: index.php');
  exit;
}

// Edit Mode logic
$editData = null;
if (isset($_GET['edit'])) {
  $id_edit = (int)$_GET['edit'];
  $editData = $conn->query("SELECT * FROM stock_opname WHERE id=$id_edit")->fetch_assoc();
  if (!$editData) {
    setFlash('error', 'Data tidak ditemukan.');
  } elseif ($editData['status'] === 'disetujui') {
    setFlash('error', 'Stock Opname yang sudah disetujui tidak dapat diedit.');
    $editData = null;
  }
}

// Filter status
$filterStatus = $_GET['status'] ?? '';
$filterBagian = ($role === 'superadmin') ? '' : "AND so.id_bagian=$id_bagian";
$filterStatusWhere = $filterStatus ? "AND so.status='" . mysqli_real_escape_string($conn, $filterStatus) . "'" : '';
$list = $conn->query("
    SELECT so.*, 
           b.nama_barang, b.kode_barang, b.satuan, 
           j.nama_jenis, j.kode_jenis,
           bg.nama as nama_bagian
    FROM stock_opname so 
    JOIN barang b ON so.id_barang=b.id 
    JOIN jenis_barang j ON b.id_jenis_barang=j.id
    JOIN bagian bg ON so.id_bagian=bg.id
    WHERE 1=1 $filterBagian $filterStatusWhere
    ORDER BY so.tanggal DESC, so.id DESC 
    LIMIT 50
");

$jenisBarangList = $conn->query("SELECT id, kode_jenis, nama_jenis FROM jenis_barang ORDER BY nama_jenis");
$barangList = $conn->query("SELECT id, id_jenis_barang, kode_barang, nama_barang, satuan FROM barang ORDER BY nama_barang");
$bagianList = ($role === 'superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-clipboard-check me-2"></i>Input Stock Opname</div>
  </div>
  <div class="page-content">
    <?php $flash = getFlash();
    if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> auto-dismiss alert-dismissible fade show">
        <?= htmlspecialchars($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Filter dan Tombol Tambah -->
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
          <button class="btn btn-primary btn-sm" id="btnTambahSO" onclick="toggleFormSO()">
            <i class="bi bi-plus-lg me-1"></i>Tambah Stock Opname
          </button>
        </div>
      </div>
    </div>

    <!-- Form Stock Opname (Hidden by default) -->
    <div class="card mb-3" id="formStockOpname" style="display: <?= $editData ? 'block' : 'none' ?>;">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-plus-lg me-2"></i><?= $editData ? 'Edit' : 'Tambah' ?> Stock Opname</span>
        <button type="button" class="btn-close" onclick="toggleFormSO()" aria-label="Close"></button>
      </div>
      <div class="card-body p-3">
        <form method="POST">
          <input type="hidden" name="id_edit" value="<?= $editData['id'] ?? 0 ?>">
          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Tanggal SO</label>
                <input type="date" name="tanggal" class="form-control" value="<?= $editData['tanggal'] ?? date('Y-m-d') ?>" required>
              </div>
            </div>
            <?php if ($role === 'superadmin'): ?>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Bagian</label>
                  <select name="id_bagian" class="form-select" id="soBagian" required>
                    <option value="">-- Pilih Bagian --</option>
                    <?php
                    $bagianList->data_seek(0);
                    while ($bg = $bagianList->fetch_assoc()):
                    ?>
                      <option value="<?= $bg['id'] ?>" <?= ($editData['id_bagian'] ?? 0) == $bg['id'] ? 'selected' : '' ?>><?= htmlspecialchars($bg['nama']) ?></option>
                    <?php endwhile; ?>
                  </select>
                </div>
              </div>
            <?php endif; ?>
            <div class="col-md-<?= $role === 'superadmin' ? '4' : '4' ?>">
              <div class="mb-3">
                <label class="form-label">Jenis Barang <span class="text-danger">*</span></label>
                <select class="form-select" id="soJenisBarang" required onchange="filterBarangByJenis()">
                  <option value="">-- Pilih Jenis Barang --</option>
                  <?php
                  $jenisBarangList->data_seek(0);
                  while ($j = $jenisBarangList->fetch_assoc()):
                  ?>
                    <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nama_jenis']) ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>
            <div class="col-md-<?= $role === 'superadmin' ? '12' : '8' ?>">
              <div class="mb-3">
                <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
                <select name="id_barang" class="form-select" id="soBarang" required onchange="getStokSistem(this.value)">
                  <option value="">-- Pilih Jenis Barang Terlebih Dahulu --</option>
                  <?php
                  $barangList->data_seek(0);
                  while ($b = $barangList->fetch_assoc()):
                  ?>
                    <option value="<?= $b['id'] ?>" data-jenis="<?= $b['id_jenis_barang'] ?>" <?= ($editData['id_barang'] ?? 0) == $b['id'] ? 'selected' : '' ?>>
                      [<?= htmlspecialchars($b['kode_barang']) ?>] <?= htmlspecialchars($b['nama_barang']) ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Stok Sistem <small class="text-muted">(otomatis)</small></label>
                <input type="number" id="stokSistemDisplay" class="form-control" readonly value="<?= $editData['stok_sistem'] ?? 0 ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Stok Fisik <span class="text-danger">*</span></label>
                <input type="number" name="stok_fisik" class="form-control" min="0" required placeholder="0" value="<?= $editData['stok_fisik'] ?? '' ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Selisih <small class="text-muted">(otomatis)</small></label>
                <input type="number" id="selisihDisplay" class="form-control" readonly value="<?= ($editData['stok_fisik'] ?? 0) - ($editData['stok_sistem'] ?? 0) ?>">
              </div>
            </div>
            <div class="col-md-12">
              <div class="mb-3">
                <label class="form-label">Keterangan</label>
                <textarea name="keterangan" class="form-control" rows="2" placeholder="Alasan selisih (jika ada)"><?= htmlspecialchars($editData['keterangan'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-save me-1"></i><?= $editData ? 'Update' : 'Simpan' ?> Stock Opname
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleFormSO()">
              <i class="bi bi-x-lg me-1"></i>Batal
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Tabel Stock Opname -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2"></i>Riwayat Stock Opname</span>
        <input type="text" class="form-control form-control-sm" style="width:250px" placeholder="Cari..." data-table-search="soTable">
      </div>
      <div class="table-wrapper">
        <table class="table table-sm" id="soTable">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Kode Jenis</th>
              <th>Jenis Barang</th>
              <th>Kode Barang</th>
              <th>Nama Barang</th>
              <th class="text-center">Stok Sistem</th>
              <th class="text-center">Stok Fisik</th>
              <th class="text-center">Selisih</th>
              <th>Keterangan</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($s = $list->fetch_assoc()):
              $canEdit = in_array($s['status'], ['pending', 'ditolak']);
            ?>
              <tr>
                <td><?= formatTanggal($s['tanggal']) ?></td>
                <td><code><?= htmlspecialchars($s['kode_jenis']) ?></code></td>
                <td><?= htmlspecialchars($s['nama_jenis']) ?></td>
                <td><code><?= htmlspecialchars($s['kode_barang']) ?></code></td>
                <td><?= htmlspecialchars($s['nama_barang']) ?></td>
                <td class="text-center"><?= number_format($s['stok_sistem']) ?></td>
                <td class="text-center"><?= number_format($s['stok_fisik']) ?></td>
                <td class="text-center fw-bold <?= $s['selisih'] > 0 ? 'text-success' : ($s['selisih'] < 0 ? 'text-danger' : '') ?>">
                  <?= ($s['selisih'] > 0 ? '+' : '') . number_format($s['selisih']) ?>
                </td>
                <td><small><?= htmlspecialchars($s['keterangan']) ?></small></td>
                <td>
                  <?php
                  $sc = ['pending' => 'badge-pending', 'disetujui' => 'badge-approved', 'ditolak' => 'badge-rejected'];
                  $si = ['pending' => 'bi-clock', 'disetujui' => 'bi-check-circle', 'ditolak' => 'bi-x-circle'];
                  ?>
                  <span class="badge-sipeba <?= $sc[$s['status']] ?? '' ?>">
                    <i class="bi <?= $si[$s['status']] ?? '' ?>"></i> <?= ucfirst($s['status']) ?>
                  </span>
                </td>
                <td>
                  <?php if ($canEdit): ?>
                    <a href="?edit=<?= $s['id'] ?>" class="text-primary me-2" title="Edit"><i class="bi bi-pencil-square"></i></a>
                    <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Hapus data ini?')">
                      <input type="hidden" name="id" value="<?= $s['id'] ?>">
                      <button type="submit" class="btn p-0 text-danger border-0 bg-transparent" title="Hapus"><i class="bi bi-trash"></i></button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted" title="Tidak dapat diedit/dihapus karena sudah disetujui"><i class="bi bi-lock"></i></span>
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
<script>
  function toggleFormSO() {
    const form = document.getElementById('formStockOpname');
    const btn = document.getElementById('btnTambahSO');
    if (form.style.display === 'none') {
      form.style.display = 'block';
      btn.style.display = 'none';
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    } else {
      form.style.display = 'none';
      btn.style.display = 'block';
      // Reset form if not editing
      <?php if (!$editData): ?>
        form.querySelector('form').reset();
        document.getElementById('soBarang').innerHTML = '<option value="">-- Pilih Jenis Barang Terlebih Dahulu --</option>';
        document.getElementById('stokSistemDisplay').value = 0;
        document.getElementById('selisihDisplay').value = 0;
      <?php endif; ?>
    }
  }

  function filterBarangByJenis() {
    const jenisId = document.getElementById('soJenisBarang').value;
    const barangSelect = document.getElementById('soBarang');
    const allOptions = barangSelect.querySelectorAll('option');

    // Reset
    barangSelect.value = '';
    document.getElementById('stokSistemDisplay').value = 0;
    document.getElementById('selisihDisplay').value = 0;

    // Filter options
    allOptions.forEach(option => {
      if (option.value === '') {
        option.style.display = 'block';
        option.textContent = jenisId ? '-- Pilih Barang --' : '-- Pilih Jenis Barang Terlebih Dahulu --';
      } else {
        option.style.display = (option.dataset.jenis === jenisId) ? 'block' : 'none';
      }
    });
  }

  async function getStokSistem(idBarang) {
    if (!idBarang) {
      document.getElementById('stokSistemDisplay').value = 0;
      document.getElementById('selisihDisplay').value = 0;
      return;
    }

    const bagianInput = <?= $role === 'superadmin' ? 'document.getElementById("soBagian")?.value' : $id_bagian ?>;
    const params = new URLSearchParams({
      id_barang: idBarang
    });
    if (bagianInput) params.append('id_bagian', bagianInput);

    const resp = await fetch('<?= BASE_URL ?>/api/get_stok.php?' + params.toString());
    const data = await resp.json();
    document.getElementById('stokSistemDisplay').value = data.stok || 0;

    // Auto calculate selisih
    calculateSelisih();
  }

  // Calculate selisih when stok fisik changes
  document.addEventListener('DOMContentLoaded', function() {
    const stokFisikInput = document.querySelector('input[name="stok_fisik"]');
    if (stokFisikInput) {
      stokFisikInput.addEventListener('input', calculateSelisih);
    }

    // Set jenis barang if editing
    <?php if ($editData): ?>
      const editBarangOption = document.querySelector('#soBarang option[value="<?= $editData['id_barang'] ?>"]');
      if (editBarangOption) {
        const jenisId = editBarangOption.dataset.jenis;
        document.getElementById('soJenisBarang').value = jenisId;
        filterBarangByJenis();
        document.getElementById('soBarang').value = '<?= $editData['id_barang'] ?>';
      }
    <?php endif; ?>
  });

  function calculateSelisih() {
    const stokSistem = parseInt(document.getElementById('stokSistemDisplay').value) || 0;
    const stokFisik = parseInt(document.querySelector('input[name="stok_fisik"]').value) || 0;
    const selisih = stokFisik - stokSistem;
    document.getElementById('selisihDisplay').value = selisih;
  }
</script>
<?php include BASE_PATH . '/includes/footer.php'; ?>