<?php
$user = getCurrentUser();
$role = getUserRole();
$curPath = $_SERVER['PHP_SELF'];
$bagianLabel = $user['nama_bagian'] ?? 'Semua Bagian';

// Helper: is active
function isActive(string $path): string
{
  global $curPath;
  return (strpos($curPath, $path) !== false) ? 'active' : '';
}
?>
<nav id="sidebar" class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="bi bi-box-seam"></i></div>
    <div class="brand-text">
      <span class="brand-name">SIPEBA</span>
      <span class="brand-sub">Setda Bantul</span>
    </div>
    <button class="sidebar-toggle ms-auto d-xl-none" id="sidebarToggleBtn">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <!-- User info -->
  <div class="sidebar-user">
    <div class="user-avatar">
      <?= strtoupper(substr($user['nama'] ?? 'U', 0, 1)) ?>
    </div>
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($user['nama'] ?? '') ?></div>
      <div class="user-role">
        <?php
        $roleLabel = ['superadmin' => 'Super Admin', 'kepala' => 'Kepala Bagian', 'pengurus' => 'Pengurus Barang'];
        echo $roleLabel[$role] ?? $role;
        ?>
      </div>
      <?php if ($role !== 'superadmin'): ?>
        <div class="user-bagian"><?= htmlspecialchars($bagianLabel) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="sidebar-menu">
    <!-- DASHBOARD -->
    <div class="menu-group">
      <span class="menu-label">Utama</span>
      <a href="<?= BASE_URL ?>/dashboard.php"
        class="menu-item <?= isActive('/dashboard.php') ?>">
        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
      </a>
    </div>

    <!-- MASTER DATA -->
    <div class="menu-group">
      <span class="menu-label">Master Data</span>
      <a href="<?= BASE_URL ?>/master_data/barang/index.php" class="menu-item <?= isActive('/barang/') ?>">
        <i class="bi bi-archive"></i><span>Data Barang</span>
      </a>
      <a href="<?= BASE_URL ?>/master_data/jenis_barang/index.php" class="menu-item <?= isActive('/jenis_barang/') ?>">
        <i class="bi bi-tags"></i><span>Jenis Barang</span>
      </a>
      <?php if ($role === 'superadmin'): ?>
        <a href="<?= BASE_URL ?>/master_data/users/index.php" class="menu-item <?= isActive('/users/') ?>">
          <i class="bi bi-people"></i><span>Manajemen User</span>
        </a>
      <?php endif; ?>
    </div>

    <?php if (in_array($role, ['pengurus', 'superadmin'])): ?>
      <!-- TRANSAKSI (PENGURUS) -->
      <div class="menu-group">
        <span class="menu-label">Transaksi</span>
        <a href="<?= BASE_URL ?>/transaksi/penerimaan/index.php" class="menu-item <?= isActive('/penerimaan/') ?>">
          <i class="bi bi-box-arrow-in-down"></i><span>Penerimaan Barang</span>
        </a>
        <a href="<?= BASE_URL ?>/transaksi/pengurangan/index.php" class="menu-item <?= isActive('/pengurangan/') ?>">
          <i class="bi bi-box-arrow-up"></i><span>Pengurangan Barang</span>
        </a>
        <a href="<?= BASE_URL ?>/transaksi/stock_opname/index.php" class="menu-item <?= isActive('/stock_opname/') ?>">
          <i class="bi bi-clipboard-check"></i><span>Stock Opname</span>
        </a>
      </div>
    <?php endif; ?>

    <?php if (in_array($role, ['kepala', 'superadmin'])): ?>
      <!-- PERSETUJUAN (KEPALA & SUPERADMIN) -->
      <div class="menu-group">
        <span class="menu-label">Persetujuan</span>
        <a href="<?= BASE_URL ?>/persetujuan/transaksi/index.php" class="menu-item <?= isActive('/persetujuan/transaksi/') ?>">
          <i class="bi bi-check2-square"></i><span>Approval Transaksi</span>
          <?php
          // Count pending dari penerimaan
          $stmt_p = $conn->prepare("SELECT COUNT(*) as c FROM penerimaan WHERE status='pending' AND id_bagian=?");
          $stmt_p->bind_param('i', $user['id_bagian']);
          $stmt_p->execute();
          $penerimaanPending = $stmt_p->get_result()->fetch_assoc()['c'];
          $stmt_p->close();

          // Count pending dari pengurangan
          $stmt_g = $conn->prepare("SELECT COUNT(*) as c FROM pengurangan WHERE status='pending' AND id_bagian=?");
          $stmt_g->bind_param('i', $user['id_bagian']);
          $stmt_g->execute();
          $penguranganPending = $stmt_g->get_result()->fetch_assoc()['c'];
          $stmt_g->close();

          $totalPending = $penerimaanPending + $penguranganPending;
          if ($totalPending > 0): ?>
            <span class="badge-count"><?= $totalPending ?></span>
          <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/persetujuan/stock_opname/index.php" class="menu-item <?= isActive('/persetujuan/stock_opname/') ?>">
          <i class="bi bi-clipboard2-pulse"></i><span>Approval Stock Opname</span>
          <?php
          // Count pending stock opname
          $stmt_so = $conn->prepare("SELECT COUNT(*) as c FROM stock_opname WHERE status='pending' AND id_bagian=?");
          $stmt_so->bind_param('i', $user['id_bagian']);
          $stmt_so->execute();
          $stockOpnamePending = $stmt_so->get_result()->fetch_assoc()['c'];
          $stmt_so->close();

          if ($stockOpnamePending > 0): ?>
            <span class="badge-count"><?= $stockOpnamePending ?></span>
          <?php endif; ?>
        </a>
      </div>
      <div class="menu-group">
        <span class="menu-label">Transaksi</span>
        <a href="<?= BASE_URL ?>/transaksi/penerimaan/index.php" class="menu-item <?= isActive('/penerimaan/') ?>">
          <i class="bi bi-box-arrow-in-down"></i><span>Penerimaan Barang</span>
        </a>
        <a href="<?= BASE_URL ?>/transaksi/pengurangan/index.php" class="menu-item <?= isActive('/pengurangan/') ?>">
          <i class="bi bi-box-arrow-up"></i><span>Pengurangan Barang</span>
        </a>
        <a href="<?= BASE_URL ?>/transaksi/stock_opname/index.php" class="menu-item <?= isActive('/stock_opname/') ?>">
          <i class="bi bi-clipboard-check"></i><span>Stock Opname</span>
        </a>
      </div>
    <?php endif; ?>

    <!-- LAPORAN (SEMUA ROLE) -->
    <div class="menu-group">
      <span class="menu-label">Laporan</span>
      <a href="<?= BASE_URL ?>/laporan/buku_penerimaan.php" class="menu-item <?= isActive('/buku_penerimaan') ?>">
        <i class="bi bi-journal-text"></i><span>Buku Penerimaan</span>
      </a>
      <a href="<?= BASE_URL ?>/laporan/buku_pengeluaran.php" class="menu-item <?= isActive('/buku_pengeluaran') ?>">
        <i class="bi bi-journal-minus"></i><span>Buku Pengeluaran</span>
      </a>
      <a href="<?= BASE_URL ?>/laporan/stock_opname.php" class="menu-item <?= isActive('/laporan/stock_opname') ?>">
        <i class="bi bi-clipboard-data"></i><span>Hasil Stock Opname</span>
      </a>
      <a href="<?= BASE_URL ?>/laporan/rekonsiliasi.php" class="menu-item <?= isActive('/rekonsiliasi') ?>">
        <i class="bi bi-bar-chart-line"></i><span>Rekonsiliasi Persediaan</span>
      </a>
    </div>
  </div>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/auth/logout.php" class="menu-item text-danger" onclick="return confirm('Yakin ingin keluar?')">
      <i class="bi bi-box-arrow-right"></i><span>Keluar</span>
    </a>
  </div>
</nav>
<!-- Overlay for mobile -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>