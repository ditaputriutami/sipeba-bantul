<?php
require_once __DIR__ . '/config/bootstrap.php';
if (isLoggedIn()) {
    redirectByRole();
}
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — SIPEBA Bantul</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    * { font-family: 'Inter', sans-serif; }
    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #0f2942 0%, #1a4a7a 50%, #1e6bb8 100%);
      display: flex; align-items: center; justify-content: center;
      position: relative; overflow: hidden;
    }
    body::before {
      content: '';
      position: absolute; inset: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="60" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1"/><circle cx="80" cy="80" r="80" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1"/><circle cx="50" cy="10" r="40" fill="none" stroke="rgba(255,255,255,0.02)" stroke-width="1"/></svg>');
      background-size: 800px;
    }
    .login-card {
      width: 100%; max-width: 440px;
      background: rgba(255,255,255,0.97);
      border-radius: 20px;
      box-shadow: 0 30px 80px rgba(0,0,0,0.35);
      overflow: hidden;
      position: relative;
      animation: slideUp .5s ease;
    }
    @keyframes slideUp { from { opacity:0; transform: translateY(30px); } to { opacity:1; transform: translateY(0); } }
    .login-header {
      background: linear-gradient(135deg, #0f2942, #1e6bb8);
      padding: 2rem 2rem 1.5rem;
      text-align: center;
      color: white;
    }
    .login-header .logo-ring {
      width: 72px; height: 72px;
      border: 3px solid rgba(255,255,255,0.4);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1rem;
      background: rgba(255,255,255,0.15);
    }
    .login-header h4 { font-weight: 700; margin: 0; letter-spacing: .5px; }
    .login-header p { margin: .3rem 0 0; opacity: .85; font-size: .85rem; }
    .login-body { padding: 2rem; }
    .form-label { font-weight: 500; font-size: .875rem; color: #374151; }
    .form-control {
      border: 1.5px solid #e5e7eb;
      border-radius: 10px; padding: .7rem 1rem;
      font-size: .9rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-control:focus {
      border-color: #1e6bb8;
      box-shadow: 0 0 0 3px rgba(30,107,184,0.12);
    }
    .input-group-text {
      border: 1.5px solid #e5e7eb;
      border-radius: 10px 0 0 10px;
      background: #f9fafb;
      color: #6b7280;
    }
    .input-group .form-control { border-radius: 0 10px 10px 0; }
    .btn-login {
      background: linear-gradient(135deg, #0f2942, #1e6bb8);
      border: none; color: white;
      font-weight: 600; letter-spacing: .5px;
      padding: .8rem; border-radius: 10px;
      width: 100%; font-size: .95rem;
      transition: all .3s;
    }
    .btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 25px rgba(30,107,184,0.4); color: white; }
    .footer-login { text-align: center; padding: 1rem 2rem 1.5rem; color: #9ca3af; font-size: .78rem; }
  </style>
</head>
<body>
<div class="login-card">
  <div class="login-header">
    <div class="logo-ring">
      <i class="bi bi-box-seam fs-3"></i>
    </div>
    <h4>SIPEBA</h4>
    <p>Sistem Informasi Persediaan Barang — Setda Bantul</p>
  </div>
  <div class="login-body">
    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show py-2" role="alert">
        <i class="bi bi-<?= $flash['type'] === 'error' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <form action="<?= BASE_URL ?>/auth/login_process.php" method="POST">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label">Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" id="passwordField" class="form-control" placeholder="Masukkan password" required>
          <button type="button" class="btn btn-outline-secondary" style="border-radius:0 10px 10px 0; border:1.5px solid #e5e7eb;" onclick="togglePassword()">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-login">
        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk ke SIPEBA
      </button>
    </form>
  </div>
  <div class="footer-login">
    <i class="bi bi-shield-lock me-1"></i>
    Sekretariat Daerah Kabupaten Bantul &copy; <?= date('Y') ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword() {
  const f = document.getElementById('passwordField');
  const i = document.getElementById('eyeIcon');
  f.type = f.type === 'password' ? 'text' : 'password';
  i.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
</body>
</html>
