<?php
/**
 * Bootstrap file — dimuat pertama oleh semua halaman
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_URL', '/SIPEBA-Bantul');
define('BASE_PATH', dirname(__DIR__));
define('APP_NAME', 'SIPEBA Bantul');
define('APP_VERSION', '1.0.0');

require_once BASE_PATH . '/config/koneksi.php';
require_once BASE_PATH . '/config/auth.php';
