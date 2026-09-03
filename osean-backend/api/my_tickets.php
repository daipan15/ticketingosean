<?php
// =============================================
// OSEAN - my_tickets.php
// Menampilkan tiket milik user yang login
// =============================================
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') send_error('Method tidak diizinkan.', 405);

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT
        p.id            AS payment_id,
        p.kode_unik,
        p.jumlah_tiket,
        p.total_bayar,
        p.metode_pembayaran,
        p.bukti_transfer,
        p.status,
        p.created_at    AS tanggal_order,
        p.verified_at,
        t.id            AS ticket_id,
        t.nama_tiket,
        t.harga,
        t.deskripsi
    FROM payments p
    JOIN tickets t ON p.ticket_id = t.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$tikets = [];
while ($row = $result->fetch_assoc()) {
    $tikets[] = [
        'payment_id'         => (int)$row['payment_id'],
        'kode_unik'          => $row['kode_unik'] ?? '',
        'jumlah_tiket'       => (int)$row['jumlah_tiket'],
        'total_bayar'        => (int)$row['total_bayar'],
        'total_format'       => format_rupiah($row['total_bayar']),
        'metode_pembayaran'  => $row['metode_pembayaran'],
        'bukti_transfer'     => UPLOAD_URL . $row['bukti_transfer'],
        'status'             => $row['status'],
        'tanggal_order'      => $row['tanggal_order'],
        'verified_at'        => $row['verified_at'],
        'ticket_id'          => (int)$row['ticket_id'],
        'nama_tiket'         => $row['nama_tiket'],
        'harga'              => (int)$row['harga'],
        'harga_format'       => format_rupiah($row['harga']),
        'deskripsi'          => $row['deskripsi']
    ];
}

$stmt->close();
send_success(['tikets' => $tikets, 'total' => count($tikets)], 'Berhasil mengambil tiket kamu.');
