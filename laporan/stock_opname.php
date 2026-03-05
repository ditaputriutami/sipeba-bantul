<?php

/**
 * Laporan Daftar Hasil Stock Opname (Summary per Jenis Barang)
 * Format: Saldo Awal, Penambahan, Pengurangan, Saldo Akhir
 * Sesuai Image 1 & 2
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
$pageTitle = 'Summary Hasil Stock Opname';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

$f_bagian  = ($role === 'superadmin') ? (int)($_GET['id_bagian'] ?? 0) : $id_bagian;
$f_tahun   = (int)($_GET['tahun'] ?? date('Y'));

$where_bagian = "";
if ($f_bagian) {
  $where_bagian = " AND id_bagian=$f_bagian";
}

// Query untuk mendapatkan ringkasan per jenis barang
// Kita hitung Saldo Awal (sebelum tahun terpilih), Penambahan, Pengurangan, dan Saldo Akhir
$query = "
    SELECT 
        j.id as id_jenis,
        j.kode_jenis,
        j.nama_jenis,
        -- Saldo Awal (Transaksi sebelum tahun filter)
        (
            SELECT COALESCE(SUM(p.jumlah * p.harga_satuan), 0)
            FROM penerimaan p
            JOIN barang b2 ON p.id_barang = b2.id
            WHERE b2.id_jenis_barang = j.id AND p.status = 'disetujui' AND YEAR(p.tanggal) < $f_tahun $where_bagian
        ) - (
            SELECT COALESCE(SUM(pd.jumlah_dipotong * pd.harga_satuan), 0)
            FROM pengurangan_detail pd
            JOIN pengurangan pr ON pd.id_pengurangan = pr.id
            JOIN barang b2 ON pr.id_barang = b2.id
            WHERE b2.id_jenis_barang = j.id AND pr.status = 'disetujui' AND YEAR(pr.tanggal) < $f_tahun $where_bagian
        ) as saldo_awal,
        -- Penambahan (Tahun filter)
        (
            SELECT COALESCE(SUM(p.jumlah * p.harga_satuan), 0)
            FROM penerimaan p
            JOIN barang b2 ON p.id_barang = b2.id
            WHERE b2.id_jenis_barang = j.id AND p.status = 'disetujui' AND YEAR(p.tanggal) = $f_tahun $where_bagian
        ) as penambahan,
        -- Pengurangan (Tahun filter)
        (
            SELECT COALESCE(SUM(pd.jumlah_dipotong * pd.harga_satuan), 0)
            FROM pengurangan_detail pd
            JOIN pengurangan pr ON pd.id_pengurangan = pr.id
            JOIN barang b2 ON pr.id_barang = b2.id
            WHERE b2.id_jenis_barang = j.id AND pr.status = 'disetujui' AND YEAR(pr.tanggal) = $f_tahun $where_bagian
        ) as pengurangan,
        -- Keterangan (Ambil dari Stock Opname tahun filter)
        (
            SELECT GROUP_CONCAT(DISTINCT so.keterangan SEPARATOR '; ')
            FROM stock_opname so
            JOIN barang b2 ON so.id_barang = b2.id
            WHERE b2.id_jenis_barang = j.id AND so.status = 'disetujui' AND YEAR(so.tanggal) = $f_tahun $where_bagian AND so.keterangan != ''
        ) as keterangan
    FROM jenis_barang j
    ORDER BY j.kode_jenis ASC
";

$data = $conn->query($query);

$bagianList = ($role === 'superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;
$years = range(date('Y') - 5, date('Y') + 1);

$nama_bagian_text = "";
if ($f_bagian) {
    if ($role === 'superadmin') {
        $bg_data = $conn->query("SELECT nama FROM bagian WHERE id=$f_bagian")->fetch_assoc();
        $nama_bagian_text = $bg_data['nama'] ?? "";
    } else {
        $nama_bagian_text = $user['nama_bagian'] ?? "";
    }
}

// Export Excel
if (isset($_GET['export'])) {
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header('Content-Disposition: attachment; filename="summary_stock_opname_' . $f_tahun . '.xls"');
  echo "\xEF\xBB\xBF";
?>
  <table border="1">
    <thead>
      <tr>
        <th colspan="13" style="text-align:center; font-weight:bold;">DAFTAR HASIL PERHITUNGAN FISIK ATAS BARANG PERSEDIAAN/STOCK OPNAME</th>
      </tr>
      <tr>
        <th colspan="13" style="text-align:center; font-weight:bold;">DI LINGKUNGAN PEMERINTAH KABUPATEN BANTUL</th>
      </tr>
      <?php if ($nama_bagian_text): ?>
      <tr>
        <th colspan="13" style="text-align:left; font-weight:bold;"><?= strtoupper($nama_bagian_text) ?></th>
      </tr>
      <?php endif; ?>
      <tr>
        <th colspan="13" style="text-align:left; font-weight:bold;">PER TANGGAL 31 DESEMBER <?= $f_tahun ?></th>
      </tr>
      <tr>
        <th colspan="13"></th>
      </tr>
      <tr>
        <th>NO</th>
        <th>NAMA BARANG</th>
        <th colspan="6">KODE BARANG</th>
        <th>SALDO AWAL <?= $f_tahun ?></th>
        <th>PENAMBAHAN <?= $f_tahun ?></th>
        <th>PENGURANGAN <?= $f_tahun ?></th>
        <th>SALDO AKHIR <?= $f_tahun ?></th>
        <th>KETERANGAN</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $no = 1;
      $t_awal = 0; $t_plus = 0; $t_minus = 0; $t_akhir = 0;
      while ($r = $data->fetch_assoc()):
        $s_akhir = $r['saldo_awal'] + $r['penambahan'] - $r['pengurangan'];
        $t_awal += $r['saldo_awal']; $t_plus += $r['penambahan']; $t_minus += $r['pengurangan']; $t_akhir += $s_akhir;
        
        $kode_raw = $r['kode_jenis'];
        if (strpos($kode_raw, '.') !== false) {
            $kode_parts = explode('.', $kode_raw);
        } elseif (strlen($kode_raw) == 9) {
            $kode_parts = [
                substr($kode_raw, 0, 1),
                substr($kode_raw, 1, 1),
                substr($kode_raw, 2, 1),
                substr($kode_raw, 3, 2),
                substr($kode_raw, 5, 2),
                substr($kode_raw, 7, 2)
            ];
        } else {
            $kode_parts = [$kode_raw];
        }
        $kode_parts = array_pad($kode_parts, 6, '');
      ?>
        <tr>
          <td align="center"><?= $no++ ?></td>
          <td><?= htmlspecialchars($r['nama_jenis']) ?></td>
          <?php foreach ($kode_parts as $kp): ?>
            <td align="center" style="mso-number-format:'\@';"><?= $kp ?></td>
          <?php endforeach; ?>
          <td align="right"><?= number_format($r['saldo_awal'], 2, ',', '.') ?></td>
          <td align="right"><?= number_format($r['penambahan'], 2, ',', '.') ?></td>
          <td align="right"><?= number_format($r['pengurangan'], 2, ',', '.') ?></td>
          <td align="right"><?= number_format($s_akhir, 2, ',', '.') ?></td>
          <td><?= htmlspecialchars($r['keterangan'] ?? '') ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:bold; background-color:#f0f0f0;">
        <td colspan="8" align="center">JUMLAH</td>
        <td align="right"><?= number_format($t_awal, 2, ',', '.') ?></td>
        <td align="right"><?= number_format($t_plus, 2, ',', '.') ?></td>
        <td align="right"><?= number_format($t_minus, 2, ',', '.') ?></td>
        <td align="right"><?= number_format($t_akhir, 2, ',', '.') ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
<?php
  exit;
}

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title"><i class="bi bi-clipboard-data me-2"></i>Summary Stock Opname</div>
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
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
            <a href="stock_opname_detail.php?tahun=<?= $f_tahun ?>&id_bagian=<?= $f_bagian ?>" class="btn btn-info btn-sm ms-1"><i class="bi bi-list-ul me-1"></i>Lihat Detail</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 1])) ?>" class="btn btn-success btn-sm ms-1"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
          </div>
        </form>
      </div>
    </div>
    <div class="card">
      <div class="card-header text-center py-3">
        <div class="fw-bold" style="font-size:1.1rem">DAFTAR HASIL PERHITUNGAN FISIK ATAS BARANG PERSEDIAAN/STOCK OPNAME</div>
        <div class="fw-bold">DI LINGKUNGAN PEMERINTAH KABUPATEN BANTUL</div>
        <div class="text-muted mt-1" style="font-size:.85rem">PER TANGGAL 31 DESEMBER <?= $f_tahun ?></div>
      </div>
      <div class="table-wrapper p-3 overflow-auto">
        <table class="table table-bordered table-sm align-middle" style="font-size:.82rem">
          <thead>
            <tr class="table-info text-center">
              <th>NO</th>
              <th>NAMA BARANG (KATEGORI)</th>
              <th colspan="6">KODE BARANG</th>
              <th>SALDO AWAL <?= $f_tahun ?></th>
              <th>PENAMBAHAN <?= $f_tahun ?></th>
              <th>PENGURANGAN <?= $f_tahun ?></th>
              <th>SALDO AKHIR <?= $f_tahun ?></th>
              <th>KETERANGAN</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $no = 1;
            $t_awal = 0; $t_plus = 0; $t_minus = 0; $t_akhir = 0;
            $data->data_seek(0);
            while ($r = $data->fetch_assoc()): 
              $s_akhir = $r['saldo_awal'] + $r['penambahan'] - $r['pengurangan'];
              $t_awal += $r['saldo_awal']; $t_plus += $r['penambahan']; $t_minus += $r['pengurangan']; $t_akhir += $s_akhir;
              
              $kode_raw = $r['kode_jenis'];
              if (strpos($kode_raw, '.') !== false) {
                  $kode_parts = explode('.', $kode_raw);
              } elseif (strlen($kode_raw) == 9) {
                  $kode_parts = [
                      substr($kode_raw, 0, 1),
                      substr($kode_raw, 1, 1),
                      substr($kode_raw, 2, 1),
                      substr($kode_raw, 3, 2),
                      substr($kode_raw, 5, 2),
                      substr($kode_raw, 7, 2)
                  ];
              } else {
                  $kode_parts = [$kode_raw];
              }
              $kode_parts = array_pad($kode_parts, 6, '');
            ?>
              <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= htmlspecialchars($r['nama_jenis']) ?></td>
                <?php foreach ($kode_parts as $kp): ?>
                  <td class="text-center"><code class="text-dark"><?= $kp ?></code></td>
                <?php endforeach; ?>
                <td class="text-end"><?= number_format($r['saldo_awal'], 0, ',', '.') ?></td>
                <td class="text-end"><?= number_format($r['penambahan'], 0, ',', '.') ?></td>
                <td class="text-end"><?= number_format($r['pengurangan'], 0, ',', '.') ?></td>
                <td class="text-end fw-bold"><?= number_format($s_akhir, 0, ',', '.') ?></td>
                <td><small><?= htmlspecialchars($r['keterangan'] ?? '') ?></small></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
          <tfoot class="table-secondary fw-bold">
            <tr>
              <td colspan="8" class="text-center">JUMLAH</td>
              <td class="text-end"><?= number_format($t_awal, 0, ',', '.') ?></td>
              <td class="text-end"><?= number_format($t_plus, 0, ',', '.') ?></td>
              <td class="text-end"><?= number_format($t_minus, 0, ',', '.') ?></td>
              <td class="text-end fw-bold"><?= number_format($t_akhir, 0, ',', '.') ?></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>