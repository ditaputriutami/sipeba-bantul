<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireRole(['pengurus','kepala','superadmin']);
$pageTitle = 'Dashboard';
$user = getCurrentUser();
$role = getUserRole();
$id_bagian = getUserBagian();

// For superadmin, show all; for others filter by bagian
$bagianFilter = ($role === 'superadmin') ? '' : "AND id_bagian=$id_bagian";

$stokTotal  = $conn->query("SELECT COALESCE(SUM(stok),0) FROM stok_current WHERE 1=1 $bagianFilter")->fetch_row()[0];
$pen_count  = $conn->query("SELECT COUNT(*) FROM penerimaan WHERE status='disetujui' $bagianFilter")->fetch_row()[0];
$peng_count = $conn->query("SELECT COUNT(*) FROM pengurangan WHERE status='disetujui' $bagianFilter")->fetch_row()[0];
$pending    = $conn->query("SELECT COUNT(*) FROM penerimaan WHERE status='pending' $bagianFilter")->fetch_row()[0];

// Chart tren 6 bulan
$trendData = [];
for ($i = 5; $i >= 0; $i--) {
    $ms = date('Y-m-01', strtotime("-$i months"));
    $me = date('Y-m-t', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $rPen  = $conn->query("SELECT COALESCE(SUM(jumlah),0) FROM penerimaan WHERE status='disetujui' AND tanggal BETWEEN '$ms' AND '$me' $bagianFilter")->fetch_row()[0];
    $rPeng = $conn->query("SELECT COALESCE(SUM(jumlah),0) FROM pengurangan WHERE status='disetujui' AND tanggal BETWEEN '$ms' AND '$me' $bagianFilter")->fetch_row()[0];
    $trendData[] = ['label'=>$label,'pen'=>(int)$rPen,'peng'=>(int)$rPeng];
}

// Stok terkini per barang (untuk tabel)
$stokList = $conn->query("
    SELECT b.kode_barang, b.nama_barang, b.satuan, COALESCE(sc.stok,0) as stok
    FROM barang b
    LEFT JOIN stok_current sc ON sc.id_barang=b.id" . ($id_bagian ? " AND sc.id_bagian=$id_bagian" : '') . "
    ORDER BY b.nama_barang LIMIT 10
");

$chartLabels     = json_encode(array_column($trendData,'label'));
$chartPenerimaan = json_encode(array_column($trendData,'pen'));
$chartPengurangan= json_encode(array_column($trendData,'peng'));

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title">Dashboard — <?=htmlspecialchars($user['nama_bagian']??'Semua Bagian')?></div>
    <div class="topbar-actions">
      <span class="topbar-badge"><i class="bi bi-calendar3 me-1"></i><?=date('d M Y')?></span>
    </div>
  </div>
  <div class="page-content">
    <?php $flash=getFlash(); if($flash): ?>
      <div class="alert alert-<?=$flash['type']==='error'?'danger':$flash['type']?> auto-dismiss alert-dismissible fade show">
        <?=htmlspecialchars($flash['message'])?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <style>
      a.stat-card-link { text-decoration: none; display: block; }
      a.stat-card-link .stat-card { transition: transform 0.2s, box-shadow 0.2s; }
      a.stat-card-link:hover .stat-card { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    </style>
    <div class="row g-3 mb-4">
      <div class="col-6 col-xl-3">
        <a href="<?=BASE_URL?>/laporan/rekonsiliasi.php" class="stat-card-link">
          <div class="stat-card blue">
            <div class="stat-icon" style="background:rgba(255,255,255,0.15)"><i class="bi bi-box-seam text-white"></i></div>
            <div class="stat-value"><?=number_format($stokTotal)?></div>
            <div class="stat-label">Total Stok Aktif</div>
          </div>
        </a>
      </div>
      <div class="col-6 col-xl-3">
        <a href="<?=BASE_URL?>/transaksi/penerimaan/index.php?status=disetujui" class="stat-card-link">
          <div class="stat-card green">
            <div class="stat-icon" style="background:rgba(255,255,255,0.15)"><i class="bi bi-box-arrow-in-down text-white"></i></div>
            <div class="stat-value"><?=number_format($pen_count)?></div>
            <div class="stat-label">Penerimaan Disetujui</div>
          </div>
        </a>
      </div>
      <div class="col-6 col-xl-3">
        <a href="<?=BASE_URL?>/transaksi/pengurangan/index.php?status=disetujui" class="stat-card-link">
          <div class="stat-card orange">
            <div class="stat-icon" style="background:rgba(255,255,255,0.15)"><i class="bi bi-box-arrow-up text-white"></i></div>
            <div class="stat-value"><?=number_format($peng_count)?></div>
            <div class="stat-label">Pengurangan Disetujui</div>
          </div>
        </a>
      </div>
      <div class="col-6 col-xl-3">
        <a href="<?=BASE_URL?>/transaksi/penerimaan/index.php?status=pending" class="stat-card-link">
          <div class="stat-card <?=$pending>0?'red':'purple'?>">
            <div class="stat-icon" style="background:rgba(255,255,255,0.15)"><i class="bi bi-hourglass-split text-white"></i></div>
            <div class="stat-value"><?=number_format($pending)?></div>
            <div class="stat-label">Menunggu Persetujuan</div>
          </div>
        </a>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-header"><i class="bi bi-graph-up me-2 text-primary"></i>Tren Barang Masuk &amp; Keluar (6 Bulan)</div>
          <div class="card-body">
            <div class="chart-container"><canvas id="trendChart"></canvas></div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header"><i class="bi bi-boxes me-2 text-success"></i>Stok Terkini (Top 10)</div>
          <div class="table-wrapper" style="max-height:300px;overflow-y:auto">
            <table class="table table-sm">
              <thead><tr><th>Barang</th><th>Stok</th><th>Sat.</th></tr></thead>
              <tbody>
                <?php while($s=$stokList->fetch_assoc()): ?>
                <tr>
                  <td title="<?=htmlspecialchars($s['kode_barang'])?>"><?=htmlspecialchars(substr($s['nama_barang'],0,25))?><?=strlen($s['nama_barang'])>25?'…':''?></td>
                  <td><span class="badge <?=$s['stok']<=0?'bg-danger':($s['stok']<=5?'bg-warning text-dark':'bg-success')?>"><?=$s['stok']?></span></td>
                  <td><?=htmlspecialchars($s['satuan'])?></td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Links -->
    <div class="row g-3">
      <div class="col-auto">
        <a href="<?=BASE_URL?>/transaksi/penerimaan/create.php" class="btn btn-success">
          <i class="bi bi-plus-circle me-1"></i>Tambah Penerimaan
        </a>
      </div>
      <div class="col-auto">
        <a href="<?=BASE_URL?>/transaksi/pengurangan/create.php" class="btn btn-warning">
          <i class="bi bi-dash-circle me-1"></i>Tambah Pengurangan
        </a>
      </div>
      <div class="col-auto">
        <a href="<?=BASE_URL?>/laporan/stock_opname_detail.php" class="btn btn-outline-primary">
          <i class="bi bi-file-earmark-text me-1"></i>Lihat Laporan
        </a>
      </div>
    </div>
  </div>
</div>
<?php
$extraJs = "
<script>
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: $chartLabels,
    datasets: [
      { label:'Penerimaan', data:$chartPenerimaan, borderColor:'#10b981', backgroundColor:'rgba(16,185,129,0.08)', borderWidth:2.5, pointRadius:5, tension:0.4, fill:true },
      { label:'Pengurangan', data:$chartPengurangan, borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.08)', borderWidth:2.5, pointRadius:5, tension:0.4, fill:true }
    ]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{position:'top'} },
    scales:{ y:{beginAtZero:true,grid:{color:'#f1f5f9'}}, x:{grid:{display:false}} }
  }
});
</script>";
include BASE_PATH . '/includes/footer.php';
