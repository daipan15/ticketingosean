<?php
// =============================================
// OSEAN - admin_payment_verify.php
// Verifikasi atau tolak pembayaran (manual override)
// Mendukung status Midtrans: admin masih bisa verify/reject
// Jika ditolak: kuota_terjual dikurangi kembali (jika sudah di-increment)
// =============================================
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_error('Method tidak diizinkan.', 405);

$data       = json_decode(file_get_contents('php://input'), true);
$payment_id = isset($data['payment_id']) ? (int)$data['payment_id']      : 0;
$action     = isset($data['action'])     ? sanitize($data['action'])       : '';

if ($payment_id <= 0)                          send_error('payment_id tidak valid.');
if (!in_array($action, ['verify', 'reject']))  send_error('Action tidak valid. Gunakan "verify" atau "reject".');

// Cek payment ada
$stmt = $conn->prepare("SELECT id, status, jumlah_tiket, ticket_id FROM payments WHERE id = ?");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) send_error('Pembayaran tidak ditemukan.', 404);
$payment = $result->fetch_assoc();
$stmt->close();

// Status yang bisa di-approve/reject oleh admin
$actionable_statuses = ['pending', 'capture'];
if (!in_array($payment['status'], $actionable_statuses)) {
    // Settlement dari Midtrans juga bisa di-reject jika perlu (edge case refund)
    if ($payment['status'] === 'settlement' && $action === 'reject') {
        // Allow admin to reject a settled payment (manual refund scenario)
    } else if (in_array($payment['status'], ['verified', 'rejected', 'expire', 'cancel', 'deny', 'refund'])) {
        send_error('Pembayaran ini sudah diproses (status: ' . $payment['status'] . ').');
    }
}

$new_status  = ($action === 'verify') ? 'verified' : 'rejected';
$verified_at = date('Y-m-d H:i:s');

// Update status
$stmt = $conn->prepare("UPDATE payments SET status = ?, verified_at = ? WHERE id = ?");
$stmt->bind_param("ssi", $new_status, $verified_at, $payment_id);
if (!$stmt->execute()) send_error('Gagal update status: ' . $conn->error, 500);
$stmt->close();

// Jika VERIFY dan kuota belum di-increment (dari pending langsung verify)
if ($action === 'verify' && $payment['status'] === 'pending') {
    $stmt = $conn->prepare("UPDATE tickets SET kuota_terjual = kuota_terjual + ? WHERE id = ?");
    $stmt->bind_param("ii", $payment['jumlah_tiket'], $payment['ticket_id']);
    $stmt->execute();
    $stmt->close();
}

// Jika REJECT → kembalikan kuota_terjual (jika sebelumnya sudah di-increment)
if ($action === 'reject') {
    $was_counted = in_array($payment['status'], ['settlement', 'capture', 'verified']);
    if ($was_counted) {
        $stmt = $conn->prepare("UPDATE tickets SET kuota_terjual = GREATEST(kuota_terjual - ?, 0) WHERE id = ?");
        $stmt->bind_param("ii", $payment['jumlah_tiket'], $payment['ticket_id']);
        $stmt->execute();
        $stmt->close();
    }
}

$msg = ($action === 'verify')
    ? 'Pembayaran DIVERIFIKASI. Tiket user sudah aktif.'
    : 'Pembayaran DITOLAK. Kuota tiket telah dikembalikan.';

send_success(['payment_id' => $payment_id, 'status_baru' => $new_status], $msg);
