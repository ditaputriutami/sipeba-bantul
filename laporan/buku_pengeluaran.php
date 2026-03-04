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
$f_dari    = $_GET['dari'] ?? date('Y-m-01');
$f_sampai  = $_GET['sampai'] ?? date('Y-m-d');

$where = "WHERE p.status='disetujui'";
if ($f_bagian) $where .= " AND p.id_bagian=$f_bagian";
if ($f_dari)   $where .= " AND p.tanggal >= '$f_dari'";
if ($f_sampai) $where .= " AND p.tanggal <= '$f_sampai'";

// Join dengan detail FIFO untuk tahu harga (ambil rata-rata tertimbang jika multi-batch)
$data = $conn->query("
    SELECT p.id, p.no_permintaan, p.tanggal, p.jumlah, p.penerima, p.tanggal_penyerahan,
           b.kode_barang, b.nama_barang, b.satuan, bg.nama as nama_bagian, u.nama as nama_input,
           COALESCE((SELECT SUM(pd.jumlah_dipotong * pd.harga_satuan) / SUM(pd.jumlah_dipotong) FROM pengurangan_detail pd WHERE pd.id_pengurangan=p.id), 0) as harga_rata_rata,
           COALESCE((SELECT SUM(pd.jumlah_dipotong * pd.harga_satuan) FROM pengurangan_detail pd WHERE pd.id_pengurangan=p.id), 0) as total_nilai
    FROM pengurangan p
    JOIN barang b ON p.id_barang=b.id
    JOIN bagian bg ON p.id_bagian=bg.id
    JOIN users u ON p.id_user=u.id
    $where
    ORDER BY p.tanggal ASC, p.id ASC
");

$bagianList = ($role === 'superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;

// Export Excel
if (isset($_GET['export'])) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="buku_pengeluaran_' . date('Ymd') . '.xls"');
    echo "\xEF\xBB\xBF";
    echo "BUKU PENGELUARAN BARANG PERSEDIAAN\t\t\t\t\t\t\n";
    echo "Periode: $f_dari s.d. $f_sampai\t\t\t\t\t\t\n\n";
    echo "No\tTanggal\tNama Barang\tKode Barang\tJumlah\tSatuan\tHarga Satuan (FIFO) (Rp)\tTotal Nilai (Rp)\tPenerima\tTanggal Penyerahan\n";
    $no = 1; $totalNilai = 0;
    $data->data_seek(0);
    while ($r = $data->fetch_assoc()) {
        $totalNilai += $r['total_nilai'];
        echo "$no\t{$r['tanggal']}\t{$r['nama_barang']}\t{$r['kode_barang']}\t{$r['jumlah']}\t{$r['satuan']}\t{$r['harga_rata_rata']}\t{$r['total_nilai']}\t{$r['penerima']}\t{$r['tanggal_penyerahan']}\n";
        $no++;
    }
    echo "TOTAL\t\t\t\t\t\t\t$totalNilai\t\t\n";
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
          <?php if($role==='superadmin'): ?>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Bagian</label>
            <select name="id_bagian" class="form-select form-select-sm">
              <option value="">Semua Bagian</option>
              <?php while($bg=$bagianList->fetch_assoc()): ?>
                <option value="<?=$bg['id']?>" <?=$f_bagian==$bg['id']?'selected':''?>><?=htmlspecialchars($bg['nama'])?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-auto">
            <label class="form-label fw-semibold">Dari</label>
            <input type="date" name="dari" class="form-control form-control-sm" value="<?=$f_dari?>">
          </div>
          <div class="col-auto">
            <label class="form-label fw-semibold">Sampai</label>
            <input type="date" name="sampai" class="form-control form-control-sm" value="<?=$f_sampai?>">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
            <a href="?<?=http_build_query(array_merge($_GET,['export'=>1]))?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
          </div>
        </form>
      </div>
    </div>
    <div class="card">
      <div class="card-header text-center">
        <div class="fw-bold">BUKU PENGELUARAN BARANG PERSEDIAAN</div>
        <div class="text-muted" style="font-size:.85rem">Periode: <?=formatTanggal($f_dari)?> s.d. <?=formatTanggal($f_sampai)?></div>
      </div>
      <div class="table-wrapper">
        <table class="table table-bordered table-sm" style="font-size:.82rem">
          <thead>
            <tr class="table-warning text-center">
              <th>No</th><th>Tanggal</th><th>Nama Barang</th><th>Kode Barang</th>
              <th>Jumlah</th><th>Satuan</th><th>Harga Satuan (FIFO)</th><th>Total Nilai</th>
              <th>Penerima</th><th>Tanggal Penyerahan</th>
            </tr>
          </thead>
          <tbody>
            <?php $no=1; $totalNilai=0; $found=false; while($r=$data->fetch_assoc()): $found=true; $totalNilai+=$r['total_nilai']; ?>
            <tr>
              <td class="text-center"><?=$no++?></td>
              <td><?=formatTanggal($r['tanggal'])?></td>
              <td><?=htmlspecialchars($r['nama_barang'])?></td>
              <td class="text-center"><code><?=htmlspecialchars($r['kode_barang'])?></code></td>
              <td class="text-center"><?=number_format($r['jumlah'])?></td>
              <td class="text-center"><?=htmlspecialchars($r['satuan'])?></td>
              <td class="text-end"><?=formatRupiah($r['harga_rata_rata'])?><br><small class="text-muted">(rata-rata FIFO)</small></td>
              <td class="text-end fw-semibold"><?=formatRupiah($r['total_nilai'])?></td>
              <td><?=htmlspecialchars($r['penerima']??'—')?></td>
              <td><?=$r['tanggal_penyerahan']?formatTanggal($r['tanggal_penyerahan']):'—'?></td>
            </tr>
            <?php endwhile; if(!$found): ?>
            <tr><td colspan="10" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Tidak ada data pengeluaran.</td></tr>
            <?php endif; ?>
          </tbody>
          <?php if($found): ?>
          <tfoot><tr class="table-secondary fw-bold">
            <td colspan="7" class="text-end">TOTAL NILAI PENGELUARAN</td>
            <td class="text-end text-danger"><?=formatRupiah($totalNilai)?></td>
            <td colspan="2"></td>
          </tr></tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
