<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus','kepala','superadmin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

$user = getCurrentUser();
$role = getUserRole();

$no_faktur         = sanitize($_POST['no_faktur'] ?? '');
$tanggal           = $_POST['tanggal'] ?? date('Y-m-d');
$id_barang         = (int)($_POST['id_barang'] ?? 0);
$jumlah            = (int)($_POST['jumlah'] ?? 0);
$harga_satuan      = (float)($_POST['harga_satuan'] ?? 0);
$no_bukti          = sanitize($_POST['no_bukti_penerimaan'] ?? '');
$keterangan        = sanitize($_POST['keterangan'] ?? '');
$id_bagian         = ($role === 'superadmin') ? (int)$_POST['id_bagian'] : (int)getUserBagian();
$id_user           = getUserId();

// Validasi
$errors = [];
if (!$no_faktur)  $errors[] = 'No. Faktur wajib diisi.';
if (!$id_barang)  $errors[] = 'Barang wajib dipilih.';
if ($jumlah < 1)  $errors[] = 'Jumlah minimal 1.';
if ($harga_satuan <= 0) $errors[] = 'Harga satuan harus lebih dari 0.';
if (!$id_bagian)  $errors[] = 'Bagian wajib diisi.';

if (!empty($errors)) {
    setFlash('error', implode('<br>', $errors));
    header('Location: create.php'); exit;
}

// Tanggal valid check
if (!DateTime::createFromFormat('Y-m-d', $tanggal)) {
    $tanggal = date('Y-m-d');
}

$stmt = $conn->prepare("
    INSERT INTO penerimaan (no_faktur, tanggal, id_barang, jumlah, sisa_stok, harga_satuan, no_bukti_penerimaan, keterangan, id_bagian, id_user, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
");
$stmt->bind_param('ssiiddssii',
    $no_faktur, $tanggal, $id_barang, $jumlah, $jumlah, /* sisa_stok = jumlah awal */
    $harga_satuan, $no_bukti, $keterangan, $id_bagian, $id_user
);

if ($stmt->execute()) {
    setFlash('success', "Penerimaan berhasil disimpan. Menunggu persetujuan Kepala Bagian.");
} else {
    setFlash('error', 'Gagal menyimpan: ' . $conn->error);
}
$stmt->close();
header('Location: index.php'); exit;
