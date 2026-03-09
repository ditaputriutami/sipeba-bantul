<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus','kepala','superadmin']);
$id = (int)($_POST['id'] ?? 0);
$role = getUserRole();
$id_bagian = getUserBagian();

if ($id) {
    $tx = $conn->query("SELECT id, id_bagian, status FROM penerimaan WHERE id=$id")->fetch_assoc();
    if ($tx) {
        // Cek bagian access
        if ($tx['status'] !== 'pending') {
            setFlash('error','Hanya transaksi pending yang dapat dihapus.');
        } elseif ($role === 'superadmin' || $tx['id_bagian'] == $id_bagian) {
            $conn->query("DELETE FROM penerimaan WHERE id=$id");
            setFlash('success','Penerimaan berhasil dihapus.');
        } else {
            setFlash('error','Akses ditolak.');
        }
    } else {
        setFlash('error','Penerimaan tidak ditemukan.');
    }
}
header('Location: index.php'); exit;
