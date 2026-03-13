-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 13, 2026 at 04:49 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sipeba`
--

-- --------------------------------------------------------

--
-- Table structure for table `bagian`
--

CREATE TABLE `bagian` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(10) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bagian`
--

INSERT INTO `bagian` (`id`, `kode`, `nama`, `created_at`) VALUES
(1, 'TAPEM', 'Bagian Tata Pemerintahan', '2026-03-05 01:06:37'),
(2, 'HUKUM', 'Bagian Hukum', '2026-03-05 01:06:37'),
(3, 'EKON', 'Bagian Perekonomian', '2026-03-05 01:06:37'),
(4, 'RENKEU', 'Bagian Perencanaan dan Keuangan', '2026-03-05 01:06:37'),
(5, 'ORG', 'Bagian Organisasi', '2026-03-05 01:06:37'),
(6, 'UMPROTK', 'Bagian Umum dan Protokol', '2026-03-05 01:06:37'),
(7, 'KESRA', 'Bagian Kesejahteraan Rakyat', '2026-03-05 01:06:37'),
(8, 'PBJ', 'Bagian Pengadaan Barang dan Jasa', '2026-03-05 01:06:37'),
(9, 'SETDA', 'Bagian Sekretariat Daerah', '2026-03-05 01:06:37');

-- --------------------------------------------------------

--
-- Table structure for table `barang`
--

CREATE TABLE `barang` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode_barang` varchar(20) NOT NULL,
  `nama_barang` varchar(150) NOT NULL,
  `satuan` varchar(30) NOT NULL,
  `id_jenis_barang` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jenis_barang`
--

CREATE TABLE `jenis_barang` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode_jenis` varchar(10) NOT NULL,
  `nama_jenis` varchar(100) NOT NULL,
  `kategori` enum('ASET TETAP','ASET LANCAR') DEFAULT 'ASET LANCAR',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jenis_barang`
--

INSERT INTO `jenis_barang` (`id`, `kode_jenis`, `nama_jenis`, `kategori`, `created_at`) VALUES
(1, '117010101', 'BAHAN BANGUNAN DAN KONTRUKSI', 'ASET LANCAR', '2026-03-10 02:11:09'),
(2, '117010102', 'BAHAN KIMIA', 'ASET LANCAR', '2026-03-10 02:11:09'),
(3, '117010104', 'BAHAN BAKAR DAN PELUMAS', 'ASET LANCAR', '2026-03-10 02:11:09'),
(4, '117010105', 'BAHAN BAKU', 'ASET LANCAR', '2026-03-10 02:11:09'),
(5, '117010108', 'BAHAN/BIBIT TANAMAN', 'ASET LANCAR', '2026-03-10 02:11:09'),
(6, '117010109', 'ISI TABUNG PEMADAN KEBAKARAN', 'ASET LANCAR', '2026-03-10 02:11:09'),
(7, '117010110', 'ISI TABUNG GAS', 'ASET LANCAR', '2026-03-10 02:11:09'),
(8, '117010112', 'BAHAN LAINNYA', 'ASET LANCAR', '2026-03-10 02:11:09'),
(9, '117010201', 'SUKU CADANG ALAT ANGKUTAN', 'ASET LANCAR', '2026-03-10 02:11:09'),
(10, '117010211', 'SUKU CADANG LAINNYA', 'ASET LANCAR', '2026-03-10 02:11:09'),
(11, '117010301', 'ALAT TULIS KANTOR', 'ASET LANCAR', '2026-03-10 02:11:09'),
(12, '117010302', 'KERTAS DAN COVER', 'ASET LANCAR', '2026-03-10 02:11:09'),
(13, '117010303', 'BAHAN CETAK', 'ASET LANCAR', '2026-03-10 02:11:09'),
(14, '117010304', 'BENDA POS', 'ASET LANCAR', '2026-03-10 02:11:09'),
(15, '117010305', 'PERSEDIAAN DOKUMEN/ADMINISTRASI TENDER', 'ASET LANCAR', '2026-03-10 02:11:09'),
(16, '117010306', 'BAHAN KOMPUTER', 'ASET LANCAR', '2026-03-10 02:11:09'),
(17, '117010307', 'PERABOT KANTOR', 'ASET LANCAR', '2026-03-10 02:11:09'),
(18, '117010308', 'ALAT LISTRIK', 'ASET LANCAR', '2026-03-10 02:11:09'),
(19, '117010312', 'SUVERNIR/CENDERA MATA', 'ASET LANCAR', '2026-03-10 02:11:09'),
(20, '117010313', 'ALAT/BAHAN UNTUK KEGIATAN KANTOR LAINNYA', 'ASET LANCAR', '2026-03-10 02:11:09'),
(21, '117010401', 'OBAT', 'ASET LANCAR', '2026-03-10 02:11:09'),
(22, '117010402', 'OBAT - OBATAN LAINNYA', 'ASET LANCAR', '2026-03-10 02:11:09'),
(23, '117010501', 'PERSEDIAAN UNTUK DIJUAL/DISERAHKAN KEPADA MASYARAKAT', 'ASET LANCAR', '2026-03-10 02:11:09'),
(24, '117010701', 'NATURA', 'ASET LANCAR', '2026-03-10 02:11:09'),
(25, '117010703', 'NATURA DAN PAKAN LAINNYA', 'ASET LANCAR', '2026-03-10 02:11:09');

-- --------------------------------------------------------

--
-- Table structure for table `penerimaan`
--

CREATE TABLE `penerimaan` (
  `id` int(10) UNSIGNED NOT NULL,
  `no_faktur` varchar(50) NOT NULL,
  `tanggal` date NOT NULL,
  `id_barang` int(10) UNSIGNED NOT NULL,
  `jumlah` int(10) UNSIGNED NOT NULL,
  `sisa_stok` int(10) UNSIGNED NOT NULL COMMENT 'Sisa stok batch ini (FIFO)',
  `harga_satuan` decimal(15,2) NOT NULL,
  `dari` varchar(255) DEFAULT NULL,
  `jumlah_harga` decimal(15,2) GENERATED ALWAYS AS (`jumlah` * `harga_satuan`) STORED,
  `no_bukti_penerimaan` varchar(50) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `sumber` enum('belanja_modal','belanja_barang_jasa','dropping','hibah') DEFAULT 'belanja_modal',
  `id_bagian` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','disetujui','ditolak') DEFAULT 'pending',
  `id_approver` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `catatan_approval` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengurangan`
--

CREATE TABLE `pengurangan` (
  `id` int(10) UNSIGNED NOT NULL,
  `no_permintaan` varchar(50) NOT NULL,
  `tanggal` date NOT NULL,
  `id_barang` int(10) UNSIGNED NOT NULL,
  `jumlah` int(10) UNSIGNED NOT NULL,
  `keterangan` text DEFAULT NULL,
  `jenis` enum('penghapusan','mutasi_keluar') DEFAULT 'penghapusan',
  `penerima` varchar(100) DEFAULT NULL,
  `tanggal_penyerahan` date DEFAULT NULL,
  `id_bagian` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','disetujui','ditolak','disetujui sebagian') DEFAULT 'pending',
  `id_approver` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `catatan_approval` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengurangan_detail`
--

CREATE TABLE `pengurangan_detail` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_pengurangan` int(10) UNSIGNED NOT NULL,
  `id_penerimaan` int(10) UNSIGNED NOT NULL COMMENT 'Batch penerimaan yang dipotong',
  `jumlah_dipotong` int(10) UNSIGNED NOT NULL,
  `harga_satuan` decimal(15,2) NOT NULL COMMENT 'Harga dari batch penerimaan',
  `status` enum('pending','disetujui','ditolak') DEFAULT 'pending',
  `id_approver` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `catatan_approval` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_opname`
--

CREATE TABLE `stock_opname` (
  `id` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `id_barang` int(10) UNSIGNED NOT NULL,
  `id_bagian` int(10) UNSIGNED NOT NULL,
  `stok_sistem` int(11) NOT NULL DEFAULT 0,
  `stok_fisik` int(11) NOT NULL DEFAULT 0,
  `selisih` int(11) GENERATED ALWAYS AS (`stok_fisik` - `stok_sistem`) STORED,
  `keterangan` text DEFAULT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','disetujui','ditolak') DEFAULT 'pending',
  `id_approver` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stok_current`
--

CREATE TABLE `stok_current` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_barang` int(10) UNSIGNED NOT NULL,
  `id_bagian` int(10) UNSIGNED NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','kepala','pengurus') NOT NULL,
  `id_bagian` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `username`, `password`, `role`, `id_bagian`, `is_active`, `created_at`) VALUES
(1, 'Administrator SIPEBA', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', NULL, 1, '2026-03-05 01:06:37'),
(2, 'Kepala Tata Pemerintahan', 'kepala_tapem', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 1, 1, '2026-03-05 01:06:37'),
(3, 'Kepala Hukum', 'kepala_hukum', '$2y$10$Xm4VLtCDO83Q0fVlrjgm5O2V50nKcKd2.AxHvwTf8HGs8hAbkOjTa', 'kepala', 2, 1, '2026-03-05 01:06:37'),
(4, 'Kepala Perekonomian', 'kepala_ekon', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 3, 1, '2026-03-05 01:06:37'),
(5, 'Kepala Renkeu', 'kepala_renkeu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 4, 1, '2026-03-05 01:06:37'),
(6, 'Kepala Organisasi', 'kepala_org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 5, 1, '2026-03-05 01:06:37'),
(7, 'Kepala Umum dan Protokol', 'kepala_umprotk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 6, 1, '2026-03-05 01:06:37'),
(8, 'Kepala Kesra', 'kepala_kesra', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 7, 1, '2026-03-05 01:06:37'),
(9, 'Kepala PBJ', 'kepala_pbj', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 8, 1, '2026-03-05 01:06:37'),
(10, 'Kepala Setda', 'kepala_setda', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kepala', 9, 1, '2026-03-05 01:06:37'),
(11, 'Pengurus Tata Pemerintahan', 'pengurus_tapem', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 1, 1, '2026-03-05 01:06:37'),
(12, 'Pengurus Hukum', 'pengurus_hukum', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 2, 1, '2026-03-05 01:06:37'),
(13, 'Pengurus Perekonomian', 'pengurus_ekon', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 3, 1, '2026-03-05 01:06:37'),
(14, 'Pengurus Renkeu', 'pengurus_renkeu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 4, 1, '2026-03-05 01:06:37'),
(15, 'Pengurus Organisasi', 'pengurus_org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 5, 1, '2026-03-05 01:06:37'),
(16, 'Pengurus Umum dan Protokol', 'pengurus_umprotk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 6, 1, '2026-03-05 01:06:37'),
(17, 'Pengurus Kesra', 'pengurus_kesra', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 7, 1, '2026-03-05 01:06:37'),
(18, 'Pengurus PBJ', 'pengurus_pbj', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 8, 1, '2026-03-05 01:06:37'),
(19, 'Pengurus Setda', 'pengurus_setda', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pengurus', 9, 1, '2026-03-05 01:06:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bagian`
--
ALTER TABLE `bagian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `barang`
--
ALTER TABLE `barang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_jenis_barang` (`id_jenis_barang`);

--
-- Indexes for table `jenis_barang`
--
ALTER TABLE `jenis_barang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_jenis` (`kode_jenis`);

--
-- Indexes for table `penerimaan`
--
ALTER TABLE `penerimaan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_barang` (`id_barang`),
  ADD KEY `id_bagian` (`id_bagian`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_approver` (`id_approver`);

--
-- Indexes for table `pengurangan`
--
ALTER TABLE `pengurangan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_barang` (`id_barang`),
  ADD KEY `id_bagian` (`id_bagian`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_approver` (`id_approver`);

--
-- Indexes for table `pengurangan_detail`
--
ALTER TABLE `pengurangan_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pengurangan` (`id_pengurangan`),
  ADD KEY `id_penerimaan` (`id_penerimaan`);

--
-- Indexes for table `stock_opname`
--
ALTER TABLE `stock_opname`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_barang` (`id_barang`),
  ADD KEY `id_bagian` (`id_bagian`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_approver` (`id_approver`);

--
-- Indexes for table `stok_current`
--
ALTER TABLE `stok_current`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_stok` (`id_barang`,`id_bagian`),
  ADD KEY `id_bagian` (`id_bagian`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `id_bagian` (`id_bagian`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bagian`
--
ALTER TABLE `bagian`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `barang`
--
ALTER TABLE `barang`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `jenis_barang`
--
ALTER TABLE `jenis_barang`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `penerimaan`
--
ALTER TABLE `penerimaan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `pengurangan`
--
ALTER TABLE `pengurangan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pengurangan_detail`
--
ALTER TABLE `pengurangan_detail`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `stock_opname`
--
ALTER TABLE `stock_opname`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stok_current`
--
ALTER TABLE `stok_current`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `barang`
--
ALTER TABLE `barang`
  ADD CONSTRAINT `barang_ibfk_1` FOREIGN KEY (`id_jenis_barang`) REFERENCES `jenis_barang` (`id`);

--
-- Constraints for table `penerimaan`
--
ALTER TABLE `penerimaan`
  ADD CONSTRAINT `penerimaan_ibfk_1` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id`),
  ADD CONSTRAINT `penerimaan_ibfk_2` FOREIGN KEY (`id_bagian`) REFERENCES `bagian` (`id`),
  ADD CONSTRAINT `penerimaan_ibfk_3` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `penerimaan_ibfk_4` FOREIGN KEY (`id_approver`) REFERENCES `users` (`id`);

--
-- Constraints for table `pengurangan`
--
ALTER TABLE `pengurangan`
  ADD CONSTRAINT `pengurangan_ibfk_1` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id`),
  ADD CONSTRAINT `pengurangan_ibfk_2` FOREIGN KEY (`id_bagian`) REFERENCES `bagian` (`id`),
  ADD CONSTRAINT `pengurangan_ibfk_3` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `pengurangan_ibfk_4` FOREIGN KEY (`id_approver`) REFERENCES `users` (`id`);

--
-- Constraints for table `pengurangan_detail`
--
ALTER TABLE `pengurangan_detail`
  ADD CONSTRAINT `pengurangan_detail_ibfk_1` FOREIGN KEY (`id_pengurangan`) REFERENCES `pengurangan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pengurangan_detail_ibfk_2` FOREIGN KEY (`id_penerimaan`) REFERENCES `penerimaan` (`id`);

--
-- Constraints for table `stock_opname`
--
ALTER TABLE `stock_opname`
  ADD CONSTRAINT `stock_opname_ibfk_1` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id`),
  ADD CONSTRAINT `stock_opname_ibfk_2` FOREIGN KEY (`id_bagian`) REFERENCES `bagian` (`id`),
  ADD CONSTRAINT `stock_opname_ibfk_3` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_opname_ibfk_4` FOREIGN KEY (`id_approver`) REFERENCES `users` (`id`);

--
-- Constraints for table `stok_current`
--
ALTER TABLE `stok_current`
  ADD CONSTRAINT `stok_current_ibfk_1` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id`),
  ADD CONSTRAINT `stok_current_ibfk_2` FOREIGN KEY (`id_bagian`) REFERENCES `bagian` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`id_bagian`) REFERENCES `bagian` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
