<?php

/**
 * API: Get Harga FIFO untuk Pengurangan
 * Metode FIFO Murni: Harga dari batch pertama yang masuk, bukan rata-rata
 */
require_once __DIR__ . '/../config/bootstrap.php';
requireRole(['pengurus', 'kepala', 'superadmin']);

header('Content-Type: application/json');

$id_barang = (int)($_GET['id_barang'] ?? 0);
$id_bagian = (int)($_GET['id_bagian'] ?? getUserBagian());
$jumlah = (int)($_GET['jumlah'] ?? 0);

if (!$id_barang || !$id_bagian || $jumlah <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter tidak lengkap',
        'harga_satuan' => 0,
        'jumlah_harga' => 0,
        'details' => []
    ]);
    exit;
}

// Ambil batch FIFO (tertua dulu) yang masih ada sisa stok
$batchQuery = $conn->prepare("
    SELECT id, no_faktur, tanggal, sisa_stok, harga_satuan,
           DATE_FORMAT(tanggal, '%d/%m/%Y') as tanggal_format
    FROM penerimaan
    WHERE id_barang = ? 
      AND id_bagian = ? 
      AND status = 'disetujui' 
      AND sisa_stok > 0
    ORDER BY tanggal ASC, id ASC
");
$batchQuery->bind_param('ii', $id_barang, $id_bagian);
$batchQuery->execute();
$batches = $batchQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$batchQuery->close();

// Hitung total stok tersedia
$total_stok = array_sum(array_column($batches, 'sisa_stok'));

if ($total_stok < $jumlah) {
    echo json_encode([
        'success' => false,
        'message' => 'Stok tidak mencukupi',
        'harga_satuan' => 0,
        'jumlah_harga' => 0,
        'stok_tersedia' => $total_stok,
        'details' => []
    ]);
    exit;
}

// Simulasi FIFO untuk hitung harga
$sisa_ambil = $jumlah;
$total_nilai = 0;
$details = [];
$harga_batch_pertama = 0; // FIFO murni: harga dari batch pertama

foreach ($batches as $index => $batch) {
    if ($sisa_ambil <= 0) break;

    $qty_dari_batch = min($sisa_ambil, $batch['sisa_stok']);
    $nilai_batch = $qty_dari_batch * $batch['harga_satuan'];

    // FIFO: Harga satuan dari batch PERTAMA yang dipotong
    if ($index === 0) {
        $harga_batch_pertama = $batch['harga_satuan'];
    }

    $total_nilai += $nilai_batch;

    $details[] = [
        'no_faktur' => $batch['no_faktur'],
        'tanggal' => $batch['tanggal_format'],
        'sisa_stok' => $batch['sisa_stok'],
        'qty_dipotong' => $qty_dari_batch,
        'harga_satuan' => $batch['harga_satuan'],
        'nilai' => $nilai_batch
    ];

    $sisa_ambil -= $qty_dari_batch;
}

echo json_encode([
    'success' => true,
    'harga_satuan' => $harga_batch_pertama, // FIFO: dari batch pertama, bukan average
    'jumlah_harga' => $total_nilai,
    'details' => $details,
    'multiple_batch' => count($details) > 1, // Indikator ada multiple batch
    'stok_tersedia' => $total_stok
]);
