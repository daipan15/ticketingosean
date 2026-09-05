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

$stmt = $conn->prepare("SELECT id, nama, email, nik, no_telepon, password_hash, role, is_verified FROM users WHERE email = ?");
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

// Set session — regenerate ID untuk mencegah session fixation
session_regenerate_id(true);
$_SESSION['user_id']    = $user['id'];
$_SESSION['nama']       = $user['nama'];
$_SESSION['email']      = $user['email'];
$_SESSION['role']       = $user['role'];
$_SESSION['nik']        = $user['nik'] ?? null;
$_SESSION['no_telepon'] = $user['no_telepon'] ?? null;

$noTelepon  = $user['no_telepon'] ?? null;
$needsPhone = empty($noTelepon);

send_success([
    'user' => [
        'id'         => $user['id'],
        'nama'       => $user['nama'],
        'email'      => $user['email'],
        'nik'        => $user['nik'] ?? null,
        'role'       => $user['role'],
        'no_telepon' => $noTelepon
    ],
    'needs_phone' => $needsPhone
], 'Login berhasil! Selamat datang, ' . $user['nama'] . '.');
