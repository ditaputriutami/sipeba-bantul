<?php

/**
 * Laporan Daftar Hasil Stock Opname (Summary per Jenis Barang)
 * Format: Saldo Awal, Penambahan, Pengurangan, Saldo Akhir
 * Sesuai Image 1 & 2
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

// Helper function untuk format tanggal Indonesia
function formatTanggalIndo($tanggal)
{
  $bulan_indo = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
  ];
  $ts = strtotime($tanggal);
  return date('d', $ts) . ' ' . $bulan_indo[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

$pageTitle = 'Hasil Stock Opname';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

$f_bagian  = ($role === 'superadmin') ? (int)($_GET['id_bagian'] ?? 0) : $id_bagian;
// Sekretariat Daerah (id=9) can see all departments
if ($id_bagian == 9) $f_bagian = 0;
$f_tahun   = (int)($_GET['tahun'] ?? date('Y'));
$f_dari_tanggal = $_GET['dari_tanggal'] ?? '';
$f_sampai_tanggal = $_GET['sampai_tanggal'] ?? '';

// Tentukan mode filter: jika tanggal diisi, gunakan filter tanggal; jika tidak, gunakan filter tahun
$use_date_filter = !empty($f_dari_tanggal) && !empty($f_sampai_tanggal);

$where_bagian = "";
if ($f_bagian) {
  $where_bagian = " AND id_bagian=$f_bagian";
}

// Ambil tahun-tahun yang ada datanya di database (dari penerimaan, pengurangan, dan stock_opname)
$years_query = "
    SELECT DISTINCT tahun FROM (
        SELECT YEAR(tanggal) as tahun FROM penerimaan WHERE status = 'disetujui' $where_bagian
        UNION
        SELECT YEAR(tanggal) as tahun FROM pengurangan WHERE status = 'disetujui' $where_bagian
        UNION
        SELECT YEAR(tanggal) as tahun FROM stock_opname WHERE status = 'disetujui' $where_bagian
    ) AS all_years
    WHERE tahun IS NOT NULL
    ORDER BY tahun DESC
";
$years_result = $conn->query($years_query);
$years = [];
while ($y = $years_result->fetch_assoc()) {
  $years[] = $y['tahun'];
}
// Jika tidak ada data sama sekali, gunakan tahun sekarang sebagai default
if (empty($years)) {
  $years = [date('Y')];
}

// Validasi $f_tahun: jika tidak ada di list, gunakan tahun pertama dari array
if (!in_array($f_tahun, $years)) {
  $f_tahun = $years[0];
}

// Query untuk mendapatkan ringkasan per jenis barang
// Kita hitung Saldo Awal (sebelum periode terpilih), Penambahan, Pengurangan, dan Saldo Akhir
if ($use_date_filter) {
  // Filter berdasarkan range tanggal
  $query = "
        SELECT 
            j.id as id_jenis,
            j.kode_jenis,
            j.nama_jenis,
            -- Saldo Awal (Transaksi sebelum dari_tanggal)
            (
                SELECT COALESCE(SUM(p.jumlah * p.harga_satuan), 0)
                FROM penerimaan p
                JOIN barang b2 ON p.id_barang = b2.id
                WHERE b2.id_jenis_barang = j.id AND p.status = 'disetujui' AND p.tanggal < '$f_dari_tanggal' $where_bagian
            ) - (
                SELECT COALESCE(SUM(pd.jumlah_dipotong * pd.harga_satuan), 0)
                FROM pengurangan_detail pd
                JOIN pengurangan pr ON pd.id_pengurangan = pr.id
                JOIN barang b2 ON pr.id_barang = b2.id
                WHERE b2.id_jenis_barang = j.id AND pd.status = 'disetujui' AND pr.tanggal < '$f_dari_tanggal' $where_bagian
            ) as saldo_awal,
            -- Penambahan (Dalam range tanggal)
            (
                SELECT COALESCE(SUM(p.jumlah * p.harga_satuan), 0)
                FROM penerimaan p
                JOIN barang b2 ON p.id_barang = b2.id
                WHERE b2.id_jenis_barang = j.id AND p.status = 'disetujui' AND p.tanggal BETWEEN '$f_dari_tanggal' AND '$f_sampai_tanggal' $where_bagian
            ) as penambahan,
            -- Pengurangan (Dalam range tanggal)
            (
                SELECT COALESCE(SUM(pd.jumlah_dipotong * pd.harga_satuan), 0)
                FROM pengurangan_detail pd
                JOIN pengurangan pr ON pd.id_pengurangan = pr.id
                JOIN barang b2 ON pr.id_barang = b2.id
                WHERE b2.id_jenis_barang = j.id AND pd.status = 'disetujui' AND pr.tanggal BETWEEN '$f_dari_tanggal' AND '$f_sampai_tanggal' $where_bagian
            ) as pengurangan,
            -- Keterangan (Ambil dari Stock Opname dalam range tanggal)
            (
              SELECT GROUP_CONCAT(DISTINCT p.keterangan SEPARATOR '; ')
              FROM penerimaan p
              JOIN barang b2 ON p.id_barang = b2.id
              WHERE b2.id_jenis_barang = j.id AND p.status = 'disetujui' AND p.tanggal BETWEEN '$f_dari_tanggal' AND '$f_sampai_tanggal' $where_bagian AND COALESCE(p.keterangan, '') != ''
            ) as sumber_dana
        FROM jenis_barang j
        ORDER BY j.kode_jenis ASC
    ";
} else {
  // Filter berdasarkan tahun
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
                WHERE b2.id_jenis_barang = j.id AND pd.status = 'disetujui' AND YEAR(pr.tanggal) < $f_tahun $where_bagian
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
                WHERE b2.id_jenis_barang = j.id AND pd.status = 'disetujui' AND YEAR(pr.tanggal) = $f_tahun $where_bagian
            ) as pengurangan,
            -- Keterangan (Ambil dari Stock Opname tahun filter)
            (
              SELECT GROUP_CONCAT(DISTINCT p.keterangan SEPARATOR '; ')
              FROM penerimaan p
              JOIN barang b2 ON p.id_barang = b2.id
              WHERE b2.id_jenis_barang = j.id AND p.status = 'disetujui' AND YEAR(p.tanggal) = $f_tahun $where_bagian AND COALESCE(p.keterangan, '') != ''
            ) as sumber_dana
        FROM jenis_barang j
        ORDER BY j.kode_jenis ASC
    ";
}

$data = $conn->query($query);

$bagianList = ($role === 'superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;

$nama_bagian_text = "";
if ($f_bagian) {
  if ($role === 'superadmin') {
    $bg_data = $conn->query("SELECT nama FROM bagian WHERE id=$f_bagian")->fetch_assoc();
    $nama_bagian_text = $bg_data['nama'] ?? "";
  } else {
    $nama_bagian_text = $user['nama_bagian'] ?? "";
  }
} elseif ($id_bagian == 9) {
  // Sekretariat Daerah viewing all departments
  $nama_bagian_text = 'Sekretariat Daerah';
}

// Tentukan periode untuk tampilan
$periode_text = $use_date_filter
  ? date('d/m/Y', strtotime($f_dari_tanggal)) . ' - ' . date('d/m/Y', strtotime($f_sampai_tanggal))
  : '31 Desember ' . $f_tahun;
$periode_text_upper = $use_date_filter
  ? strtoupper(formatTanggalIndo($f_dari_tanggal)) . ' - ' . strtoupper(formatTanggalIndo($f_sampai_tanggal))
  : '31 DESEMBER ' . $f_tahun;

// Export Excel
if (isset($_GET['export'])) {
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  $filename = $use_date_filter
    ? 'summary_stock_opname_' . date('Ymd', strtotime($f_dari_tanggal)) . '_' . date('Ymd', strtotime($f_sampai_tanggal)) . '.xls'
    : 'summary_stock_opname_' . $f_tahun . '.xls';
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  echo "\xEF\xBB\xBF";
?>
  <table border="1">
    <thead>
      <tr>
        <th colspan="13" style="text-align:center; font-weight:bold; font-size:12pt; padding:8px;">DAFTAR HASIL PERHITUNGAN FISIK ATAS BARANG PERSEDIAAN/STOCK OPNAME</th>
      </tr>
      <tr>
        <th colspan="13" style="text-align:center; font-weight:bold; font-size:11pt; padding:5px;"><?= $nama_bagian_text ? strtoupper($nama_bagian_text) : 'SEKRETARIAT DAERAH KABUPATEN BANTUL' ?></th>
      </tr>
      <tr>
        <th colspan="13" style="text-align:left; font-weight:bold;">PER TANGGAL <?= $periode_text_upper ?></th>
      </tr>
      <tr>
        <th colspan="13"></th>
      </tr>
      <tr>
        <th>NO</th>
        <th>NAMA BARANG</th>
        <th colspan="6">KODE BARANG</th>
        <th>SALDO AWAL<?= $use_date_filter ? '' : ' ' . $f_tahun ?></th>
        <th>PENAMBAHAN<?= $use_date_filter ? '' : ' ' . $f_tahun ?></th>
        <th>PENGURANGAN<?= $use_date_filter ? '' : ' ' . $f_tahun ?></th>
        <th>SALDO AKHIR<?= $use_date_filter ? '' : ' ' . $f_tahun ?></th>
        <th>SUMBER DANA</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $no = 1;
      $t_awal = 0;
      $t_plus = 0;
      $t_minus = 0;
      $t_akhir = 0;
      while ($r = $data->fetch_assoc()):
        $s_akhir = $r['saldo_awal'] + $r['penambahan'] - $r['pengurangan'];
        $t_awal += $r['saldo_awal'];
        $t_plus += $r['penambahan'];
        $t_minus += $r['pengurangan'];
        $t_akhir += $s_akhir;

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
          <td><?= htmlspecialchars($r['sumber_dana'] ?? '') ?></td>
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
    <div class="topbar-title"><i class="bi bi-clipboard-data me-2"></i>Stock Opname</div>
  </div>
  <div class="page-content">
    <?php if ($use_date_filter): ?>
      <div class="alert alert-info py-2 mb-2">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Filter Tanggal Aktif:</strong> <?= date('d/m/Y', strtotime($f_dari_tanggal)) ?> - <?= date('d/m/Y', strtotime($f_sampai_tanggal)) ?>
        <a href="?<?= http_build_query(array_filter(['id_bagian' => $f_bagian, 'tahun' => $f_tahun])) ?>" class="btn btn-sm btn-outline-secondary ms-2"><i class="bi bi-x-circle me-1"></i>Gunakan Filter Tahun</a>
      </div>
    <?php else: ?>
      <div class="alert alert-secondary py-2 mb-2">
        <i class="bi bi-calendar-check me-1"></i>
        <strong>Filter Tahun Aktif:</strong> <?= $f_tahun ?>
      </div>
    <?php endif; ?>
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
          <?php if ($role === 'superadmin'): ?>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Bagian</label>
              <select name="id_bagian" class="form-select form-select-sm">
                <option value="">Sekretariat Daerah</option>
                <?php while ($bg = $bagianList->fetch_assoc()): ?>
                  <?php if ($bg['id'] == 9) continue; ?>
                  <option value="<?= $bg['id'] ?>" <?= $f_bagian == $bg['id'] ? 'selected' : '' ?>><?= htmlspecialchars($bg['nama']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          <?php else: ?>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Bagian</label>
              <div class="form-control form-control-sm" style="background-color: #e9ecef; border: 1px solid #ced4da;">
                <strong><?= htmlspecialchars($id_bagian == 9 ? 'Sekretariat Daerah' : (isset($user['nama_bagian']) ? $user['nama_bagian'] : '')) ?></strong>
              </div>
            </div>
          <?php endif; ?>
          <div class="col-auto">
            <label class="form-label fw-semibold">Tahun</label>
            <select name="tahun" class="form-select form-select-sm" id="filterTahun">
              <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $f_tahun == $y ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-auto">
            <label class="form-label fw-semibold">Dari Tanggal</label>
            <input type="date" name="dari_tanggal" class="form-control form-control-sm" value="<?= $f_dari_tanggal ?>">
          </div>
          <div class="col-auto">
            <label class="form-label fw-semibold">Sampai Tanggal</label>
            <input type="date" name="sampai_tanggal" class="form-control form-control-sm" value="<?= $f_sampai_tanggal ?>">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
            <a href="stock_opname_detail.php?<?= http_build_query(array_filter(['id_bagian' => $f_bagian, 'dari_tanggal' => $f_dari_tanggal, 'sampai_tanggal' => $f_sampai_tanggal])) ?>" class="btn btn-info btn-sm ms-1"><i class="bi bi-list-ul me-1"></i>Lihat Detail</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 1])) ?>" class="btn btn-success btn-sm ms-1"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
          </div>
        </form>
      </div>
    </div>
    <div class="card">
      <div class="card-header text-center py-3">
        <div class="fw-bold" style="font-size:1.1rem">DAFTAR HASIL PERHITUNGAN FISIK ATAS BARANG PERSEDIAAN/STOCK OPNAME</div>
        <div class="fw-bold"><?= $nama_bagian_text ? strtoupper($nama_bagian_text) : 'SEKRETARIAT DAERAH KABUPATEN BANTUL' ?></div>
        <div class="text-muted mt-1" style="font-size:.85rem">PER TANGGAL <?= strtoupper($periode_text_upper) ?></div>
      </div>
      <div class="table-wrapper p-3 overflow-auto">
        <table class="table table-bordered table-sm align-middle" style="font-size:.82rem">
          <thead>
            <tr class="table-info text-center">
              <th>NO</th>
              <th>NAMA BARANG (KATEGORI)</th>
              <th colspan="6">KODE BARANG</th>
              <th>SALDO AWAL<?= $use_date_filter ? '' : ' ' . $f_tahun ?></th>
              <th>PENAMBAHAN<?= $use_date_filter ? '' : ' ' . $f_tahun ?></th>
              <th>PENGURANGAN<?= $use_date_filter ? '' : ' ' . $f_tahun ?></th>
              <th>SALDO AKHIR<?= $use_date_filter ? '' : ' ' . $f_tahun ?></th>
              <th>SUMBER DANA</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no = 1;
            $t_awal = 0;
            $t_plus = 0;
            $t_minus = 0;
            $t_akhir = 0;
            $data->data_seek(0);
            while ($r = $data->fetch_assoc()):
              $s_akhir = $r['saldo_awal'] + $r['penambahan'] - $r['pengurangan'];
              $t_awal += $r['saldo_awal'];
              $t_plus += $r['penambahan'];
              $t_minus += $r['pengurangan'];
              $t_akhir += $s_akhir;

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
                <td><small><?= htmlspecialchars($r['sumber_dana'] ?? '') ?></small></td>
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
    </div>
  </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>