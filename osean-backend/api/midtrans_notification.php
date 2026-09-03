<?php
// =============================================
// OSEAN - midtrans_notification.php
// Webhook handler untuk menerima notifikasi dari Midtrans
// URL ini harus di-set di Midtrans Dashboard → Settings → Payment Notification URL
// CATATAN: order_id di Midtrans = kode_unik (format OSN-XXXX-XXXX)
// =============================================
require_once __DIR__ . '/../config.php';

// Webhook Midtrans tidak butuh session/login — langsung terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit();
}

$raw_body = file_get_contents('php://input');
$notification = json_decode($raw_body, true);

if (!$notification) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid JSON']);
    exit();
}

// Log notifikasi untuk debugging
error_log("[OSEAN Midtrans] Notification received: " . $raw_body);

// Extract data dari notifikasi
// order_id di Midtrans = kode_unik (misal: OSN-ABCD-EFGH)
$order_id           = $notification['order_id'] ?? '';
$transaction_status = $notification['transaction_status'] ?? '';
$fraud_status       = $notification['fraud_status'] ?? '';
$status_code        = $notification['status_code'] ?? '';
$gross_amount       = $notification['gross_amount'] ?? '';
$signature_key      = $notification['signature_key'] ?? '';
$transaction_id     = $notification['transaction_id'] ?? '';
$payment_type       = $notification['payment_type'] ?? '';

// =============================================
// Verifikasi Signature Key (keamanan)
// SHA512(order_id + status_code + gross_amount + server_key)
// =============================================
$expected_signature = hash('sha512',
    $order_id . $status_code . $gross_amount . MIDTRANS_SERVER_KEY
);

if ($signature_key !== $expected_signature) {
    error_log("[OSEAN Midtrans] INVALID SIGNATURE for order: $order_id");
    http_response_code(403);
    echo json_encode(['message' => 'Invalid signature']);
    exit();
}

// Cari payment berdasarkan kode_unik (order_id dari Midtrans = kode_unik)
$stmt = $conn->prepare("SELECT id, status, jumlah_tiket, ticket_id FROM payments WHERE kode_unik = ?");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log("[OSEAN Midtrans] Order not found: $order_id");
    http_response_code(404);
    echo json_encode(['message' => 'Order not found']);
    exit();
}

$payment = $result->fetch_assoc();
$stmt->close();

$old_status = $payment['status'];

// =============================================
// Tentukan status baru berdasarkan transaction_status Midtrans
// =============================================
$new_status = $old_status; // default: tidak berubah

if ($transaction_status === 'capture') {
    // Untuk kartu kredit: cek fraud_status
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

// Update payment di database
$verified_at = in_array($new_status, ['settlement', 'capture']) ? date('Y-m-d H:i:s') : null;

$stmt = $conn->prepare("
    UPDATE payments
    SET status = ?,
        midtrans_transaction_id = ?,
        payment_type = ?,
        metode_pembayaran = ?,
        verified_at = COALESCE(?, verified_at)
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
// Update kuota tiket berdasarkan perubahan status
// =============================================

// Jika status berubah menjadi settlement/capture → increment kuota_terjual
if (in_array($new_status, ['settlement', 'capture']) && !in_array($old_status, ['settlement', 'capture', 'verified'])) {
    $stmt = $conn->prepare("UPDATE tickets SET kuota_terjual = kuota_terjual + ? WHERE id = ?");
    $stmt->bind_param("ii", $payment['jumlah_tiket'], $payment['ticket_id']);
    $stmt->execute();
    $stmt->close();
    error_log("[OSEAN Midtrans] Kuota incremented for order: $order_id (+{$payment['jumlah_tiket']})");
}

// Jika status berubah menjadi cancel/expire/deny/refund DARI settlement → kurangi kuota
if (in_array($new_status, ['cancel', 'expire', 'deny', 'refund']) && in_array($old_status, ['settlement', 'capture', 'verified'])) {
    $stmt = $conn->prepare("UPDATE tickets SET kuota_terjual = GREATEST(kuota_terjual - ?, 0) WHERE id = ?");
    $stmt->bind_param("ii", $payment['jumlah_tiket'], $payment['ticket_id']);
    $stmt->execute();
    $stmt->close();
    error_log("[OSEAN Midtrans] Kuota decremented for order: $order_id (-{$payment['jumlah_tiket']})");
}

error_log("[OSEAN Midtrans] Order $order_id: $old_status → $new_status (payment_type: $payment_type)");

// Midtrans expects HTTP 200 response
http_response_code(200);
echo json_encode(['message' => 'OK']);
