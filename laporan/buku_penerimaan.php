<?php

/**
 * Laporan Buku Penerimaan Barang Persediaan
 * Sesuai Lampiran IV Perbup Bantul
 * Kolom: No, Jenis Barang, Kode Barang, Dokumen Faktur, Banyaknya, Satuan, Harga, Jumlah, Bukti Penerimaan
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
$pageTitle = 'Buku Penerimaan Barang';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

// Filter params
$f_bagian  = ($role === 'superadmin') ? (int)($_GET['id_bagian'] ?? 0) : $id_bagian;
$f_dari    = $_GET['dari'] ?? date('Y-m-01');
$f_sampai  = $_GET['sampai'] ?? date('Y-m-d');
$f_status  = $_GET['status'] ?? 'disetujui';

$where = "WHERE p.status='" . $conn->real_escape_string($f_status) . "'";
if ($f_bagian) $where .= " AND p.id_bagian=$f_bagian";
if ($f_dari)   $where .= " AND p.tanggal >= '$f_dari'";
if ($f_sampai) $where .= " AND p.tanggal <= '$f_sampai'";

$data = $conn->query("
    SELECT p.*, b.kode_barang, b.nama_barang, b.satuan, j.nama_jenis, j.kode_jenis, bg.nama as nama_bagian, u.nama as nama_input
    FROM penerimaan p
    JOIN barang b ON p.id_barang=b.id
    JOIN jenis_barang j ON b.id_jenis_barang=j.id
    JOIN bagian bg ON p.id_bagian=bg.id
    JOIN users u ON p.id_user=u.id
    $where
    ORDER BY p.tanggal ASC, p.id ASC
");

$bagianList = ($role === 'superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;

// Export Excel handler
if (isset($_GET['export'])) {
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header('Content-Disposition: attachment; filename="buku_penerimaan_' . date('Ymd') . '.xls"');
  header('Pragma: no-cache');
  echo "\xEF\xBB\xBF"; // BOM UTF-8

  // Sort data for grouping by Jenis Barang
  $exportData = [];
  $data->data_seek(0);
  while ($r = $data->fetch_assoc()) {
    $exportData[] = $r;
  }
  usort($exportData, function ($a, $b) {
    if ($a['kode_jenis'] === $b['kode_jenis']) {
      return strcmp($a['tanggal'], $b['tanggal']);
    }
    return strcmp($a['kode_jenis'], $b['kode_jenis']);
  });

?>
  <table border="1" cellpadding="3" cellspacing="0">
    <thead>
      <tr>
        <th colspan="20" style="text-align:center; font-size:14pt; font-weight:bold; border:none;">BUKU PENERIMAAN BARANG PERSEDIAAN</th>
      </tr>
      <tr>
        <th colspan="20" style="text-align:center; font-size:11pt; border:none;">Periode: <?= $f_dari ?> s.d. <?= $f_sampai ?></th>
      </tr>
      <tr>
        <th colspan="20" style="border:none;"></th>
      </tr>
      <tr>
        <th rowspan="2">NO</th>
        <th colspan="2" rowspan="2">JENIS/BARANG YANG DIBELI</th>
        <th colspan="7" rowspan="2">KODE BARANG</th>
        <th rowspan="2">DARI</th>
        <th colspan="2">DOKUMEN FAKTUR</th>
        <th rowspan="2">BANYAKNYA</th>
        <th rowspan="2">SATUAN</th>
        <th rowspan="2">HARGA SATUAN (Rp)</th>
        <th rowspan="2">JUMLAH HARGA (Rp)</th>
        <th rowspan="2">KETERANGAN</th>
      </tr>
      <tr>
        <th>NOMOR</th>
        <th>TANGGAL</th>
      </tr>
      <tr>
        <?php for ($i = 1; $i <= 18; $i++) echo "<th style='text-align:center;'>$i</th>"; ?>
      </tr>
    </thead>
    <tbody>
      <?php
      $jenis_no = 1;
      $current_jenis = null;
      foreach ($exportData as $r) {
        if ($current_jenis !== $r['kode_jenis']) {
          $current_jenis = $r['kode_jenis'];
          $kode_j = explode('.', $r['kode_jenis']);
          $kode_j = array_pad($kode_j, 7, '');
      ?>
          <tr>
            <td style="text-align:center; font-weight:bold;"><?= $jenis_no++ ?></td>
            <td style="text-align:right; font-weight:bold;"></td>
            <td style="font-weight:bold;"><?= htmlspecialchars($r['nama_jenis']) ?></td>
            <?php foreach ($kode_j as $kj): ?>
              <td style="mso-number-format:'\@'; text-align:center; font-weight:bold;"><?= htmlspecialchars($kj) ?></td>
            <?php endforeach; ?>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
          </tr>
        <?php
        }

        $kode_b = explode('.', $r['kode_barang']);
        $kode_b = array_pad($kode_b, 7, '');
        ?>
        <tr>
          <td></td>
          <td style="text-align:center;">-</td>
          <td><?= htmlspecialchars($r['nama_barang']) ?></td>
          <?php foreach ($kode_b as $kb): ?>
            <td style="mso-number-format:'\@'; text-align:center;"><?= htmlspecialchars($kb) ?></td>
          <?php endforeach; ?>
          <td><?= htmlspecialchars($r['dari'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['no_faktur']) ?></td>
          <td style="mso-number-format:'yyyy\-mm\-dd';"><?= htmlspecialchars($r['tanggal']) ?></td>
          <td style="text-align:center;"><?= number_format($r['jumlah'], 0, '', '') ?></td>
          <td style="text-align:center;"><?= htmlspecialchars($r['satuan']) ?></td>
          <td style="text-align:right;"><?= number_format($r['harga_satuan'], 0, '', '') ?></td>
          <td style="text-align:right;"><?= number_format($r['jumlah_harga'], 0, '', '') ?></td>
          <td><?= htmlspecialchars($r['keterangan'] ?? '') ?></td>
        </tr>
      <?php
      }
      ?>
    </tbody>
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
    <div class="topbar-title"><i class="bi bi-journal-text me-2"></i>Buku Penerimaan Barang — Lampiran IV</div>
  </div>
  <div class="page-content">
    <!-- Filter -->
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
          <div class="col-md-2">
            <label class="form-label fw-semibold">Dari</label>
            <input type="date" name="dari" class="form-control form-control-sm" value="<?= $f_dari ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Sampai</label>
            <input type="date" name="sampai" class="form-control form-control-sm" value="<?= $f_sampai ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="disetujui" <?= $f_status === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
              <option value="pending" <?= $f_status === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="ditolak" <?= $f_status === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
            </select>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 1])) ?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
          </div>
        </form>
      </div>
    </div>

    <!-- Tabel Lampiran IV -->
    <div class="card">
      <div class="card-header p-3">
        <div class="text-center">
          <div class="fw-bold" style="font-size:1.1rem">BUKU PENERIMAAN BARANG PERSEDIAAN</div>
          <div class="text-muted" style="font-size:.9rem">Periode: <?= formatTanggal($f_dari) ?> s.d. <?= formatTanggal($f_sampai) ?></div>
        </div>
      </div>
      <div class="table-responsive p-3">
        <table class="table table-bordered table-sm align-middle" style="font-size:.75rem; white-space:nowrap;">
          <thead class="table-primary text-center align-middle">
            <tr>
              <th rowspan="2">NO</th>
              <th colspan="2" rowspan="2">JENIS/BARANG YANG DIBELI</th>
              <th rowspan="2">KODE BARANG</th>
              <th rowspan="2">DARI</th>
              <th colspan="2">DOKUMEN FAKTUR</th>
              <th rowspan="2">BANYAKNYA</th>
              <th rowspan="2">SATUAN</th>
              <th rowspan="2">HARGA SATUAN (Rp)</th>
              <th rowspan="2">JUMLAH HARGA (Rp)</th>
              <th rowspan="2">KETERANGAN</th>
            </tr>
            <tr>
              <th>NOMOR</th>
              <th>TANGGAL</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Prepare data for grouping
            $htmlData = [];
            $data->data_seek(0);
            while ($r = $data->fetch_assoc()) $htmlData[] = $r;
            usort($htmlData, function ($a, $b) {
              if ($a['kode_jenis'] === $b['kode_jenis']) {
                return strcmp($a['tanggal'], $b['tanggal']);
              }
              return strcmp($a['kode_jenis'], $b['kode_jenis']);
            });

            if (empty($htmlData)): ?>
              <tr>
                <td colspan="20" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Tidak ada data untuk filter yang dipilih.</td>
              </tr>
              <?php else:
              $jenis_no = 1;
              $current_jenis = null;
              $totalQty = 0;
              $totalJumlah = 0;

              foreach ($htmlData as $r):
                $totalQty += $r['jumlah'];
                $totalJumlah += $r['jumlah_harga'];

                if ($current_jenis !== $r['kode_jenis']):
                  $current_jenis = $r['kode_jenis'];
                  $kode_j = explode('.', $r['kode_jenis']);
                  $kode_j = array_pad($kode_j, 7, '');
              ?>
                  <tr class="table-light fw-bold">
                    <td class="text-center"><?= $jenis_no++ ?></td>
                    <td class="text-end"></td>
                    <td><?= htmlspecialchars($r['nama_jenis']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['kode_jenis']) ?></td>
                    <td colspan="8"></td>
                  </tr>
                <?php endif; ?>
                <tr>
                  <td></td>
                  <td class="text-center">-</td>
                  <td><?= htmlspecialchars($r['nama_barang']) ?></td>
                  <td class="text-center"><?= htmlspecialchars($r['kode_barang']) ?></td>
                  <td><?= htmlspecialchars($r['dari'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['no_faktur']) ?></td>
                  <td><?= formatTanggal($r['tanggal']) ?></td>
                  <td class="text-center"><?= number_format($r['jumlah'], 0, ',', '.') ?></td>
                  <td class="text-center"><?= htmlspecialchars($r['satuan']) ?></td>
                  <td class="text-end"><?= number_format($r['harga_satuan'], 0, ',', '.') ?></td>
                  <td class="text-end fw-semibold"><?= number_format($r['jumlah_harga'], 0, ',', '.') ?></td>
                  <td><?= htmlspecialchars($r['keterangan'] ?? '') ?></td>
                </tr>
            <?php endforeach;
            endif; ?>
          </tbody>
          <?php if (!empty($htmlData)): ?>
            <tfoot class="table-secondary fw-bold">
              <tr>
                <td colspan="7" class="text-end">TOTAL</td>
                <td class="text-center"><?= number_format($totalQty, 0, ',', '.') ?></td>
                <td></td>
                <td></td>
                <td class="text-end text-success"><?= number_format($totalJumlah, 0, ',', '.') ?></td>
                <td colspan="1"></td>
              </tr>
            </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>