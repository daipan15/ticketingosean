<?php
// =============================================
// OSEAN - payment_create.php
// DB payments: id, user_id, ticket_id, jumlah_tiket, total_bayar,
//              metode_pembayaran, bukti_transfer, status, created_at, verified_at
// DB tickets : kuota_terjual (increment saat beli)
// =============================================
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_error('Method tidak diizinkan.', 405);

$user_id       = $_SESSION['user_id'];
$ticket_id     = isset($_POST['ticket_id'])          ? (int)$_POST['ticket_id']             : 0;
$jumlah_tiket  = isset($_POST['jumlah_tiket'])       ? (int)$_POST['jumlah_tiket']          : 1;
$metode        = isset($_POST['metode_pembayaran'])   ? sanitize($_POST['metode_pembayaran']) : 'transfer';

if ($ticket_id <= 0 || $jumlah_tiket <= 0) send_error('ticket_id dan jumlah_tiket wajib diisi.');

// Cek stok tiket (sisa = kuota - kuota_terjual)
$stmt = $conn->prepare("SELECT id, nama_tiket, harga, kuota, kuota_terjual FROM tickets WHERE id = ?");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) send_error('Tiket tidak ditemukan.', 404);
$tiket = $result->fetch_assoc();
$stmt->close();

$sisa_kuota = $tiket['kuota'] - $tiket['kuota_terjual'];
if ($sisa_kuota < $jumlah_tiket) {
    send_error("Stok tiket tidak cukup. Sisa: {$sisa_kuota} tiket.");
}

// Validasi upload bukti transfer
if (!isset($_FILES['bukti_transfer']) || $_FILES['bukti_transfer']['error'] !== UPLOAD_ERR_OK) {
    send_error('Bukti transfer wajib diupload.');
}

$allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
$file_type     = mime_content_type($_FILES['bukti_transfer']['tmp_name']);
if (!in_array($file_type, $allowed_types)) send_error('Format file tidak didukung (JPG/PNG/WebP).');
if ($_FILES['bukti_transfer']['size'] > 5 * 1024 * 1024) send_error('Ukuran file maks 5 MB.');

// Simpan file upload
$ext      = pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION);
$filename = 'bukti_' . $user_id . '_' . time() . '.' . $ext;
$filepath = UPLOAD_DIR . $filename;

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $filepath)) {
    send_error('Gagal upload file.', 500);
}

$total_bayar = $tiket['harga'] * $jumlah_tiket;
$status      = 'pending';

// Insert payment
$stmt = $conn->prepare("
    INSERT INTO payments (user_id, ticket_id, jumlah_tiket, total_bayar, metode_pembayaran, bukti_transfer, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("iiidsss", $user_id, $ticket_id, $jumlah_tiket, $total_bayar, $metode, $filename, $status);

if (!$stmt->execute()) {
    @unlink($filepath);
    send_error('Gagal menyimpan data: ' . $conn->error, 500);
}
$payment_id = $stmt->insert_id;
$stmt->close();

// Update kuota_terjual
$stmt = $conn->prepare("UPDATE tickets SET kuota_terjual = kuota_terjual + ? WHERE id = ?");
$stmt->bind_param("ii", $jumlah_tiket, $ticket_id);
$stmt->execute();
$stmt->close();

send_success([
    'payment_id'    => $payment_id,
    'nama_tiket'    => $tiket['nama_tiket'],
    'jumlah_tiket'  => $jumlah_tiket,
    'total_bayar'   => $total_bayar,
    'total_format'  => format_rupiah($total_bayar),
    'bukti_transfer'=> UPLOAD_URL . $filename,
    'status'        => 'pending'
], 'Pembayaran berhasil dikirim! Menunggu verifikasi admin.', 201);
