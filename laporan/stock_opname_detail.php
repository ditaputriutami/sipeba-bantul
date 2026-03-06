<?php

/**
 * Laporan Detail Stock Opname per Jenis Barang
 * Membandingkan Stok Administrasi vs Stok Opname
 * Sesuai Image 3 & 4
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

// Helper fungsi untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal)
{
  $bulan = [
    1 => 'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember'
  ];
  $split = explode('-', date('Y-m-d', strtotime($tanggal)));
  return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

$id_jenis = (int)($_GET['id_jenis'] ?? 0);
$f_bagian = (int)($_GET['id_bagian'] ?? 0);
$f_dari_tanggal = $_GET['dari_tanggal'] ?? date('Y') . "-01-01";
$f_sampai_tanggal = $_GET['sampai_tanggal'] ?? date('Y') . "-12-31";

$role = getUserRole();
$user_bagian = getUserBagian();
if ($role !== 'superadmin' && $f_bagian !== $user_bagian) {
  $f_bagian = $user_bagian;
}

$jenis = $id_jenis ? $conn->query("SELECT * FROM jenis_barang WHERE id=$id_jenis")->fetch_assoc() : null;
$bagian = $f_bagian ? $conn->query("SELECT * FROM bagian WHERE id=$f_bagian")->fetch_assoc() : null;

$where_bagian = $f_bagian ? " AND so.id_bagian=$f_bagian" : "";
$where_jenis = $id_jenis ? " AND b.id_jenis_barang = $id_jenis" : "";
$where_tanggal = " AND so.tanggal BETWEEN '$f_dari_tanggal' AND '$f_sampai_tanggal'";

// Query Detail: Mengambil SEMUA transaksi stock opname (per transaksi, tidak digabung)
$query = "
    SELECT 
        b.id as id_barang,
        b.kode_barang,
        b.nama_barang,
        b.satuan,
        j.nama_jenis,
        j.kode_jenis,
        so.stok_sistem as admin_qty,
        so.stok_fisik as opname_qty,
        so.selisih as selisih,
        COALESCE(so.keterangan, '-') as keterangan,
        COALESCE((SELECT harga_satuan FROM penerimaan WHERE id_barang = b.id AND status = 'disetujui' ORDER BY tanggal DESC, id DESC LIMIT 1), 0) as harga_satuan,
        so.tanggal as tanggal_opname
    FROM stock_opname so
    JOIN barang b ON so.id_barang = b.id
    JOIN jenis_barang j ON b.id_jenis_barang = j.id
    WHERE so.status = 'disetujui'
        $where_tanggal
        $where_bagian
        $where_jenis
    ORDER BY j.kode_jenis ASC, b.nama_barang ASC, so.tanggal ASC, so.id ASC
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
  $filename = "detail_stock_opname_" . ($jenis ? strtolower(str_replace(' ', '_', $jenis['nama_jenis'])) : 'all') . "_" . date('Ymd', strtotime($f_dari_tanggal)) . "_" . date('Ymd', strtotime($f_sampai_tanggal)) . ".xls";
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  echo "\xEF\xBB\xBF";
?>
  <table border="1" style="border-collapse: collapse; table-layout: fixed; width: 2200px;">
    <col width="40">
    <col width="220">
    <col width="24">
    <col width="24">
    <col width="24">
    <col width="24">
    <col width="24">
    <col width="24">
    <col width="24">
    <col width="90">
    <col width="80">
    <col width="120">
    <col width="160">
    <col width="90">
    <col width="80">
    <col width="120">
    <col width="160">
    <col width="90">
    <col width="160">
    <col width="220">
    <thead>
      <tr>
        <th colspan="20" style="text-align:center; font-weight:bold;">DAFTAR HASIL PERHITUNGAN FISIK ATAS BARANG PERSEDIAAN/STOCK OPNAME</th>
      </tr>
      <tr>
        <th colspan="20" style="text-align:center; font-weight:bold;">DI LINGKUNGAN PEMERINTAH KABUPATEN BANTUL</th>
      </tr>
      <tr>
        <td colspan="20"></td>
      </tr>
      <tr>
        <th colspan="20" style="text-align:left; font-weight:bold;">OPD : <?= $bagian ? strtoupper(htmlspecialchars($bagian['nama'])) : 'SEMUA BAGIAN' ?></th>
      </tr>
      <tr>
        <th colspan="20" style="text-align:left; font-weight:bold;">PERIODE : <?= strtoupper(formatTanggalIndonesia($f_dari_tanggal)) ?> - <?= strtoupper(formatTanggalIndonesia($f_sampai_tanggal)) ?></th>
      </tr>
      <tr>
        <td colspan="20"></td>
      </tr>
      <tr style="background-color:#eee; font-weight:bold;">
        <th rowspan="4" style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">NO</th>
        <th rowspan="4" style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">NAMA BARANG</th>
        <th colspan="7" rowspan="4" style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">KODE BARANG</th>
        <th colspan="10" style="text-align:center; border:1px solid #000; white-space:nowrap; vertical-align:middle;">JUMLAH PERSEDIAAN PER TANGGAL PERHITUNGAN</th>
        <th rowspan="4" style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">KETERANGAN</th>
      </tr>
      <tr style="background-color:#eee; font-weight:bold;">
        <th colspan="4" style="text-align:center; border:1px solid #000">MENURUT ADMINISTRASI</th>
        <th colspan="4" style="text-align:center; border:1px solid #000">MENURUT OPNAME</th>
        <th colspan="2" style="text-align:center; border:1px solid #000">SELISIH</th>
      </tr>
      <tr style="background-color:#eee; font-weight:bold;">
        <th colspan="2" style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">BARANG</th>
        <th colspan="2" style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">HARGA</th>
        <th colspan="2" style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">BARANG</th>
        <th colspan="2" style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">HARGA</th>
        <th rowspan="2" style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">JUMLAH</th>
        <th rowspan="2" style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">TOTAL (Rp)</th>
      </tr>
      <tr style="font-size:.75rem; background-color:#eee; font-weight:bold;">
        <th style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">JUMLAH</th>
        <th style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">SATUAN</th>
        <th style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">SATUAN (Rp)</th>
        <th style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">TOTAL (Rp)</th>
        <th style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">JUMLAH</th>
        <th style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">SATUAN</th>
        <th style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">SATUAN (Rp)</th>
        <th style="border:1px solid #000; white-space:nowrap; vertical-align:middle;">TOTAL (Rp)</th>
      </tr>
      <tr style="font-size:.7rem; background-color:#f5f5f5; font-weight:bold; text-align:center;">
        <th style="border:1px solid #000;">1</th>
        <th style="border:1px solid #000;">2</th>
        <th colspan="7" style="border:1px solid #000;">3</th>
        <th style="border:1px solid #000;">4</th>
        <th style="border:1px solid #000;">5</th>
        <th style="border:1px solid #000;">6</th>
        <th style="border:1px solid #000;">7 (4 x 6)</th>
        <th style="border:1px solid #000;">8</th>
        <th style="border:1px solid #000;">9</th>
        <th style="border:1px solid #000;">10</th>
        <th style="border:1px solid #000;">11 (8 x 10)</th>
        <th style="border:1px solid #000;">12</th>
        <th style="border:1px solid #000;">13</th>
        <th style="border:1px solid #000;">14</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $no = 1;
      $t_admin_rp = 0;
      $t_opname_rp = 0;
      $t_selisih_rp = 0;
      foreach ($items as $nama_jenis => $rows):
        $kj_raw = $rows[0]['kode_jenis'];
        if (strpos($kj_raw, '.') !== false) {
          $kj_parts = explode('.', $kj_raw);
        } elseif (strlen($kj_raw) == 9) {
          $kj_parts = [substr($kj_raw, 0, 1), substr($kj_raw, 1, 1), substr($kj_raw, 2, 1), substr($kj_raw, 3, 2), substr($kj_raw, 5, 2), substr($kj_raw, 7, 2)];
        } else {
          $kj_parts = [$kj_raw];
        }
        // Cap at 7 segments
        if (count($kj_parts) > 7) {
          $rem = array_slice($kj_parts, 6);
          $kj_parts = array_slice($kj_parts, 0, 6);
          $kj_parts[] = implode('.', $rem);
        } else {
          $kj_parts = array_pad($kj_parts, 7, '');
        }
      ?>
        <tr style="font-weight:bold; background-color:#f5f5f5;">
          <td style="border:1px solid #000"></td>
          <td style="border:1px solid #000"><?= strtoupper(htmlspecialchars($nama_jenis)) ?></td>
          <?php foreach ($kj_parts as $kp): ?>
            <td align="center" style="border:1px solid #000; mso-number-format:'\@'; white-space:nowrap; vertical-align:middle;"><?= $kp ?></td>
          <?php endforeach; ?>
          <td style="border:1px solid #000"></td>
          <td colspan="11" style="border:1px solid #000"></td>
        </tr>
        <?php foreach ($rows as $r):
          $admin_rp = $r['admin_qty'] * $r['harga_satuan'];
          $opname_rp = $r['opname_qty'] * $r['harga_satuan'];
          $selisih_rp = $opname_rp - $admin_rp;
          $t_admin_rp += $admin_rp;
          $t_opname_rp += $opname_rp;
          $t_selisih_rp += $selisih_rp;

          $kode_raw = $r['kode_barang'];
          if (strpos($kode_raw, '.') !== false) {
            $kode_parts = explode('.', $kode_raw);
          } elseif (strlen($kode_raw) >= 11) {
            $kode_parts = [
              substr($kode_raw, 0, 1),
              substr($kode_raw, 1, 1),
              substr($kode_raw, 2, 1),
              substr($kode_raw, 3, 2),
              substr($kode_raw, 5, 2),
              substr($kode_raw, 7, 2),
              substr($kode_raw, 9)
            ];
          } else {
            $kode_parts = [$kode_raw];
          }
          // Cap at 7 segments
          if (count($kode_parts) > 7) {
            $rem = array_slice($kode_parts, 6);
            $kode_parts = array_slice($kode_parts, 0, 6);
            $kode_parts[] = implode('.', $rem);
          } else {
            $kode_parts = array_pad($kode_parts, 7, '');
          }
        ?>
          <tr>
            <td align="center" style="border:1px solid #000"><?= $no++ ?></td>
            <td style="border:1px solid #000"><?= htmlspecialchars($r['nama_barang']) ?></td>
            <?php foreach ($kode_parts as $kp): ?>
              <td align="center" style="border:1px solid #000; mso-number-format:'\@'; white-space:nowrap; vertical-align:middle;"><?= $kp ?></td>
            <?php endforeach; ?>
            <td align="center" style="border:1px solid #000"><?= $r['admin_qty'] ?></td>
            <td align="center" style="border:1px solid #000"><?= htmlspecialchars($r['satuan']) ?></td>
            <td align="right" style="border:1px solid #000; mso-number-format:'\#\,\#\#0\.00';"><?= $r['harga_satuan'] ?></td>
            <td align="right" style="border:1px solid #000; mso-number-format:'\#\,\#\#0\.00'; font-weight:bold;"><?= $admin_rp ?></td>
            <td align="center" style="border:1px solid #000"><?= $r['opname_qty'] ?></td>
            <td align="center" style="border:1px solid #000"><?= htmlspecialchars($r['satuan']) ?></td>
            <td align="right" style="border:1px solid #000; mso-number-format:'\#\,\#\#0\.00';"><?= $r['harga_satuan'] ?></td>
            <td align="right" style="border:1px solid #000; mso-number-format:'\#\,\#\#0\.00'; font-weight:bold;"><?= $opname_rp ?></td>
            <td align="center" style="border:1px solid #000"><?= $r['opname_qty'] - $r['admin_qty'] ?></td>
            <td align="right" style="border:1px solid #000; mso-number-format:'\#\,\#\#0\.00'; font-weight:bold;"><?= $selisih_rp ?></td>
            <td style="border:1px solid #000"><?= htmlspecialchars($r['keterangan']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:bold; background-color:#eee;">
        <td colspan="12" align="center" style="border:1px solid #000">JUMLAH</td>
        <td align="right" style="border:1px solid #000; mso-number-format:'\#\,\#\#0\.00';"><?= $t_admin_rp ?></td>
        <td colspan="3" style="border:1px solid #000"></td>
        <td align="right" style="border:1px solid #000; mso-number-format:'\#\,\#\#0\.00';"><?= $t_opname_rp ?></td>
        <td style="border:1px solid #000"></td>
        <td align="right" style="border:1px solid #000; mso-number-format:'\#\,\#\#0\.00';"><?= $t_selisih_rp ?></td>
        <td style="border:1px solid #000"></td>
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
      <a href="stock_opname.php?id_bagian=<?= $f_bagian ?>" class="text-white text-decoration-none"><i class="bi bi-arrow-left me-2"></i></a>
      Detail Stock Opname <?= $jenis ? ': ' . htmlspecialchars($jenis['nama_jenis']) : '' ?>
    </div>
  </div>
  <div class="page-content">
    <!-- Filter Tanggal -->
    <div class="card mb-3">
      <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
          <input type="hidden" name="id_jenis" value="<?= $id_jenis ?>">
          <input type="hidden" name="id_bagian" value="<?= $f_bagian ?>">
          <div class="col-md-3">
            <label class="form-label mb-1 small fw-semibold">Dari Tanggal</label>
            <input type="date" name="dari_tanggal" class="form-control form-control-sm" value="<?= $f_dari_tanggal ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small fw-semibold">Sampai Tanggal</label>
            <input type="date" name="sampai_tanggal" class="form-control form-control-sm" value="<?= $f_sampai_tanggal ?>" required>
          </div>
          <div class="col-md-6">
            <button type="submit" class="btn btn-primary btn-sm me-1">
              <i class="bi bi-funnel me-1"></i>Filter
            </button>
            <a href="?id_jenis=<?= $id_jenis ?>&id_bagian=<?= $f_bagian ?>" class="btn btn-secondary btn-sm">
              <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
            </a>
          </div>
        </form>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <div>
          <span class="badge bg-primary">Periode: <?= date('d/m/Y', strtotime($f_dari_tanggal)) ?> - <?= date('d/m/Y', strtotime($f_sampai_tanggal)) ?></span>
          <span class="badge bg-secondary">Bagian: <?= $bagian ? htmlspecialchars($bagian['nama']) : 'Semua' ?></span>
        </div>
        <div>
          <a href="stock_opname.php?id_bagian=<?= $f_bagian ?>" class="btn btn-secondary btn-sm me-1">
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
        <div class="text-muted mt-1" style="font-size:.85rem">PERIODE <?= strtoupper(formatTanggalIndonesia($f_dari_tanggal)) ?> - <?= strtoupper(formatTanggalIndonesia($f_sampai_tanggal)) ?></div>
      </div>
      <div class="table-responsive p-3">
        <table class="table table-bordered table-sm align-middle mb-0" style="font-size:.75rem;">
          <thead class="bg-light text-center align-middle">
            <tr class="text-dark">
              <th rowspan="4" style="min-width:40px">NO</th>
              <th rowspan="4" style="min-width:200px">NAMA BARANG</th>
              <th colspan="7" rowspan="4" class="align-middle">KODE BARANG</th>
              <th colspan="10">JUMLAH PERSEDIAAN PER TANGGAL PERHITUNGAN</th>
              <th rowspan="4" style="min-width:120px">KETERANGAN</th>
            </tr>
            <tr class="text-dark">
              <th colspan="4">MENURUT ADMINISTRASI</th>
              <th colspan="4">MENURUT OPNAME</th>
              <th colspan="2">SELISIH</th>
            </tr>
            <tr style="font-size:.7rem" class="fw-bold text-dark">
              <th colspan="2" class="bg-secondary bg-opacity-10">BARANG</th>
              <th colspan="2" class="bg-secondary bg-opacity-10">HARGA</th>
              <th colspan="2" class="bg-secondary bg-opacity-10">BARANG</th>
              <th colspan="2" class="bg-secondary bg-opacity-10">HARGA</th>
              <th rowspan="2" class="bg-secondary bg-opacity-10">QTY</th>
              <th rowspan="2" class="bg-secondary bg-opacity-10">TOTAL</th>
            </tr>
            <tr style="font-size:.7rem; background-color:#f8f9fa;" class="text-center fw-bold">
              <td class="px-1 text-nowrap">JUMLAH</td>
              <td class="px-1 text-nowrap">SATUAN</td>
              <td class="px-1 text-nowrap">SATUAN (Rp)</td>
              <td class="px-1 text-nowrap">TOTAL (Rp)</td>
              <td class="px-1 text-nowrap">JUMLAH</td>
              <td class="px-1 text-nowrap">SATUAN</td>
              <td class="px-1 text-nowrap">SATUAN (Rp)</td>
              <td class="px-1 text-nowrap">TOTAL (Rp)</td>
            </tr>
            <tr style="font-size:.65rem; background-color:#e9ecef;" class="text-center fw-bold text-muted">
              <td>1</td>
              <td>2</td>
              <td colspan="7">3</td>
              <td>4</td>
              <td>5</td>
              <td>6</td>
              <td>7</td>
              <td>8</td>
              <td>9</td>
              <td>10</td>
              <td>11</td>
              <td>12</td>
              <td>13</td>
              <td>14</td>
            </tr>
          </thead>
          <tbody>
            <?php
            $no = 1;
            $t_admin_rp = 0;
            $t_opname_rp = 0;
            $t_selisih_rp = 0;
            foreach ($items as $nama_jenis => $rows):
            ?>
              <?php
              $kj_raw = $rows[0]['kode_jenis'];
              if (strpos($kj_raw, '.') !== false) {
                $kj_parts = explode('.', $kj_raw);
              } elseif (strlen($kj_raw) == 9) {
                $kj_parts = [substr($kj_raw, 0, 1), substr($kj_raw, 1, 1), substr($kj_raw, 2, 1), substr($kj_raw, 3, 2), substr($kj_raw, 5, 2), substr($kj_raw, 7, 2)];
              } else {
                $kj_parts = [$kj_raw];
              }
              // Cap at 7 segments
              if (count($kj_parts) > 7) {
                $rem = array_slice($kj_parts, 6);
                $kj_parts = array_slice($kj_parts, 0, 6);
                $kj_parts[] = implode('.', $rem);
              } else {
                $kj_parts = array_pad($kj_parts, 7, '');
              }
              ?>
              <tr class="table-secondary fw-bold text-dark">
                <td></td>
                <td><?= strtoupper(htmlspecialchars($nama_jenis)) ?></td>
                <?php foreach ($kj_parts as $kp): ?>
                  <td class="text-center text-nowrap" style="min-width:30px; padding: 0 0.1rem;"><?= $kp ?></td>
                <?php endforeach; ?>
                <td></td>
                <td colspan="11"></td>
              </tr>
              <?php foreach ($rows as $r):
                $admin_rp = $r['admin_qty'] * $r['harga_satuan'];
                $opname_rp = $r['opname_qty'] * $r['harga_satuan'];
                $selisih_rp = $opname_rp - $admin_rp;
                $t_admin_rp += $admin_rp;
                $t_opname_rp += $opname_rp;
                $t_selisih_rp += $selisih_rp;

                $kode_raw = $r['kode_barang'];
                if (strpos($kode_raw, '.') !== false) {
                  $kode_parts = explode('.', $kode_raw);
                } elseif (strlen($kode_raw) >= 11) {
                  $kode_parts = [
                    substr($kode_raw, 0, 1),
                    substr($kode_raw, 1, 1),
                    substr($kode_raw, 2, 1),
                    substr($kode_raw, 3, 2),
                    substr($kode_raw, 5, 2),
                    substr($kode_raw, 7, 2),
                    substr($kode_raw, 9)
                  ];
                } else {
                  $kode_parts = [$kode_raw];
                }
                // Cap at 7 segments
                if (count($kode_parts) > 7) {
                  $rem = array_slice($kode_parts, 6);
                  $kode_parts = array_slice($kode_parts, 0, 6);
                  $kode_parts[] = implode('.', $rem);
                } else {
                  $kode_parts = array_pad($kode_parts, 7, '');
                }
              ?>
                <tr class="text-dark">
                  <td class="text-center"><?= $no++ ?></td>
                  <td><?= htmlspecialchars($r['nama_barang']) ?></td>
                  <?php foreach ($kode_parts as $kp): ?>
                    <td class="text-center text-nowrap" style="min-width:30px; padding: 0 0.1rem;"><?= $kp ?></td>
                  <?php endforeach; ?>
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
          <tfoot class="table-secondary fw-bold text-dark">
            <tr>
              <td colspan="12" class="text-end">JUMLAH</td>
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