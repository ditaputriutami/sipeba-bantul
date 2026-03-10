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

$where = "WHERE p.status IN ('disetujui','disetujui sebagian') AND pd.status='disetujui'";
if ($f_bagian) $where .= " AND p.id_bagian=$f_bagian";
if ($f_dari)   $where .= " AND p.tanggal >= '$f_dari'";
if ($f_sampai) $where .= " AND p.tanggal <= '$f_sampai'";

// Join dengan detail FIFO untuk breakdown per batch
$data = $conn->query("
    SELECT p.id, p.no_permintaan, p.tanggal, p.keterangan,
           b.kode_barang, b.nama_barang, b.satuan, bg.nama as nama_bagian, u.nama as nama_input,
           j.nama_jenis, j.kode_jenis,
           pd.jumlah_dipotong, pd.harga_satuan,
           (pd.jumlah_dipotong * pd.harga_satuan) as total_nilai_batch,
           pen.no_faktur as batch_no_faktur, pen.tanggal as batch_tanggal
    FROM pengurangan p
    JOIN pengurangan_detail pd ON pd.id_pengurangan = p.id
    JOIN penerimaan pen ON pen.id = pd.id_penerimaan
    JOIN barang b ON p.id_barang=b.id
    JOIN jenis_barang j ON b.id_jenis_barang=j.id
    JOIN bagian bg ON p.id_bagian=bg.id
    JOIN users u ON p.id_user=u.id
    $where
    ORDER BY p.tanggal ASC, p.id ASC, pd.id ASC
");

$bagianList = ($role === 'superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;

// Export Excel
if (isset($_GET['export'])) {
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header('Content-Disposition: attachment; filename="buku_pengeluaran_' . date('Ymd') . '.xls"');
  header('Pragma: no-cache');
  echo "\xEF\xBB\xBF";

  // Sort data for grouping
  $exportData = [];
  $data->data_seek(0);
  while ($r = $data->fetch_assoc()) $exportData[] = $r;
  usort($exportData, function ($a, $b) {
    if ($a['kode_jenis'] === $b['kode_jenis']) return strcmp($a['tanggal'], $b['tanggal']);
    return strcmp($a['kode_jenis'], $b['kode_jenis']);
  });
?>
  <table border="1" cellpadding="3" cellspacing="0">
    <thead>
      <tr>
        <th colspan="11" style="text-align:center; font-size:14pt; font-weight:bold; border:none;">BUKU PENGELUARAN BARANG PERSEDIAAN</th>
      </tr>
      <tr>
        <th colspan="11" style="text-align:center; font-size:11pt; border:none;">Periode: <?= $f_dari ?> s.d. <?= $f_sampai ?></th>
      </tr>
      <tr>
        <th colspan="11" style="border:none;"></th>
      </tr>
      <tr>
        <th rowspan="2">NO</th>
        <th rowspan="2">TANGGAL PENGELUARAN BARANG</th>
        <th colspan="2" rowspan="2">JENIS/NAMA BARANG</th>
        <th rowspan="2">KODE BARANG</th>
        <th rowspan="2">NOMOR</th>
        <th rowspan="2">BANYAKNYA</th>
        <th rowspan="2">SATUAN</th>
        <th rowspan="2">HARGA SATUAN (RP)</th>
        <th rowspan="2">JUMLAH HARGA (RP)</th>
        <th rowspan="2">KETERANGAN</th>
      </tr>
      <tr></tr>
      <tr>
        <?php for ($i = 1; $i <= 11; $i++) echo "<th style='text-align:center;'>$i</th>"; ?>
      </tr>
    </thead>
    <tbody>
      <?php
      $jenis_no = 1;
      $current_jenis = null;
      $totalNilai = 0;
      $totalQty = 0;
      foreach ($exportData as $r) {
        $totalNilai += $r['total_nilai_batch'];
        $totalQty += $r['jumlah_dipotong'];
        if ($current_jenis !== $r['kode_jenis']) {
          $current_jenis = $r['kode_jenis'];
      ?>
          <tr style="font-weight:bold; background-color:#f8f9fa;">
            <td style="text-align:center;"><?= $jenis_no++ ?></td>
            <td></td>
            <td></td>
            <td><?= htmlspecialchars($r['nama_jenis']) ?></td>
            <td style="mso-number-format:'\@'; text-align:center;"><?= htmlspecialchars($r['kode_jenis'] ?? '') ?></td>
            <td colspan="6"></td>
          </tr>
        <?php } ?>
        <tr>
          <td></td>
          <td style="mso-number-format:'yyyy\-mm\-dd';"><?= htmlspecialchars($r['tanggal']) ?></td>
          <td style="text-align:center;">-</td>
          <td><?= htmlspecialchars($r['nama_barang']) ?></td>
          <td style="mso-number-format:'\@'; text-align:center;"><?= htmlspecialchars($r['kode_barang']) ?></td>
          <td><?= htmlspecialchars($r['no_permintaan']) ?></td>
          <td style="text-align:center;"><?= number_format($r['jumlah_dipotong'], 0, '', '') ?></td>
          <td><?= htmlspecialchars($r['satuan']) ?></td>
          <td style="text-align:right;"><?= number_format($r['harga_satuan'], 0, '', '') ?></td>
          <td style="text-align:right;"><?= number_format($r['total_nilai_batch'], 0, '', '') ?></td>
          <td><?= htmlspecialchars($r['keterangan'] ?? '') ?></td>
        </tr>
      <?php } ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:bold; background-color:#e9ecef;">
        <td colspan="6" style="text-align:right;">TOTAL</td>
        <td style="text-align:center;"><?= number_format($totalQty, 0, '', '') ?></td>
        <td colspan="2"></td>
        <td style="text-align:right;"><?= number_format($totalNilai, 0, '', '') ?></td>
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
    <div class="topbar-title"><i class="bi bi-journal-minus me-2"></i>Buku Pengeluaran Barang — Lampiran X</div>
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
        <div class="text-muted" style="font-size:.85rem">Periode: <?= formatTanggal($f_dari) ?> s.d. <?= formatTanggal($f_sampai) ?></div>
      </div>
      <div class="table-wrapper">
        <table class="table table-bordered table-sm align-middle" style="font-size:.82rem; white-space:nowrap;">
          <thead class="table-warning text-center align-middle">
            <tr>
              <th rowspan="2">NO</th>
              <th rowspan="2">TANGGAL PENGELUARAN BARANG</th>
              <th colspan="2" rowspan="2">JENIS/NAMA BARANG</th>
              <th rowspan="2">KODE BARANG</th>
              <th rowspan="2">NOMOR</th>
              <th rowspan="2">BANYAKNYA</th>
              <th rowspan="2">SATUAN</th>
              <th rowspan="2">HARGA SATUAN (RP)</th>
              <th rowspan="2">JUMLAH HARGA (RP)</th>
              <th rowspan="2">KETERANGAN</th>
            </tr>
            <tr></tr>
          </thead>
          <tbody>
            <?php
            // Prepare HTML data for consistent grouping by kode_jenis
            $htmlData = [];
            $data->data_seek(0);
            while ($r = $data->fetch_assoc()) $htmlData[] = $r;
            usort($htmlData, function ($a, $b) {
              if ($a['kode_jenis'] === $b['kode_jenis']) return strcmp($a['tanggal'], $b['tanggal']);
              return strcmp($a['kode_jenis'], $b['kode_jenis']);
            });

            $totalNilai = 0;
            $totalQty = 0;
            $found = false;
            $current_jenis = null;
            $jenis_no = 1;
            foreach ($htmlData as $r):
              $found = true;
              $totalNilai += $r['total_nilai_batch'];
              $totalQty += $r['jumlah_dipotong'];

              if ($current_jenis !== $r['kode_jenis']):
                $current_jenis = $r['kode_jenis'];
            ?>
                <tr class="table-light fw-bold">
                  <td class="text-center"><?= $jenis_no++ ?></td>
                  <td></td>
                  <td class="text-center"></td>
                  <td><?= htmlspecialchars($r['nama_jenis']) ?></td>
                  <td class="text-center"><?= htmlspecialchars($r['kode_jenis'] ?? '') ?></td>
                  <td colspan="6"></td>
                </tr>
              <?php endif; ?>
              <tr>
                <td></td>
                <td><?= formatTanggal($r['tanggal']) ?></td>
                <td class="text-center">-</td>
                <td><?= htmlspecialchars($r['nama_barang']) ?></td>
                <td class="text-center"><?= htmlspecialchars($r['kode_barang']) ?></td>
                <td><?= htmlspecialchars($r['no_permintaan']) ?></td>
                <td class="text-center"><?= number_format($r['jumlah_dipotong']) ?></td>
                <td class="text-center"><?= htmlspecialchars($r['satuan']) ?></td>
                <td class="text-end"><?= formatRupiah($r['harga_satuan']) ?></td>
                <td class="text-end fw-semibold"><?= formatRupiah($r['total_nilai_batch']) ?></td>
                <td><?= htmlspecialchars($r['keterangan'] ?? '—') ?></td>
              </tr>
            <?php endforeach;
            if (!$found): ?>
              <tr>
                <td colspan="11" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Tidak ada data pengeluaran.</td>
              </tr>
            <?php endif; ?>
          </tbody>
          <?php if ($found): ?>
            <tfoot>
              <tr class="table-secondary fw-bold">
                <td colspan="6" class="text-end">TOTAL</td>
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