<?php

/**
 * Laporan Daftar Hasil Stock Opname
 * Format: Saldo Awal (stok sebelum SO), Penambahan, Pengurangan, Saldo Akhir
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
$pageTitle = 'Hasil Stock Opname';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

$f_bagian  = ($role === 'superadmin') ? (int)($_GET['id_bagian'] ?? 0) : $id_bagian;
$f_tahun   = (int)($_GET['tahun'] ?? date('Y'));
$f_status  = $_GET['status'] ?? 'disetujui';

$where = "WHERE so.status='" . $conn->real_escape_string($f_status) . "'";
$where .= " AND YEAR(so.tanggal)=$f_tahun";
if ($f_bagian) {
  $where .= " AND so.id_bagian=$f_bagian";
} elseif ($role !== 'superadmin') {
  $where .= " AND so.id_bagian=$id_bagian";
}

$data = $conn->query("
    SELECT so.*, b.kode_barang, b.nama_barang, b.satuan, bg.nama as nama_bagian, u.nama as nama_input, ap.nama as nama_approver
    FROM stock_opname so
    JOIN barang b ON so.id_barang=b.id
    JOIN bagian bg ON so.id_bagian=bg.id
    JOIN users u ON so.id_user=u.id
    LEFT JOIN users ap ON so.id_approver=ap.id
    $where
    ORDER BY so.tanggal DESC, b.nama_barang ASC
");

$bagianList = ($role === 'superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;
$years = range(date('Y') - 2, date('Y') + 1);

// Export
if (isset($_GET['export'])) {
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header('Content-Disposition: attachment; filename="hasil_stock_opname_' . $f_tahun . '.xls"');
  echo "\xEF\xBB\xBF";
  echo "DAFTAR HASIL STOCK OPNAME\t\t\t\t\t\t\n";
  echo "Tahun: $f_tahun\t\t\t\t\t\t\n\n";
  echo "No\tTanggal\tKode Barang\tNama Barang\tSatuan\tStok Sistem\tStok Fisik\tSelisih\tStatus\n";
  $no = 1;
  $data->data_seek(0);
  while ($r = $data->fetch_assoc()) {
    echo "$no\t{$r['tanggal']}\t{$r['kode_barang']}\t{$r['nama_barang']}\t{$r['satuan']}\t{$r['stok_sistem']}\t{$r['stok_fisik']}\t{$r['selisih']}\t{$r['status']}\n";
    $no++;
  }
  exit;
}

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-clipboard-data me-2"></i>Hasil Stock Opname</div>
  </div>
  <div class="page-content">
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
          <?php if ($role === 'superadmin'): ?>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Bagian</label>
              <select name="id_bagian" class="form-select form-select-sm">
                <option value="">Semua Bagian</option>
                <?php while ($bg = $bagianList->fetch_assoc()): ?>
                  <option value="<?= $bg['id'] ?>" <?= $f_bagian == $bg['id'] ? 'selected' : '' ?>><?= htmlspecialchars($bg['nama']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          <?php endif; ?>
          <div class="col-auto">
            <label class="form-label fw-semibold">Tahun</label>
            <select name="tahun" class="form-select form-select-sm">
              <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $f_tahun == $y ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-auto">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="disetujui" <?= $f_status === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
              <option value="pending" <?= $f_status === 'pending' ? 'selected' : '' ?>>Pending</option>
            </select>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 1])) ?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
          </div>
        </form>
      </div>
    </div>
    <div class="card">
      <div class="card-header text-center">
        <div class="fw-bold">DAFTAR HASIL STOCK OPNAME TAHUN <?= $f_tahun ?></div>
        <div class="text-muted" style="font-size:.85rem">Lampiran Berita Acara Pemeriksaan Persediaan</div>
      </div>
      <div class="table-wrapper">
        <table class="table table-bordered table-sm" style="font-size:.82rem">
          <thead>
            <tr class="table-info text-center">
              <th>No</th>
              <th>Tanggal</th>
              <th>Kode Barang</th>
              <th>Nama Barang</th>
              <th>Satuan</th>
              <th>Stok Sistem</th>
              <th>Stok Fisik</th>
              <th>Selisih</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php $no = 1;
            $found = false;
            $totalSelisih = 0;
            while ($r = $data->fetch_assoc()): $found = true;
              $totalSelisih += $r['selisih']; ?>
              <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= formatTanggal($r['tanggal']) ?></td>
                <td class="text-center"><code><?= htmlspecialchars($r['kode_barang']) ?></code></td>
                <td><?= htmlspecialchars($r['nama_barang']) ?></td>
                <td class="text-center"><?= htmlspecialchars($r['satuan']) ?></td>
                <td class="text-center"><?= number_format($r['stok_sistem']) ?></td>
                <td class="text-center"><?= number_format($r['stok_fisik']) ?></td>
                <td class="text-center fw-bold <?= $r['selisih'] > 0 ? 'text-success' : ($r['selisih'] < 0 ? 'text-danger' : '') ?>"><?= ($r['selisih'] > 0 ? '+' : '') . number_format($r['selisih']) ?></td>
                <td class="text-center">
                  <?php
                  $sc = ['pending' => 'badge-pending', 'disetujui' => 'badge-approved', 'ditolak' => 'badge-rejected'];
                  ?>
                  <span class="badge-sipeba <?= $sc[$r['status']] ?? '' ?>"><?= ucfirst($r['status']) ?></span>
                </td>
              </tr>
            <?php endwhile;
            if (!$found): ?>
              <tr>
                <td colspan="9" class="text-center text-muted py-4">Tidak ada data stock opname.</td>
              </tr>
            <?php endif; ?>
          </tbody>
          <?php if ($found): ?>
            <tfoot>
              <tr class="table-secondary fw-bold">
                <td colspan="7" class="text-end">TOTAL SELISIH</td>
                <td class="text-center <?= $totalSelisih > 0 ? 'text-success' : ($totalSelisih < 0 ? 'text-danger' : '') ?>"><?= ($totalSelisih > 0 ? '+' : '') . number_format($totalSelisih) ?></td>
                <td></td>
              </tr>
            </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>