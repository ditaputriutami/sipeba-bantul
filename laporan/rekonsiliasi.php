<?php

/**
 * Laporan Rekonsiliasi Persediaan
 * Format: Berita Acara Rekonsiliasi per Bulan dan Bagian
 * Kolom: No, Uraian (Jenis & Nama Barang), Penerimaan, Pengurangan, Saldo Akhir
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
$pageTitle = 'Rekonsiliasi Persediaan';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

$f_bagian   = ($role === 'superadmin') ? (int)($_GET['id_bagian'] ?? 0) : $id_bagian;
// Sekretariat Daerah (id=9) can see all departments
if ($id_bagian == 9) $f_bagian = 0;
$f_tahun    = (int)($_GET['tahun'] ?? date('Y'));
$f_dari_tanggal = $_GET['dari_tanggal'] ?? '';
$f_sampai_tanggal = $_GET['sampai_tanggal'] ?? '';

// Tentukan mode filter: jika tanggal diisi, gunakan filter tanggal; jika tidak, gunakan filter tahun
$use_date_filter = !empty($f_dari_tanggal) && !empty($f_sampai_tanggal);

if ($use_date_filter) {
  $f_dari = $f_dari_tanggal;
  $f_sampai = $f_sampai_tanggal;
} else {
  $f_dari = $f_tahun . '-01-01';
  $f_sampai = $f_tahun . '-12-31';
}

$bagianFilter = '';
$bagianFilterPenerimaan = '';
$bagianFilterPengurangan = '';
if ($f_bagian) {
  $bagianFilterPenerimaan = "AND p.id_bagian=$f_bagian";
  $bagianFilterPengurangan = "AND pg.id_bagian=$f_bagian";
}
if ($role !== 'superadmin') {
  $bagianFilterPenerimaan = "AND p.id_bagian=$id_bagian";
  $bagianFilterPengurangan = "AND pg.id_bagian=$id_bagian";
}

// Query: ambil data transaksi per periode yang dipilih
$query = "
    SELECT 
        jb.id as id_jenis,
        jb.nama_jenis,
        b.id as id_barang,
        b.kode_barang,
        b.nama_barang,
        b.satuan,
        
        /* === PENERIMAAN PERIODE INI === */
        COALESCE((
            SELECT SUM(p.jumlah * p.harga_satuan) 
            FROM penerimaan p 
            WHERE p.id_barang=b.id 
                AND p.status='disetujui' 
                AND p.tanggal BETWEEN '$f_dari' AND '$f_sampai'
                $bagianFilterPenerimaan
        ), 0) AS penerimaan_nilai,
        
        /* === PENGURANGAN PERIODE INI === */
        COALESCE((
            SELECT SUM(pd.jumlah_dipotong * pd.harga_satuan) 
            FROM pengurangan pg 
            JOIN pengurangan_detail pd ON pd.id_pengurangan=pg.id 
            WHERE pg.id_barang=b.id 
                AND pg.status='disetujui' 
                AND pg.tanggal BETWEEN '$f_dari' AND '$f_sampai'
                $bagianFilterPengurangan
        ), 0) AS pengurangan_nilai
        
    FROM jenis_barang jb
    LEFT JOIN barang b ON b.id_jenis_barang = jb.id
    ORDER BY jb.nama_jenis, b.nama_barang
";

$result = $conn->query($query);

// Kelompokkan data per jenis -> barang
$dataByJenis = [];
while ($row = $result->fetch_assoc()) {
  if (!$row['id_barang']) continue; // Skip jika tidak ada barang

  $id_jenis = $row['id_jenis'];
  $penerimaan = $row['penerimaan_nilai'];
  $pengurangan = $row['pengurangan_nilai'];
  $saldo_akhir = $penerimaan - $pengurangan;

  // Skip barang yang tidak ada transaksi di periode ini
  if ($penerimaan == 0 && $pengurangan == 0) {
    continue;
  }

  // Inisialisasi jenis jika belum ada
  if (!isset($dataByJenis[$id_jenis])) {
    $dataByJenis[$id_jenis] = [
      'nama_jenis' => $row['nama_jenis'],
      'barang' => []
    ];
  }

  $dataByJenis[$id_jenis]['barang'][] = [
    'kode_barang' => $row['kode_barang'],
    'nama_barang' => $row['nama_barang'],
    'satuan' => $row['satuan'],
    'penerimaan' => $penerimaan,
    'pengurangan' => $pengurangan,
    'saldo_akhir' => $saldo_akhir,
  ];
}

// Hapus jenis yang tidak memiliki barang
$dataByJenis = array_filter($dataByJenis, function ($jenis) {
  return count($jenis['barang']) > 0;
});

// Ambil nama bagian berdasarkan id_bagian user
$namaBagianTerpilih = 'Semua Bagian';
if ($role === 'superadmin') {
  // Superadmin - ambil dari filter atau default "Semua Bagian"
  if ($f_bagian) {
    $bagianQuery = $conn->query("SELECT nama FROM bagian WHERE id = $f_bagian");
    if ($bagianRow = $bagianQuery->fetch_assoc()) {
      $namaBagianTerpilih = $bagianRow['nama'];
    }
  }
  $bagianList = $conn->query("SELECT * FROM bagian ORDER BY nama");
} else {
  // Non-superadmin - ambil nama bagian dari user yang login
  $bagianQuery = $conn->query("SELECT nama FROM bagian WHERE id = $id_bagian");
  if ($bagianRow = $bagianQuery->fetch_assoc()) {
    $namaBagianTerpilih = $bagianRow['nama'];
  }
  $bagianList = null;
}

// For Sekretariat Daerah users viewing all departments, append "Daftar Gabungan"
if ($id_bagian == 9 && $f_bagian == 0) {
  $namaBagianTerpilih = $namaBagianTerpilih . ' (Daftar Gabungan)';
}

// Ambil tahun-tahun yang ada datanya dari penerimaan dan pengurangan
$where_bagian_years = $f_bagian ? " AND id_bagian=$f_bagian" : "";
$years_query = "SELECT DISTINCT tahun FROM (
  SELECT YEAR(tanggal) as tahun FROM penerimaan WHERE status='disetujui' $where_bagian_years
  UNION
  SELECT YEAR(tanggal) as tahun FROM pengurangan WHERE status='disetujui' $where_bagian_years
) AS all_years WHERE tahun IS NOT NULL ORDER BY tahun DESC";
$years_result = $conn->query($years_query);
$years = [];
while ($y = $years_result->fetch_assoc()) {
  $years[] = $y['tahun'];
}
if (empty($years)) {
  $years = [date('Y')];
}
if (!in_array($f_tahun, $years)) {
  $f_tahun = $years[0];
}

// Export Excel
if (isset($_GET['export'])) {
  $periodeLabel = date('d-m-Y', strtotime($f_dari)) . '_sd_' . date('d-m-Y', strtotime($f_sampai));
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header('Content-Disposition: attachment; filename="rekonsiliasi_' . $periodeLabel . '.xls"');
  header('Pragma: no-cache');
  echo "\xEF\xBB\xBF";
?>
<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse; font-family:Calibri; font-size:11pt;">
  <tr>
    <td colspan="5" align="center" style="font-size:14pt; font-weight:bold; border:none; padding:15px;">BERITA ACARA REKONSILIASI</td>
  </tr>
  <tr>
    <td colspan="5" align="center" style="font-size:12pt; font-weight:bold; border:none; padding:8px;"><?= strtoupper($namaBagianTerpilih) ?></td>
  </tr>
  <tr>
    <td colspan="5" align="center" style="font-size:12pt; font-weight:bold; border:none; padding:8px;">PERIODE <?= date('d/m/Y', strtotime($f_dari)) ?> s.d. <?= date('d/m/Y', strtotime($f_sampai)) ?></td>
  </tr>
  <tr>
    <td colspan="5" style="border:none; padding:3px;"></td>
  </tr>
  <tr style="font-weight:bold; background-color:#cccccc;">
    <td align="center" style="border:1px solid #000; padding:10px; width:8%;">NO.</td>
    <td align="center" style="border:1px solid #000; padding:10px; width:35%;">URAIAN</td>
    <td align="center" style="border:1px solid #000; padding:10px; width:19%;">PENERIMAAN</td>
    <td align="center" style="border:1px solid #000; padding:10px; width:19%;">PENGURANGAN</td>
    <td align="center" style="border:1px solid #000; padding:10px; width:19%;">SALDO AKHIR</td>
  </tr>
<?php
  if (empty($dataByJenis)) {
    echo "<tr><td colspan='5' align='center' style='border:1px solid #000; padding:15px;'>Tidak ada transaksi pada periode " . date('d/m/Y', strtotime($f_dari)) . " s.d. " . date('d/m/Y', strtotime($f_sampai)) . "</td></tr>";
  } else {
    $no = 1;
    $total_penerimaan = 0;
    $total_pengurangan = 0;
    $total_saldo = 0;
    foreach ($dataByJenis as $jenis) {
?>
  <tr style="font-weight:bold; background-color:#e8e8e8;">
    <td align="center" style="border:1px solid #000; padding:10px;"><?= $no++ ?></td>
    <td colspan="4" style="border:1px solid #000; padding:10px;"><?= strtoupper(htmlspecialchars($jenis['nama_jenis'])) ?></td>
  </tr>
<?php
      foreach ($jenis['barang'] as $brg) {
        $total_penerimaan += $brg['penerimaan'];
        $total_pengurangan += $brg['pengurangan'];
        $total_saldo += $brg['saldo_akhir'];
?>
  <tr>
    <td style="border:1px solid #000; padding:8px;"></td>
    <td style="border:1px solid #000; padding:8px; padding-left:20px;"><?= htmlspecialchars($brg['nama_barang']) ?></td>
    <td style="border:1px solid #000; padding:8px; text-align:right; mso-number-format:'#,##0';" x:num><?= (int)$brg['penerimaan'] ?></td>
    <td style="border:1px solid #000; padding:8px; text-align:right; mso-number-format:'#,##0';" x:num><?= (int)$brg['pengurangan'] ?></td>
    <td style="border:1px solid #000; padding:8px; text-align:right; font-weight:bold; mso-number-format:'#,##0';" x:num><?= (int)$brg['saldo_akhir'] ?></td>
  </tr>
<?php
      }
    }
?>
  <tr style="font-weight:bold; background-color:#e9ecef;">
    <td colspan="2" align="center" style="border:1px solid #000; padding:10px;">JUMLAH</td>
    <td style="border:1px solid #000; padding:8px; text-align:right; mso-number-format:'#,##0';" x:num><?= (int)$total_penerimaan ?></td>
    <td style="border:1px solid #000; padding:8px; text-align:right; mso-number-format:'#,##0';" x:num><?= (int)$total_pengurangan ?></td>
    <td style="border:1px solid #000; padding:8px; text-align:right; mso-number-format:'#,##0';" x:num><?= (int)$total_saldo ?></td>
  </tr>
<?php
  }
?>
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
    <div class="topbar-title"><i class="bi bi-bar-chart-line me-2"></i>Rekonsiliasi Persediaan</div>
  </div>
  <div class="page-content">

    <!-- Filter Status Alert -->
    <?php if ($use_date_filter): ?>
      <div class="alert alert-info py-2 mb-3">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Filter Tanggal Aktif:</strong> <?= date('d/m/Y', strtotime($f_dari)) ?> - <?= date('d/m/Y', strtotime($f_sampai)) ?>
        <a href="?<?= http_build_query(array_filter(['id_bagian' => $f_bagian, 'tahun' => $f_tahun])) ?>" class="btn btn-sm btn-outline-secondary ms-2">
          <i class="bi bi-x-circle me-1"></i>Gunakan Filter Tahun
        </a>
      </div>
    <?php else: ?>
      <div class="alert alert-secondary py-2 mb-3">
        <i class="bi bi-calendar-check me-1"></i>
        <strong>Filter Tahun Aktif:</strong> <?= $f_tahun ?>
      </div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
          <?php if ($role === 'superadmin'): ?>
            <div class="col-auto">
              <label class="form-label fw-semibold">Bagian</label>
              <select name="id_bagian" class="form-select form-select-sm">
                <option value="">Sekretariat Daerah</option>
                <?php
                if ($bagianList) {
                  $bagianList->data_seek(0);
                  while ($bg = $bagianList->fetch_assoc()):
                ?>
                    <?php if ($bg['id'] == 9) continue; ?>
                    <option value="<?= $bg['id'] ?>" <?= $f_bagian == $bg['id'] ? 'selected' : '' ?>><?= htmlspecialchars($bg['nama']) ?></option>
                <?php endwhile;
                } ?>
              </select>
            </div>
          <?php else: ?>
            <div class="col-auto">
              <label class="form-label fw-semibold">Bagian</label>
              <div class="form-control form-control-sm" style="background-color: #e9ecef; border: 1px solid #ced4da;">
                <strong><?= htmlspecialchars($id_bagian == 9 ? 'Sekretariat Daerah' : (isset($user['nama_bagian']) ? $user['nama_bagian'] : '')) ?></strong>
              </div>
            </div>
          <?php endif; ?>
          <div class="col-auto">
            <label class="form-label fw-semibold">Tahun</label>
            <select name="tahun" class="form-select form-select-sm">
              <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $f_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
              <?php endforeach; ?>
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
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 1])) ?>" class="btn btn-success btn-sm ms-1"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
          </div>
        </form>
      </div>
    </div>

    <!-- Laporan -->
    <div class="card">
      <div class="card-header text-center py-3" style="background: white; color: #000; border-bottom: 2px solid #000;">
        <h4 class="mb-2 fw-bold" style="font-size: 14pt;">BERITA ACARA REKONSILIASI</h4>
        <h5 class="mb-1" style="font-size: 11pt;"><?= strtoupper($namaBagianTerpilih) ?></h5>
        <h5 class="mb-0" style="font-size: 11pt;">PERIODE <?= date('d/m/Y', strtotime($f_dari)) ?> s.d. <?= date('d/m/Y', strtotime($f_sampai)) ?></h5>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered mb-0" style="font-size: 0.9rem;">
          <thead style="background: #f8f9fa;">
            <tr class="text-center align-middle">
              <th style="width: 60px;">No.</th>
              <th style="min-width: 250px;">URAIAN</th>
              <th style="width: 150px;">PENERIMAAN</th>
              <th style="width: 150px;">PENGURANGAN</th>
              <th style="width: 150px;">SALDO AKHIR</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($dataByJenis)): ?>
              <tr>
                <td colspan="5" class="text-center text-muted py-4">
                  <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                  Tidak ada transaksi pada periode <?= date('d/m/Y', strtotime($f_dari)) ?> s.d. <?= date('d/m/Y', strtotime($f_sampai)) ?>
                </td>
              </tr>
            <?php else: ?>
              <?php
              $no = 1;
              $total_penerimaan = 0;
              $total_pengurangan = 0;
              $total_saldo = 0;
              foreach ($dataByJenis as $jenis):
              ?>
                <!-- Header Jenis Barang -->
                <tr style="background: #e9ecef; font-weight: bold;">
                  <td class="text-center"><?= $no++ ?></td>
                  <td colspan="4"><?= htmlspecialchars(strtoupper($jenis['nama_jenis'])) ?></td>
                </tr>

                <?php foreach ($jenis['barang'] as $brg):
                  $total_penerimaan += $brg['penerimaan'];
                  $total_pengurangan += $brg['pengurangan'];
                  $total_saldo += $brg['saldo_akhir'];
                ?>
                  <tr>
                    <td></td>
                    <td style="padding-left: 30px;"><?= htmlspecialchars($brg['nama_barang']) ?></td>
                    <td class="text-end"><?= formatRupiah($brg['penerimaan']) ?></td>
                    <td class="text-end"><?= formatRupiah($brg['pengurangan']) ?></td>
                    <td class="text-end fw-bold"><?= formatRupiah($brg['saldo_akhir']) ?></td>
                  </tr>
                <?php endforeach; ?>

              <?php endforeach; ?>

              <!-- Footer JUMLAH -->
              <tr style="background: #e9ecef; font-weight: bold;">
                <td colspan="2" class="text-center">JUMLAH</td>
                <td class="text-end"><?= formatRupiah($total_penerimaan) ?></td>
                <td class="text-end"><?= formatRupiah($total_pengurangan) ?></td>
                <td class="text-end"><?= formatRupiah($total_saldo) ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>