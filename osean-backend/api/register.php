<?php
// =============================================
// OSEAN - register.php  (kolom: nama, email, password_hash, role)
// =============================================
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_error('Method tidak diizinkan.', 405);

$data     = json_decode(file_get_contents('php://input'), true);
$nama     = isset($data['nama'])     ? sanitize($data['nama'])     : '';
$email    = isset($data['email'])    ? sanitize($data['email'])    : '';
$password = isset($data['password']) ? $data['password']           : '';

if (empty($nama) || empty($email) || empty($password)) send_error('Nama, email, dan password wajib diisi.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL))         send_error('Format email tidak valid.');
if (strlen($password) < 6)                              send_error('Password minimal 6 karakter.');

// Cek email duplikat
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) send_error('Email sudah terdaftar.');
$stmt->close();

$password_hash = password_hash($password, PASSWORD_BCRYPT);
$role          = 'user';
$token         = bin2hex(random_bytes(32)); // 64 karakter token unik
$is_verified   = 0;

$stmt = $conn->prepare("INSERT INTO users (nama, email, password_hash, role, is_verified, verification_token) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssis", $nama, $email, $password_hash, $role, $is_verified, $token);

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
