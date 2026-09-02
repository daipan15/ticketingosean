<?php
// =============================================
// OSEAN - admin_payments_list.php
// Kolom DB: jumlah_tiket, total_bayar, bukti_transfer, metode_pembayaran
//           users: nama, email
//           tickets: nama_tiket, harga
// =============================================
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') send_error('Method tidak diizinkan.', 405);

$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$valid_status  = ['pending', 'verified', 'rejected'];

if (!empty($filter_status) && in_array($filter_status, $valid_status)) {
    $stmt = $conn->prepare("
        SELECT p.id AS payment_id, p.jumlah_tiket, p.total_bayar, p.metode_pembayaran,
               p.bukti_transfer, p.status, p.created_at AS tanggal_order, p.verified_at,
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
$verified  = 0;
$rejected  = 0;
$revenue   = 0;

while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'pending')  $pending++;
    if ($row['status'] === 'verified') { $verified++; $revenue += $row['total_bayar']; }
    if ($row['status'] === 'rejected') $rejected++;

    $payments[] = [
        'payment_id'        => (int)$row['payment_id'],
        'jumlah_tiket'      => (int)$row['jumlah_tiket'],
        'total_bayar'       => (int)$row['total_bayar'],
        'total_format'      => format_rupiah($row['total_bayar']),
        'metode_pembayaran' => $row['metode_pembayaran'],
        'bukti_transfer'    => UPLOAD_URL . $row['bukti_transfer'],
        'status'            => $row['status'],
        'tanggal_order'     => $row['tanggal_order'],
        'verified_at'       => $row['verified_at'],
        'ticket_id'         => (int)$row['ticket_id'],
        'nama_tiket'        => $row['nama_tiket'],
        'harga'             => (int)$row['harga'],
        'user_id'           => (int)$row['user_id'],
        'nama'              => $row['nama'],
        'email'             => $row['email']
    ];
}

$stmt->close();
send_success([
    'payments' => $payments,
    'total'    => count($payments),
    'stats'    => [
        'pending'        => $pending,
        'verified'       => $verified,
        'rejected'       => $rejected,
        'total_revenue'  => $revenue,
        'revenue_format' => format_rupiah($revenue)
    ]
], 'Berhasil mengambil daftar pembayaran.');
