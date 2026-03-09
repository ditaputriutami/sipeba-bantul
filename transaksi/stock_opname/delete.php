<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus', 'kepala', 'superadmin']);

$id = (int)($_POST['id'] ?? 0);
$role = getUserRole();
$id_bagian = getUserBagian();

if ($id) {
    $tx = $conn->query("SELECT id, id_bagian, status FROM stock_opname WHERE id=$id")->fetch_assoc();
    if ($tx) {
        // Hanya data pending yang dapat dihapus
        if ($tx['status'] !== 'pending') {
            setFlash('error', 'Hanya Stock Opname dengan status pending yang dapat dihapus.');
        }
        // Access control
        elseif ($role === 'superadmin' || $tx['id_bagian'] == $id_bagian) {
            $conn->query("DELETE FROM stock_opname WHERE id=$id");
            setFlash('success', 'Stock Opname berhasil dihapus.');
        } else {
            setFlash('error', 'Akses ditolak.');
        }
    } else {
        setFlash('error', 'Data tidak ditemukan.');
    }
}
header('Location: index.php');
exit;
