<?php
// =============================================
// OSEAN - admin_payments_list.php
// Kolom DB: kode_unik, jumlah_tiket, total_bayar, bukti_transfer,
//           metode_pembayaran, payment_type, snap_token,
//           is_checked_in, checked_in_at
//           users: nama, email
//           tickets: nama_tiket, harga
// =============================================
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') send_error('Method tidak diizinkan.', 405);

$where_clauses = [];
$params        = [];
$types         = '';

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$sql = "
    SELECT p.id AS payment_id, p.kode_unik, p.jumlah_tiket, p.total_bayar, p.metode_pembayaran,
           p.referral_code, p.bukti_transfer, p.status, p.created_at AS tanggal_order, p.verified_at,
           p.payment_type, p.is_checked_in, p.checked_in_at,
           t.id AS ticket_id, t.nama_tiket, t.harga,
           u.id AS user_id, u.nama, u.email
    FROM payments p
    JOIN tickets t ON p.ticket_id = t.id
    JOIN users u ON p.user_id = u.id
    {$where_sql}
    ORDER BY p.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
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
        'payment_id'        => (int)$row['payment_id'],
        'kode_unik'         => $row['kode_unik'],
        'jumlah_tiket'      => (int)$row['jumlah_tiket'],
        'total_bayar'       => (int)$row['total_bayar'],
        'total_format'      => format_rupiah($row['total_bayar']),
        'metode_pembayaran' => $row['metode_pembayaran'],
        'referral_code'     => $row['referral_code'],
        'bukti_transfer'    => $row['bukti_transfer'] ? UPLOAD_URL . $row['bukti_transfer'] : null,
        'status'            => $row['status'],
        'tanggal_order'     => $row['tanggal_order'],
        'verified_at'       => $row['verified_at'],
        'payment_type'      => $row['payment_type'],
        'is_checked_in'     => (int)$row['is_checked_in'],
        'checked_in_at'     => $row['checked_in_at'],
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
        'settled'        => $settled,
        'rejected'       => $rejected,
        'expired'        => $expired,
        'total_revenue'  => $revenue,
        'revenue_format' => format_rupiah($revenue)
    ]
], 'Berhasil mengambil daftar pembayaran.');
