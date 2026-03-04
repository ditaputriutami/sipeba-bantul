<?php
require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$username = sanitize($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    setFlash('error', 'Username dan password tidak boleh kosong.');
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$stmt = $conn->prepare(
    "SELECT u.*, b.nama AS nama_bagian, b.kode AS kode_bagian 
     FROM users u 
     LEFT JOIN bagian b ON u.id_bagian = b.id 
     WHERE u.username = ? AND u.is_active = 1"
);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password'])) {
    setFlash('error', 'Username atau password salah. Silakan coba lagi.');
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user'] = [
    'id'          => $user['id'],
    'nama'        => $user['nama'],
    'username'    => $user['username'],
    'role'        => $user['role'],
    'id_bagian'   => $user['id_bagian'],
    'nama_bagian' => $user['nama_bagian'],
    'kode_bagian' => $user['kode_bagian'],
];

session_regenerate_id(true);
redirectByRole();
