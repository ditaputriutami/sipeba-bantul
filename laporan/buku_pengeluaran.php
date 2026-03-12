<?php

/**
 * Laporan Buku Pengeluaran Barang Persediaan
 * Sesuai Lampiran X Perbup Bantul
 * Kolom: Tanggal, Nama Barang, Kode Barang, Jumlah, Harga Satuan (FIFO), Tanggal Penyerahan
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
$pageTitle = 'Buku Pengeluaran Barang';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

$f_bagian  = ($role === 'superadmin') ? (int)($_GET['id_bagian'] ?? 0) : $id_bagian;
// Sekretariat Daerah (id=9) can see all departments
if ($id_bagian == 9) $f_bagian = 0;
$f_dari    = $_GET['dari'] ?? date('Y-m-01');
$f_sampai  = $_GET['sampai'] ?? date('Y-m-d');

$where = "WHERE p.status IN ('disetujui','disetujui sebagian')";
if ($f_bagian) $where .= " AND p.id_bagian=$f_bagian";
if ($f_dari)   $where .= " AND p.tanggal >= '$f_dari'";
if ($f_sampai) $where .= " AND p.tanggal <= '$f_sampai'";

$allJenis = [];
$qJenis = $conn->query("SELECT id, kode_jenis, nama_jenis FROM jenis_barang ORDER BY kode_jenis ASC");
while ($j = $qJenis->fetch_assoc()) {
  $allJenis[$j['kode_jenis']] = [
    'kode_jenis' => $j['kode_jenis'],
    'nama_jenis' => $j['nama_jenis'],
    'rows'       => [],
  ];
}

// Join dengan detail FIFO untuk breakdown per batch
$data = $conn->query("
  SELECT p.id, p.no_permintaan, p.tanggal, p.keterangan, p.created_at,
           b.kode_barang, b.nama_barang, b.satuan, bg.nama as nama_bagian, u.nama as nama_input,
           j.nama_jenis, j.kode_jenis,
           pd.jumlah_dipotong, pd.harga_satuan,
           (pd.jumlah_dipotong * pd.harga_satuan) as total_nilai_batch,
           pen.keterangan as batch_keterangan,
           pen.no_faktur as batch_no_faktur, pen.tanggal as batch_tanggal
    FROM pengurangan p
    JOIN pengurangan_detail pd ON pd.id_pengurangan = p.id
    JOIN penerimaan pen ON pen.id = pd.id_penerimaan
    JOIN barang b ON p.id_barang=b.id
    JOIN jenis_barang j ON b.id_jenis_barang=j.id
    JOIN bagian bg ON p.id_bagian=bg.id
    JOIN users u ON p.id_user=u.id
    $where AND pd.status = 'disetujui'
    ORDER BY j.kode_jenis ASC, p.created_at ASC, p.id ASC, pd.id ASC
");

$totalNilai = 0;
$totalQty = 0;
while ($r = $data->fetch_assoc()) {
  $kj = $r['kode_jenis'];
  if (isset($allJenis[$kj])) {
    $allJenis[$kj]['rows'][] = $r;
    $totalNilai += $r['total_nilai_batch'];
    $totalQty += $r['jumlah_dipotong'];
  }
}

$bagianList = ($role === 'superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;

// Nama bagian yang ditampilkan pada header laporan
$namaBagianLaporan = 'Sekretariat Daerah';
if ($f_bagian > 0) {
  if ($role !== 'superadmin' && !empty($user['nama_bagian']) && (int)$id_bagian === (int)$f_bagian) {
    $namaBagianLaporan = $user['nama_bagian'];
  } else {
    $qBagian = $conn->query("SELECT nama FROM bagian WHERE id=" . (int)$f_bagian . " LIMIT 1");
    if ($qBagian && $qBagian->num_rows > 0) {
      $namaBagianLaporan = $qBagian->fetch_assoc()['nama'];
    }
  }
}

// Hindari duplikasi "Bagian Bagian ..."
$labelBagianLaporan = preg_match('/^Bagian\s+/i', (string)$namaBagianLaporan)
  ? $namaBagianLaporan
  : 'Bagian ' . $namaBagianLaporan;

// Fungsi split kode menjadi 7 segment: 1,1,1,2,2,2,2
if (!function_exists('splitKode')) {
  function splitKode($kode) {
    $raw = str_replace('.', '', $kode);
    return [
      substr($raw, 0, 1),
      substr($raw, 1, 1),
      substr($raw, 2, 1),
      substr($raw, 3, 2),
      substr($raw, 5, 2),
      substr($raw, 7, 2),
      substr($raw, 9),    // semua digit sisa
    ];
  }
}

// Export Excel
if (isset($_GET['export'])) {
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header('Content-Disposition: attachment; filename="buku_pengeluaran_' . date('Ymd') . '.xls"');
  header('Pragma: no-cache');
  echo "\xEF\xBB\xBF";


?>
  <table border="1" cellpadding="3" cellspacing="0" style="border-collapse:collapse; font-family:Calibri; font-size:10pt;">
    <thead>
      <tr>
        <th colspan="17" style="text-align:center; font-size:14pt; font-weight:bold; border:none;">BUKU PENGELUARAN BARANG PERSEDIAAN</th>
      </tr>
      <tr>
        <th colspan="17" style="text-align:center; font-size:11pt; font-weight:bold; border:none;"><?= htmlspecialchars($labelBagianLaporan) ?></th>
      </tr>
      <tr>
        <th colspan="17" style="text-align:center; font-size:11pt; border:none;">Periode: <?= date('d/m/Y', strtotime($f_dari)) ?> s.d. <?= date('d/m/Y', strtotime($f_sampai)) ?></th>
      </tr>
      <tr><th colspan="17" style="border:none;"></th></tr>
      <tr>
        <th rowspan="2" style="text-align:center; vertical-align:middle;">NO</th>
        <th rowspan="2" style="text-align:center; vertical-align:middle;">TANGGAL PENGELUARAN BARANG</th>
        <th colspan="2" rowspan="2" style="text-align:center; vertical-align:middle;">JENIS/NAMA BARANG</th>
        <th colspan="7" rowspan="2" style="text-align:center; vertical-align:middle;">KODE BARANG</th>
        <th rowspan="2" style="text-align:center; vertical-align:middle;">NOMOR</th>
        <th rowspan="2" style="text-align:center; vertical-align:middle;">BANYAKNYA</th>
        <th rowspan="2" style="text-align:center; vertical-align:middle;">SATUAN</th>
        <th rowspan="2" style="text-align:center; vertical-align:middle;">HARGA SATUAN (Rp)</th>
        <th rowspan="2" style="text-align:center; vertical-align:middle;">JUMLAH HARGA (Rp)</th>
        <th rowspan="2" style="text-align:center; vertical-align:middle;">SUMBER DANA</th>
      </tr>
      <tr></tr>
      <tr>
        <?php for ($i = 1; $i <= 17; $i++) echo "<th style='text-align:center;'>$i</th>"; ?>
      </tr>
    </thead>
    <tbody>
      <?php
      $jenis_no = 1;
      foreach ($allJenis as $kj => $jenis) {
          $kode_j = splitKode($kj);
          $item_no = 1;
      ?>
          <tr style="font-weight:bold; background-color:#f8f9fa;">
            <td style="text-align:center; border: 0.5pt solid windowtext;"><?= $jenis_no++ ?></td>
            <td style="border: 0.5pt solid windowtext;"></td>
            <td colspan="2" style="border: 0.5pt solid windowtext;"><?= htmlspecialchars($jenis['nama_jenis']) ?></td>
            <?php foreach ($kode_j as $k): ?>
              <td style="text-align:center; font-weight:bold; mso-number-format:'\@'; border: 0.5pt solid windowtext;" x:str><?= htmlspecialchars($k) ?></td>
            <?php endforeach; ?>
            <?php for($i=0; $i<6; $i++): ?>
              <td style="border: 0.5pt solid windowtext;"></td>
            <?php endfor; ?>
          </tr>
        <?php foreach ($jenis['rows'] as $r) {
            $kode_b = splitKode($r['kode_barang']);
        ?>
        <tr>
          <td style="border: 0.5pt solid windowtext; text-align:center; mso-number-format:'\@';"><?= ($jenis_no-1) . '.' . ($item_no++) ?></td>
          <td style="border: 0.5pt solid windowtext;"><?= htmlspecialchars($r['tanggal']) ?></td>
          <td colspan="2" style="border: 0.5pt solid windowtext;"><?= htmlspecialchars($r['nama_barang']) ?></td>
<?php foreach ($kode_b as $kb): ?>
            <td style="text-align:center; mso-number-format:'\@'; border: 0.5pt solid windowtext;" x:str><?= htmlspecialchars($kb) ?></td>
          <?php endforeach; ?>
          <td style="border: 0.5pt solid windowtext;"><?= htmlspecialchars($r['no_permintaan']) ?></td>
          <td style="text-align:center; border: 0.5pt solid windowtext;" x:num><?= (int)$r['jumlah_dipotong'] ?></td>
          <td style="border: 0.5pt solid windowtext;"><?= htmlspecialchars($r['satuan']) ?></td>
          <td style="text-align:right; mso-number-format:'#,##0'; border: 0.5pt solid windowtext;" x:num><?= (int)$r['harga_satuan'] ?></td>
          <td style="text-align:right; font-weight:bold; mso-number-format:'#,##0'; border: 0.5pt solid windowtext;" x:num><?= (int)$r['total_nilai_batch'] ?></td>
          <td style="border: 0.5pt solid windowtext;"><?= htmlspecialchars($r['keterangan'] ?? '-') ?></td>
        </tr>
      <?php } } ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:bold; background-color:#e9ecef;">
        <td colspan="12" style="text-align:right;">JUMLAH</td>
        <td style="text-align:center;" x:num><?= (int)$totalQty ?></td>
        <td></td>
        <td></td>
        <td style="text-align:right; font-weight:bold; mso-number-format:'#,##0';" x:num><?= (int)$totalNilai ?></td>
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
    <div class="topbar-title"><i class="bi bi-journal-minus me-2"></i>Buku Pengeluaran Barang</div>
  </div>
  <div class="page-content">
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
            <label class="form-label fw-semibold">Dari</label>
            <input type="date" name="dari" class="form-control form-control-sm" value="<?= $f_dari ?>">
          </div>
          <div class="col-auto">
            <label class="form-label fw-semibold">Sampai</label>
            <input type="date" name="sampai" class="form-control form-control-sm" value="<?= $f_sampai ?>">
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
        <div class="fw-bold">BUKU PENGELUARAN BARANG PERSEDIAAN</div>
        <div class="fw-semibold" style="font-size:1rem"><?= htmlspecialchars($labelBagianLaporan) ?></div>
        <div class="text-muted" style="font-size:.85rem">Periode: <?= date('d/m/Y', strtotime($f_dari)) ?> s.d. <?= date('d/m/Y', strtotime($f_sampai)) ?></div>
      </div>
      <div class="table-wrapper">
        <table class="table table-bordered table-sm align-middle" style="font-size:.82rem; white-space:nowrap;">
          <thead class="table-warning text-center align-middle">
            <tr>
              <th rowspan="2">NO</th>
              <th rowspan="2">TANGGAL PENGELUARAN BARANG</th>
              <th colspan="2" rowspan="2">JENIS/NAMA BARANG</th>
              <th colspan="7" rowspan="2">KODE BARANG</th>
              <th rowspan="2">NOMOR</th>
              <th rowspan="2">BANYAKNYA</th>
              <th rowspan="2">SATUAN</th>
              <th rowspan="2">HARGA SATUAN (RP)</th>
              <th rowspan="2">JUMLAH HARGA (RP)</th>
              <th rowspan="2">SUMBER DANA</th>
            </tr>
            <tr></tr>
          </thead>
          <tbody>
            <?php
            $jenis_no = 1;
            foreach ($allJenis as $kj => $jenis):
              $item_no = 1;
            ?>
                <tr class="table-light fw-bold">
                  <td class="text-center"><?= $jenis_no++ ?></td>
                  <td></td>
                  <td colspan="2"><?= htmlspecialchars($jenis['nama_jenis']) ?></td>
                  <?php $kode_j = splitKode($kj); foreach ($kode_j as $k): ?>
                    <td class="text-center" style="font-size:0.75rem;"><?= htmlspecialchars($k) ?></td>
                  <?php endforeach; ?>
                  <td colspan="5"></td>
                </tr>
              <?php foreach ($jenis['rows'] as $r): ?>
                <tr>
                  <td class="text-center"><?= ($jenis_no-1) . '.' . ($item_no++) ?></td>
                  <td><?= htmlspecialchars($r['tanggal']) ?></td>
                  <td colspan="2"><?= htmlspecialchars($r['nama_barang']) ?></td>
                  <?php $kode_b = splitKode($r['kode_barang']); foreach ($kode_b as $kb): ?>
                    <td class="text-center" style="font-size:0.75rem;"><?= htmlspecialchars($kb) ?></td>
                  <?php endforeach; ?>
                  <td><?= htmlspecialchars($r['no_permintaan']) ?></td>
                  <td class="text-center"><?= number_format($r['jumlah_dipotong']) ?></td>
                  <td class="text-center"><?= htmlspecialchars($r['satuan']) ?></td>
                  <td class="text-end"><?= formatRupiah($r['harga_satuan']) ?></td>
                  <td class="text-end fw-semibold"><?= formatRupiah($r['total_nilai_batch']) ?></td>
                  <td><?= htmlspecialchars($r['keterangan'] ?? '-') ?></td>
                </tr>
              <?php endforeach; endforeach; ?>
          </tbody>
          <?php if (true): ?>
            <tfoot>
              <tr class="table-secondary fw-bold">
                <td colspan="12" class="text-end">JUMLAH</td>
                <td class="text-center"><?= number_format($totalQty) ?></td>
                <td colspan="2"></td>
                <td class="text-end text-danger"><?= formatRupiah($totalNilai) ?></td>
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