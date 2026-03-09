-- ============================================================
-- SIPEBA (Sistem Informasi Persediaan Barang) - Bantul
-- Database Schema + Seed Data
-- ============================================================

CREATE DATABASE IF NOT EXISTS `sipeba` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sipeba`;

-- ============================================================
-- Tabel: bagian (9 Unit Kerja Setda Bantul)
-- ============================================================
CREATE TABLE `bagian` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `kode` VARCHAR(10) NOT NULL UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `bagian` (`kode`, `nama`) VALUES
('TAPEM',   'Bagian Tata Pemerintahan'),
('HUKUM',   'Bagian Hukum'),
('EKON',    'Bagian Perekonomian'),
('RENKEU',  'Bagian Perencanaan dan Keuangan'),
('ORG',     'Bagian Organisasi'),
('UMPROTK', 'Bagian Umum dan Protokol'),
('KESRA',   'Bagian Kesejahteraan Rakyat'),
('PBJ',     'Bagian Pengadaan Barang dan Jasa'),
('SETDA',   'Sekretariat Daerah');

-- ============================================================
-- Tabel: users
-- ============================================================
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('superadmin','kepala','pengurus') NOT NULL,
  `id_bagian` INT UNSIGNED NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_bagian`) REFERENCES `bagian`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Password untuk semua user: password (di-hash dengan bcrypt)
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO `users` (`nama`, `username`, `password`, `role`, `id_bagian`) VALUES
-- Super Admin (tidak perlu bagian)
('Administrator SIPEBA', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', NULL),

-- Kepala Bagian (9 bagian)
('Kepala Tata Pemerintahan',   'kepala_tapem',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 1),
('Kepala Hukum',               'kepala_hukum',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 2),
('Kepala Perekonomian',        'kepala_ekon',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 3),
('Kepala Renkeu',              'kepala_renkeu',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 4),
('Kepala Organisasi',          'kepala_org',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 5),
('Kepala Umum dan Protokol',   'kepala_umprotk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 6),
('Kepala Kesra',               'kepala_kesra',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 7),
('Kepala PBJ',                 'kepala_pbj',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 8),
('Kepala Setda',               'kepala_setda',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 9),

-- Pengurus Barang (9 bagian)
('Pengurus Tata Pemerintahan', 'pengurus_tapem',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 1),
('Pengurus Hukum',             'pengurus_hukum',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 2),
('Pengurus Perekonomian',      'pengurus_ekon',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 3),
('Pengurus Renkeu',            'pengurus_renkeu',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 4),
('Pengurus Organisasi',        'pengurus_org',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 5),
('Pengurus Umum dan Protokol', 'pengurus_umprotk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 6),
('Pengurus Kesra',             'pengurus_kesra',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 7),
('Pengurus PBJ',               'pengurus_pbj',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 8),
('Pengurus Setda',             'pengurus_setda',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 9);

-- ============================================================
-- Tabel: jenis_barang
-- ============================================================
CREATE TABLE `jenis_barang` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `kode_jenis` VARCHAR(10) NOT NULL UNIQUE,
  `nama_jenis` VARCHAR(100) NOT NULL,
  `kategori` ENUM('ASET TETAP','ASET LANCAR') NOT NULL DEFAULT 'ASET LANCAR',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `jenis_barang` (`kode_jenis`, `nama_jenis`, `kategori`) VALUES
('ATK',   'Alat Tulis Kantor',       'ASET LANCAR'),
('BHP',   'Bahan Habis Pakai',       'ASET LANCAR'),
('KOMP',  'Komputer dan Elektronik', 'ASET TETAP'),
('PRINT', 'Tinta dan Printer',       'ASET TETAP'),
('KEBRS', 'Kebersihan dan Sanitasi', 'ASET LANCAR'),
('LAIN',  'Lain-lain',               'ASET LANCAR');

-- ============================================================
-- Tabel: barang (Master Barang)
-- ============================================================
CREATE TABLE `barang` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `kode_barang` VARCHAR(20) NOT NULL UNIQUE,
  `nama_barang` VARCHAR(150) NOT NULL,
  `satuan` VARCHAR(30) NOT NULL,
  `id_jenis_barang` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_jenis_barang`) REFERENCES `jenis_barang`(`id`)
) ENGINE=InnoDB;

INSERT INTO `barang` (`kode_barang`, `nama_barang`, `satuan`, `id_jenis_barang`) VALUES
('ATK-001', 'Kertas A4 80gr',         'Rim',   1),
('ATK-002', 'Pulpen',                 'Lusin',  1),
('ATK-003', 'Stapler',                'Buah',   1),
('ATK-004', 'Amplop Coklat Folio',    'Kotak',  1),
('BHP-001', 'Tisu Kotak',             'Kotak',  2),
('BHP-002', 'Hand Sanitizer 500ml',   'Botol',  2),
('KOMP-001','Flashdisk 32GB',         'Buah',   3),
('PRINT-001','Tinta Printer Hitam',   'Botol',  4),
('PRINT-002','Tinta Printer Warna',   'Set',    4),
('KEBRS-001','Sabun Cuci Tangan',     'Botol',  5);

-- ============================================================
-- Tabel: penerimaan (Transaksi Penerimaan Barang — Buku IV)
-- ============================================================
CREATE TABLE `penerimaan` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `no_faktur` VARCHAR(50) NOT NULL,
  `tanggal` DATE NOT NULL,
  `id_barang` INT UNSIGNED NOT NULL,
  `jumlah` INT UNSIGNED NOT NULL,
  `sisa_stok` INT UNSIGNED NOT NULL COMMENT 'Sisa stok batch ini (FIFO)',
  `harga_satuan` DECIMAL(15,2) NOT NULL,
  `jumlah_harga` DECIMAL(15,2) GENERATED ALWAYS AS (`jumlah` * `harga_satuan`) STORED,
  `no_bukti_penerimaan` VARCHAR(50) NULL,
  `keterangan` TEXT NULL,
  `sumber` ENUM('belanja_modal','belanja_barang_jasa','dropping','hibah') DEFAULT 'belanja_modal',
  `id_bagian` INT UNSIGNED NOT NULL,
  `id_user` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
  `id_approver` INT UNSIGNED NULL,
  `approved_at` TIMESTAMP NULL,
  `catatan_approval` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_barang`) REFERENCES `barang`(`id`),
  FOREIGN KEY (`id_bagian`) REFERENCES `bagian`(`id`),
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id`),
  FOREIGN KEY (`id_approver`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- Tabel: pengurangan (Transaksi Pengurangan/Pengeluaran — Buku X)
-- ============================================================
CREATE TABLE `pengurangan` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `no_permintaan` VARCHAR(50) NOT NULL,
  `tanggal` DATE NOT NULL,
  `id_barang` INT UNSIGNED NOT NULL,
  `jumlah` INT UNSIGNED NOT NULL,
  `keterangan` TEXT NULL,
  `jenis` ENUM('penghapusan','mutasi_keluar') DEFAULT 'penghapusan',
  `penerima` VARCHAR(100) NULL,
  `tanggal_penyerahan` DATE NULL,
  `id_bagian` INT UNSIGNED NOT NULL,
  `id_user` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
  `id_approver` INT UNSIGNED NULL,
  `approved_at` TIMESTAMP NULL,
  `catatan_approval` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_barang`) REFERENCES `barang`(`id`),
  FOREIGN KEY (`id_bagian`) REFERENCES `bagian`(`id`),
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id`),
  FOREIGN KEY (`id_approver`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- Tabel: pengurangan_detail (Rincian FIFO per-batch penerimaan)
-- ============================================================
CREATE TABLE `pengurangan_detail` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_pengurangan` INT UNSIGNED NOT NULL,
  `id_penerimaan` INT UNSIGNED NOT NULL COMMENT 'Batch penerimaan yang dipotong',
  `jumlah_dipotong` INT UNSIGNED NOT NULL,
  `harga_satuan` DECIMAL(15,2) NOT NULL COMMENT 'Harga dari batch penerimaan',
  FOREIGN KEY (`id_pengurangan`) REFERENCES `pengurangan`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_penerimaan`) REFERENCES `penerimaan`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- Tabel: stok_current (Stok Aktif per Barang per Bagian)
-- Hanya mencerminkan transaksi yang sudah 'disetujui'
-- ============================================================
CREATE TABLE `stok_current` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_barang` INT UNSIGNED NOT NULL,
  `id_bagian` INT UNSIGNED NOT NULL,
  `stok` INT NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_stok` (`id_barang`, `id_bagian`),
  FOREIGN KEY (`id_barang`) REFERENCES `barang`(`id`),
  FOREIGN KEY (`id_bagian`) REFERENCES `bagian`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- Tabel: stock_opname
-- ============================================================
CREATE TABLE `stock_opname` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tanggal` DATE NOT NULL,
  `id_barang` INT UNSIGNED NOT NULL,
  `id_bagian` INT UNSIGNED NOT NULL,
  `stok_sistem` INT NOT NULL DEFAULT 0,
  `stok_fisik` INT NOT NULL DEFAULT 0,
  `selisih` INT GENERATED ALWAYS AS (`stok_fisik` - `stok_sistem`) STORED,
  `keterangan` TEXT NULL,
  `id_user` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
  `id_approver` INT UNSIGNED NULL,
  `approved_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_barang`) REFERENCES `barang`(`id`),
  FOREIGN KEY (`id_bagian`) REFERENCES `bagian`(`id`),
  FOREIGN KEY (`id_user`) REFERENCES `users`(`id`),
  FOREIGN KEY (`id_approver`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;
