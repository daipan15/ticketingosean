<?php
// =============================================
// OSEAN - tickets_list.php
// Kolom DB: id, nama_tiket, deskripsi, harga, kuota, kuota_terjual, created_at
// =============================================
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') send_error('Method tidak diizinkan.', 405);

$stmt = $conn->prepare("
    SELECT id, nama_tiket, deskripsi, harga, kuota, kuota_terjual,
           (kuota - kuota_terjual) AS sisa_kuota, created_at
    FROM tickets
    WHERE (kuota - kuota_terjual) > 0
    ORDER BY harga ASC
");
$stmt->execute();
$result = $stmt->get_result();

$tickets = [];
while ($row = $result->fetch_assoc()) {
    $tickets[] = [
        'id'           => (int)$row['id'],
        'nama_tiket'   => $row['nama_tiket'],
        'deskripsi'    => $row['deskripsi'],
        'harga'        => (int)$row['harga'],
        'harga_format' => format_rupiah($row['harga']),
        'kuota'        => (int)$row['kuota'],
        'kuota_terjual'=> (int)$row['kuota_terjual'],
        'sisa_kuota'   => (int)$row['sisa_kuota'],
        'created_at'   => $row['created_at']
    ];
}

$stmt->close();
send_success(['tickets' => $tickets, 'total' => count($tickets)], 'Berhasil mengambil daftar tiket.');
