<?php

/**
 * Laporan Detail Stock Opname per Jenis Barang
 * Format Sederhana sesuai standar pelaporan
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
$f_tahun = (int)($_GET['tahun'] ?? date('Y'));
$f_dari_tanggal = $_GET['dari_tanggal'] ?? '';
$f_sampai_tanggal = $_GET['sampai_tanggal'] ?? '';

$role = getUserRole();
$user_bagian = getUserBagian();
$user = getCurrentUser();
if ($role !== 'superadmin') {
  // Sekretariat Daerah (id=9) dapat melihat seluruh bagian.
  $f_bagian = ((int)$user_bagian === 9) ? 0 : (int)$user_bagian;
}

$jenis = $id_jenis ? $conn->query("SELECT * FROM jenis_barang WHERE id=$id_jenis")->fetch_assoc() : null;
$bagian = $f_bagian ? $conn->query("SELECT * FROM bagian WHERE id=$f_bagian")->fetch_assoc() : null;

// Tentukan mode filter: jika tanggal diisi, gunakan filter tanggal; jika tidak, gunakan filter tahun
$use_date_filter = !empty($f_dari_tanggal) && !empty($f_sampai_tanggal);

if ($use_date_filter) {
  $where_tanggal = " AND so.tanggal BETWEEN '$f_dari_tanggal' AND '$f_sampai_tanggal'";
  $periode_display = strtoupper(formatTanggalIndonesia($f_dari_tanggal)) . ' s/d ' . strtoupper(formatTanggalIndonesia($f_sampai_tanggal));
} else {
  $where_tanggal = " AND YEAR(so.tanggal) = $f_tahun";
  $periode_display = '01 JANUARI ' . $f_tahun . ' s/d 31 DESEMBER ' . $f_tahun;
}

$where_bagian = $f_bagian ? " AND id_bagian=$f_bagian" : "";
$where_bagian_so = $f_bagian ? " AND so.id_bagian=$f_bagian" : "";
$where_bagian_p = $f_bagian ? " AND p.id_bagian=$f_bagian" : "";
$where_bagian_p2 = $f_bagian ? " AND p2.id_bagian=$f_bagian" : "";
$where_bagian_pr = $f_bagian ? " AND pr.id_bagian=$f_bagian" : "";
$where_jenis = $id_jenis ? " AND b.id_jenis_barang = $id_jenis" : "";

$kondisi_penerimaan_sampai = $use_date_filter
  ? "p.tanggal <= '$f_sampai_tanggal'"
  : "YEAR(p.tanggal) <= $f_tahun";

$kondisi_pengurangan_sampai = $use_date_filter
  ? "pr.tanggal <= '$f_sampai_tanggal'"
  : "YEAR(pr.tanggal) <= $f_tahun";

$kondisi_harga_terakhir = $use_date_filter
  ? "p2.tanggal <= '$f_sampai_tanggal'"
  : "YEAR(p2.tanggal) <= $f_tahun";

// Ambil tahun-tahun yang ada datanya
$years_query = "
  SELECT DISTINCT tahun FROM (
    SELECT YEAR(tanggal) as tahun FROM penerimaan WHERE status = 'disetujui' $where_bagian
    UNION
    SELECT YEAR(tanggal) as tahun FROM pengurangan WHERE status IN ('disetujui','disetujui sebagian') $where_bagian
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
if (empty($years)) {
  $years = [date('Y')];
}
if (!in_array($f_tahun, $years)) {
  $f_tahun = $years[0];
}

// Nama bagian untuk display
$nama_bagian_display = 'SEKRETARIAT DAERAH';
if ($f_bagian) {
  $nama_bagian_display = $bagian ? strtoupper(trim(preg_replace('/^Bagian\s+/i', '', $bagian['nama']))) : '';
} elseif ($user_bagian == 9) {
  $nama_bagian_display = 'SEKRETARIAT DAERAH';
}

// Query Detail: Ambil posisi stok per barang dari transaksi disetujui sampai akhir periode
$query = "
    SELECT 
        b.id as id_barang,
        b.kode_barang,
        b.nama_barang,
        b.satuan,
        j.nama_jenis,
        j.kode_jenis,
        (
            COALESCE((
                SELECT SUM(p.jumlah)
                FROM penerimaan p
                WHERE p.id_barang = b.id
                  AND p.status = 'disetujui'
                  AND $kondisi_penerimaan_sampai
                  $where_bagian_p
            ), 0)
            -
            COALESCE((
                SELECT SUM(pd.jumlah_dipotong)
                FROM pengurangan_detail pd
                JOIN pengurangan pr ON pd.id_pengurangan = pr.id
                WHERE pr.id_barang = b.id
                  AND pr.status IN ('disetujui','disetujui sebagian')
                  AND $kondisi_pengurangan_sampai
                  $where_bagian_pr
            ), 0)
        ) as jumlah,
        COALESCE((
            SELECT p2.harga_satuan
            FROM penerimaan p2
            WHERE p2.id_barang = b.id
              AND p2.status = 'disetujui'
              AND $kondisi_harga_terakhir
              $where_bagian_p2
            ORDER BY p2.tanggal DESC, p2.id DESC
            LIMIT 1
        ), 0) as harga_satuan,
        (
            SELECT GROUP_CONCAT(DISTINCT COALESCE(so.keterangan, '-') SEPARATOR '; ')
            FROM stock_opname so
            WHERE so.id_barang = b.id
              AND so.status = 'disetujui'
              $where_tanggal
              $where_bagian_so
        ) as keterangan
    FROM barang b
    JOIN jenis_barang j ON b.id_jenis_barang = j.id
    WHERE 1=1
        $where_jenis
    GROUP BY b.id, b.kode_barang, b.nama_barang, b.satuan, j.nama_jenis, j.kode_jenis
    HAVING jumlah > 0
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
  $filename = "stock_opname_" . ($jenis ? strtolower(str_replace(' ', '_', $jenis['nama_jenis'])) : 'all') . "_" . ($use_date_filter ? date('Ymd', strtotime($f_dari_tanggal)) . "_" . date('Ymd', strtotime($f_sampai_tanggal)) : $f_tahun) . ".xls";
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  echo "\xEF\xBB\xBF";
?>
  <table border="1" style="border-collapse: collapse; width:100%;">
    <thead>
      <tr>
        <th colspan="13" style="text-align:center; font-weight:bold; font-size:13pt; padding:8px; border:1px solid #000;">JUMLAH PERSEDIAAN/STOCK OPNAME</th>
      </tr>
      <tr>
        <th colspan="13" style="text-align:center; font-weight:bold; font-size:11pt; padding:6px; border:1px solid #000;">BAGIAN <?= strtoupper($nama_bagian_display) ?></th>
      </tr>
      <tr>
        <th colspan="13" style="text-align:center; font-weight:bold; font-size:10pt; padding:6px; border:1px solid #000;">PERIODE: <?= strtoupper($periode_display) ?></th>
      </tr>
      <tr style="background-color:#fff; font-weight:bold; text-align:center; vertical-align:middle;">
        <th style="border:1px solid #000; padding:6px; width:35px;">NO</th>
        <th style="border:1px solid #000; padding:6px; width:200px;">NAMA BARANG</th>
        <th colspan="7" style="border:1px solid #000; padding:6px;">KODE BARANG</th>
        <th style="border:1px solid #000; padding:6px; width:60px;">JUMLAH</th>
        <th style="border:1px solid #000; padding:6px; width:65px;">SATUAN</th>
        <th style="border:1px solid #000; padding:6px; width:110px;">HARGA SATUAN (Rp)</th>
        <th style="border:1px solid #000; padding:6px; width:110px;">TOTAL (Rp)</th>
        <th style="border:1px solid #000; padding:6px; width:120px;">KETERANGAN</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $no = 1;
      $grand_total = 0;
      $total_qty = 0;
      foreach ($items as $nama_jenis => $rows):
        $kj_raw = $rows[0]['kode_jenis'];
        if (strpos($kj_raw, '.') !== false) {
          $kj_parts = explode('.', $kj_raw);
        } elseif (strlen($kj_raw) >= 9) {
          $kj_parts = [substr($kj_raw, 0, 1), substr($kj_raw, 1, 1), substr($kj_raw, 2, 1), substr($kj_raw, 3, 2), substr($kj_raw, 5, 2), substr($kj_raw, 7, 2)];
        } else {
          $kj_parts = [$kj_raw];
        }
        if (count($kj_parts) > 7) {
          $rem = array_slice($kj_parts, 6);
          $kj_parts = array_slice($kj_parts, 0, 6);
          $kj_parts[] = implode('.', $rem);
        } else {
          $kj_parts = array_pad($kj_parts, 7, '');
        }
      ?>
        <tr style="font-weight:bold; background-color:#f5f5f5;">
          <td style="border:1px solid #000; padding:5px;"></td>
          <td style="border:1px solid #000; padding:5px; font-weight:bold; text-transform:uppercase;"><?= strtoupper(htmlspecialchars($nama_jenis)) ?></td>
          <?php foreach ($kj_parts as $kp): ?>
            <td align="center" style="border:1px solid #000; mso-number-format:'\@'; padding:4px; font-weight:bold;"><?= $kp ?></td>
          <?php endforeach; ?>
          <td style="border:1px solid #000; padding:5px;"></td>
          <td style="border:1px solid #000; padding:5px;"></td>
          <td style="border:1px solid #000; padding:5px;"></td>
          <td style="border:1px solid #000; padding:5px;"></td>
          <td style="border:1px solid #000; padding:5px;"></td>
        </tr>
        <?php foreach ($rows as $r):
          $total_rp = $r['jumlah'] * $r['harga_satuan'];
          $grand_total += $total_rp;
          $total_qty += $r['jumlah'];

          $kode_raw = $r['kode_barang'];
          if (strpos($kode_raw, '.') !== false) {
            $kode_parts = explode('.', $kode_raw);
          } elseif (strlen($kode_raw) >= 9) {
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
          if (count($kode_parts) > 7) {
            $rem = array_slice($kode_parts, 6);
            $kode_parts = array_slice($kode_parts, 0, 6);
            $kode_parts[] = implode('.', $rem);
          } else {
            $kode_parts = array_pad($kode_parts, 7, '');
          }
        ?>
          <tr>
            <td align="center" style="border:1px solid #000; padding:5px;"><?= $no++ ?></td>
            <td style="border:1px solid #000; padding:5px;"><?= htmlspecialchars($r['nama_barang']) ?></td>
            <?php foreach ($kode_parts as $kp): ?>
              <td align="center" style="border:1px solid #000; mso-number-format:'\@'; padding:4px;"><?= $kp ?></td>
            <?php endforeach; ?>
            <td align="center" style="border:1px solid #000; padding:5px; mso-number-format:'0';"><?= $r['jumlah'] ?></td>
            <td align="center" style="border:1px solid #000; padding:5px;"><?= htmlspecialchars($r['satuan']) ?></td>
            <td align="right" style="border:1px solid #000; padding:5px; mso-number-format:'#,##0';"><?= $r['harga_satuan'] ?></td>
            <td align="right" style="border:1px solid #000; padding:5px; mso-number-format:'#,##0';"><?= $total_rp ?></td>
            <td style="border:1px solid #000; padding:5px;"><?= htmlspecialchars($r['keterangan'] ?? '-') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:bold;">
        <td colspan="2" style="border:1px solid #000; padding:6px;"></td>
        <td colspan="7" align="center" style="border:1px solid #000; padding:6px; font-weight:bold;">JUMLAH</td>
        <td align="center" style="border:1px solid #000; padding:6px; font-weight:bold; mso-number-format:'0';"><?= $total_qty ?></td>
        <td style="border:1px solid #000; padding:6px;"></td>
        <td style="border:1px solid #000; padding:6px;"></td>
        <td align="right" style="border:1px solid #000; mso-number-format:'#,##0'; font-weight:bold; padding:6px;"><?= $grand_total ?></td>
        <td style="border:1px solid #000;"></td>
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
    <?php if ($use_date_filter): ?>
      <div class="alert alert-info py-2 mb-3">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Filter Tanggal Aktif:</strong> <?= date('d/m/Y', strtotime($f_dari_tanggal)) ?> - <?= date('d/m/Y', strtotime($f_sampai_tanggal)) ?>
        <a href="?<?= http_build_query(array_filter(['id_jenis' => $id_jenis, 'id_bagian' => $f_bagian, 'tahun' => $f_tahun])) ?>" class="btn btn-sm btn-outline-secondary ms-2">
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
      <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
          <input type="hidden" name="id_jenis" value="<?= $id_jenis ?>">
          <?php if ($role === 'superadmin'): ?>
            <div class="col-md-3">
              <label class="form-label mb-1 small fw-semibold">Bagian</label>
              <select name="id_bagian" class="form-select form-select-sm">
                <option value="0" <?= $f_bagian == 0 ? 'selected' : '' ?>>Sekretariat Daerah</option>
                <?php
                $bagianList = $conn->query("SELECT * FROM bagian ORDER BY nama");
                while ($bg = $bagianList->fetch_assoc()):
                  if ((int)$bg['id'] === 9) continue;
                  $namaBagianOption = htmlspecialchars($bg['nama']);
                ?>
                  <option value="<?= $bg['id'] ?>" <?= $f_bagian == $bg['id'] ? 'selected' : '' ?>><?= $namaBagianOption ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          <?php else: ?>
            <input type="hidden" name="id_bagian" value="<?= $f_bagian ?>">
          <?php endif; ?>
          <div class="col-auto">
            <label class="form-label mb-1 small fw-semibold">Tahun</label>
            <select name="tahun" class="form-select form-select-sm">
              <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $f_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small fw-semibold">Dari Tanggal</label>
            <input type="date" name="dari_tanggal" class="form-control form-control-sm" value="<?= $f_dari_tanggal ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small fw-semibold">Sampai Tanggal</label>
            <input type="date" name="sampai_tanggal" class="form-control form-control-sm" value="<?= $f_sampai_tanggal ?>">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm me-1">
              <i class="bi bi-funnel me-1"></i>Filter
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="card mb-3">
      <div class="card-body py-2 d-flex justify-content-end align-items-center">
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
        <div class="fw-bold" style="font-size:1.1rem">JUMLAH PERSEDIAAN/STOCK OPNAME</div>
        <div class="fw-bold">Bagian <?= $nama_bagian_display ?></div>
        <div class="text-muted mt-1" style="font-size:.85rem">PERIODE: <?= $periode_display ?></div>
      </div>
      <div class="table-responsive p-3">
        <table class="table table-bordered table-sm align-middle mb-0" style="font-size:.8rem;">
          <thead class="bg-light text-center align-middle">
            <tr class="text-dark fw-bold">
              <th rowspan="2" style="min-width:40px; vertical-align:middle;">NO</th>
              <th rowspan="2" style="min-width:200px; vertical-align:middle;">NAMA BARANG</th>
              <th colspan="7" style="vertical-align:middle;">KODE BARANG</th>
              <th rowspan="2" style="min-width:80px; vertical-align:middle;">JUMLAH</th>
              <th rowspan="2" style="min-width:80px; vertical-align:middle;">SATUAN</th>
              <th rowspan="2" style="min-width:120px; vertical-align:middle;">HARGA SATUAN (Rp)</th>
              <th rowspan="2" style="min-width:120px; vertical-align:middle;">TOTAL (Rp)</th>
              <th rowspan="2" style="min-width:150px; vertical-align:middle;">KETERANGAN</th>
            </tr>
            <tr class="text-dark fw-bold" style="font-size:.7rem;">
              <th style="min-width:30px; padding:3px;"></th>
              <th style="min-width:30px; padding:3px;"></th>
              <th style="min-width:30px; padding:3px;"></th>
              <th style="min-width:30px; padding:3px;"></th>
              <th style="min-width:30px; padding:3px;"></th>
              <th style="min-width:30px; padding:3px;"></th>
              <th style="min-width:30px; padding:3px;"></th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no = 1;
            $grand_total = 0;
            $total_qty = 0;
            foreach ($items as $nama_jenis => $rows):
              $kj_raw = $rows[0]['kode_jenis'];
              if (strpos($kj_raw, '.') !== false) {
                $kj_parts = explode('.', $kj_raw);
              } elseif (strlen($kj_raw) >= 9) {
                $kj_parts = [substr($kj_raw, 0, 1), substr($kj_raw, 1, 1), substr($kj_raw, 2, 1), substr($kj_raw, 3, 2), substr($kj_raw, 5, 2), substr($kj_raw, 7, 2)];
              } else {
                $kj_parts = [$kj_raw];
              }
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
                  <td class="text-center text-nowrap" style="padding: 0 0.1rem;"><?= $kp ?></td>
                <?php endforeach; ?>
                <td colspan="5"></td>
              </tr>
              <?php foreach ($rows as $r):
                $total_rp = $r['jumlah'] * $r['harga_satuan'];
                $grand_total += $total_rp;
                $total_qty += $r['jumlah'];

                $kode_raw = $r['kode_barang'];
                if (strpos($kode_raw, '.') !== false) {
                  $kode_parts = explode('.', $kode_raw);
                } elseif (strlen($kode_raw) >= 9) {
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
                    <td class="text-center text-nowrap" style="padding: 0 0.1rem;"><?= $kp ?></td>
                  <?php endforeach; ?>
                  <td class="text-center"><?= number_format($r['jumlah'], 0, ',', '.') ?></td>
                  <td class="text-center"><?= htmlspecialchars($r['satuan']) ?></td>
                  <td class="text-end"><?= number_format($r['harga_satuan'], 0, ',', '.') ?></td>
                  <td class="text-end fw-semibold"><?= number_format($total_rp, 0, ',', '.') ?></td>
                  <td><?= htmlspecialchars($r['keterangan'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-secondary fw-bold text-dark">
            <tr>
              <td colspan="2"></td>
              <td colspan="7" class="text-center">JUMLAH</td>
              <td class="text-center"><?= number_format($total_qty, 0, ',', '.') ?></td>
              <td></td>
              <td></td>
              <td class="text-end"><?= number_format($grand_total, 0, ',', '.') ?></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>