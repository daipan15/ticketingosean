<?php
// =============================================
// OSEAN - admin_payment_delete.php
// Hapus data tiket/pembayaran (termasuk yang sudah verified)
// Jika status verified atau pending: kembalikan kuota_terjual
// Hapus file fisik bukti transfer jika ada
// =============================================
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_error('Method tidak diizinkan.', 405);

$data       = json_decode(file_get_contents('php://input'), true);
$payment_id = isset($data['payment_id']) ? (int)$data['payment_id'] : 0;

if ($payment_id <= 0) send_error('ID Pembayaran tidak valid.');

// Cek keberadaan payment
$stmt = $conn->prepare("
    SELECT p.id, p.kode_unik, p.status, p.jumlah_tiket, p.ticket_id, p.bukti_transfer,
           u.nama AS user_nama, t.nama_tiket
    FROM payments p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN tickets t ON p.ticket_id = t.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    send_error('Data tiket / pembayaran tidak ditemukan.', 404);
}

$payment = $result->fetch_assoc();
$stmt->close();

// Mulai transaksi database
$conn->begin_transaction();

try {
    // 1. Jika statusnya 'verified' atau 'pending', kembalikan kuota tiket
    if ($payment['status'] === 'verified' || $payment['status'] === 'pending') {
        $stmt_kuota = $conn->prepare("UPDATE tickets SET kuota_terjual = GREATEST(0, kuota_terjual - ?) WHERE id = ?");
        $stmt_kuota->bind_param("ii", $payment['jumlah_tiket'], $payment['ticket_id']);
        $stmt_kuota->execute();
        $stmt_kuota->close();
    }

    // 2. Hapus data dari tabel payments
    $stmt_del = $conn->prepare("DELETE FROM payments WHERE id = ?");
    $stmt_del->bind_param("i", $payment_id);
    $stmt_del->execute();
    $stmt_del->close();

    // 3. Hapus file fisik bukti transfer jika ada
    if (!empty($payment['bukti_transfer'])) {
        $filepath = UPLOAD_DIR . basename($payment['bukti_transfer']);
        if (file_exists($filepath) && is_file($filepath)) {
            @unlink($filepath);
        }
    }

    $conn->commit();

    send_success([
        'payment_id' => $payment_id,
        'kode_unik'  => $payment['kode_unik'],
        'nama_tiket' => $payment['nama_tiket'],
        'user_nama'  => $payment['user_nama']
    ], "Tiket {$payment['kode_unik']} berhasil dihapus dan kuota telah dikembalikan.");

} catch (Exception $e) {
    $conn->rollback();
    send_error('Gagal menghapus tiket: ' . $e->getMessage(), 500);
}
