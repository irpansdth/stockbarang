-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 03, 2025 at 04:54 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mandirijayatop`
--

-- --------------------------------------------------------

--
-- Table structure for table `barang_keluar`
--

CREATE TABLE `barang_keluar` (
  `id_keluar` int NOT NULL,
  `id_barang` int NOT NULL,
  `customer_id` int DEFAULT NULL,
  `qty` int UNSIGNED NOT NULL DEFAULT '1',
  `tanggal` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `penerima` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `barang_keluar`
--

INSERT INTO `barang_keluar` (`id_keluar`, `id_barang`, `customer_id`, `qty`, `tanggal`, `penerima`) VALUES
(1, 10, 1, 1, '2025-10-22 17:00:00', 'Junaidi'),
(2, 6, 2, 2, '2025-10-22 17:00:00', 'Jonevi'),
(3, 6, 1, 1, '2025-11-02 17:00:00', 'Jonevi'),
(4, 11, 2, 8, '2025-11-02 17:00:00', 'Jonevi'),
(5, 10, 2, 5, '2025-11-02 17:00:00', 'Junaidi'),
(6, 10, NULL, 1, '2025-11-02 17:00:00', ''),
(7, 11, 2, 7, '2025-11-02 17:00:00', 'Jonevi'),
(8, 11, 2, 1, '2025-11-02 17:00:00', 'Junaidi');

-- --------------------------------------------------------

--
-- Table structure for table `barang_masuk`
--

CREATE TABLE `barang_masuk` (
  `id_masuk` int NOT NULL,
  `id_barang` int NOT NULL,
  `supplier_id` int DEFAULT NULL,
  `qty` int UNSIGNED NOT NULL DEFAULT '1',
  `tanggal` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `keterangan` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `barang_masuk`
--

INSERT INTO `barang_masuk` (`id_masuk`, `id_barang`, `supplier_id`, `qty`, `tanggal`, `keterangan`) VALUES
(1, 6, 2, 20, '2025-10-21 17:00:00', ''),
(2, 10, 1, 10, '2025-10-22 17:00:00', ''),
(3, 11, 1, 1, '2025-11-01 17:00:00', 'dsfc');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `alamat` text,
  `telp` varchar(50) DEFAULT NULL,
  `pic` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `nama`, `alamat`, `telp`, `pic`, `created_at`) VALUES
(1, 'RMS', 'Jakarta Timur', '0821 4537 789', 'd2das2', '2025-10-21 14:43:25'),
(2, 'Askol', 'bonang', '085876543241', 'muhammad', '2025-10-23 06:47:37');

-- --------------------------------------------------------

--
-- Table structure for table `stock`
--

CREATE TABLE `stock` (
  `id_barang` int NOT NULL,
  `nama_barang` varchar(25) NOT NULL,
  `deskripsi` varchar(30) NOT NULL,
  `stock` int NOT NULL,
  `min_stock` int DEFAULT '0',
  `satuan` varchar(50) DEFAULT NULL,
  `lokasi` varchar(100) DEFAULT NULL,
  `kode_barang` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `stock`
--

INSERT INTO `stock` (`id_barang`, `nama_barang`, `deskripsi`, `stock`, `min_stock`, `satuan`, `lokasi`, `kode_barang`) VALUES
(6, 'kyowapmp Jet Cleaner Pump', 'Supplier mingguan', 67, 0, 'pcs', 'Guang A', '001'),
(10, 'scop', 'klx', 13, 0, 'box', 'klx', '003'),
(11, 'Dulux', 'ccc', 0, 0, 'box', 'Guang A', '002');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `alamat` text,
  `telp` varchar(50) DEFAULT NULL,
  `pic` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `nama`, `alamat`, `telp`, `pic`, `created_at`) VALUES
(1, 'Anugerah Rhadika Pratama', 'Perumahan Palem Ganda Asri 2, Jl. Musi II No.21, Karang Mulya, Kec. Karang Tengah, Kota Tangerang', '081212871525', 'Muhammad Romadhon', '2025-10-22 08:11:18'),
(2, 'Big Dipper Machinery Indonesia', 'Jl. Husein Sastranegara, Jurumudi, Kec. Benda, Kota Tangerang, Banten 15124', '082121030343', 'Abdul Mukti', '2025-10-22 08:46:41'),
(3, 'Global Saranamesin Mandiri', 'Jl. Marsekal Suryadarma Jl. Bandara Mas, RT.003/RW.003, Kedaung Wetan, Kec. Neglasari, Kota Tangeran', '0821559916100', 'Anggi Putra Pratama', '2025-10-22 08:47:27'),
(4, 'PT. Jetpak Mandiri Jaya', 'Jl. Permata Raya No.76, RT.004/RW.016, Tanah Tinggi, Kec. Tangerang, Kota Tangerang, Banten 15119', '082122260634', 'Suryanto', '2025-10-22 08:48:27'),
(5, 'Utama Teknik Indonesia', 'Jalan Sibuaten Blok C No. 7, RT.002/RW.005, Karang Mulya, Kec. Karang Tengah, Kota Tangerang, Banten', '081288120637', 'Wawan Hermawan', '2025-10-22 08:50:21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `role` enum('admin','staff','viewer') DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `nama`, `role`, `is_active`, `created_at`) VALUES
(3, 'admingudang', '$2y$10$JrGiHo8vt81em.LEmlhJe.nIAf8VQjxIDFvDFN5vUKsLtB6wp0ZFa', 'Markibi', 'admin', 1, '2025-10-31 06:37:47'),
(4, 'admin', '$2y$10$.cyQdzV7/pXVuVT89A6o5.G5CJI7J1S8T3Tc1bXEc8Cm7butGx.YC', 'sutisna', 'viewer', 1, '2025-11-02 03:37:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barang_keluar`
--
ALTER TABLE `barang_keluar`
  ADD KEY `idx_keluar_tanggal` (`tanggal`),
  ADD KEY `idx_customer_id` (`customer_id`);

--
-- Indexes for table `barang_masuk`
--
ALTER TABLE `barang_masuk`
  ADD PRIMARY KEY (`id_masuk`),
  ADD KEY `idx_masuk_tanggal` (`tanggal`),
  ADD KEY `idx_supplier_id` (`supplier_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`id_barang`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `barang_masuk`
--
ALTER TABLE `barang_masuk`
  MODIFY `id_masuk` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stock`
--
ALTER TABLE `stock`
  MODIFY `id_barang` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `barang_keluar`
--
ALTER TABLE `barang_keluar`
  ADD CONSTRAINT `fk_bk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `barang_masuk`
--
ALTER TABLE `barang_masuk`
  ADD CONSTRAINT `fk_bm_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
