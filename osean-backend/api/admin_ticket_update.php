<?php
// =============================================
// OSEAN - admin_ticket_update.php
// POST: Update nama_tiket, kuota, dan harga tiket
// Body JSON: { ticket_id, nama_tiket?, kuota?, harga? }
// Catatan: kuota tidak boleh kurang dari kuota_terjual
// =============================================
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_error('Method tidak diizinkan.', 405);

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['ticket_id'])) {
    send_error('Data tidak valid. Pastikan ticket_id disertakan.', 400);
}

$ticket_id = (int)$body['ticket_id'];
if ($ticket_id <= 0) send_error('ID tiket tidak valid.', 400);

// Ambil data tiket saat ini
$check = $conn->prepare("SELECT id, nama_tiket, harga, kuota, kuota_terjual FROM tickets WHERE id = ? LIMIT 1");
$check->bind_param('i', $ticket_id);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if (!$existing) send_error('Tiket tidak ditemukan.', 404);

// Ambil field yang akan diperbarui (fallback ke nilai lama jika tidak dikirim)
$nama_tiket = isset($body['nama_tiket']) ? sanitize($body['nama_tiket']) : $existing['nama_tiket'];
$harga      = isset($body['harga'])      ? (int)$body['harga']           : (int)$existing['harga'];
$kuota      = isset($body['kuota'])      ? (int)$body['kuota']           : (int)$existing['kuota'];

// Validasi
if (empty($nama_tiket)) send_error('Nama tiket tidak boleh kosong.', 400);
if ($harga < 0)         send_error('Harga tidak boleh negatif.', 400);
if ($kuota < 0)         send_error('Kuota tidak boleh negatif.', 400);

// Kuota tidak boleh kurang dari jumlah yang sudah terjual
$kuota_terjual = (int)$existing['kuota_terjual'];
if ($kuota < $kuota_terjual) {
    send_error("Kuota tidak boleh kurang dari jumlah tiket yang sudah terjual ({$kuota_terjual}).", 400);
}

// Update
$stmt = $conn->prepare("
    UPDATE tickets
    SET nama_tiket = ?, harga = ?, kuota = ?
    WHERE id = ?
");
$stmt->bind_param('siii', $nama_tiket, $harga, $kuota, $ticket_id);

if (!$stmt->execute()) {
    send_error('Gagal memperbarui tiket. Coba lagi.', 500);
}
$stmt->close();

send_success([
    'ticket' => [
        'id'            => $ticket_id,
        'nama_tiket'    => $nama_tiket,
        'harga'         => $harga,
        'harga_format'  => format_rupiah($harga),
        'kuota'         => $kuota,
        'kuota_terjual' => $kuota_terjual,
        'sisa_kuota'    => max(0, $kuota - $kuota_terjual),
    ]
], 'Tiket berhasil diperbarui.');
