-- =============================================
-- OSEAN Ticketing System — Database Setup (All-in-One)
-- Versi gabungan: osean_db.sql + semua migration
-- Cukup import file ini saja untuk fresh install
-- =============================================
-- Server version: 10.4.32-MariaDB (atau MySQL 8+)
-- PHP Version: 8.2+
-- Generated: Sep 04, 2026
-- =============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- =============================================
-- Database: `osean_db`
-- =============================================

-- --------------------------------------------------------
-- Tabel: `tickets`
-- --------------------------------------------------------
CREATE TABLE `tickets` (
  `id`            int(11)       NOT NULL AUTO_INCREMENT,
  `kategori`      varchar(50)   NOT NULL DEFAULT 'Early Bird',
  `nama_tiket`    varchar(100)  NOT NULL,
  `deskripsi`     text          DEFAULT NULL,
  `harga`         int(11)       NOT NULL,
  `kuota`         int(11)       NOT NULL,
  `kuota_terjual` int(11)       NOT NULL DEFAULT 0,
  `created_at`    timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tickets_kategori` (`kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabel: `users`
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id`                  int(11)       NOT NULL AUTO_INCREMENT,
  `nama`                varchar(100)  NOT NULL,
  `email`               varchar(100)  NOT NULL,
  `no_telepon`          varchar(20)   DEFAULT NULL,
  `password_hash`       varchar(255)  NOT NULL,
  `role`                enum('user','admin') NOT NULL DEFAULT 'user',
  `is_verified`         tinyint(1)    NOT NULL DEFAULT 0,
  `verification_token`  varchar(255)  DEFAULT NULL,
  `created_at`          timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_verification_token` (`verification_token`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabel: `questions`
-- --------------------------------------------------------
CREATE TABLE `questions` (
  `id`          int(11)   NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)   NOT NULL,
  `pertanyaan`  text      NOT NULL,
  `jawaban`     text      DEFAULT NULL,
  `status`      enum('menunggu','dijawab') NOT NULL DEFAULT 'menunggu',
  `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  `answered_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabel: `payments`
-- Versi final: sudah include semua kolom dari migration_midtrans
--              + migration_ticket_verification
-- --------------------------------------------------------
CREATE TABLE `payments` (
  `id`                      int(11)       NOT NULL AUTO_INCREMENT,
  `user_id`                 int(11)       NOT NULL,
  `ticket_id`               int(11)       NOT NULL,
  `kode_unik`               varchar(50)   NOT NULL,
  `jumlah_tiket`            int(11)       NOT NULL DEFAULT 1,
  `total_bayar`             int(11)       NOT NULL,
  `metode_pembayaran`       varchar(50)   DEFAULT NULL,
  `referral_code`           varchar(20)   DEFAULT NULL,
  `bukti_transfer`          varchar(255)  DEFAULT NULL,
  `snap_token`              varchar(255)  DEFAULT NULL,
  `midtrans_transaction_id` varchar(255)  DEFAULT NULL,
  `payment_type`            varchar(50)   DEFAULT NULL,
  `status`                  enum('pending','settlement','capture','expire','cancel','deny','refund','verified','rejected') NOT NULL DEFAULT 'pending',
  `created_at`              timestamp     NOT NULL DEFAULT current_timestamp(),
  `verified_at`             timestamp     NULL DEFAULT NULL,
  `is_checked_in`           tinyint(1)    NOT NULL DEFAULT 0,
  `checked_in_at`           datetime      NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_unik` (`kode_unik`),
  KEY `user_id` (`user_id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `idx_checked_in` (`is_checked_in`),
  KEY `idx_payments_status` (`status`),
  KEY `idx_payments_created_at` (`created_at`),
  KEY `idx_payments_user_id_status` (`user_id`, `status`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`)   ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- DATA AWAL: Tiket
-- =============================================
INSERT INTO `tickets` (`id`, `kategori`, `nama_tiket`, `deskripsi`, `harga`, `kuota`, `kuota_terjual`, `created_at`) VALUES
(1,  'Early Bird',  'Early Bird - Spectrum Single',  'Akses tiket masuk area festival untuk 1 orang',                              38000,  100, 0, '2026-09-02 18:11:42'),
(2,  'Early Bird',  'Early Bird - Spectrum Duo',     'Paket hemat tiket masuk festival untuk 2 orang (Duo)',                      68000,   50, 0, '2026-09-02 18:11:42'),
(3,  'Pre Sale 1',  'Pre Sale 1 - Spectrum Single',  'Akses tiket masuk area festival untuk 1 orang',                              48000,  200, 0, '2026-09-02 18:11:42'),
(4,  'Pre Sale 1',  'Pre Sale 1 - Spectrum Duo',     'Paket hemat tiket masuk area festival untuk 2 orang (Duo)',                  88000,  100, 0, '2026-09-02 18:11:42'),
(5,  'Pre Sale 1',  'Pre Sale 1 - Spectrum Trio',    'Paket bundling tiket masuk area festival untuk 3 orang (Trio)',             123000,   50, 0, '2026-09-02 18:11:42'),
(6,  'Pre Sale 2',  'Pre Sale 2 - Spectrum Single',  'Akses tiket masuk area festival untuk 1 orang',                              58000,  200, 0, '2026-09-02 18:11:42'),
(7,  'Pre Sale 2',  'Pre Sale 2 - Spectrum Duo',     'Paket hemat tiket masuk area festival untuk 2 orang (Duo)',                 108000,  100, 0, '2026-09-02 18:11:42'),
(8,  'Pre Sale 2',  'Pre Sale 2 - Spectrum Trio',    'Paket bundling tiket masuk area festival untuk 3 orang (Trio)',             153000,   50, 0, '2026-09-02 18:11:42'),
(9,  'On The Spot', 'On The Spot - Spectrum Single', 'Akses tiket masuk langsung di venue hari H untuk 1 orang',                  68000,  100, 0, '2026-09-02 18:11:42'),
(10, 'On The Spot', 'On The Spot - Spectrum Duo',    'Akses tiket masuk langsung di venue hari H untuk 2 orang (Duo)',            128000,   50, 0, '2026-09-02 18:11:42');

INSERT INTO `users` (`id`, `nama`, `email`, `no_telepon`, `password_hash`, `role`, `is_verified`, `verification_token`, `created_at`) VALUES
(1, 'Admin OSEAN', 'admin@osean.com', NULL, '$2y$12$CnRlbQYA5I5pI8suZGkNkuBdCzy93F0DzrvDQd438ay3GIzn90aJq', 'admin', 1, NULL, '2026-09-02 18:11:42');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
