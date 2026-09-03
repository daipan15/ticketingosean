<?php
// =============================================
// OSEAN - ticket_scan.php
// Validasi QR Code tiket saat acara (Check-in)
// Endpoint: POST { "kode_unik": "OSN-XXXX-XXXX" }
// Hanya bisa di-scan 1x. Jika sudah: "Tiket sudah diambil"
// =============================================
require_once __DIR__ . '/../config.php';
require_admin(); // Hanya admin/panitia yang bisa scan

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_error('Method tidak diizinkan.', 405);

$data      = json_decode(file_get_contents('php://input'), true);
$kode_unik = isset($data['kode_unik']) ? strtoupper(trim(sanitize($data['kode_unik']))) : '';

if (empty($kode_unik)) {
    send_error('kode_unik tidak boleh kosong.', 400);
}

// Cari payment berdasarkan kode_unik
$stmt = $conn->prepare("
    SELECT
        p.id            AS payment_id,
        p.kode_unik,
        p.status,
        p.is_checked_in,
        p.checked_in_at,
        p.jumlah_tiket,
        p.total_bayar,
        p.created_at    AS tanggal_order,
        u.nama          AS nama_user,
        u.email         AS email_user,
        t.nama_tiket,
        t.kategori
    FROM payments p
    JOIN users u   ON p.user_id   = u.id
    JOIN tickets t ON p.ticket_id = t.id
    WHERE p.kode_unik = ?
    LIMIT 1
");
$stmt->bind_param("s", $kode_unik);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    send_error('Tiket tidak ditemukan. QR Code tidak valid.', 404);
}

$payment = $result->fetch_assoc();
$stmt->close();

// Cek apakah status tiket valid (sudah dibayar)
$valid_statuses = ['settlement', 'capture', 'verified'];
if (!in_array($payment['status'], $valid_statuses)) {
    $status_label = 'Status tiket tidak valid (' . $payment['status'] . ')';
    if ($payment['status'] === 'pending')  $status_label = 'Pembayaran belum selesai (pending)';
    if ($payment['status'] === 'expire')   $status_label = 'Tiket sudah kedaluwarsa';
    if ($payment['status'] === 'cancel')   $status_label = 'Tiket dibatalkan';
    if ($payment['status'] === 'deny')     $status_label = 'Pembayaran ditolak';
    if ($payment['status'] === 'refund')   $status_label = 'Tiket sudah direfund';
    if ($payment['status'] === 'rejected') $status_label = 'Tiket ditolak panitia';

    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode([
        'success'     => false,
        'scan_result' => 'invalid',
        'message'     => $status_label,
        'tiket'       => [
            'kode_unik'  => $payment['kode_unik'],
            'nama_user'  => $payment['nama_user'],
            'nama_tiket' => $payment['nama_tiket'],
            'status'     => $payment['status'],
        ]
    ]);
    exit;
}

// Cek apakah sudah pernah di-scan (check-in)
if ((int)$payment['is_checked_in'] === 1) {
    $checked_time = $payment['checked_in_at']
        ? (new DateTime($payment['checked_in_at']))->format('d M Y, H:i')
        : '-';

    http_response_code(409);
    header('Content-Type: application/json');
    echo json_encode([
        'success'     => false,
        'scan_result' => 'already_used',
        'message'     => 'Tiket sudah diambil',
        'tiket'       => [
            'kode_unik'     => $payment['kode_unik'],
            'nama_user'     => $payment['nama_user'],
            'email_user'    => $payment['email_user'],
            'nama_tiket'    => $payment['nama_tiket'],
            'kategori'      => $payment['kategori'],
            'jumlah_tiket'  => (int)$payment['jumlah_tiket'],
            'checked_in_at' => $checked_time,
        ]
    ]);
    exit;
}

// ✅ Tiket valid & belum di-scan — lakukan check-in
$checked_in_at = date('Y-m-d H:i:s');
$up = $conn->prepare("
    UPDATE payments
    SET is_checked_in = 1, checked_in_at = ?
    WHERE id = ?
");
$up->bind_param("si", $checked_in_at, $payment['payment_id']);
if (!$up->execute()) {
    send_error('Gagal memproses check-in: ' . $conn->error, 500);
}
$up->close();

header('Content-Type: application/json');
echo json_encode([
    'success'     => true,
    'scan_result' => 'valid',
    'message'     => 'Tiket valid! Selamat datang di OSEAN.',
    'tiket'       => [
        'kode_unik'     => $payment['kode_unik'],
        'nama_user'     => $payment['nama_user'],
        'email_user'    => $payment['email_user'],
        'nama_tiket'    => $payment['nama_tiket'],
        'kategori'      => $payment['kategori'],
        'jumlah_tiket'  => (int)$payment['jumlah_tiket'],
        'total_format'  => format_rupiah($payment['total_bayar']),
        'checked_in_at' => $checked_in_at,
    ]
]);
