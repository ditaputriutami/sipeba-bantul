<?php
/**
 * Laporan Rekonsiliasi Persediaan
 * Kolom: Nama Barang, Saldo Awal, Penambahan (Pembelian), Pengurangan, Saldo Akhir, Harga Satuan Akhir (FIFO), Nilai
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
$pageTitle = 'Rekonsiliasi Persediaan';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

$f_bagian   = ($role === 'superadmin') ? (int)($_GET['id_bagian'] ?? 0) : $id_bagian;
$f_tahun    = (int)($_GET['tahun'] ?? date('Y'));

$bagianFilter = '';
if ($f_bagian) $bagianFilter = "AND id_bagian=$f_bagian";
if ($role !== 'superadmin') $bagianFilter = "AND id_bagian=$id_bagian";

// Saldo awal = stok awal tahun (total penerimaan disetujui sebelum tahun berjalan - total pengurangan disetujui sebelum tahun berjalan)
// Penambahan = penerimaan disetujui selama tahun $f_tahun
// Pengurangan = pengurangan disetujui selama tahun $f_tahun
// Saldo akhir = saldo awal + penambahan - pengurangan (atau ambil dari stok_current)

$rekonsiliasi = $conn->query("
    SELECT
        b.id, b.kode_barang, b.nama_barang, b.satuan,
        /* Saldo Awal: stok disetujui sebelum tahun berjalan */
        COALESCE((SELECT SUM(p.jumlah) FROM penerimaan p WHERE p.id_barang=b.id AND p.status='disetujui' AND YEAR(p.tanggal) < $f_tahun $bagianFilter),0)
        - COALESCE((SELECT SUM(p.jumlah) FROM pengurangan p WHERE p.id_barang=b.id AND p.status='disetujui' AND YEAR(p.tanggal) < $f_tahun $bagianFilter),0)
        AS saldo_awal,
        /* Penambahan tahun berjalan */
        COALESCE((SELECT SUM(p.jumlah) FROM penerimaan p WHERE p.id_barang=b.id AND p.status='disetujui' AND YEAR(p.tanggal)=$f_tahun $bagianFilter),0) AS penambahan,
        /* Nilai penambahan */
        COALESCE((SELECT SUM(p.jumlah_harga) FROM penerimaan p WHERE p.id_barang=b.id AND p.status='disetujui' AND YEAR(p.tanggal)=$f_tahun $bagianFilter),0) AS nilai_penambahan,
        /* Pengurangan tahun berjalan */
        COALESCE((SELECT SUM(p.jumlah) FROM pengurangan p WHERE p.id_barang=b.id AND p.status='disetujui' AND YEAR(p.tanggal)=$f_tahun $bagianFilter),0) AS pengurangan_qty,
        /* Nilai pengurangan (FIFO) */
        COALESCE((SELECT SUM(pd.jumlah_dipotong*pd.harga_satuan) FROM pengurangan pq JOIN pengurangan_detail pd ON pd.id_pengurangan=pq.id WHERE pq.id_barang=b.id AND pq.status='disetujui' AND YEAR(pq.tanggal)=$f_tahun $bagianFilter),0) AS nilai_pengurangan,
        /* Stok akhir dari stok_current */
        COALESCE((SELECT SUM(sc.stok) FROM stok_current sc WHERE sc.id_barang=b.id $bagianFilter),0) AS stok_akhir
    FROM barang b
    HAVING (saldo_awal + penambahan + pengurangan_qty) > 0
    ORDER BY b.nama_barang
");

$bagianList = ($role === 'superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;
$years = range(date('Y')-2, date('Y')+1);

// Export
if (isset($_GET['export'])) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="rekonsiliasi_' . $f_tahun . '.xls"');
    echo "\xEF\xBB\xBF";
    echo "REKONSILIASI PERSEDIAAN BARANG\t\t\t\t\t\t\n";
    echo "Tahun: $f_tahun\t\t\t\t\t\t\n\n";
    echo "No\tKode Barang\tNama Barang\tSatuan\tSaldo Awal\tPenambahan (Qty)\tNilai Penambahan\tPengurangan (Qty)\tNilai Pengurangan\tSaldo Akhir\n";
    $no=1; $rekonsiliasi->data_seek(0);
    while($r=$rekonsiliasi->fetch_assoc()) {
        echo "$no\t{$r['kode_barang']}\t{$r['nama_barang']}\t{$r['satuan']}\t{$r['saldo_awal']}\t{$r['penambahan']}\t{$r['nilai_penambahan']}\t{$r['pengurangan_qty']}\t{$r['nilai_pengurangan']}\t{$r['stok_akhir']}\n";
        $no++;
    }
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
            <label class="form-label fw-semibold">Tahun</label>
            <select name="tahun" class="form-select form-select-sm">
              <?php foreach($years as $y): ?><option value="<?=$y?>" <?=$f_tahun==$y?'selected':''?>><?=$y?></option><?php endforeach; ?>
            </select>
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
        <div class="fw-bold">LAPORAN REKONSILIASI PERSEDIAAN BARANG TAHUN <?=$f_tahun?></div>
        <div class="text-muted" style="font-size:.85rem">Sekretariat Daerah Kabupaten Bantul</div>
      </div>
      <div class="table-wrapper">
        <table class="table table-bordered table-sm" style="font-size:.8rem">
          <thead>
            <tr class="table-primary text-center">
              <th rowspan="2">No</th>
              <th rowspan="2">Kode Barang</th>
              <th rowspan="2">Nama Barang</th>
              <th rowspan="2">Satuan</th>
              <th rowspan="2">Saldo Awal</th>
              <th colspan="2">Penambahan (Pembelian)</th>
              <th colspan="2">Pengurangan (Pemakaian)</th>
              <th rowspan="2">Saldo Akhir</th>
            </tr>
            <tr class="table-primary text-center">
              <th>Qty</th><th>Nilai (Rp)</th>
              <th>Qty</th><th>Nilai (Rp)</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no=1; $found=false;
            $totSaldoAwal=0; $totPenambahan=0; $totNilaiPen=0;
            $totPengurangan=0; $totNilaiPeng=0; $totSaldoAkhir=0;
            while($r=$rekonsiliasi->fetch_assoc()): $found=true;
            $totSaldoAwal+=$r['saldo_awal']; $totPenambahan+=$r['penambahan'];
            $totNilaiPen+=$r['nilai_penambahan']; $totPengurangan+=$r['pengurangan_qty'];
            $totNilaiPeng+=$r['nilai_pengurangan']; $totSaldoAkhir+=$r['stok_akhir'];
            ?>
            <tr>
              <td class="text-center"><?=$no++?></td>
              <td class="text-center"><code><?=htmlspecialchars($r['kode_barang'])?></code></td>
              <td><?=htmlspecialchars($r['nama_barang'])?></td>
              <td class="text-center"><?=htmlspecialchars($r['satuan'])?></td>
              <td class="text-center"><?=number_format($r['saldo_awal'])?></td>
              <td class="text-center text-success"><?=number_format($r['penambahan'])?></td>
              <td class="text-end"><?=formatRupiah($r['nilai_penambahan'])?></td>
              <td class="text-center text-danger"><?=number_format($r['pengurangan_qty'])?></td>
              <td class="text-end"><?=formatRupiah($r['nilai_pengurangan'])?></td>
              <td class="text-center fw-bold"><?=number_format($r['stok_akhir'])?></td>
            </tr>
            <?php endwhile; if(!$found): ?>
            <tr><td colspan="10" class="text-center text-muted py-4">Tidak ada data untuk ditampilkan.</td></tr>
            <?php endif; ?>
          </tbody>
          <?php if($found): ?>
          <tfoot>
            <tr class="table-secondary fw-bold">
              <td colspan="4" class="text-end">TOTAL</td>
              <td class="text-center"><?=number_format($totSaldoAwal)?></td>
              <td class="text-center text-success"><?=number_format($totPenambahan)?></td>
              <td class="text-end"><?=formatRupiah($totNilaiPen)?></td>
              <td class="text-center text-danger"><?=number_format($totPengurangan)?></td>
              <td class="text-end"><?=formatRupiah($totNilaiPeng)?></td>
              <td class="text-center"><?=number_format($totSaldoAkhir)?></td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
