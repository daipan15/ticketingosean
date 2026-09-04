<?php
// =============================================
// OSEAN - admin_tickets_list.php
// GET: Ambil semua data tiket untuk admin
// Kolom DB: id, kategori, nama_tiket, deskripsi, harga, kuota, kuota_terjual
// =============================================
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') send_error('Method tidak diizinkan.', 405);

$stmt = $conn->prepare("
    SELECT id, kategori, nama_tiket, deskripsi, harga, kuota, kuota_terjual,
           GREATEST(0, (kuota - kuota_terjual)) AS sisa_kuota,
           created_at
    FROM tickets
    ORDER BY id ASC
");
$stmt->execute();
$result = $stmt->get_result();

$tickets = [];
while ($row = $result->fetch_assoc()) {
    $sisa = (int)$row['sisa_kuota'];
    $tickets[] = [
        'id'            => (int)$row['id'],
        'kategori'      => $row['kategori'] ?? '',
        'nama_tiket'    => $row['nama_tiket'],
        'deskripsi'     => $row['deskripsi'],
        'harga'         => (int)$row['harga'],
        'harga_format'  => format_rupiah($row['harga']),
        'kuota'         => (int)$row['kuota'],
        'kuota_terjual' => (int)$row['kuota_terjual'],
        'sisa_kuota'    => $sisa,
        'is_sold_out'   => ($sisa <= 0),
        'created_at'    => $row['created_at']
    ];
}

$stmt->close();
send_success(['tickets' => $tickets, 'total' => count($tickets)], 'Berhasil mengambil daftar tiket.');
