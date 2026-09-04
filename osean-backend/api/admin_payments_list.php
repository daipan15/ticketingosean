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

// Pagination
$page     = max(1, (int)($_GET['page']  ?? 1));
$limit    = min(200, max(10, (int)($_GET['limit'] ?? 100))); // 10–200 per page
$offset   = ($page - 1) * $limit;

// Filter opsional per status
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$where_clauses = [];
$params        = [];
$types         = '';

if (!empty($status_filter)) {
    $valid_statuses = ['pending','settlement','capture','expire','cancel','deny','refund','verified','rejected'];
    if (in_array($status_filter, $valid_statuses, true)) {
        $where_clauses[] = 'p.status = ?';
        $params[]        = $status_filter;
        $types          .= 's';
    }
}

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Hitung total untuk metadata pagination
$count_sql  = "SELECT COUNT(*) AS total FROM payments p {$where_sql}";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_count = (int)($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$count_stmt->close();

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
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$types   .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$payments           = [];
$pending            = 0;
$settled            = 0;
$rejected           = 0;
$expired            = 0;
$revenue            = 0;
$total_tickets_sold = 0;

while ($row = $result->fetch_assoc()) {
    $st = $row['status'];
    if ($st === 'pending') $pending++;
    if (in_array($st, ['settlement', 'capture', 'verified'])) {
        $settled++;
        $revenue += $row['total_bayar'];
        $multiplier = 1;
        $tName = strtolower($row['nama_tiket'] ?? '');
        if (strpos($tName, 'trio') !== false) {
            $multiplier = 3;
        } elseif (strpos($tName, 'duo') !== false) {
            $multiplier = 2;
        }
        $total_tickets_sold += ((int)$row['jumlah_tiket'] * $multiplier);
    }
    if (in_array($st, ['rejected', 'deny'])) $rejected++;
    if (in_array($st, ['expire', 'cancel'])) $expired++;

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
    'payments'   => $payments,
    'total'      => count($payments),
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total_count' => $total_count,
        'total_pages' => (int)ceil($total_count / $limit),
        'has_next'    => ($page * $limit) < $total_count,
    ],
    'stats'    => [
        'pending'            => $pending,
        'settled'            => $settled,
        'rejected'           => $rejected,
        'expired'            => $expired,
        'total_revenue'      => $revenue,
        'revenue_format'     => format_rupiah($revenue),
        'total_tickets_sold' => $total_tickets_sold
    ]
], 'Berhasil mengambil daftar pembayaran.');
