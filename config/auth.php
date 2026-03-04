<?php
/**
 * Auth Helper - SIPEBA Bantul
 * Fungsi-fungsi session dan otorisasi
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Ambil data user saat ini dari session
 */
function getCurrentUser(): array {
    return $_SESSION['user'] ?? [];
}

function getUserRole(): string {
    return $_SESSION['user']['role'] ?? '';
}

function getUserBagian(): ?int {
    return isset($_SESSION['user']['id_bagian']) ? (int)$_SESSION['user']['id_bagian'] : null;
}

function getUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Paksa login jika belum
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Paksa role tertentu — redirect jika tidak sesuai
 * @param string|array $roles
 */
function requireRole($roles): void {
    requireLogin();
    $roles = (array)$roles;
    $currentRole = getUserRole();
    if (!in_array($currentRole, $roles)) {
        header('Location: ' . BASE_URL . '/403.php');
        exit;
    }
}

/**
 * Redirect user ke dashboard sesuai rolenya
 */
function redirectByRole(): void {
    $role = getUserRole();
    switch ($role) {
        case 'superadmin':
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            break;
        case 'kepala':
            header('Location: ' . BASE_URL . '/kepala/dashboard.php');
            break;
        case 'pengurus':
            header('Location: ' . BASE_URL . '/pengurus/dashboard.php');
            break;
        default:
            header('Location: ' . BASE_URL . '/login.php');
    }
    exit;
}

/**
 * Cek apakah user berhak akses data bagian tertentu
 * Superadmin bisa akses semua bagian
 */
function canAccessBagian(int $id_bagian): bool {
    $role = getUserRole();
    if ($role === 'superadmin') return true;
    return getUserBagian() === $id_bagian;
}

/**
 * Format rupiah
 */
function formatRupiah(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Format tanggal Indonesia
 */
function formatTanggal(string $date): string {
    if (empty($date) || $date === '0000-00-00') return '-';
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
        4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September',
        10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $ts = strtotime($date);
    return date('d', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Flash message helper
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Sanitize input
 */
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}
