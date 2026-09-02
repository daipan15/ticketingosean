<?php
// =============================================
// OSEAN - login.php  (kolom: nama, password_hash)
// =============================================
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_error('Method tidak diizinkan.', 405);

$data     = json_decode(file_get_contents('php://input'), true);
$email    = isset($data['email'])    ? sanitize($data['email']) : '';
$password = isset($data['password']) ? $data['password']        : '';

if (empty($email) || empty($password)) send_error('Email dan password wajib diisi.');

$stmt = $conn->prepare("SELECT id, nama, email, password_hash, role, is_verified FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) send_error('Email atau password salah.', 401);

$user = $result->fetch_assoc();
$stmt->close();

if (!password_verify($password, $user['password_hash'])) send_error('Email atau password salah.', 401);

// Cek apakah email sudah diverifikasi
if ($user['role'] !== 'admin' && isset($user['is_verified']) && (int)$user['is_verified'] === 0) {
    send_error('Akun belum aktif! Silakan buka email Anda dan klik link verifikasi terlebih dahulu.', 403);
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['nama']    = $user['nama'];
$_SESSION['email']   = $user['email'];
$_SESSION['role']    = $user['role'];

send_success([
    'user' => [
        'id'    => $user['id'],
        'nama'  => $user['nama'],
        'email' => $user['email'],
        'role'  => $user['role']
    ]
], 'Login berhasil! Selamat datang, ' . $user['nama'] . '.');
