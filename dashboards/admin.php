<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireRole(['superadmin']);
$pageTitle = 'Dashboard Admin';

// ---- Statistik Kartu ----
$totalStok      = $conn->query("SELECT COALESCE(SUM(stok),0) FROM stok_current")->fetch_row()[0];
$totalUser      = $conn->query("SELECT COUNT(*) FROM users WHERE role != 'superadmin'")->fetch_row()[0];
$totalPenerimaan = $conn->query("SELECT COUNT(*) FROM penerimaan WHERE status='disetujui'")->fetch_row()[0];
$totalPengurangan = $conn->query("SELECT COUNT(*) FROM pengurangan WHERE status='disetujui'")->fetch_row()[0];
$pendingAll     = $conn->query("SELECT (SELECT COUNT(*) FROM penerimaan WHERE status='pending')+(SELECT COUNT(*) FROM pengurangan WHERE status='pending')")->fetch_row()[0];

// ---- Tren 6 bulan terakhir (penerimaan + pengurangan disetujui) ----
$trendData = [];
for ($i = 5; $i >= 0; $i--) {
  $monthStart = date('Y-m-01', strtotime("-$i months"));
  $monthEnd   = date('Y-m-t', strtotime("-$i months"));
  $label      = date('M Y', strtotime("-$i months"));

  $rPen  = $conn->query("SELECT COALESCE(SUM(jumlah),0) FROM penerimaan WHERE status='disetujui' AND tanggal BETWEEN '$monthStart' AND '$monthEnd'")->fetch_row()[0];
  $rPeng = $conn->query("SELECT COALESCE(SUM(jumlah),0) FROM pengurangan WHERE status='disetujui' AND tanggal BETWEEN '$monthStart' AND '$monthEnd'")->fetch_row()[0];
  $trendData[] = ['label' => $label, 'penerimaan' => (int)$rPen, 'pengurangan' => (int)$rPeng];
}
$chartLabels    = json_encode(array_column($trendData, 'label'));
$chartPenerimaan = json_encode(array_column($trendData, 'penerimaan'));
$chartPengurangan = json_encode(array_column($trendData, 'pengurangan'));

// ---- Distribusi per bagian ----
$distribusi = $conn->query("
    SELECT b.nama, COALESCE(SUM(sc.stok),0) as total_stok
    FROM bagian b
    LEFT JOIN stok_current sc ON sc.id_bagian = b.id
    GROUP BY b.id ORDER BY total_stok DESC
");
$distLabels = [];
$distData = [];
while ($row = $distribusi->fetch_assoc()) {
  $distLabels[] = $row['nama'];
  $distData[]   = (int)$row['total_stok'];
}

// ---- Transaksi terbaru ----
$recentTx = $conn->query("
    SELECT 'Penerimaan' as jenis, p.no_faktur as no_doc, b.nama_barang, p.jumlah, p.tanggal, bg.nama as bagian, p.status
    FROM penerimaan p JOIN barang b ON p.id_barang=b.id JOIN bagian bg ON p.id_bagian=bg.id
    UNION ALL
    SELECT 'Pengurangan', pg.no_permintaan, b.nama_barang, pg.jumlah, pg.tanggal, bg.nama, pg.status
    FROM pengurangan pg JOIN barang b ON pg.id_barang=b.id JOIN bagian bg ON pg.id_bagian=bg.id
    ORDER BY tanggal DESC LIMIT 10
");

include BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/sidebar.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="sidebar-toggle-btn me-3" id="mainSidebarToggle"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-title">Dashboard Super Admin</div>
    <div class="topbar-actions">
      <span class="topbar-badge"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y') ?></span>
    </div>
  </div>
  <div class="page-content">

    <!-- Flash Message -->
    <?php $flash = getFlash();
    if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> auto-dismiss alert-dismissible fade show">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <style>
      a.stat-card-link {
        text-decoration: none;
        display: block;
      }

      a.stat-card-link .stat-card {
        transition: transform 0.2s, box-shadow 0.2s;
      }

      a.stat-card-link:hover .stat-card {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
      }
    </style>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-xl-3">
        <a href="<?= BASE_URL ?>/laporan/stock_opname_detail.php" class="stat-card-link">
          <div class="stat-card blue">
            <div class="stat-icon" style="background:rgba(255,255,255,0.15)"><i class="bi bi-box-seam text-white"></i></div>
            <div class="stat-value"><?= number_format($totalStok) ?></div>
            <div class="stat-label">Total Stok Aktif</div>
          </div>
        </a>
      </div>
      <div class="col-6 col-xl-3">
        <a href="<?= BASE_URL ?>/transaksi/penerimaan/index.php?status=disetujui" class="stat-card-link">
          <div class="stat-card green">
            <div class="stat-icon" style="background:rgba(255,255,255,0.15)"><i class="bi bi-box-arrow-in-down text-white"></i></div>
            <div class="stat-value"><?= number_format($totalPenerimaan) ?></div>
            <div class="stat-label">Penerimaan Disetujui</div>
          </div>
        </a>
      </div>
      <div class="col-6 col-xl-3">
        <a href="<?= BASE_URL ?>/transaksi/pengurangan/index.php?status=disetujui" class="stat-card-link">
          <div class="stat-card orange">
            <div class="stat-icon" style="background:rgba(255,255,255,0.15)"><i class="bi bi-box-arrow-up text-white"></i></div>
            <div class="stat-value"><?= number_format($totalPengurangan) ?></div>
            <div class="stat-label">Pengurangan Disetujui</div>
          </div>
        </a>
      </div>
      <div class="col-6 col-xl-3">
        <a href="<?= BASE_URL ?>/persetujuan/transaksi/index.php" class="stat-card-link">
          <div class="stat-card red">
            <div class="stat-icon" style="background:rgba(255,255,255,0.15)"><i class="bi bi-hourglass-split text-white"></i></div>
            <div class="stat-value"><?= number_format($pendingAll) ?></div>
            <div class="stat-label">Transaksi Pending</div>
          </div>
        </a>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-bar-chart-line me-2 text-primary"></i>Tren Barang Masuk &amp; Keluar (6 Bulan)</span>
          </div>
          <div class="card-body">
            <div class="chart-container"><canvas id="trendChart"></canvas></div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header"><i class="bi bi-pie-chart me-2 text-success"></i>Stok per Bagian</div>
          <div class="card-body d-flex align-items-center">
            <div class="chart-container w-100"><canvas id="distribusiChart"></canvas></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-clock-history me-2"></i>Transaksi Terbaru</span>
        <div class="d-flex gap-2">
          <input type="text" class="form-control form-control-sm" style="width:200px" data-table-search="recentTable" placeholder="Cari...">
        </div>
      </div>
      <div class="table-wrapper">
        <table class="table" id="recentTable">
          <thead>
            <tr>
              <th>Jenis</th>
              <th>No. Dokumen</th>
              <th>Nama Barang</th>
              <th>Jumlah</th>
              <th>Bagian</th>
              <th>Tanggal</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($tx = $recentTx->fetch_assoc()): ?>
              <tr>
                <td>
                  <span class="badge <?= $tx['jenis'] === 'Penerimaan' ? 'bg-success' : 'bg-warning text-dark' ?>">
                    <?= $tx['jenis'] ?>
                  </span>
                </td>
                <td><code><?= htmlspecialchars($tx['no_doc']) ?></code></td>
                <td><?= htmlspecialchars($tx['nama_barang']) ?></td>
                <td><?= number_format($tx['jumlah']) ?></td>
                <td><?= htmlspecialchars($tx['bagian']) ?></td>
                <td><?= formatTanggal($tx['tanggal']) ?></td>
                <td>
                  <?php
                  $sc = ['pending' => 'badge-pending', 'disetujui' => 'badge-approved', 'ditolak' => 'badge-rejected'];
                  $si = ['pending' => 'bi-clock', 'disetujui' => 'bi-check-circle', 'ditolak' => 'bi-x-circle'];
                  ?>
                  <span class="badge-sipeba <?= $sc[$tx['status']] ?? '' ?>">
                    <i class="bi <?= $si[$tx['status']] ?? '' ?>"></i>
                    <?= ucfirst($tx['status']) ?>
                  </span>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<?php
$distLabelsJson = json_encode($distLabels);
$distDataJson   = json_encode($distData);
$extraJs = "
<script>
// Trend Chart
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: $chartLabels,
    datasets: [
      {
        label: 'Penerimaan',
        data: $chartPenerimaan,
        borderColor: '#10b981',
        backgroundColor: 'rgba(16,185,129,0.08)',
        borderWidth: 2.5,
        pointBackgroundColor: '#10b981',
        pointRadius: 5, pointHoverRadius: 7,
        tension: 0.4, fill: true
      },
      {
        label: 'Pengurangan',
        data: $chartPengurangan,
        borderColor: '#f59e0b',
        backgroundColor: 'rgba(245,158,11,0.08)',
        borderWidth: 2.5,
        pointBackgroundColor: '#f59e0b',
        pointRadius: 5, pointHoverRadius: 7,
        tension: 0.4, fill: true
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top' } },
    scales: {
      y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
      x: { grid: { display: false } }
    }
  }
});

// Distribusi Chart
new Chart(document.getElementById('distribusiChart'), {
  type: 'doughnut',
  data: {
    labels: $distLabelsJson,
    datasets: [{
      data: $distDataJson,
      backgroundColor: ['#1e6bb8','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#ec4899','#6b7280'],
      borderWidth: 0,
      hoverOffset: 6
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } } },
    cutout: '65%'
  }
});
</script>";
include BASE_PATH . '/includes/footer.php';
