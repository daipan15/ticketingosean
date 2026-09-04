<?php
// =============================================
// OSEAN - update_phone.php
// Update nomor telepon user yang sedang login
// Input JSON: { "no_telepon": "08..." }
// =============================================
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method tidak diizinkan.', 405);
}

$data       = json_decode(file_get_contents('php://input'), true);
$no_telepon = isset($data['no_telepon']) ? trim($data['no_telepon']) : '';

if (empty($no_telepon)) {
    send_error('Nomor telepon wajib diisi.');
}

// Bersihkan spasi dan tanda hubung
$cleaned = preg_replace('/[\s\-]/', '', $no_telepon);

// Validasi format nomor telepon Indonesia
if (!preg_match('/^(\+62|0)[0-9]{9,13}$/', $cleaned)) {
    send_error('Format nomor telepon tidak valid. Gunakan format 08xxx atau +62xxx (10-15 digit).');
}

$no_telepon = $cleaned;
$user_id    = $_SESSION['user_id'];

// Pastikan user ada dan ambil data terkini
$stmt = $conn->prepare("SELECT id, no_telepon FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    send_error('Akun pengguna tidak ditemukan. Silakan login kembali.', 401);
}

// Update nomor telepon di database
$upd = $conn->prepare("UPDATE users SET no_telepon = ? WHERE id = ?");
$upd->bind_param("si", $no_telepon, $user_id);

if (!$upd->execute()) {
    send_error('Gagal menyimpan nomor telepon: ' . $conn->error, 500);
}
$upd->close();

// Update session
$_SESSION['no_telepon'] = $no_telepon;

send_success([
    'no_telepon' => $no_telepon
], 'Nomor telepon berhasil disimpan.');
