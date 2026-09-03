<?php
// =============================================
// OSEAN - admin_payments_list.php
// Kolom DB: jumlah_tiket, total_bayar, bukti_transfer, metode_pembayaran,
//           midtrans_order_id, midtrans_transaction_id, payment_type, snap_token
//           users: nama, email
//           tickets: nama_tiket, harga
// =============================================
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') send_error('Method tidak diizinkan.', 405);

$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$valid_status  = ['pending', 'settlement', 'capture', 'expire', 'cancel', 'deny', 'refund', 'verified', 'rejected'];

if (!empty($filter_status) && in_array($filter_status, $valid_status)) {
    $stmt = $conn->prepare("
        SELECT p.id AS payment_id, p.jumlah_tiket, p.total_bayar, p.metode_pembayaran,
               p.bukti_transfer, p.status, p.created_at AS tanggal_order, p.verified_at,
               p.midtrans_order_id, p.midtrans_transaction_id, p.payment_type, p.snap_token,
               t.id AS ticket_id, t.nama_tiket, t.harga,
               u.id AS user_id, u.nama, u.email
        FROM payments p
        JOIN tickets t ON p.ticket_id = t.id
        JOIN users u ON p.user_id = u.id
        WHERE p.status = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->bind_param("s", $filter_status);
} else {
    $stmt = $conn->prepare("
        SELECT p.id AS payment_id, p.jumlah_tiket, p.total_bayar, p.metode_pembayaran,
               p.bukti_transfer, p.status, p.created_at AS tanggal_order, p.verified_at,
               p.midtrans_order_id, p.midtrans_transaction_id, p.payment_type, p.snap_token,
               t.id AS ticket_id, t.nama_tiket, t.harga,
               u.id AS user_id, u.nama, u.email
        FROM payments p
        JOIN tickets t ON p.ticket_id = t.id
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
    ");
}

$stmt->execute();
$result = $stmt->get_result();

$payments  = [];
$pending   = 0;
$settled   = 0;
$rejected  = 0;
$expired   = 0;
$revenue   = 0;

while ($row = $result->fetch_assoc()) {
    $st = $row['status'];
    if ($st === 'pending')                                  $pending++;
    if (in_array($st, ['settlement', 'capture', 'verified'])) { $settled++; $revenue += $row['total_bayar']; }
    if (in_array($st, ['rejected', 'deny']))                $rejected++;
    if (in_array($st, ['expire', 'cancel']))                $expired++;

    $payments[] = [
        'payment_id'              => (int)$row['payment_id'],
        'jumlah_tiket'            => (int)$row['jumlah_tiket'],
        'total_bayar'             => (int)$row['total_bayar'],
        'total_format'            => format_rupiah($row['total_bayar']),
        'metode_pembayaran'       => $row['metode_pembayaran'],
        'bukti_transfer'          => $row['bukti_transfer'] ? UPLOAD_URL . $row['bukti_transfer'] : null,
        'status'                  => $row['status'],
        'tanggal_order'           => $row['tanggal_order'],
        'verified_at'             => $row['verified_at'],
        'midtrans_order_id'       => $row['midtrans_order_id'],
        'midtrans_transaction_id' => $row['midtrans_transaction_id'],
        'payment_type'            => $row['payment_type'],
        'ticket_id'               => (int)$row['ticket_id'],
        'nama_tiket'              => $row['nama_tiket'],
        'harga'                   => (int)$row['harga'],
        'user_id'                 => (int)$row['user_id'],
        'nama'                    => $row['nama'],
        'email'                   => $row['email']
    ];
}

$stmt->close();
send_success([
    'payments' => $payments,
    'total'    => count($payments),
    'stats'    => [
        'pending'        => $pending,
        'settled'        => $settled,
        'rejected'       => $rejected,
        'expired'        => $expired,
        'total_revenue'  => $revenue,
        'revenue_format' => format_rupiah($revenue)
    ]
], 'Berhasil mengambil daftar pembayaran.');
