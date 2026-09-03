-- =============================================
-- OSEAN - Migration: Ticket Verification & Cleanup
-- Jalankan script ini di phpMyAdmin atau MySQL CLI
-- SETELAH migration_midtrans.sql sudah dijalankan
-- =============================================

-- 1. Tambah kolom untuk check-in / verifikasi tiket
ALTER TABLE `payments`
  ADD COLUMN `is_checked_in` TINYINT(1) NOT NULL DEFAULT 0 AFTER `verified_at`,
  ADD COLUMN `checked_in_at` DATETIME NULL DEFAULT NULL AFTER `is_checked_in`;

-- 2. Tambah index untuk is_checked_in (mempercepat query verifikasi)
ALTER TABLE `payments`
  ADD KEY `idx_checked_in` (`is_checked_in`);

-- 3. Hapus kolom midtrans_order_id (tumpang tindih dengan kode_unik)
--    CATATAN: Backup data terlebih dahulu jika ada data produksi!
--    kode_unik sekarang menjadi satu-satunya order_id yang dikirim ke Midtrans.
ALTER TABLE `payments`
  DROP INDEX `uk_midtrans_order_id`,
  DROP COLUMN `midtrans_order_id`;

-- Verifikasi hasil
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_NAME = 'payments' AND TABLE_SCHEMA = DATABASE();
