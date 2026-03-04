<?php
/**
 * API endpoint untuk cek stok barang per bagian
 * Dipanggil oleh form pengurangan via fetch()
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json');

$id_barang = (int)($_GET['id_barang'] ?? 0);
$id_bagian = (int)($_GET['id_bagian'] ?? 0);

if (!$id_barang) {
    echo json_encode(['stok' => 0, 'error' => 'id_barang required']);
    exit;
}

// Jika bukan admin, paksa bagian dari session
$role = getUserRole();
if ($role !== 'superadmin') {
    $id_bagian = getUserBagian();
}

if (!$id_bagian) {
    echo json_encode(['stok' => 0]);
    exit;
}

$stmt = $conn->prepare("SELECT COALESCE(stok, 0) as stok FROM stok_current WHERE id_barang=? AND id_bagian=?");
$stmt->bind_param('ii', $id_barang, $id_bagian);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode(['stok' => (int)($row['stok'] ?? 0)]);
