<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus','kepala','superadmin']);
$id = (int)($_POST['id'] ?? 0);
$role = getUserRole();
$id_bagian = getUserBagian();

if ($id) {
    $tx = $conn->query("SELECT id, id_bagian, status FROM pengurangan WHERE id=$id")->fetch_assoc();
    if ($tx && $tx['status'] === 'pending') {
        if ($role === 'superadmin' || $tx['id_bagian'] == $id_bagian) {
            // Hapus detail FIFO terlebih dahulu (FK constraint)
            $conn->query("DELETE FROM pengurangan_detail WHERE id_pengurangan=$id");
            $conn->query("DELETE FROM pengurangan WHERE id=$id");
            setFlash('success','Pengurangan berhasil dihapus.');
        } else {
            setFlash('error','Akses ditolak.');
        }
    } else {
        setFlash('error','Hanya transaksi pending yang bisa dihapus.');
    }
}
header('Location: index.php'); exit;
