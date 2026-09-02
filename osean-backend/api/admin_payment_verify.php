<?php
// =============================================
// OSEAN - admin_payment_verify.php
// Verifikasi atau tolak pembayaran
// Jika ditolak: kuota_terjual dikurangi kembali
// =============================================
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_error('Method tidak diizinkan.', 405);

$data       = json_decode(file_get_contents('php://input'), true);
$payment_id = isset($data['payment_id']) ? (int)$data['payment_id']      : 0;
$action     = isset($data['action'])     ? sanitize($data['action'])       : '';

if ($payment_id <= 0)                          send_error('payment_id tidak valid.');
if (!in_array($action, ['verify', 'reject']))  send_error('Action tidak valid. Gunakan "verify" atau "reject".');

// Cek payment ada dan masih pending
$stmt = $conn->prepare("SELECT id, status, jumlah_tiket, ticket_id FROM payments WHERE id = ?");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) send_error('Pembayaran tidak ditemukan.', 404);
$payment = $result->fetch_assoc();
$stmt->close();

if ($payment['status'] !== 'pending') {
    send_error('Pembayaran ini sudah diproses (status: ' . $payment['status'] . ').');
}

$new_status  = ($action === 'verify') ? 'verified' : 'rejected';
$verified_at = date('Y-m-d H:i:s');

// Update status
$stmt = $conn->prepare("UPDATE payments SET status = ?, verified_at = ? WHERE id = ?");
$stmt->bind_param("ssi", $new_status, $verified_at, $payment_id);
if (!$stmt->execute()) send_error('Gagal update status: ' . $conn->error, 500);
$stmt->close();

// Jika ditolak → kembalikan kuota_terjual
if ($action === 'reject') {
    $stmt = $conn->prepare("UPDATE tickets SET kuota_terjual = kuota_terjual - ? WHERE id = ?");
    $stmt->bind_param("ii", $payment['jumlah_tiket'], $payment['ticket_id']);
    $stmt->execute();
    $stmt->close();
}

$msg = ($action === 'verify')
    ? 'Pembayaran DIVERIFIKASI. Tiket user sudah aktif.'
    : 'Pembayaran DITOLAK. Kuota tiket telah dikembalikan.';

send_success(['payment_id' => $payment_id, 'status_baru' => $new_status], $msg);
