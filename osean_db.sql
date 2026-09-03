-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 02, 2026 at 08:37 PM
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
-- Database: `osean_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `kode_unik` varchar(50) NOT NULL,
  `jumlah_tiket` int(11) NOT NULL DEFAULT 1,
  `total_bayar` int(11) NOT NULL,
  `metode_pembayaran` varchar(50) DEFAULT NULL,
  `referral_code` varchar(20) DEFAULT NULL,
  `bukti_transfer` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pertanyaan` text NOT NULL,
  `jawaban` text DEFAULT NULL,
  `status` enum('menunggu','dijawab') NOT NULL DEFAULT 'menunggu',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `answered_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `kategori` varchar(50) NOT NULL DEFAULT 'Early Bird',
  `nama_tiket` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `harga` int(11) NOT NULL,
  `kuota` int(11) NOT NULL,
  `kuota_terjual` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `kategori`, `nama_tiket`, `deskripsi`, `harga`, `kuota`, `kuota_terjual`, `created_at`) VALUES
(1, 'Early Bird', 'Early Bird - Spectrum Single', 'Akses tiket masuk area festival untuk 1 orang', 38000, 100, 0, '2026-09-02 18:11:42'),
(2, 'Early Bird', 'Early Bird - Spectrum Duo', 'Paket hemat tiket masuk festival untuk 2 orang (Duo)', 68000, 50, 0, '2026-09-02 18:11:42'),
(3, 'Pre Sale 1', 'Pre Sale 1 - Spectrum Single', 'Akses tiket masuk area festival untuk 1 orang', 48000, 200, 0, '2026-09-02 18:11:42'),
(4, 'Pre Sale 1', 'Pre Sale 1 - Spectrum Duo', 'Paket hemat tiket masuk area festival untuk 2 orang (Duo)', 88000, 100, 0, '2026-09-02 18:11:42'),
(5, 'Pre Sale 1', 'Pre Sale 1 - Spectrum Trio', 'Paket bundling tiket masuk area festival untuk 3 orang (Trio)', 123000, 50, 0, '2026-09-02 18:11:42'),
(6, 'Pre Sale 2', 'Pre Sale 2 - Spectrum Single', 'Akses tiket masuk area festival untuk 1 orang', 58000, 200, 0, '2026-09-02 18:11:42'),
(7, 'Pre Sale 2', 'Pre Sale 2 - Spectrum Duo', 'Paket hemat tiket masuk area festival untuk 2 orang (Duo)', 108000, 100, 0, '2026-09-02 18:11:42'),
(8, 'Pre Sale 2', 'Pre Sale 2 - Spectrum Trio', 'Paket bundling tiket masuk area festival untuk 3 orang (Trio)', 153000, 50, 0, '2026-09-02 18:11:42'),
(9, 'On The Spot', 'On The Spot - Spectrum Single', 'Akses tiket masuk langsung di venue hari H untuk 1 orang', 68000, 100, 0, '2026-09-02 18:11:42'),
(10, 'On The Spot', 'On The Spot - Spectrum Duo', 'Akses tiket masuk langsung di venue hari H untuk 2 orang (Duo)', 128000, 50, 0, '2026-09-02 18:11:42');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password_hash`, `role`, `is_verified`, `verification_token`, `created_at`) VALUES
(1, 'Admin OSEAN', 'admin@osean.com', '$2y$12$CnRlbQYA5I5pI8suZGkNkuBdCzy93F0DzrvDQd438ay3GIzn90aJq', 'admin', 1, NULL, '2026-09-02 18:11:42');
-- Password default admin: admin123
-- PENTING: Ganti password ini setelah pertama kali login!

--
-- Indexes for dumped tables
--

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_unik` (`kode_unik`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
