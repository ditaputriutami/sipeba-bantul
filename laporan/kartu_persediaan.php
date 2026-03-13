<?php

/**
 * Laporan Kartu Persediaan Barang
 * Menampilkan riwayat masuk/keluar per barang dengan sisa unit berjalan
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
$pageTitle = 'Kartu Persediaan Barang';
$user      = getCurrentUser();
$role      = getUserRole();
$id_bagian = getUserBagian();

$f_bagian      = ($role === 'superadmin') ? (int)($_GET['id_bagian'] ?? 0) : (int)$id_bagian;
if ((int)$id_bagian === 9) $f_bagian = 0; // Sekretariat Daerah lihat semua
$f_id_jenis    = (int)($_GET['id_jenis'] ?? 0);
$f_id_barang   = (int)($_GET['id_barang'] ?? 0);
$f_dari        = $_GET['dari'] ?? date('Y-m-01');
$f_sampai      = $_GET['sampai'] ?? date('Y-m-d');

// --- List Bagian (superadmin only) ---
$bagianList = ($role === 'superadmin') ? $conn->query("SELECT id, nama FROM bagian WHERE id != 9 ORDER BY nama") : null;

// --- List Jenis Barang ---
$jenisList = $conn->query("SELECT id, nama_jenis FROM jenis_barang ORDER BY nama_jenis");

// --- List Nama Barang berdasarkan jenis terpilih ---
$barangList = null;
if ($f_id_jenis) {
    $barangList = $conn->query("SELECT id, nama_barang FROM barang WHERE id_jenis_barang = $f_id_jenis ORDER BY nama_barang");
}

// --- Info barang & bagian terpilih ---
$infoBarang = null;
if ($f_id_barang) {
    $infoBarang = $conn->query("
        SELECT b.nama_barang, b.kode_barang, b.satuan, j.nama_jenis
        FROM barang b JOIN jenis_barang j ON b.id_jenis_barang = j.id
        WHERE b.id = $f_id_barang
    ")->fetch_assoc();
}

$namaBagianLaporan = 'Sekretariat Daerah';
if ($f_bagian > 0) {
    $qb = $conn->query("SELECT nama FROM bagian WHERE id = $f_bagian LIMIT 1");
    if ($qb && $qb->num_rows) $namaBagianLaporan = $qb->fetch_assoc()['nama'];
} elseif ((int)$id_bagian === 9 || $role === 'superadmin') {
    $namaBagianLaporan = 'Sekretariat Daerah';
} else {
    $namaBagianLaporan = $user['nama_bagian'] ?? '';
}
$labelBagianLaporan = ($namaBagianLaporan === 'Sekretariat Daerah' || preg_match('/^Bagian\s+/i', $namaBagianLaporan))
    ? $namaBagianLaporan
    : 'Bagian ' . $namaBagianLaporan;

if (!function_exists('formatFifoLines')) {
  function formatFifoLines(array $details, string $field): array
  {
    $lines = [];
    foreach ($details as $detail) {
      $value = $detail[$field] ?? 0;
      $lines[] = number_format((float)$value, 0, ',', '.');
    }

    return $lines;
  }
}

if (!function_exists('consumeFifoBatches')) {
  function consumeFifoBatches(array &$batchQueue, int $qty): array
  {
    $remaining = $qty;
    $details = [];
    $totalNilai = 0;

    foreach ($batchQueue as &$batch) {
      if ($remaining <= 0) {
        break;
      }

      $qtyRemaining = (int)($batch['qty_remaining'] ?? 0);
      if ($qtyRemaining <= 0) {
        continue;
      }

      $take = min($remaining, $qtyRemaining);
      $batch['qty_remaining'] = $qtyRemaining - $take;
      $nilai = $take * (float)$batch['harga_satuan'];

      $details[] = [
        'qty' => $take,
        'harga_satuan' => (float)$batch['harga_satuan'],
        'jumlah_harga' => $nilai,
      ];
      $totalNilai += $nilai;
      $remaining -= $take;
    }
    unset($batch);

    $batchQueue = array_values(array_filter($batchQueue, static function ($batch) {
      return (int)($batch['qty_remaining'] ?? 0) > 0;
    }));

    if ($remaining > 0) {
      $details[] = [
        'qty' => $remaining,
        'harga_satuan' => 0,
        'jumlah_harga' => 0,
        'shortage' => true,
      ];
    }

    return [
      'details' => $details,
      'total_nilai' => $totalNilai,
      'shortage_qty' => $remaining,
    ];
  }
}

if (!function_exists('sumQueueStock')) {
  function sumQueueStock(array $batchQueue): int
  {
    return array_sum(array_map(static function ($batch) {
      return (int)($batch['qty_remaining'] ?? 0);
    }, $batchQueue));
  }
}

// --- Query data kartu persediaan ---
$kartuData = [];
$saldoAwal = 0;
if ($f_id_barang) {
    $bagianWherePen   = $f_bagian ? "AND p.id_bagian = $f_bagian" : '';
    $bagianWherePeng  = $f_bagian ? "AND pr.id_bagian = $f_bagian" : '';
    if ($role !== 'superadmin' && (int)$id_bagian !== 9) {
        $bagianWherePen  = "AND p.id_bagian = $id_bagian";
        $bagianWherePeng = "AND pr.id_bagian = $id_bagian";
    }

  $queryMasuk = "
        SELECT
            p.tanggal      AS tanggal,
            p.no_faktur    AS nomor_dokumen,
            p.jumlah       AS qty,
            p.harga_satuan AS harga_satuan,
            (p.jumlah * p.harga_satuan) AS jumlah_harga,
            p.id           AS sort_id,
        COALESCE(p.created_at, CONCAT(p.tanggal, ' 00:00:00')) AS waktu_input,
        COALESCE(p.created_at, CONCAT(p.tanggal, ' 00:00:00')) AS sort_batch_waktu,
        p.id           AS sort_batch_id
        FROM penerimaan p
        WHERE p.id_barang = $f_id_barang
          AND p.status = 'disetujui'
          AND p.tanggal <= '$f_sampai'
          $bagianWherePen
        ORDER BY p.tanggal ASC, p.created_at ASC, p.id ASC
      ";

  $queryKeluar = "
        SELECT
<<<<<<< HEAD
            'keluar'          AS tipe,
            pr.tanggal        AS tanggal,
            pr.no_permintaan  AS nomor_dokumen,
            pd.jumlah_dipotong AS qty,
            pd.harga_satuan   AS harga_satuan,
            (pd.jumlah_dipotong * pd.harga_satuan) AS jumlah_harga,
            pr.id             AS sort_id,
            COALESCE(pr.created_at, CONCAT(pr.tanggal, ' 00:00:00')) AS waktu_input,
            COALESCE(pen.created_at, CONCAT(pen.tanggal, ' 00:00:00')) AS sort_batch_waktu,
            pen.id            AS sort_batch_id
        FROM pengurangan pr
        JOIN pengurangan_detail pd ON pd.id_pengurangan = pr.id
        LEFT JOIN penerimaan pen ON pen.id = pd.id_penerimaan
=======
          pr.id            AS sort_id,
          pr.tanggal       AS tanggal,
          pr.no_permintaan AS nomor_dokumen,
          COALESCE(SUM(CASE WHEN pd.status = 'disetujui' THEN pd.jumlah_dipotong ELSE 0 END), 0) AS qty,
          pr.created_at    AS waktu_input
        FROM pengurangan pr
        LEFT JOIN pengurangan_detail pd ON pd.id_pengurangan = pr.id
>>>>>>> fix
        WHERE pr.id_barang = $f_id_barang
          AND pr.status IN ('disetujui', 'disetujui sebagian')
          AND pr.tanggal <= '$f_sampai'
          $bagianWherePeng
        GROUP BY pr.id, pr.tanggal, pr.no_permintaan, pr.created_at
        HAVING qty > 0
        ORDER BY pr.tanggal ASC, pr.created_at ASC, pr.id ASC
      ";
  $timeline = [];
  $resMasuk = $conn->query($queryMasuk);
  while ($row = $resMasuk->fetch_assoc()) {
    $timeline[] = [
      'tipe' => 'masuk',
      'tanggal' => $row['tanggal'],
      'nomor_dokumen' => $row['nomor_dokumen'],
      'qty' => (int)$row['qty'],
      'harga_satuan' => (float)$row['harga_satuan'],
      'jumlah_harga' => (float)$row['jumlah_harga'],
      'sort_id' => (int)$row['sort_id'],
      'waktu_input' => $row['waktu_input'],
      'fifo_details' => [],
    ];
  }

<<<<<<< HEAD
        ORDER BY
          sort_batch_waktu ASC,
          sort_batch_id ASC,
          CASE WHEN tipe = 'masuk' THEN 0 ELSE 1 END ASC,
          waktu_input ASC,
          sort_id ASC
    ";
    $res = $conn->query($query);
    while ($row = $res->fetch_assoc()) {
        $kartuData[] = $row;
=======
  $resKeluar = $conn->query($queryKeluar);
  while ($row = $resKeluar->fetch_assoc()) {
    $timeline[] = [
      'tipe' => 'keluar',
      'tanggal' => $row['tanggal'],
      'nomor_dokumen' => $row['nomor_dokumen'],
      'qty' => (int)$row['qty'],
      'harga_satuan' => 0,
      'jumlah_harga' => 0,
      'sort_id' => (int)$row['sort_id'],
      'waktu_input' => $row['waktu_input'],
      'fifo_details' => [],
    ];
  }

  usort($timeline, static function ($a, $b) {
    $timeCompare = strcmp((string)($a['waktu_input'] ?? ''), (string)($b['waktu_input'] ?? ''));
    if ($timeCompare !== 0) {
      return $timeCompare;
>>>>>>> fix
    }

    $idCompare = ((int)($a['sort_id'] ?? 0)) <=> ((int)($b['sort_id'] ?? 0));
    if ($idCompare !== 0) {
      return $idCompare;
    }

    if (($a['tipe'] ?? '') !== ($b['tipe'] ?? '')) {
      return ($a['tipe'] === 'masuk') ? -1 : 1;
    }

    return strcmp((string)($a['tanggal'] ?? ''), (string)($b['tanggal'] ?? ''));
  });

  $batchQueue = [];
  foreach ($timeline as $row) {
    if ($row['tipe'] === 'masuk') {
      $batchQueue[] = [
        'qty_remaining' => (int)$row['qty'],
        'harga_satuan' => (float)$row['harga_satuan'],
        'tanggal' => $row['tanggal'],
        'sort_id' => (int)$row['sort_id'],
      ];

      if ($row['tanggal'] >= $f_dari) {
        $kartuData[] = $row;
      }
      continue;
    }

    $alloc = consumeFifoBatches($batchQueue, (int)$row['qty']);
    $row['jumlah_harga'] = (float)$alloc['total_nilai'];
    $row['fifo_details'] = $alloc['details'];
    $row['harga_satuan'] = (float)($alloc['details'][0]['harga_satuan'] ?? 0);

    if ($row['tanggal'] >= $f_dari) {
      $kartuData[] = $row;
    }
  }

  $saldoAwal = 0;
  $openingQueue = [];
  foreach ($timeline as $row) {
    if ($row['tanggal'] >= $f_dari) {
      continue;
    }

    if ($row['tipe'] === 'masuk') {
      $openingQueue[] = [
        'qty_remaining' => (int)$row['qty'],
        'harga_satuan' => (float)$row['harga_satuan'],
      ];
      continue;
    }

    consumeFifoBatches($openingQueue, (int)$row['qty']);
  }
  $saldoAwal = sumQueueStock($openingQueue);
}

// --- Export Excel ---
if (isset($_GET['export']) && $f_id_barang) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    $namaFile = 'kartu_persediaan_' . ($infoBarang ? strtolower(str_replace(' ', '_', $infoBarang['nama_barang'])) : 'barang') . '_' . date('Ymd') . '.xls';
    header('Content-Disposition: attachment; filename="' . $namaFile . '"');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF";
    $saldoBerjalan = $saldoAwal;
?>
<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse; font-family:Calibri; font-size:10pt;">
  <tr>
    <td colspan="10" style="text-align:center; font-weight:bold; font-size:13pt; border:none;">KARTU PERSEDIAAN BARANG</td>
  </tr>
  <tr>
    <td colspan="10" style="text-align:center; font-weight:bold; font-size:11pt; border:none;"><?= htmlspecialchars($infoBarang['nama_barang']) ?></td>
  </tr>
  <tr>
    <td colspan="10" style="text-align:center; font-size:10pt; border:none;"><?= htmlspecialchars($labelBagianLaporan) ?></td>
  </tr>
  <tr>
    <td colspan="10" style="text-align:center; font-size:10pt; border:none;">Periode: <?= date('d/m/Y', strtotime($f_dari)) ?> s.d. <?= date('d/m/Y', strtotime($f_sampai)) ?></td>
  </tr>
  <tr><td colspan="10" style="border:none;"></td></tr>
  <tr style="background-color:#dce6f1; font-weight:bold; text-align:center;">
    <th style="border:1px solid #000; padding:5px;">NO URUT INPUT</th>
    <th style="border:1px solid #000; padding:5px;">TANGGAL</th>
    <th style="border:1px solid #000; padding:5px;">NOMOR DOKUMEN</th>
    <th style="border:1px solid #000; padding:5px;">UNIT MASUK</th>
    <th style="border:1px solid #000; padding:5px;">HARGA SATUAN (Rp)</th>
    <th style="border:1px solid #000; padding:5px;">JUMLAH HARGA (Rp)</th>
    <th style="border:1px solid #000; padding:5px;">UNIT KELUAR</th>
    <th style="border:1px solid #000; padding:5px;">HARGA SATUAN (Rp)</th>
    <th style="border:1px solid #000; padding:5px;">JUMLAH HARGA (Rp)</th>
    <th style="border:1px solid #000; padding:5px;">SISA UNIT</th>
  </tr>
  <?php if ($saldoAwal > 0): ?>
  <tr>
    <td style="border:1px solid #000; padding:4px;"></td>
    <td style="border:1px solid #000; padding:4px;"></td>
    <td style="border:1px solid #000; padding:4px; font-style:italic;">Saldo Awal</td>
    <td style="border:1px solid #000; padding:4px;"></td>
    <td style="border:1px solid #000; padding:4px;"></td>
    <td style="border:1px solid #000; padding:4px;"></td>
    <td style="border:1px solid #000; padding:4px;"></td>
    <td style="border:1px solid #000; padding:4px;"></td>
    <td style="border:1px solid #000; padding:4px;"></td>
    <td style="text-align:center; border:1px solid #000; padding:4px; mso-number-format:'0';"><?= $saldoAwal ?></td>
  </tr>
  <?php endif; ?>
  <?php
  $no = 1;
  $totalMasuk = 0; $totalNilaiMasuk = 0;
  $totalKeluar = 0; $totalNilaiKeluar = 0;
  foreach ($kartuData as $r):
      if ($r['tipe'] === 'masuk') {
          $saldoBerjalan += $r['qty'];
          $totalMasuk += $r['qty'];
          $totalNilaiMasuk += $r['jumlah_harga'];
      } else {
        $saldoBerjalan = max(0, $saldoBerjalan - $r['qty']);
          $totalKeluar += $r['qty'];
          $totalNilaiKeluar += $r['jumlah_harga'];
      }
      $fifoQtyLines = formatFifoLines($r['fifo_details'] ?? [], 'qty');
      $fifoHargaLines = formatFifoLines($r['fifo_details'] ?? [], 'harga_satuan');
      $fifoJumlahLines = formatFifoLines($r['fifo_details'] ?? [], 'jumlah_harga');
  ?>
  <tr>
    <td style="text-align:center; border:1px solid #000; padding:4px;"><?= $no++ ?></td>
    <td style="border:1px solid #000; padding:4px;"><?= htmlspecialchars($r['tanggal']) ?></td>
    <td style="border:1px solid #000; padding:4px;"><?= htmlspecialchars($r['nomor_dokumen'] ?? '') ?></td>
    <?php if ($r['tipe'] === 'masuk'): ?>
      <td style="text-align:center; border:1px solid #000; padding:4px; mso-number-format:'0';"><?= $r['qty'] ?></td>
      <td style="text-align:right; border:1px solid #000; padding:4px; mso-number-format:'#,##0';"><?= (int)$r['harga_satuan'] ?></td>
      <td style="text-align:right; border:1px solid #000; padding:4px; mso-number-format:'#,##0';"><?= (int)$r['jumlah_harga'] ?></td>
      <td style="border:1px solid #000; padding:4px;"></td>
      <td style="border:1px solid #000; padding:4px;"></td>
      <td style="border:1px solid #000; padding:4px;"></td>
    <?php else: ?>
      <td style="border:1px solid #000; padding:4px;"></td>
      <td style="border:1px solid #000; padding:4px;"></td>
      <td style="border:1px solid #000; padding:4px;"></td>
      <td style="text-align:center; border:1px solid #000; padding:4px; mso-number-format:'0';">
        <?php if (!empty($fifoQtyLines)): ?>
          <?php foreach ($fifoQtyLines as $line): ?>
            <div><?= $line ?></div>
          <?php endforeach; ?>
        <?php else: ?>
          <?= (int)$r['qty'] ?>
        <?php endif; ?>
      </td>
      <td style="text-align:right; border:1px solid #000; padding:4px; mso-number-format:'#,##0';">
        <?php if (!empty($fifoHargaLines)): ?>
          <?php foreach ($fifoHargaLines as $line): ?>
            <div><?= $line ?></div>
          <?php endforeach; ?>
        <?php else: ?>
          <?= (int)$r['harga_satuan'] ?>
        <?php endif; ?>
      </td>
      <td style="text-align:right; border:1px solid #000; padding:4px; mso-number-format:'#,##0';">
        <?php if (!empty($fifoJumlahLines)): ?>
          <?php foreach ($fifoJumlahLines as $line): ?>
            <div><?= $line ?></div>
          <?php endforeach; ?>
        <?php else: ?>
          <?= (int)$r['jumlah_harga'] ?>
        <?php endif; ?>
      </td>
    <?php endif; ?>
    <td style="text-align:center; border:1px solid #000; padding:4px; mso-number-format:'0';"><?= $saldoBerjalan ?></td>
  </tr>
  <?php endforeach; ?>
  <tr style="font-weight:bold; background-color:#e9ecef;">
    <td colspan="3" style="text-align:right; border:1px solid #000; padding:5px;">JUMLAH</td>
    <td style="text-align:center; border:1px solid #000; padding:5px; mso-number-format:'0';"><?= $totalMasuk ?></td>
    <td style="border:1px solid #000; padding:5px;"></td>
    <td style="text-align:right; border:1px solid #000; padding:5px; mso-number-format:'#,##0';"><?= $totalNilaiMasuk ?></td>
    <td style="text-align:center; border:1px solid #000; padding:5px; mso-number-format:'0';"><?= $totalKeluar ?></td>
    <td style="border:1px solid #000; padding:5px;"></td>
    <td style="text-align:right; border:1px solid #000; padding:5px; mso-number-format:'#,##0';"><?= $totalNilaiKeluar ?></td>
    <td style="text-align:center; border:1px solid #000; padding:5px; mso-number-format:'0';"><?= $saldoBerjalan ?></td>
  </tr>
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
    <div class="topbar-title"><i class="bi bi-card-list me-2"></i>Kartu Persediaan Barang</div>
  </div>
  <div class="page-content">

    <!-- Filter -->
    <div class="card mb-3">
      <div class="card-body py-3">
        <form method="GET" id="filterForm" class="row g-2 align-items-end">
          <?php if ($role === 'superadmin'): ?>
            <div class="col-md-2">
              <label class="form-label mb-1 small fw-semibold">Bagian</label>
              <select name="id_bagian" class="form-select form-select-sm">
                <option value="0" <?= $f_bagian == 0 ? 'selected' : '' ?>>Sekretariat Daerah</option>
                <?php $bagianList->data_seek(0); while ($bg = $bagianList->fetch_assoc()): ?>
                  <option value="<?= $bg['id'] ?>" <?= $f_bagian == $bg['id'] ? 'selected' : '' ?>><?= htmlspecialchars($bg['nama']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          <?php else: ?>
            <input type="hidden" name="id_bagian" value="<?= $f_bagian ?>">
          <?php endif; ?>

          <div class="col-md-2">
            <label class="form-label mb-1 small fw-semibold">Jenis Barang</label>
            <select name="id_jenis" class="form-select form-select-sm" id="selectJenis" onchange="this.form.submit()">
              <option value="0">-- Pilih Jenis --</option>
              <?php $jenisList->data_seek(0); while ($j = $jenisList->fetch_assoc()): ?>
                <option value="<?= $j['id'] ?>" <?= $f_id_jenis == $j['id'] ? 'selected' : '' ?>><?= htmlspecialchars($j['nama_jenis']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label mb-1 small fw-semibold">Nama Barang</label>
            <select name="id_barang" class="form-select form-select-sm" <?= !$f_id_jenis ? 'disabled' : '' ?>>
              <option value="0">-- Pilih Barang --</option>
              <?php if ($barangList): $barangList->data_seek(0); while ($b = $barangList->fetch_assoc()): ?>
                <option value="<?= $b['id'] ?>" <?= $f_id_barang == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nama_barang']) ?></option>
              <?php endwhile; endif; ?>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label mb-1 small fw-semibold">Dari</label>
            <input type="date" name="dari" class="form-control form-control-sm" value="<?= htmlspecialchars($f_dari) ?>">
          </div>

          <div class="col-md-2">
            <label class="form-label mb-1 small fw-semibold">Sampai</label>
            <input type="date" name="sampai" class="form-control form-control-sm" value="<?= htmlspecialchars($f_sampai) ?>">
          </div>

          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-funnel me-1"></i>Tampilkan
            </button>
            <?php if ($f_id_barang): ?>
              <a href="?<?= http_build_query(array_merge($_GET, ['export' => 1])) ?>" class="btn btn-success btn-sm ms-1">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
              </a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- Tabel Kartu -->
    <div class="card">
      <?php if ($f_id_barang && $infoBarang): ?>
        <div class="card-header text-center py-3">
          <div class="fw-bold" style="font-size:1.1rem">KARTU PERSEDIAAN BARANG</div>
          <div class="fw-bold mt-1"><?= htmlspecialchars($infoBarang['nama_barang']) ?></div>
          <div class="fw-semibold"><?= htmlspecialchars($labelBagianLaporan) ?></div>
          <div class="text-muted mt-1" style="font-size:.85rem">
            Periode: <?= date('d/m/Y', strtotime($f_dari)) ?> s.d. <?= date('d/m/Y', strtotime($f_sampai)) ?>
          </div>
        </div>
      <?php else: ?>
        <div class="card-header py-2">
          <span class="fw-semibold text-muted"><i class="bi bi-info-circle me-1"></i>Pilih jenis dan nama barang untuk menampilkan kartu persediaan.</span>
        </div>
      <?php endif; ?>

      <div class="table-responsive p-3">
        <table class="table table-bordered table-sm align-middle mb-0" style="font-size:.8rem;">
          <thead class="text-center align-middle table-primary">
            <tr>
              <th rowspan="2">NO URUT INPUT</th>
              <th rowspan="2">TANGGAL</th>
              <th rowspan="2">NOMOR DOKUMEN</th>
              <th colspan="3" style="background-color:#cfe2ff;">MASUK</th>
              <th colspan="3" style="background-color:#f8d7da;">KELUAR</th>
              <th rowspan="2">SISA UNIT</th>
            </tr>
            <tr class="text-center">
              <th style="background-color:#cfe2ff; font-size:.7rem;">UNIT MASUK</th>
              <th style="background-color:#cfe2ff; font-size:.7rem;">HARGA SATUAN (Rp)</th>
              <th style="background-color:#cfe2ff; font-size:.7rem;">JUMLAH HARGA (Rp)</th>
              <th style="background-color:#f8d7da; font-size:.7rem;">UNIT KELUAR</th>
              <th style="background-color:#f8d7da; font-size:.7rem;">HARGA SATUAN (Rp)</th>
              <th style="background-color:#f8d7da; font-size:.7rem;">JUMLAH HARGA (Rp)</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$f_id_barang): ?>
              <tr>
                <td colspan="10" class="text-center text-muted py-5">
                  <i class="bi bi-card-list fs-3 d-block mb-2"></i>
                  Pilih Jenis dan Nama Barang serta tentukan periode untuk menampilkan kartu persediaan.
                </td>
              </tr>
            <?php elseif (empty($kartuData) && $saldoAwal == 0): ?>
              <tr>
                <td colspan="10" class="text-center text-muted py-5">
                  <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                  Tidak ada data transaksi pada periode ini.
                </td>
              </tr>
            <?php else: ?>
              <?php if ($saldoAwal > 0): ?>
                <tr class="text-muted fst-italic">
                  <td></td>
                  <td></td>
                  <td>Saldo Awal</td>
                  <td colspan="6"></td>
                  <td class="text-center fw-semibold"><?= number_format($saldoAwal, 0, ',', '.') ?></td>
                </tr>
              <?php endif; ?>
              <?php
              $no = 1;
              $saldoBerjalan = $saldoAwal;
              $totalMasuk = 0; $totalNilaiMasuk = 0;
              $totalKeluar = 0; $totalNilaiKeluar = 0;
              foreach ($kartuData as $r):
                  if ($r['tipe'] === 'masuk') {
                      $saldoBerjalan += $r['qty'];
                      $totalMasuk += $r['qty'];
                      $totalNilaiMasuk += $r['jumlah_harga'];
                  } else {
                    $saldoBerjalan = max(0, $saldoBerjalan - $r['qty']);
                      $totalKeluar += $r['qty'];
                      $totalNilaiKeluar += $r['jumlah_harga'];
                  }
                  $fifoQtyLines = formatFifoLines($r['fifo_details'] ?? [], 'qty');
                  $fifoHargaLines = formatFifoLines($r['fifo_details'] ?? [], 'harga_satuan');
                  $fifoJumlahLines = formatFifoLines($r['fifo_details'] ?? [], 'jumlah_harga');
              ?>
                <tr class="text-dark <?= $r['tipe'] === 'masuk' ? '' : '' ?>">
                  <td class="text-center"><?= $no++ ?></td>
                  <td><?= formatTanggal($r['tanggal']) ?></td>
                  <td><?= htmlspecialchars($r['nomor_dokumen'] ?? '') ?></td>
                  <?php if ($r['tipe'] === 'masuk'): ?>
                    <td class="text-center" style="background-color:#f0f7ff;"><?= number_format($r['qty'], 0, ',', '.') ?></td>
                    <td class="text-end" style="background-color:#f0f7ff;"><?= number_format($r['harga_satuan'], 0, ',', '.') ?></td>
                    <td class="text-end fw-semibold" style="background-color:#f0f7ff;"><?= number_format($r['jumlah_harga'], 0, ',', '.') ?></td>
                    <td style="background-color:#fff5f5;"></td>
                    <td style="background-color:#fff5f5;"></td>
                    <td style="background-color:#fff5f5;"></td>
                  <?php else: ?>
                    <td style="background-color:#f0f7ff;"></td>
                    <td style="background-color:#f0f7ff;"></td>
                    <td style="background-color:#f0f7ff;"></td>
                    <td class="text-center" style="background-color:#fff5f5;">
                      <?php if (!empty($fifoQtyLines)): ?>
                        <?php foreach ($fifoQtyLines as $line): ?>
                          <div><?= $line ?></div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <?= number_format($r['qty'], 0, ',', '.') ?>
                      <?php endif; ?>
                    </td>
                    <td class="text-end" style="background-color:#fff5f5;">
                      <?php if (!empty($fifoHargaLines)): ?>
                        <?php foreach ($fifoHargaLines as $line): ?>
                          <div><?= $line ?></div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <?= number_format($r['harga_satuan'], 0, ',', '.') ?>
                      <?php endif; ?>
                    </td>
                    <td class="text-end fw-semibold" style="background-color:#fff5f5;">
                      <?php if (!empty($fifoJumlahLines)): ?>
                        <?php foreach ($fifoJumlahLines as $line): ?>
                          <div><?= $line ?></div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <?= number_format($r['jumlah_harga'], 0, ',', '.') ?>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                  <td class="text-center fw-semibold <?= $saldoBerjalan < 0 ? 'text-danger' : '' ?>"><?= number_format($saldoBerjalan, 0, ',', '.') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <?php if ($f_id_barang && (!empty($kartuData) || $saldoAwal > 0)): ?>
            <tfoot class="table-secondary fw-bold text-center">
              <tr>
                <td colspan="3" class="text-end">JUMLAH</td>
                <td><?= number_format($totalMasuk, 0, ',', '.') ?></td>
                <td></td>
                <td class="text-end"><?= number_format($totalNilaiMasuk, 0, ',', '.') ?></td>
                <td><?= number_format($totalKeluar, 0, ',', '.') ?></td>
                <td></td>
                <td class="text-end"><?= number_format($totalNilaiKeluar, 0, ',', '.') ?></td>
                <td class="<?= $saldoBerjalan < 0 ? 'text-danger' : '' ?>"><?= number_format($saldoBerjalan, 0, ',', '.') ?></td>
              </tr>
            </tfoot>
          <?php endif; ?>
        </table>
      </div>

      <?php if ($f_id_barang && $infoBarang): ?>
        <div class="card-footer text-muted small py-2 px-3">
          <i class="bi bi-info-circle me-1"></i>
          Kode Barang: <strong><?= htmlspecialchars($infoBarang['kode_barang']) ?></strong> &nbsp;|&nbsp;
          Satuan: <strong><?= htmlspecialchars($infoBarang['satuan']) ?></strong> &nbsp;|&nbsp;
          Jenis: <strong><?= htmlspecialchars($infoBarang['nama_jenis']) ?></strong>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
