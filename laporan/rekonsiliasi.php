<?php
/**
 * Laporan Rekonsiliasi Persediaan
 * Format: Sesuai dengan laporan aset (dikelompokkan per jenis barang)
 * Kolom: Saldo Awal, Penerimaan Aset (4 kategori), Pengurangan Aset (2 kategori), Saldo Akhir
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

// Query: ambil data per jenis barang dan barang-barangnya
$query = "
    SELECT 
        jb.id as id_jenis,
        jb.nama_jenis,
        jb.kategori,
        b.id as id_barang,
        b.kode_barang,
        b.nama_barang,
        b.satuan,
        
        /* === SALDO AWAL (Sebelum tahun berjalan) === */
        COALESCE((
            SELECT SUM(p.jumlah) 
            FROM penerimaan p 
            WHERE p.id_barang=b.id 
                AND p.status='disetujui' 
                AND YEAR(p.tanggal) < $f_tahun 
                $bagianFilter
        ), 0) AS saldo_awal_qty,
        
        COALESCE((
            SELECT SUM(p.jumlah * p.harga_satuan) 
            FROM penerimaan p 
            WHERE p.id_barang=b.id 
                AND p.status='disetujui' 
                AND YEAR(p.tanggal) < $f_tahun 
                $bagianFilter
        ), 0) AS saldo_awal_nilai,
        
        /* === PENGURANGAN SEBELUM TAHUN BERJALAN (untuk hitung saldo awal) === */
        COALESCE((
            SELECT SUM(pg.jumlah) 
            FROM pengurangan pg 
            WHERE pg.id_barang=b.id 
                AND pg.status='disetujui' 
                AND YEAR(pg.tanggal) < $f_tahun 
                $bagianFilter
        ), 0) AS pengurangan_awal_qty,
        
        COALESCE((
            SELECT SUM(pd.jumlah_dipotong * pd.harga_satuan) 
            FROM pengurangan pg 
            JOIN pengurangan_detail pd ON pd.id_pengurangan=pg.id 
            WHERE pg.id_barang=b.id 
                AND pg.status='disetujui' 
                AND YEAR(pg.tanggal) < $f_tahun 
                $bagianFilter
        ), 0) AS pengurangan_awal_nilai,
        
        /* === PENERIMAAN TAHUN BERJALAN - BELANJA MODAL === */
        COALESCE((
            SELECT SUM(p.jumlah) 
            FROM penerimaan p 
            WHERE p.id_barang=b.id 
                AND p.status='disetujui' 
                AND YEAR(p.tanggal) = $f_tahun 
                AND p.sumber='belanja_modal'
                $bagianFilter
        ), 0) AS belanja_modal_qty,
        
        COALESCE((
            SELECT SUM(p.jumlah * p.harga_satuan) 
            FROM penerimaan p 
            WHERE p.id_barang=b.id 
                AND p.status='disetujui' 
                AND YEAR(p.tanggal) = $f_tahun 
                AND p.sumber='belanja_modal'
                $bagianFilter
        ), 0) AS belanja_modal_nilai,
        
        /* === PENERIMAAN TAHUN BERJALAN - BELANJA BARANG/JASA === */
        COALESCE((
            SELECT SUM(p.jumlah) 
            FROM penerimaan p 
            WHERE p.id_barang=b.id 
                AND p.status='disetujui' 
                AND YEAR(p.tanggal) = $f_tahun 
                AND p.sumber='belanja_barang_jasa'
                $bagianFilter
        ), 0) AS belanja_barang_jasa_qty,
        
        COALESCE((
            SELECT SUM(p.jumlah * p.harga_satuan) 
            FROM penerimaan p 
            WHERE p.id_barang=b.id 
                AND p.status='disetujui' 
                AND YEAR(p.tanggal) = $f_tahun 
                AND p.sumber='belanja_barang_jasa'
                $bagianFilter
        ), 0) AS belanja_barang_jasa_nilai,
        
        /* === PENERIMAAN TAHUN BERJALAN - DROPPING === */
        COALESCE((
            SELECT SUM(p.jumlah) 
            FROM penerimaan p 
            WHERE p.id_barang=b.id 
                AND p.status='disetujui' 
                AND YEAR(p.tanggal) = $f_tahun 
                AND p.sumber='dropping'
                $bagianFilter
        ), 0) AS dropping_qty,
        
        COALESCE((
            SELECT SUM(p.jumlah * p.harga_satuan) 
            FROM penerimaan p 
            WHERE p.id_barang=b.id 
                AND p.status='disetujui' 
                AND YEAR(p.tanggal) = $f_tahun 
                AND p.sumber='dropping'
                $bagianFilter
        ), 0) AS dropping_nilai,
        
        /* === PENERIMAAN TAHUN BERJALAN - HIBAH === */
        COALESCE((
            SELECT SUM(p.jumlah) 
            FROM penerimaan p 
            WHERE p.id_barang=b.id 
                AND p.status='disetujui' 
                AND YEAR(p.tanggal) = $f_tahun 
                AND p.sumber='hibah'
                $bagianFilter
        ), 0) AS hibah_qty,
        
        COALESCE((
            SELECT SUM(p.jumlah * p.harga_satuan) 
            FROM penerimaan p 
            WHERE p.id_barang=b.id 
                AND p.status='disetujui' 
                AND YEAR(p.tanggal) = $f_tahun 
                AND p.sumber='hibah'
                $bagianFilter
        ), 0) AS hibah_nilai,
        
        /* === PENGURANGAN TAHUN BERJALAN - PENGHAPUSAN === */
        COALESCE((
            SELECT SUM(pg.jumlah) 
            FROM pengurangan pg 
            WHERE pg.id_barang=b.id 
                AND pg.status='disetujui' 
                AND YEAR(pg.tanggal) = $f_tahun 
                AND pg.jenis='penghapusan'
                $bagianFilter
        ), 0) AS penghapusan_qty,
        
        COALESCE((
            SELECT SUM(pd.jumlah_dipotong * pd.harga_satuan) 
            FROM pengurangan pg 
            JOIN pengurangan_detail pd ON pd.id_pengurangan=pg.id 
            WHERE pg.id_barang=b.id 
                AND pg.status='disetujui' 
                AND YEAR(pg.tanggal) = $f_tahun 
                AND pg.jenis='penghapusan'
                $bagianFilter
        ), 0) AS penghapusan_nilai,
        
        /* === PENGURANGAN TAHUN BERJALAN - MUTASI KELUAR === */
        COALESCE((
            SELECT SUM(pg.jumlah) 
            FROM pengurangan pg 
            WHERE pg.id_barang=b.id 
                AND pg.status='disetujui' 
                AND YEAR(pg.tanggal) = $f_tahun 
                AND pg.jenis='mutasi_keluar'
                $bagianFilter
        ), 0) AS mutasi_keluar_qty,
        
        COALESCE((
            SELECT SUM(pd.jumlah_dipotong * pd.harga_satuan) 
            FROM pengurangan pg 
            JOIN pengurangan_detail pd ON pd.id_pengurangan=pg.id 
            WHERE pg.id_barang=b.id 
                AND pg.status='disetujui' 
                AND YEAR(pg.tanggal) = $f_tahun 
                AND pg.jenis='mutasi_keluar'
                $bagianFilter
        ), 0) AS mutasi_keluar_nilai
        
    FROM jenis_barang jb
    LEFT JOIN barang b ON b.id_jenis_barang = jb.id
    ORDER BY 
        CASE WHEN jb.kategori='ASET TETAP' THEN 1 ELSE 2 END,
        jb.nama_jenis, 
        b.nama_barang
";

$result = $conn->query($query);

// Kelompokkan data per kategori (ASET TETAP/ASET LANCAR) -> jenis -> barang
$dataByKategori = [];
while ($row = $result->fetch_assoc()) {
    if (!$row['id_barang']) continue; // Skip jika tidak ada barang
    
    $kategori = $row['kategori'];
    $id_jenis = $row['id_jenis'];
    
    // Inisialisasi kategori jika belum ada
    if (!isset($dataByKategori[$kategori])) {
        $dataByKategori[$kategori] = [];
    }
    
    // Inisialisasi jenis dalam kategori jika belum ada
    if (!isset($dataByKategori[$kategori][$id_jenis])) {
        $dataByKategori[$kategori][$id_jenis] = [
            'nama_jenis' => $row['nama_jenis'],
            'barang' => []
        ];
    }
    
    // Hitung saldo awal dan akhir
    $saldo_awal_qty = $row['saldo_awal_qty'] - $row['pengurangan_awal_qty'];
    $saldo_awal_nilai = $row['saldo_awal_nilai'] - $row['pengurangan_awal_nilai'];
    
    $total_penerimaan_qty = $row['belanja_modal_qty'] + $row['belanja_barang_jasa_qty'] + $row['dropping_qty'] + $row['hibah_qty'];
    $total_penerimaan_nilai = $row['belanja_modal_nilai'] + $row['belanja_barang_jasa_nilai'] + $row['dropping_nilai'] + $row['hibah_nilai'];
    
    $total_pengurangan_qty = $row['penghapusan_qty'] + $row['mutasi_keluar_qty'];
    $total_pengurangan_nilai = $row['penghapusan_nilai'] + $row['mutasi_keluar_nilai'];
    
    $saldo_akhir_qty = $saldo_awal_qty + $total_penerimaan_qty - $total_pengurangan_qty;
    $saldo_akhir_nilai = $saldo_awal_nilai + $total_penerimaan_nilai - $total_pengurangan_nilai;
    
    // Skip barang yang tidak ada transaksi sama sekali
    if ($saldo_awal_qty == 0 && $total_penerimaan_qty == 0 && $total_pengurangan_qty == 0) {
        continue;
    }
    
    $dataByKategori[$kategori][$id_jenis]['barang'][] = [
        'kode_barang' => $row['kode_barang'],
        'nama_barang' => $row['nama_barang'],
        'satuan' => $row['satuan'],
        'saldo_awal_qty' => $saldo_awal_qty,
        'saldo_awal_nilai' => $saldo_awal_nilai,
        'belanja_modal_qty' => $row['belanja_modal_qty'],
        'belanja_modal_nilai' => $row['belanja_modal_nilai'],
        'belanja_barang_jasa_qty' => $row['belanja_barang_jasa_qty'],
        'belanja_barang_jasa_nilai' => $row['belanja_barang_jasa_nilai'],
        'dropping_qty' => $row['dropping_qty'],
        'dropping_nilai' => $row['dropping_nilai'],
        'hibah_qty' => $row['hibah_qty'],
        'hibah_nilai' => $row['hibah_nilai'],
        'penghapusan_qty' => $row['penghapusan_qty'],
        'penghapusan_nilai' => $row['penghapusan_nilai'],
        'mutasi_keluar_qty' => $row['mutasi_keluar_qty'],
        'mutasi_keluar_nilai' => $row['mutasi_keluar_nilai'],
        'saldo_akhir_qty' => $saldo_akhir_qty,
        'saldo_akhir_nilai' => $saldo_akhir_nilai,
    ];
}

// Hapus jenis yang tidak memiliki barang
foreach ($dataByKategori as $kat => $jenisList) {
    $dataByKategori[$kat] = array_filter($jenisList, function($jenis) {
        return count($jenis['barang']) > 0;
    });
    // Hapus kategori jika tidak ada jenis
    if (empty($dataByKategori[$kat])) {
        unset($dataByKategori[$kat]);
    }
}

$bagianList = ($role === 'superadmin') ? $conn->query("SELECT * FROM bagian ORDER BY nama") : null;
$years = range(date('Y')-2, date('Y')+1);

// Export Excel
if (isset($_GET['export'])) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="rekonsiliasi_' . $f_tahun . '.xls"');
    echo "\xEF\xBB\xBF";
    echo "LAPORAN REKONSILIASI PERSEDIAAN BARANG\n";
    echo "TAHUN: $f_tahun\n";
    echo "Sekretariat Daerah Kabupaten Bantul\n\n";
    
    echo "No\tURAIAN\tSATUAN\tSALDO AWAL\t\tPENAMBAHAN ASET\t\t\t\t\t\t\t\tPENGURRANGAN ASET\t\t\t\tSALDO AKHIR\t\tKETERANGAN\n";
    echo "\t\t\tUnit\tNilai\tBelanja Modal Unit\tBelanja Modal Nilai\tBelanja Barang/Jasa Unit\tBelanja Barang/Jasa Nilai\tDropping Unit\tDropping Nilai\tHibah Unit\tHibah Nilai\tPenghapusan Unit\tPenghapusan Nilai\tMutasi Keluar Unit\tMutasi Keluar Nilai\tUnit\tNilai Keluar\t\n";
    
    $no = 1;
    foreach ($dataByKategori as $kategoriName => $jenisList) {
        echo "\t" . strtoupper($kategoriName) . "\n";
        
        foreach ($jenisList as $jenis) {
            echo "$no\t" . strtoupper($jenis['nama_jenis']) . "\n";
            $no++;
            
            foreach ($jenis['barang'] as $brg) {
                echo "\t{$brg['nama_barang']}\t{$brg['satuan']}\t";
                echo "{$brg['saldo_awal_qty']}\t{$brg['saldo_awal_nilai']}\t";
                echo "{$brg['belanja_modal_qty']}\t{$brg['belanja_modal_nilai']}\t";
                echo "{$brg['belanja_barang_jasa_qty']}\t{$brg['belanja_barang_jasa_nilai']}\t";
                echo "{$brg['dropping_qty']}\t{$brg['dropping_nilai']}\t";
                echo "{$brg['hibah_qty']}\t{$brg['hibah_nilai']}\t";
                echo "{$brg['penghapusan_qty']}\t{$brg['penghapusan_nilai']}\t";
                echo "{$brg['mutasi_keluar_qty']}\t{$brg['mutasi_keluar_nilai']}\t";
                echo "{$brg['saldo_akhir_qty']}\t{$brg['saldo_akhir_nilai']}\t\n";
            }
            echo "\n";
        }
        echo "\n";
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
    
    <!-- Filter -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
          <?php if($role==='superadmin'): ?>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Bagian</label>
            <select name="id_bagian" class="form-select form-select-sm">
              <option value="">Semua Bagian</option>
              <?php 
              if ($bagianList) {
                  $bagianList->data_seek(0);
                  while($bg=$bagianList->fetch_assoc()): 
              ?>
                <option value="<?=$bg['id']?>" <?=$f_bagian==$bg['id']?'selected':''?>><?=htmlspecialchars($bg['nama'])?></option>
              <?php endwhile; } ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-auto">
            <label class="form-label fw-semibold">Tahun</label>
            <select name="tahun" class="form-select form-select-sm">
              <?php foreach($years as $y): ?>
                <option value="<?=$y?>" <?=$f_tahun==$y?'selected':''?>><?=$y?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
            <a href="?<?=http_build_query(array_merge($_GET,['export'=>1]))?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
          </div>
        </form>
      </div>
    </div>

    <!-- Laporan -->
    <div class="card">
      <div class="card-header text-center py-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <h5 class="mb-1 fw-bold">LAPORAN REKONSILIASI PERSEDIAAN BARANG</h5>
        <div class="fs-6">TAHUN <?=$f_tahun?></div>
        <div style="font-size:.9rem; opacity: 0.95;">Sekretariat Daerah Kabupaten Bantul</div>
      </div>
      
      <div class="table-responsive">
        <table class="table table-bordered mb-0" style="font-size: 0.75rem;">
          <thead style="background: #f8f9fa;">
            <tr class="text-center align-middle">
              <th rowspan="3" style="vertical-align: middle; width: 35px;">NO.</th>
              <th rowspan="3" style="vertical-align: middle; min-width: 180px;">URAIAN</th>
              <th rowspan="2" style="background: #e3f2fd;">SALDO AWAL</th>
              <th colspan="8" style="background: #e8f5e9;">PENAMBAHAN ASET</th>
              <th colspan="4" style="background: #fff3e0;">PENGURANGAN ASET</th>
              <th rowspan="2" style="background: #f3e5f5;">SALDO AKHIR</th>
              <th rowspan="3" style="vertical-align: middle; width: 100px;">KETERANGAN</th>
            </tr>
            <tr class="text-center" style="font-size: 0.7rem;">
              <th colspan="2" style="background: #e8f5e9;">Belanja Modal</th>
              <th colspan="2" style="background: #e8f5e9;">Belanja Barang/Jasa</th>
              <th colspan="2" style="background: #e8f5e9;">Dropping</th>
              <th colspan="2" style="background: #e8f5e9;">Hibah</th>
              <th colspan="2" style="background: #fff3e0;">Penghapusan</th>
              <th colspan="2" style="background: #fff3e0;">Mutasi Keluar</th>
            </tr>
            <tr class="text-center" style="font-size: 0.7rem;">
              <th style="background: #e3f2fd; width: 90px;">Nilai</th>
              <th style="background: #e8f5e9; width: 45px;">Unit</th>
              <th style="background: #e8f5e9; width: 85px;">Nilai</th>
              <th style="background: #e8f5e9; width: 45px;">Unit</th>
              <th style="background: #e8f5e9; width: 85px;">Nilai</th>
              <th style="background: #e8f5e9; width: 45px;">Unit</th>
              <th style="background: #e8f5e9; width: 85px;">Nilai</th>
              <th style="background: #e8f5e9; width: 45px;">Unit</th>
              <th style="background: #e8f5e9; width: 85px;">Nilai</th>
              <th style="background: #fff3e0; width: 45px;">Unit</th>
              <th style="background: #fff3e0; width: 85px;">Nilai</th>
              <th style="background: #fff3e0; width: 45px;">Unit</th>
              <th style="background: #fff3e0; width: 85px;">Nilai</th>
              <th style="background: #f3e5f5; width: 90px;">Nilai</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($dataByKategori)): ?>
            <tr>
              <td colspan="17" class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                Tidak ada data transaksi untuk tahun <?=$f_tahun?>
              </td>
            </tr>
            <?php else: ?>
            <?php 
            $no = 1;
            $grandTotal = [
                'saldo_awal_qty' => 0, 'saldo_awal_nilai' => 0,
                'belanja_modal_qty' => 0, 'belanja_modal_nilai' => 0,
                'belanja_barang_jasa_qty' => 0, 'belanja_barang_jasa_nilai' => 0,
                'dropping_qty' => 0, 'dropping_nilai' => 0,
                'hibah_qty' => 0, 'hibah_nilai' => 0,
                'penghapusan_qty' => 0, 'penghapusan_nilai' => 0,
                'mutasi_keluar_qty' => 0, 'mutasi_keluar_nilai' => 0,
                'saldo_akhir_qty' => 0, 'saldo_akhir_nilai' => 0,
            ];
            
            foreach ($dataByKategori as $kategoriName => $jenisList):
                $totalKategori = [
                    'saldo_awal_qty' => 0, 'saldo_awal_nilai' => 0,
                    'belanja_modal_qty' => 0, 'belanja_modal_nilai' => 0,
                    'belanja_barang_jasa_qty' => 0, 'belanja_barang_jasa_nilai' => 0,
                    'dropping_qty' => 0, 'dropping_nilai' => 0,
                    'hibah_qty' => 0, 'hibah_nilai' => 0,
                    'penghapusan_qty' => 0, 'penghapusan_nilai' => 0,
                    'mutasi_keluar_qty' => 0, 'mutasi_keluar_nilai' => 0,
                    'saldo_akhir_qty' => 0, 'saldo_akhir_nilai' => 0,
                ];
            ?>
            <!-- Header Kategori Besar -->
            <tr style="background: #6c757d; color: white; font-weight: bold; font-size: 0.85rem;">
              <td colspan="17" class="text-center py-2"><?=htmlspecialchars($kategoriName)?></td>
            </tr>
            
            <?php foreach ($jenisList as $jenis):
                $totalJenis = [
                    'saldo_awal_qty' => 0, 'saldo_awal_nilai' => 0,
                    'belanja_modal_qty' => 0, 'belanja_modal_nilai' => 0,
                    'belanja_barang_jasa_qty' => 0, 'belanja_barang_jasa_nilai' => 0,
                    'dropping_qty' => 0, 'dropping_nilai' => 0,
                    'hibah_qty' => 0, 'hibah_nilai' => 0,
                    'penghapusan_qty' => 0, 'penghapusan_nilai' => 0,
                    'mutasi_keluar_qty' => 0, 'mutasi_keluar_nilai' => 0,
                    'saldo_akhir_qty' => 0, 'saldo_akhir_nilai' => 0,
                ];
            ?>
            <!-- Header Jenis -->
            <tr style="background: #f0f0f0; font-weight: bold;">
              <td class="text-center"><?=$no++?></td>
              <td colspan="16"><?=htmlspecialchars(strtoupper($jenis['nama_jenis']))?></td>
            </tr>
            
            <?php 
            $sub_no = 1;
            foreach ($jenis['barang'] as $brg):
                // Akumulasi total jenis
                foreach ($totalJenis as $key => $val) {
                    $totalJenis[$key] += $brg[$key];
                }
            ?>
            <tr style="font-size: 0.75rem;">
              <td></td>
              <td style="padding-left: 25px;"><?=$sub_no?>. <?=htmlspecialchars($brg['nama_barang'])?></td>
              <td class="text-end" style="background: #f8fbff;"><?=formatRupiah($brg['saldo_awal_nilai'])?></td>
              <td class="text-end" style="background: #f9fff9;"><?=number_format($brg['belanja_modal_qty'])?></td>
              <td class="text-end" style="background: #f9fff9;"><?=formatRupiah($brg['belanja_modal_nilai'])?></td>
              <td class="text-end" style="background: #f9fff9;"><?=number_format($brg['belanja_barang_jasa_qty'])?></td>
              <td class="text-end" style="background: #f9fff9;"><?=formatRupiah($brg['belanja_barang_jasa_nilai'])?></td>
              <td class="text-end" style="background: #f9fff9;"><?=number_format($brg['dropping_qty'])?></td>
              <td class="text-end" style="background: #f9fff9;"><?=formatRupiah($brg['dropping_nilai'])?></td>
              <td class="text-end" style="background: #f9fff9;"><?=number_format($brg['hibah_qty'])?></td>
              <td class="text-end" style="background: #f9fff9;"><?=formatRupiah($brg['hibah_nilai'])?></td>
              <td class="text-end" style="background: #fffbf5;"><?=number_format($brg['penghapusan_qty'])?></td>
              <td class="text-end" style="background: #fffbf5;"><?=formatRupiah($brg['penghapusan_nilai'])?></td>
              <td class="text-end" style="background: #fffbf5;"><?=number_format($brg['mutasi_keluar_qty'])?></td>
              <td class="text-end" style="background: #fffbf5;"><?=formatRupiah($brg['mutasi_keluar_nilai'])?></td>
              <td class="text-end fw-bold" style="background: #faf8ff;"><?=formatRupiah($brg['saldo_akhir_nilai'])?></td>
              <td></td>
            </tr>
            <?php $sub_no++; endforeach; // endforeach barang ?>
            
            <!-- Subtotal Jenis -->
            <tr style="background: #e8e8e8; font-weight: 600; font-size: 0.75rem;">
              <td></td>
              <td style="padding-left: 25px;">JUMLAH <?=strtoupper($jenis['nama_jenis'])?></td>
              <td class="text-end"><?=formatRupiah($totalJenis['saldo_awal_nilai'])?></td>
              <td class="text-end"><?=number_format($totalJenis['belanja_modal_qty'])?></td>
              <td class="text-end"><?=formatRupiah($totalJenis['belanja_modal_nilai'])?></td>
              <td class="text-end"><?=number_format($totalJenis['belanja_barang_jasa_qty'])?></td>
              <td class="text-end"><?=formatRupiah($totalJenis['belanja_barang_jasa_nilai'])?></td>
              <td class="text-end"><?=number_format($totalJenis['dropping_qty'])?></td>
              <td class="text-end"><?=formatRupiah($totalJenis['dropping_nilai'])?></td>
              <td class="text-end"><?=number_format($totalJenis['hibah_qty'])?></td>
              <td class="text-end"><?=formatRupiah($totalJenis['hibah_nilai'])?></td>
              <td class="text-end"><?=number_format($totalJenis['penghapusan_qty'])?></td>
              <td class="text-end"><?=formatRupiah($totalJenis['penghapusan_nilai'])?></td>
              <td class="text-end"><?=number_format($totalJenis['mutasi_keluar_qty'])?></td>
              <td class="text-end"><?=formatRupiah($totalJenis['mutasi_keluar_nilai'])?></td>
              <td class="text-end"><?=formatRupiah($totalJenis['saldo_akhir_nilai'])?></td>
              <td></td>
            </tr>
            
            <?php 
                // Akumulasi total kategori dan grand total
                foreach ($totalJenis as $key => $val) {
                    $totalKategori[$key] += $totalJenis[$key];
                    $grandTotal[$key] += $totalJenis[$key];
                }
            endforeach; // endforeach jenis
            ?>
            
            <!-- Total Kategori -->
            <tr style="background: #5a6268; color: white; font-weight: bold; font-size: 0.8rem;">
              <td></td>
              <td style="padding-left: 25px;">JUMLAH <?=htmlspecialchars($kategoriName)?></td>
              <td class="text-end"><?=formatRupiah($totalKategori['saldo_awal_nilai'])?></td>
              <td class="text-end"><?=number_format($totalKategori['belanja_modal_qty'])?></td>
              <td class="text-end"><?=formatRupiah($totalKategori['belanja_modal_nilai'])?></td>
              <td class="text-end"><?=number_format($totalKategori['belanja_barang_jasa_qty'])?></td>
              <td class="text-end"><?=formatRupiah($totalKategori['belanja_barang_jasa_nilai'])?></td>
              <td class="text-end"><?=number_format($totalKategori['dropping_qty'])?></td>
              <td class="text-end"><?=formatRupiah($totalKategori['dropping_nilai'])?></td>
              <td class="text-end"><?=number_format($totalKategori['hibah_qty'])?></td>
              <td class="text-end"><?=formatRupiah($totalKategori['hibah_nilai'])?></td>
              <td class="text-end"><?=number_format($totalKategori['penghapusan_qty'])?></td>
              <td class="text-end"><?=formatRupiah($totalKategori['penghapusan_nilai'])?></td>
              <td class="text-end"><?=number_format($totalKategori['mutasi_keluar_qty'])?></td>
              <td class="text-end"><?=formatRupiah($totalKategori['mutasi_keluar_nilai'])?></td>
              <td class="text-end"><?=formatRupiah($totalKategori['saldo_akhir_nilai'])?></td>
              <td></td>
            </tr>
            
            <?php endforeach; // endforeach kategori ?>
            
            <!-- Grand Total -->
            <tr style="background: #d0d0d0; font-weight: bold; border-top: 3px solid #666; font-size: 0.75rem;">
              <td></td>
              <td style="padding-left: 25px;">JUMLAH ASET TETAP</td>
              <td class="text-end"><?=formatRupiah($grandTotal['saldo_awal_nilai'])?></td>
              <td class="text-end"><?=number_format($grandTotal['belanja_modal_qty'])?></td>
              <td class="text-end"><?=formatRupiah($grandTotal['belanja_modal_nilai'])?></td>
              <td class="text-end"><?=number_format($grandTotal['belanja_barang_jasa_qty'])?></td>
              <td class="text-end"><?=formatRupiah($grandTotal['belanja_barang_jasa_nilai'])?></td>
              <td class="text-end"><?=number_format($grandTotal['dropping_qty'])?></td>
              <td class="text-end"><?=formatRupiah($grandTotal['dropping_nilai'])?></td>
              <td class="text-end"><?=number_format($grandTotal['hibah_qty'])?></td>
              <td class="text-end"><?=formatRupiah($grandTotal['hibah_nilai'])?></td>
              <td class="text-end"><?=number_format($grandTotal['penghapusan_qty'])?></td>
              <td class="text-end"><?=formatRupiah($grandTotal['penghapusan_nilai'])?></td>
              <td class="text-end"><?=number_format($grandTotal['mutasi_keluar_qty'])?></td>
              <td class="text-end"><?=formatRupiah($grandTotal['mutasi_keluar_nilai'])?></td>
              <td class="text-end"><?=formatRupiah($grandTotal['saldo_akhir_nilai'])?></td>
              <td></td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <!-- Footer Info -->
      <div class="card-footer text-muted" style="font-size: 0.85rem;">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <i class="bi bi-info-circle me-1"></i>
            Laporan ini menampilkan rekonsiliasi persediaan berdasarkan transaksi yang telah disetujui
          </div>
          <div class="text-end">
            <strong>Tanggal Cetak:</strong> <?=date('d-m-Y H:i')?> WIB
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
