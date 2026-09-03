<?php
// =============================================
// OSEAN - admin_ticket_checkin.php
// Penukaran tiket fisik / Check-in langsung oleh Admin
// Endpoint: POST { "payment_id": 123, "action": "checkin" | "uncheckin" }
// Requires: Admin session
// =============================================
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_error('Method tidak diizinkan.', 405);

$data       = json_decode(file_get_contents('php://input'), true);
$payment_id = isset($data['payment_id']) ? (int)$data['payment_id'] : 0;
$action     = isset($data['action']) ? trim($data['action']) : 'checkin';

if ($payment_id <= 0) {
    send_error('ID Pembayaran tidak valid.', 400);
}

// Ambil data payment
$stmt = $conn->prepare("
    SELECT p.id, p.kode_unik, p.status, p.is_checked_in, p.checked_in_at,
           u.nama, t.nama_tiket
    FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN tickets t ON p.ticket_id = t.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    send_error('Data tiket / transaksi tidak ditemukan.', 404);
}

$payment = $result->fetch_assoc();
$stmt->close();

$valid_statuses = ['settlement', 'capture', 'verified'];

if ($action === 'checkin') {
    // Validasi apakah status pembayaran valid (sudah lunas)
    if (!in_array($payment['status'], $valid_statuses)) {
        send_error('Tiket belum lunas / tidak valid sehingga tidak dapat ditukarkan dengan tiket fisik.', 422);
    }

    if ((int)$payment['is_checked_in'] === 1) {
        $checked_time = $payment['checked_in_at']
            ? (new DateTime($payment['checked_in_at']))->format('d M Y, H:i')
            : '-';
        send_error("Tiket sudah pernah di-check in pada {$checked_time}.", 409);
    }

    $now = date('Y-m-d H:i:s');
    $up = $conn->prepare("UPDATE payments SET is_checked_in = 1, checked_in_at = ? WHERE id = ?");
    $up->bind_param("si", $now, $payment_id);
    if (!$up->execute()) {
        send_error('Gagal memperbarui status check-in: ' . $conn->error, 500);
    }
    $up->close();

    $time_fmt = (new DateTime($now))->format('d M Y, H:i');
    send_success([
        'payment_id'    => $payment_id,
        'kode_unik'     => $payment['kode_unik'],
        'is_checked_in' => 1,
        'checked_in_at' => $time_fmt
    ], "Tiket fisik untuk {$payment['nama']} ({$payment['kode_unik']}) berhasil ditukarkan!");

} else if ($action === 'uncheckin') {
    $up = $conn->prepare("UPDATE payments SET is_checked_in = 0, checked_in_at = NULL WHERE id = ?");
    $up->bind_param("i", $payment_id);
    if (!$up->execute()) {
        send_error('Gagal membatalkan status check-in: ' . $conn->error, 500);
    }
    $up->close();

    send_success([
        'payment_id'    => $payment_id,
        'kode_unik'     => $payment['kode_unik'],
        'is_checked_in' => 0,
        'checked_in_at' => null
    ], "Check-in tiket {$payment['kode_unik']} berhasil dibatalkan.");

} else {
    send_error('Action tidak valid. Gunakan "checkin" atau "uncheckin".', 400);
}
