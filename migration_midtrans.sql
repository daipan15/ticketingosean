-- =============================================
-- OSEAN - Migration: Midtrans Payment Integration
-- Jalankan script ini di phpMyAdmin atau MySQL CLI
-- SETELAH database osean_db sudah ada
-- =============================================

-- 1. Tambah kolom Midtrans ke tabel payments
ALTER TABLE `payments`
  ADD COLUMN `snap_token` VARCHAR(255) DEFAULT NULL AFTER `bukti_transfer`,
  ADD COLUMN `midtrans_order_id` VARCHAR(100) DEFAULT NULL AFTER `snap_token`,
  ADD COLUMN `midtrans_transaction_id` VARCHAR(255) DEFAULT NULL AFTER `midtrans_order_id`,
  ADD COLUMN `payment_type` VARCHAR(50) DEFAULT NULL AFTER `midtrans_transaction_id`;

-- 2. Ubah enum status untuk mendukung status Midtrans
ALTER TABLE `payments`
  MODIFY COLUMN `status` ENUM(
    'pending',
    'settlement',
    'capture',
    'expire',
    'cancel',
    'deny',
    'refund',
    'verified',
    'rejected'
  ) NOT NULL DEFAULT 'pending';

-- 3. Buat bukti_transfer menjadi nullable (tidak wajib lagi)
ALTER TABLE `payments`
  MODIFY COLUMN `bukti_transfer` VARCHAR(255) DEFAULT NULL;

-- 4. Buat metode_pembayaran menjadi nullable
ALTER TABLE `payments`
  MODIFY COLUMN `metode_pembayaran` VARCHAR(50) DEFAULT NULL;

-- 5. Tambah unique index untuk midtrans_order_id
ALTER TABLE `payments`
  ADD UNIQUE KEY `uk_midtrans_order_id` (`midtrans_order_id`);
