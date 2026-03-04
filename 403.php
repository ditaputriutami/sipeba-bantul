<?php
require_once __DIR__ . '/config/bootstrap.php';
requireLogin();
$pageTitle = '403 — Akses Ditolak';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="main-content d-flex align-items-center justify-content-center" style="min-height:80vh">
  <div class="text-center">
    <div style="font-size:5rem;color:#e2e8f0">🚫</div>
    <h2 class="fw-bold text-danger mt-3">Akses Ditolak</h2>
    <p class="text-muted">Anda tidak memiliki izin untuk mengakses halaman ini.</p>
    <a href="javascript:history.back()" class="btn btn-outline-primary mt-2"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
