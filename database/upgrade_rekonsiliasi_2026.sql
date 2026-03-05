-- Upgrade schema untuk fitur rekonsiliasi (DB existing)
-- Jalankan file ini jika database sudah terlanjur dibuat dari versi lama.

ALTER TABLE `penerimaan`
ADD COLUMN `sumber` ENUM('belanja_modal','belanja_barang_jasa','dropping','hibah')
DEFAULT 'belanja_modal'
AFTER `keterangan`;

ALTER TABLE `pengurangan`
ADD COLUMN `jenis` ENUM('penghapusan','mutasi_keluar')
DEFAULT 'penghapusan'
AFTER `keterangan`;

ALTER TABLE `jenis_barang`
ADD COLUMN `kategori` ENUM('ASET TETAP', 'ASET LANCAR')
DEFAULT 'ASET LANCAR'
AFTER `nama_jenis`;

-- Default semua ke ASET LANCAR dulu.
UPDATE `jenis_barang` SET `kategori` = 'ASET LANCAR';

-- Mapping awal sesuai seed terbaru.
UPDATE `jenis_barang` SET `kategori` = 'ASET TETAP' WHERE `kode_jenis` IN ('KOMP', 'PRINT');
