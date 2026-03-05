<?php

/**
 * Laporan Detail Stock Opname per Jenis Barang
 * Membandingkan Stok Administrasi vs Stok Opname
 * Sesuai Image 3 & 4
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$id_jenis = (int)($_GET['id_jenis'] ?? 0);
$f_tahun  = (int)($_GET['tahun'] ?? date('Y'));
$f_bagian = (int)($_GET['id_bagian'] ?? 0);

$role = getUserRole();
$user_bagian = getUserBagian();
if ($role !== 'superadmin' && $f_bagian !== $user_bagian) {
  $f_bagian = $user_bagian;
}

$jenis = $id_jenis ? $conn->query("SELECT * FROM jenis_barang WHERE id=$id_jenis")->fetch_assoc() : null;
$bagian = $f_bagian ? $conn->query("SELECT * FROM bagian WHERE id=$f_bagian")->fetch_assoc() : null;

$where_bagian = $f_bagian ? " AND id_bagian=$f_bagian" : "";
$where_jenis = $id_jenis ? " AND b.id_jenis_barang = $id_jenis" : "";

// Query Detail: Mengambil semua barang, 
// lalu join dengan record stock opname terakhir di tahun tsb.
$query = "
    SELECT 
        b.id as id_barang,
        b.kode_barang,
        b.nama_barang,
        b.satuan,
        j.nama_jenis,
        j.kode_jenis,
        COALESCE(so.stok_sistem, (
            SELECT COALESCE(SUM(p.jumlah), 0) FROM penerimaan p WHERE p.id_barang = b.id AND p.status = 'disetujui' AND YEAR(p.tanggal) <= $f_tahun " . ($f_bagian ? " AND p.id_bagian=$f_bagian" : "") . "
        ) - (
            SELECT COALESCE(SUM(pg.jumlah), 0) FROM pengurangan pg WHERE pg.id_barang = b.id AND pg.status = 'disetujui' AND YEAR(pg.tanggal) <= $f_tahun " . ($f_bagian ? " AND pg.id_bagian=$f_bagian" : "") . "
        )) as admin_qty,
        COALESCE(so.stok_fisik, 0) as opname_qty,
        COALESCE(so.selisih, 0) as selisih,
        COALESCE(so.keterangan, '-') as keterangan,
        COALESCE((SELECT harga_satuan FROM penerimaan WHERE id_barang = b.id AND status = 'disetujui' ORDER BY tanggal DESC, id DESC LIMIT 1), 0) as harga_satuan
    FROM barang b
    JOIN jenis_barang j ON b.id_jenis_barang = j.id
    LEFT JOIN (
        SELECT so1.* 
        FROM stock_opname so1
        WHERE so1.id IN (
            SELECT MAX(id) FROM stock_opname WHERE id_barang = so1.id_barang AND YEAR(tanggal) = $f_tahun $where_bagian AND status = 'disetujui' GROUP BY id_barang
        )
    ) so ON b.id = so.id_barang
    WHERE 1=1 $where_jenis
    ORDER BY j.kode_jenis ASC, b.nama_barang ASC
";

$data = $conn->query($query);

// Helper untuk grouping
$items = [];
while ($r = $data->fetch_assoc()) {
    $items[$r['nama_jenis']][] = $r;
}

// Export Excel
if (isset($_GET['export'])) {
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  $filename = "detail_stock_opname_" . ($jenis ? strtolower(str_replace(' ', '_', $jenis['nama_jenis'])) : 'all') . "_" . $f_tahun . ".xls";
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  echo "\xEF\xBB\xBF";
?>
  <table border="1">
    <thead>
      <tr><th colspan="13" style="text-align:center; font-weight:bold;">DAFTAR HASIL PERHITUNGAN FISIK ATAS BARANG PERSEDIAAN/STOCK OPNAME</th></tr>
      <tr><th colspan="13" style="text-align:center; font-weight:bold;">DI LINGKUNGAN PEMERINTAH KABUPATEN BANTUL</th></tr>
      <tr><td colspan="13"></td></tr>
      <tr>
        <td colspan="2">OPD</td><td>: <?= $bagian ? htmlspecialchars($bagian['nama']) : 'Semua Bagian' ?></td>
        <td colspan="10"></td>
      </tr>
      <tr>
        <td colspan="2">PER TANGGAL</td><td>: 31 Desember <?= $f_tahun ?></td>
        <td colspan="10"></td>
      </tr>
      <tr><td colspan="13"></td></tr>
      <tr>
        <th rowspan="3">NO</th>
        <th rowspan="3">NAMA BARANG</th>
        <th rowspan="3">KODE BARANG</th>
        <th colspan="10" style="text-align:center;">JUMLAH PERSEDIAAN PER TANGGAL PERHITUNGAN</th>
        <th rowspan="3">KETERANGAN</th>
      </tr>
      <tr>
        <th colspan="4" style="text-align:center;">MENURUT ADMINISTRASI</th>
        <th colspan="4" style="text-align:center;">MENURUT OPNAME</th>
        <th colspan="2" style="text-align:center;">SELISIH</th>
      </tr>
      <tr>
        <th>JUMLAH</th><th>SATUAN</th><th>HARGA (Rp)</th><th>JUMLAH (Rp)</th>
        <th>JUMLAH</th><th>SATUAN</th><th>HARGA (Rp)</th><th>JUMLAH (Rp)</th>
        <th>JUMLAH</th><th>JUMLAH (Rp)</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      $no = 1;
      $t_admin_rp = 0; $t_opname_rp = 0; $t_selisih_rp = 0;
      foreach ($items as $nama_jenis => $rows):
      ?>
        <tr style="font-weight:bold; background-color:#f5f5f5;">
            <td></td>
            <td colspan="13"><?= strtoupper(htmlspecialchars($nama_jenis)) ?></td>
        </tr>
        <?php foreach ($rows as $r): 
            $admin_rp = $r['admin_qty'] * $r['harga_satuan'];
            $opname_rp = $r['opname_qty'] * $r['harga_satuan'];
            $selisih_rp = $opname_rp - $admin_rp;
            $t_admin_rp += $admin_rp; $t_opname_rp += $opname_rp; $t_selisih_rp += $selisih_rp;
        ?>
            <tr>
              <td align="center"><?= $no++ ?></td>
              <td><?= htmlspecialchars($r['nama_barang']) ?></td>
              <td align="center" style="mso-number-format:'\@';"><?= htmlspecialchars($r['kode_barang']) ?></td>
              <td align="center"><?= $r['admin_qty'] ?></td>
              <td align="center"><?= htmlspecialchars($r['satuan']) ?></td>
              <td align="right"><?= number_format($r['harga_satuan'], 2, ',', '.') ?></td>
              <td align="right"><?= number_format($admin_rp, 2, ',', '.') ?></td>
              <td align="center"><?= $r['opname_qty'] ?></td>
              <td align="center"><?= htmlspecialchars($r['satuan']) ?></td>
              <td align="right"><?= number_format($r['harga_satuan'], 2, ',', '.') ?></td>
              <td align="right"><?= number_format($opname_rp, 2, ',', '.') ?></td>
              <td align="center"><?= $r['opname_qty'] - $r['admin_qty'] ?></td>
              <td align="right"><?= number_format($selisih_rp, 2, ',', '.') ?></td>
              <td><?= htmlspecialchars($r['keterangan']) ?></td>
            </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:bold; background-color:#eee;">
        <td colspan="6" align="center">JUMLAH</td>
        <td align="right"><?= number_format($t_admin_rp, 2, ',', '.') ?></td>
        <td colspan="3"></td>
        <td align="right"><?= number_format($t_opname_rp, 2, ',', '.') ?></td>
        <td></td>
        <td align="right"><?= number_format($t_selisih_rp, 2, ',', '.') ?></td>
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
    <div class="topbar-title">
        <a href="stock_opname.php?tahun=<?= $f_tahun ?>&id_bagian=<?= $f_bagian ?>" class="text-white text-decoration-none"><i class="bi bi-arrow-left me-2"></i></a>
        Detail Stock Opname <?= $jenis ? ': ' . htmlspecialchars($jenis['nama_jenis']) : '' ?>
    </div>
  </div>
  <div class="page-content">
    <div class="card mb-3">
        <div class="card-body py-2 d-flex justify-content-between align-items-center">
            <div>
                <span class="badge bg-primary">Tahun: <?= $f_tahun ?></span>
                <span class="badge bg-secondary">Bagian: <?= $bagian ? htmlspecialchars($bagian['nama']) : 'Semua' ?></span>
            </div>
            <div>
                <a href="stock_opname.php?tahun=<?= $f_tahun ?>&id_bagian=<?= $f_bagian ?>" class="btn btn-secondary btn-sm me-1">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
                <a href="?<?= http_build_query($_GET) ?>&export=1" class="btn btn-success btn-sm">
                    <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
                </a>
            </div>
        </div>
    </div>

    <div class="card">
      <div class="card-header text-center py-3">
        <div class="fw-bold" style="font-size:1.1rem">DAFTAR HASIL PERHITUNGAN FISIK ATAS BARANG PERSEDIAAN/STOCK OPNAME</div>
        <div class="fw-bold"><?= $jenis ? strtoupper(htmlspecialchars($jenis['nama_jenis'])) : 'SELURUH BARANG' ?></div>
        <div class="text-muted mt-1" style="font-size:.85rem">PER TANGGAL 31 DESEMBER <?= $f_tahun ?></div>
      </div>
      <div class="table-responsive p-3">
        <table class="table table-bordered table-sm align-middle" style="font-size:.75rem">
          <thead class="table-primary text-center align-middle">
            <tr>
              <th rowspan="3">NO</th>
              <th rowspan="3">NAMA BARANG</th>
              <th rowspan="3">KODE BARANG</th>
              <th colspan="10">JUMLAH PERSEDIAAN PER TANGGAL PERHITUNGAN</th>
              <th rowspan="3">KETERANGAN</th>
            </tr>
            <tr>
              <th colspan="4" class="bg-info bg-opacity-10">MENURUT ADMINISTRASI</th>
              <th colspan="4" class="bg-success bg-opacity-10">MENURUT OPNAME</th>
              <th colspan="2" class="bg-warning bg-opacity-10">SELISIH</th>
            </tr>
            <tr style="font-size:.7rem">
              <th>JUMLAH</th><th>SATUAN</th><th>HARGA</th><th>TOTAL</th>
              <th>JUMLAH</th><th>SATUAN</th><th>HARGA</th><th>TOTAL</th>
              <th>QTY</th><th>TOTAL</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $no = 1;
            $t_admin_rp = 0; $t_opname_rp = 0; $t_selisih_rp = 0;
            foreach ($items as $nama_jenis => $rows):
            ?>
              <tr class="table-secondary fw-bold">
                  <td></td>
                  <td colspan="13"><?= strtoupper(htmlspecialchars($nama_jenis)) ?></td>
              </tr>
              <?php foreach ($rows as $r): 
                  $admin_rp = $r['admin_qty'] * $r['harga_satuan'];
                  $opname_rp = $r['opname_qty'] * $r['harga_satuan'];
                  $selisih_rp = $opname_rp - $admin_rp;
                  $t_admin_rp += $admin_rp; $t_opname_rp += $opname_rp; $t_selisih_rp += $selisih_rp;
              ?>
                <tr>
                  <td class="text-center"><?= $no++ ?></td>
                  <td><?= htmlspecialchars($r['nama_barang']) ?></td>
                  <td class="text-center"><code><?= htmlspecialchars($r['kode_barang']) ?></code></td>
                  <?php /* Administrasi */ ?>
                  <td class="text-center"><?= number_format($r['admin_qty'], 0, ',', '.') ?></td>
                  <td class="text-center"><?= htmlspecialchars($r['satuan']) ?></td>
                  <td class="text-end"><?= number_format($r['harga_satuan'], 0, ',', '.') ?></td>
                  <td class="text-end fw-semibold"><?= number_format($admin_rp, 0, ',', '.') ?></td>
                  <?php /* Opname */ ?>
                  <td class="text-center"><?= number_format($r['opname_qty'], 0, ',', '.') ?></td>
                  <td class="text-center"><?= htmlspecialchars($r['satuan']) ?></td>
                  <td class="text-end"><?= number_format($r['harga_satuan'], 0, ',', '.') ?></td>
                  <td class="text-end fw-semibold"><?= number_format($opname_rp, 0, ',', '.') ?></td>
                  <?php /* Selisih */ ?>
                  <td class="text-center <?= ($r['opname_qty'] - $r['admin_qty']) != 0 ? 'text-danger fw-bold' : '' ?>">
                      <?= number_format($r['opname_qty'] - $r['admin_qty'], 0, ',', '.') ?>
                  </td>
                  <td class="text-end <?= $selisih_rp != 0 ? 'text-danger fw-bold' : '' ?>">
                      <?= number_format($selisih_rp, 0, ',', '.') ?>
                  </td>
                  <td><?= htmlspecialchars($r['keterangan']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-secondary fw-bold">
            <tr>
              <td colspan="6" class="text-end">JUMLAH</td>
              <td class="text-end"><?= number_format($t_admin_rp, 0, ',', '.') ?></td>
              <td colspan="3"></td>
              <td class="text-end"><?= number_format($t_opname_rp, 0, ',', '.') ?></td>
              <td></td>
              <td class="text-end <?= $t_selisih_rp != 0 ? 'text-danger' : '' ?>"><?= number_format($t_selisih_rp, 0, ',', '.') ?></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
