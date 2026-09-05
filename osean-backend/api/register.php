<?php
// =============================================
// OSEAN - register.php  (kolom: nama, email, no_telepon, password_hash, role)
// =============================================
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_error('Method tidak diizinkan.', 405);

$data       = json_decode(file_get_contents('php://input'), true);
$nama       = isset($data['nama'])        ? sanitize($data['nama'])        : '';
$nik        = isset($data['nik'])         ? sanitize($data['nik'])         : '';
$email      = isset($data['email'])       ? sanitize($data['email'])       : '';
$no_telepon = isset($data['no_telepon'])  ? sanitize($data['no_telepon'])  : '';
$password   = isset($data['password'])    ? $data['password']              : '';

if (empty($nama) || empty($nik) || empty($email) || empty($no_telepon) || empty($password)) {
    send_error('Nama, NIK, email, nomor telepon, dan password wajib diisi.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) send_error('Format email tidak valid.');
if (strlen($password) < 6)                      send_error('Password minimal 6 karakter.');

// Validasi format NIK (16 digit angka)
$cleaned_nik = preg_replace('/[\s\-]/', '', $nik);
if (!preg_match('/^[0-9]{16}$/', $cleaned_nik)) {
    send_error('Format NIK tidak valid. NIK harus terdiri dari 16 digit angka.');
}
$nik = $cleaned_nik;

// Validasi format nomor telepon
$cleaned = preg_replace('/[\s\-]/', '', $no_telepon);
if (!preg_match('/^(\+62|0)[0-9]{9,13}$/', $cleaned)) {
    send_error('Format nomor telepon tidak valid. Gunakan format 08xxx atau +62xxx.');
}
$no_telepon = $cleaned;

// Cek email duplikat
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) send_error('Email sudah terdaftar.');
$stmt->close();

// Cek NIK duplikat
$stmt = $conn->prepare("SELECT id FROM users WHERE nik = ?");
$stmt->bind_param("s", $nik);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) send_error('NIK sudah terdaftar.');
$stmt->close();

$password_hash = password_hash($password, PASSWORD_BCRYPT);
$role          = 'user';
$token         = bin2hex(random_bytes(32)); // 64 karakter token unik
$is_verified   = 0;

$stmt = $conn->prepare("INSERT INTO users (nama, email, nik, no_telepon, password_hash, role, is_verified, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssis", $nama, $email, $nik, $no_telepon, $password_hash, $role, $is_verified, $token);
// note: no_telepon may be empty string — stored as '' (acceptable)

if ($stmt->execute()) {
    $mailSent = send_verification_email($email, $nama, $token);
    
    $msg = 'Registrasi berhasil! Silakan periksa inbox email Anda untuk mengaktifkan akun.';
    if (!$mailSent) {
        $msg = 'Registrasi berhasil! Cek email Anda untuk verifikasi (Jika menggunakan mode localhost/offline, hubungi admin).';
    }

    send_success([
        'user_id'     => $stmt->insert_id,
        'email'       => $email,
        'mail_sent'   => $mailSent
    ], $msg, 201);
} else {
    send_error('Registrasi gagal: ' . $conn->error, 500);
}
