<?php

/**
 * SIPEBA - FIFO Pengurangan Update
 * 
 * Update pengurangan yang masih berstatus pending.
 * Menghapus detail lama dan membuat ulang detail FIFO yang baru.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus', 'kepala']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$user = getCurrentUser();
$role = getUserRole();

$id                = (int)($_POST['id'] ?? 0);
$no_permintaan     = sanitize($_POST['no_permintaan'] ?? '');
$tanggal           = $_POST['tanggal'] ?? date('Y-m-d');
$id_barang         = (int)($_POST['id_barang'] ?? 0);
$jumlah            = (int)($_POST['jumlah'] ?? 0);
$jenis             = sanitize($_POST['jenis'] ?? 'penghapusan');
$keterangan        = sanitize($_POST['keterangan'] ?? '');
$id_bagian         = ($role === 'superadmin') ? (int)$_POST['id_bagian'] : (int)getUserBagian();
$id_user           = getUserId();

// ---- Validasi ID ----
if (!$id) {
    setFlash('error', 'ID pengurangan tidak valid.');
    header('Location: index.php');
    exit;
}

// ---- Get existing data ----
$stmt = $conn->prepare("SELECT * FROM pengurangan WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result->fetch_assoc();
$stmt->close();

if (!$existing) {
    setFlash('error', 'Data pengurangan tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// ---- Validasi: Hanya pending yang bisa diedit ----
if ($existing['status'] !== 'pending') {
    setFlash('error', 'Hanya pengurangan dengan status pending yang dapat diedit.');
    header('Location: index.php');
    exit;
}

// ---- Validasi ownership ----
if ($role !== 'superadmin' && $existing['id_bagian'] != getUserBagian()) {
    setFlash('error', 'Anda tidak memiliki akses untuk mengedit data ini.');
    header('Location: index.php');
    exit;
}

// ---- Validasi dasar ----
$errors = [];
if (!$id_barang)     $errors[] = 'Barang wajib dipilih.';
if ($jumlah < 1)     $errors[] = 'Jumlah minimal 1.';
if (!$id_bagian)     $errors[] = 'Bagian wajib diisi.';
if (!in_array($jenis, ['penghapusan', 'mutasi_keluar'])) {
    $errors[] = 'Jenis pengurangan tidak valid.';
}

if (!empty($errors)) {
    setFlash('error', implode('<br>', $errors));
    header('Location: edit.php?id=' . $id);
    exit;
}

// ---- Validasi stok dari stok_current ----
$stok = $conn->query("SELECT COALESCE(stok,0) FROM stok_current WHERE id_barang=$id_barang AND id_bagian=$id_bagian")->fetch_row()[0] ?? 0;
if ($jumlah > $stok) {
    setFlash('error', "Stok tidak mencukupi. Stok tersedia: <strong>$stok</strong>.");
    header('Location: edit.php?id=' . $id);
    exit;
}

// ---- FIFO: Cek kecukupan dari batch penerimaan ----
$batchQuery = $conn->prepare("
    SELECT id, sisa_stok, harga_satuan, tanggal
    FROM penerimaan
    WHERE id_barang=? AND id_bagian=? AND status='disetujui' AND sisa_stok > 0
    ORDER BY tanggal ASC, id ASC
");
$batchQuery->bind_param('ii', $id_barang, $id_bagian);
$batchQuery->execute();
$batches = $batchQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$batchQuery->close();

$totalSisa = array_sum(array_column($batches, 'sisa_stok'));
if ($jumlah > $totalSisa) {
    setFlash('error', "Jumlah melebihi stok yang tersedia di batch penerimaan ($totalSisa).");
    header('Location: edit.php?id=' . $id);
    exit;
}

// ---- Mulai transaksi DB ----
$conn->begin_transaction();
try {
    // Hapus detail lama
    $deleteStmt = $conn->prepare("DELETE FROM pengurangan_detail WHERE id_pengurangan = ?");
    $deleteStmt->bind_param('i', $id);
    $deleteStmt->execute();
    $deleteStmt->close();

    // Update header pengurangan
    $updateStmt = $conn->prepare("
        UPDATE pengurangan 
        SET no_permintaan = ?, tanggal = ?, id_barang = ?, jumlah = ?, 
            keterangan = ?, jenis = ?, id_bagian = ?, id_user = ?
        WHERE id = ?
    ");
    $updateStmt->bind_param(
        'ssiissiii',
        $no_permintaan,
        $tanggal,
        $id_barang,
        $jumlah,
        $keterangan,
        $jenis,
        $id_bagian,
        $id_user,
        $id
    );
    $updateStmt->execute();
    $updateStmt->close();

    // ---- FIFO: Hitung pemotongan per-batch dan simpan detail baru ----
    $sisaPotongan = $jumlah;
    $detailStmt = $conn->prepare("
        INSERT INTO pengurangan_detail (id_pengurangan, id_penerimaan, jumlah_dipotong, harga_satuan)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($batches as $batch) {
        if ($sisaPotongan <= 0) break;
        $dipotong = min($sisaPotongan, $batch['sisa_stok']);
        $detailStmt->bind_param('iiid', $id, $batch['id'], $dipotong, $batch['harga_satuan']);
        $detailStmt->execute();
        $sisaPotongan -= $dipotong;
    }
    $detailStmt->close();

    $conn->commit();
    setFlash('success', "Pengurangan berhasil diupdate. Menunggu persetujuan Kepala Bagian.");
} catch (Exception $e) {
    $conn->rollback();
    setFlash('error', 'Gagal mengupdate: ' . $e->getMessage());
    header('Location: edit.php?id=' . $id);
    exit;
}

header('Location: index.php');
exit;
