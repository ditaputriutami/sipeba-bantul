<?php

/**
 * SIPEBA - FIFO Pengurangan Store
 * 
 * Logika FIFO (First In, First Out):
 * 1. Ambil semua batch penerimaan yang DISETUJUI untuk barang ini (urut tanggal ASC)
 * 2. Potong stok dari batch paling lama sampai jumlah terpenuhi
 * 3. Simpan detail pemotongan per-batch di tabel pengurangan_detail
 * 4. Stok actual di stok_current dikurangi setelah approval kepala
 */

require_once __DIR__ . '/../../config/bootstrap.php';
requireRole(['pengurus', 'kepala']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$user = getCurrentUser();
$role = getUserRole();

$no_permintaan     = sanitize($_POST['no_permintaan'] ?? '');
$tanggal           = $_POST['tanggal'] ?? date('Y-m-d');
$id_barang         = (int)($_POST['id_barang'] ?? 0);
$jumlah            = (int)($_POST['jumlah'] ?? 0);
$jenis             = sanitize($_POST['jenis'] ?? 'penghapusan');
$keterangan        = sanitize($_POST['keterangan'] ?? '');
$id_bagian         = ($role === 'superadmin') ? (int)$_POST['id_bagian'] : (int)getUserBagian();
$id_user           = getUserId();

// ---- Validasi dasar// Validasi
$errors = [];
if (!$id_barang)     $errors[] = 'Barang wajib dipilih.';
if ($jumlah < 1)     $errors[] = 'Jumlah minimal 1.';
if (!$id_bagian)     $errors[] = 'Bagian wajib diisi.';
if (!in_array($jenis, ['penghapusan', 'mutasi_keluar'])) {
    $errors[] = 'Jenis pengurangan tidak valid.';
}

if (!empty($errors)) {
    setFlash('error', implode('<br>', $errors));
    header('Location: create.php');
    exit;
}

// ---- Validasi stok dari stok_current ----
$stok = $conn->query("SELECT COALESCE(stok,0) FROM stok_current WHERE id_barang=$id_barang AND id_bagian=$id_bagian")->fetch_row()[0] ?? 0;
if ($jumlah > $stok) {
    setFlash('error', "Stok tidak mencukupi. Stok tersedia: <strong>$stok</strong>.");
    header('Location: create.php');
    exit;
}

// ---- FIFO: Cek kecukupan dari batch penerimaan ----
// Ambil semua batch disetujui dengan sisa_stok > 0, urut dari terlama
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
    header('Location: create.php');
    exit;
}

// ---- Mulai transaksi DB ----
$conn->begin_transaction();
try {
    // Insert header pengurangan (status pending)
    $stmt = $conn->prepare("
        INSERT INTO pengurangan (no_permintaan, tanggal, id_barang, jumlah, keterangan, jenis, id_bagian, id_user, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param(
        'ssiissii',
        $no_permintaan,
        $tanggal,
        $id_barang,
        $jumlah,
        $keterangan,
        $jenis,
        $id_bagian,
        $id_user
    );
    $stmt->execute();
    $id_pengurangan = $conn->insert_id;
    $stmt->close();

    // ---- FIFO: Hitung pemotongan per-batch dan simpan detail ----
    $sisaPotongan = $jumlah;
    $detailStmt = $conn->prepare("
        INSERT INTO pengurangan_detail (id_pengurangan, id_penerimaan, jumlah_dipotong, harga_satuan)
        VALUES (?, ?, ?, ?)
    ");
    // Simpan rencana pemotongan — actual update sisa_stok dilakukan saat APPROVAL
    foreach ($batches as $batch) {
        if ($sisaPotongan <= 0) break;
        $dipotong = min($sisaPotongan, $batch['sisa_stok']);
        $detailStmt->bind_param('iiid', $id_pengurangan, $batch['id'], $dipotong, $batch['harga_satuan']);
        $detailStmt->execute();
        $sisaPotongan -= $dipotong;
    }
    $detailStmt->close();

    $conn->commit();
    setFlash('success', "Pengurangan berhasil disimpan. Menunggu persetujuan Kepala Bagian.");
} catch (Exception $e) {
    $conn->rollback();
    setFlash('error', 'Gagal menyimpan: ' . $e->getMessage());
}

header('Location: index.php');
exit;
