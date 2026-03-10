<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus', 'kepala', 'superadmin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$user = getCurrentUser();
$role = getUserRole();
$id_bagian_user = getUserBagian();

$id                = (int)($_POST['id'] ?? 0);
$no_faktur         = sanitize($_POST['no_faktur'] ?? '');
$tanggal           = $_POST['tanggal'] ?? date('Y-m-d');
$id_barang         = (int)($_POST['id_barang'] ?? 0);
$jumlah            = (int)($_POST['jumlah'] ?? 0);
$harga_satuan      = (float)($_POST['harga_satuan'] ?? 0);
$dari              = sanitize($_POST['dari'] ?? '');
$keterangan        = sanitize($_POST['keterangan'] ?? '');
$id_bagian         = ($role === 'superadmin') ? (int)$_POST['id_bagian'] : $id_bagian_user;

// Validasi
$errors = [];
if (!$id)         $errors[] = 'ID tidak valid.';
if (!$no_faktur)  $errors[] = 'No. Faktur wajib diisi.';
if (!$id_barang)  $errors[] = 'Barang wajib dipilih.';
if ($jumlah < 1)  $errors[] = 'Jumlah minimal 1.';
if ($harga_satuan <= 0) $errors[] = 'Harga satuan harus lebih dari 0.';
if (!$id_bagian)  $errors[] = 'Bagian wajib diisi.';

if (!empty($errors)) {
    setFlash(implode('<br>', $errors), 'error');
    header('Location: edit.php?id=' . $id);
    exit;
}

// Cek apakah data ada dan statusnya pending
$stmt = $conn->prepare("SELECT id, status, id_bagian FROM penerimaan WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$penerimaan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$penerimaan) {
    setFlash('Data tidak ditemukan', 'error');
    header('Location: index.php');
    exit;
}

if ($penerimaan['status'] !== 'pending') {
    setFlash('Tidak dapat mengedit transaksi yang sudah disetujui atau ditolak', 'error');
    header('Location: index.php');
    exit;
}

if ($role !== 'superadmin' && $penerimaan['id_bagian'] != $id_bagian_user) {
    setFlash('Anda tidak memiliki akses untuk mengedit transaksi ini', 'error');
    header('Location: index.php');
    exit;
}

// Tanggal valid check
if (!DateTime::createFromFormat('Y-m-d', $tanggal)) {
    $tanggal = date('Y-m-d');
}

// Update data
$stmt = $conn->prepare("
    UPDATE penerimaan 
    SET no_faktur=?, tanggal=?, id_barang=?, jumlah=?, sisa_stok=?, harga_satuan=?, dari=?, keterangan=?, id_bagian=?
    WHERE id=?
");
$stmt->bind_param(
    'ssiiidssii',
    $no_faktur,
    $tanggal,
    $id_barang,
    $jumlah,
    $jumlah,
    $harga_satuan,
    $dari,
    $keterangan,
    $id_bagian,
    $id
);

if ($stmt->execute()) {
    setFlash('Penerimaan berhasil diupdate', 'success');
} else {
    setFlash('Gagal mengupdate: ' . $conn->error, 'error');
}
$stmt->close();
header('Location: index.php');
exit;
