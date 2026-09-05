<?php
// =============================================
// OSEAN - update_phone.php (Lengkapi Profil: Nama, NIK, No. Telepon)
// Input JSON: { "nama": "...", "nik": "...", "no_telepon": "08..." }
// =============================================
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method tidak diizinkan.', 405);
}

$data    = json_decode(file_get_contents('php://input'), true);
$user_id = (int)$_SESSION['user_id'];

// Ambil data user terkini dari database
$stmt = $conn->prepare("SELECT id, nama, email, nik, no_telepon FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    send_error('Akun pengguna tidak ditemukan. Silakan login kembali.', 401);
}

// Input data
$nama       = isset($data['nama'])       ? sanitize(trim($data['nama']))       : $user['nama'];
$nik        = isset($data['nik'])        ? sanitize(trim($data['nik']))        : ($user['nik'] ?? '');
$no_telepon = isset($data['no_telepon']) ? sanitize(trim($data['no_telepon'])) : ($user['no_telepon'] ?? '');

// Validasi Nama
if (empty($nama)) {
    send_error('Nama lengkap wajib diisi.');
}

// Validasi NIK (16 digit angka)
$cleaned_nik = preg_replace('/[\s\-]/', '', $nik);
if (empty($cleaned_nik)) {
    send_error('NIK (Nomor Induk Kependudukan) wajib diisi.');
}
if (!preg_match('/^[0-9]{16}$/', $cleaned_nik)) {
    send_error('Format NIK tidak valid. NIK harus terdiri dari 16 digit angka.');
}
$nik = $cleaned_nik;

// Cek duplikasi NIK pada user lain
$chk = $conn->prepare("SELECT id FROM users WHERE nik = ? AND id != ?");
$chk->bind_param("si", $nik, $user_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows > 0) {
    $chk->close();
    send_error('NIK sudah terdaftar pada akun lain.');
}
$chk->close();

// Validasi No Telepon
if (empty($no_telepon)) {
    send_error('Nomor telepon wajib diisi.');
}
$cleaned_phone = preg_replace('/[\s\-]/', '', $no_telepon);
if (!preg_match('/^(\+62|0)[0-9]{9,13}$/', $cleaned_phone)) {
    send_error('Format nomor telepon tidak valid. Gunakan format 08xxx atau +62xxx (10-15 digit).');
}
$no_telepon = $cleaned_phone;

// Update data di database
$upd = $conn->prepare("UPDATE users SET nama = ?, nik = ?, no_telepon = ? WHERE id = ?");
$upd->bind_param("sssi", $nama, $nik, $no_telepon, $user_id);

if (!$upd->execute()) {
    send_error('Gagal menyimpan profil: ' . $conn->error, 500);
}
$upd->close();

// Update session
$_SESSION['nama']       = $nama;
$_SESSION['nik']        = $nik;
$_SESSION['no_telepon'] = $no_telepon;

send_success([
    'nama'       => $nama,
    'nik'        => $nik,
    'no_telepon' => $no_telepon
], 'Data profil berhasil disimpan.');

