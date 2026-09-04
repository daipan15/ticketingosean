<?php
// =============================================
// OSEAN - notification_handler.php (Midtrans Webhook)
// Menerima notifikasi status pembayaran dari Midtrans secara otomatis.
//
// Setup:
//   Isi field ini di Midtrans Dashboard → Settings → Payment Notification URL:
//   https://<domain-anda>/midtrans/notification_handler.php
//
// Alur:
//   1. Midtrans POST JSON ke URL ini setiap ada perubahan status transaksi
//   2. Signature diverifikasi untuk keamanan
//   3. Tabel `payments` diupdate sesuai status baru
//   4. Kuota tiket diupdate otomatis jika pembayaran berhasil/dibatalkan
//
// Catatan:
//   order_id di Midtrans = kode_unik di tabel payments (format: OSN-XXXX-XXXX)
// =============================================

// Hanya terima POST dari Midtrans
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit();
}

// Load konfigurasi OSEAN (DB connection + konstanta Midtrans key)
require_once __DIR__ . '/../osean-backend/config.php';

// Load Midtrans PHP SDK
require_once __DIR__ . '/Midtrans.php';

use Midtrans\Config;
use Midtrans\Notification;

// Konfigurasi Midtrans SDK dari konstanta OSEAN di config.php
Config::$serverKey    = MIDTRANS_SERVER_KEY;
Config::$isProduction = MIDTRANS_IS_PRODUCTION;

// Baca raw body untuk verifikasi signature manual
$raw_body        = file_get_contents('php://input');
$notification_raw = json_decode($raw_body, true);

if (!$notification_raw) {
    error_log('[OSEAN Midtrans] Invalid JSON received');
    http_response_code(400);
    echo json_encode(['message' => 'Invalid JSON body']);
    exit();
}

// Log notifikasi masuk untuk debugging
error_log('[OSEAN Midtrans] Notification received: ' . $raw_body);

// Parse notifikasi via Midtrans SDK (termasuk validasi internal)
try {
    $notif = new Notification();
} catch (Exception $e) {
    error_log('[OSEAN Midtrans] Notification parse error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['message' => 'Notification parse error: ' . $e->getMessage()]);
    exit();
}

// Ekstrak data dari notifikasi Midtrans
$order_id           = $notif->order_id;
$transaction_status = $notif->transaction_status;
$fraud_status       = $notif->fraud_status;
$status_code        = $notif->status_code;
$gross_amount       = $notif->gross_amount;
$transaction_id     = $notif->transaction_id;
$payment_type       = $notif->payment_type;

// =============================================
// Verifikasi Signature Key (keamanan)
// Rumus: SHA512(order_id + status_code + gross_amount + server_key)
// =============================================
$signature_key_from_midtrans = $notification_raw['signature_key'] ?? '';
$expected_signature = hash('sha512',
    $order_id . $status_code . $gross_amount . MIDTRANS_SERVER_KEY
);

if ($signature_key_from_midtrans !== $expected_signature) {
    error_log('[OSEAN Midtrans] INVALID SIGNATURE for order: ' . $order_id);
    http_response_code(403);
    echo json_encode(['message' => 'Invalid signature']);
    exit();
}

// =============================================
// Cari record pembayaran di database OSEAN
// order_id dari Midtrans = kode_unik di tabel payments
// =============================================
$stmt = $conn->prepare(
    "SELECT id, status, jumlah_tiket, ticket_id FROM payments WHERE kode_unik = ? LIMIT 1"
);
$stmt->bind_param("s", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log('[OSEAN Midtrans] Order not found in DB: ' . $order_id);
    // Tetap kirim 200 agar Midtrans tidak retry terus-menerus
    http_response_code(200);
    echo json_encode(['message' => 'Order not found, skipped']);
    exit();
}

$payment    = $result->fetch_assoc();
$stmt->close();
$old_status = $payment['status'];

// =============================================
// Tentukan status baru berdasarkan transaction_status dari Midtrans
// =============================================
$new_status = $old_status; // default: tidak berubah

if ($transaction_status === 'capture') {
    // Kartu kredit: cek fraud_status
    $new_status = ($fraud_status === 'accept') ? 'settlement' : 'deny';
} elseif ($transaction_status === 'settlement') {
    $new_status = 'settlement';
} elseif ($transaction_status === 'pending') {
    $new_status = 'pending';
} elseif ($transaction_status === 'deny') {
    $new_status = 'deny';
} elseif ($transaction_status === 'expire') {
    $new_status = 'expire';
} elseif ($transaction_status === 'cancel') {
    $new_status = 'cancel';
} elseif ($transaction_status === 'refund' || $transaction_status === 'partial_refund') {
    $new_status = 'refund';
}

// Tentukan waktu verifikasi (hanya diisi saat pembayaran berhasil)
$verified_at = in_array($new_status, ['settlement', 'capture'])
    ? date('Y-m-d H:i:s')
    : null;

// =============================================
// Update tabel payments
// =============================================
$stmt = $conn->prepare("
    UPDATE payments
    SET status                  = ?,
        midtrans_transaction_id = ?,
        payment_type            = ?,
        metode_pembayaran       = ?,
        verified_at             = COALESCE(?, verified_at)
    WHERE id = ?
");
$stmt->bind_param("sssssi",
    $new_status,
    $transaction_id,
    $payment_type,
    $payment_type,
    $verified_at,
    $payment['id']
);
$stmt->execute();
$stmt->close();

// =============================================
// Update kuota tiket otomatis
// =============================================

// Pembayaran berhasil → tambah kuota_terjual
if (in_array($new_status, ['settlement', 'capture'])
    && !in_array($old_status, ['settlement', 'capture', 'verified'])
) {
    $stmt = $conn->prepare(
        "UPDATE tickets SET kuota_terjual = kuota_terjual + ? WHERE id = ?"
    );
    $stmt->bind_param("ii", $payment['jumlah_tiket'], $payment['ticket_id']);
    $stmt->execute();
    $stmt->close();
    error_log(
        '[OSEAN Midtrans] Kuota +' . $payment['jumlah_tiket']
        . ' untuk order: ' . $order_id
    );
}

// Pembayaran gagal/dibatalkan dari status berhasil → kurangi kuota_terjual
if (in_array($new_status, ['cancel', 'expire', 'deny', 'refund'])
    && in_array($old_status, ['settlement', 'capture', 'verified'])
) {
    $stmt = $conn->prepare(
        "UPDATE tickets SET kuota_terjual = GREATEST(kuota_terjual - ?, 0) WHERE id = ?"
    );
    $stmt->bind_param("ii", $payment['jumlah_tiket'], $payment['ticket_id']);
    $stmt->execute();
    $stmt->close();
    error_log(
        '[OSEAN Midtrans] Kuota -' . $payment['jumlah_tiket']
        . ' untuk order: ' . $order_id
    );
}

error_log(
    '[OSEAN Midtrans] Order ' . $order_id
    . ': ' . $old_status . ' → ' . $new_status
    . ' (payment_type: ' . $payment_type . ')'
);

// Midtrans mengharapkan HTTP 200 sebagai tanda notifikasi diterima
http_response_code(200);
echo json_encode(['message' => 'OK']);