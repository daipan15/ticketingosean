<?php
// =============================================
// OSEAN - payment_cancel.php
// Membatalkan transaksi pembayaran yang masih pending
// CATATAN: kode_unik digunakan sebagai Midtrans order_id
// =============================================
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method tidak diizinkan.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$payment_id = isset($input['payment_id']) ? (int)$input['payment_id'] : 0;
$user_id = $_SESSION['user_id'];

if (!$payment_id) {
    send_error('Payment ID tidak valid.');
}

// Cek data payment milik user (gunakan kode_unik untuk cancel Midtrans)
$stmt = $conn->prepare("SELECT id, status, kode_unik FROM payments WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $payment_id, $user_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    send_error('Data pembayaran tidak ditemukan.', 404);
}

if ($payment['status'] !== 'pending') {
    send_error('Hanya pembayaran dengan status pending yang dapat dibatalkan.');
}

// Coba batalkan ke Midtrans API menggunakan kode_unik sebagai order_id
if (!empty($payment['kode_unik']) && defined('MIDTRANS_SERVER_KEY') && !str_contains(MIDTRANS_SERVER_KEY, 'XXXX')) {
    $order_id = $payment['kode_unik']; // kode_unik = Midtrans order_id
    $auth     = base64_encode(MIDTRANS_SERVER_KEY . ':');
    $url      = MIDTRANS_API_URL . "/{$order_id}/cancel";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . $auth
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    apply_curl_ssl_options($ch);
    curl_exec($ch);
    curl_close($ch);
}

// Update status transaksi menjadi cancel
$stmt = $conn->prepare("UPDATE payments SET status = 'cancel' WHERE id = ?");
$stmt->bind_param("i", $payment_id);
if ($stmt->execute()) {
    $stmt->close();
    send_success([], 'Pembayaran berhasil dibatalkan.');
} else {
    $stmt->close();
    send_error('Gagal membatalkan pembayaran: ' . $conn->error, 500);
}
