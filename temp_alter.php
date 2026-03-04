<?php
require_once __DIR__ . '/config/bootstrap.php';
$conn->query("ALTER TABLE penerimaan ADD COLUMN dari VARCHAR(150) NULL AFTER id_barang");
$conn->query("ALTER TABLE penerimaan ADD COLUMN tanggal_bukti_penerimaan DATE NULL AFTER no_bukti_penerimaan");
echo "DONE";
