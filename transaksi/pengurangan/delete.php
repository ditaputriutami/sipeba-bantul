<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus', 'kepala']);
$id = (int)($_POST['id'] ?? 0);
$role = getUserRole();
$id_bagian = getUserBagian();

if ($id) {
    $tx = $conn->query("SELECT id, id_bagian, status FROM pengurangan WHERE id=$id")->fetch_assoc();
    if ($tx) {
        if ($tx['status'] !== 'pending') {
            setFlash('error','Hanya transaksi pending yang dapat dihapus.');
        } elseif ($role === 'superadmin' || $tx['id_bagian'] == $id_bagian) {
            // Hapus detail FIFO terlebih dahulu (FK constraint)
            $conn->query("DELETE FROM pengurangan_detail WHERE id_pengurangan=$id");
            $conn->query("DELETE FROM pengurangan WHERE id=$id");
            setFlash('success','Pengeluaran berhasil dihapus.');
        } else {
            setFlash('error','Akses ditolak.');
        }
    } else {
        setFlash('error','Pengeluaran tidak ditemukan.');
    }
}
header('Location: index.php'); exit;
