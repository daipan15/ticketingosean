<?php
// =============================================
// OSEAN - forgot_password.php
// Mengirim tautan reset password ke email user
// =============================================
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method tidak diizinkan.', 405);
}

$raw_input = file_get_contents('php://input');
$data      = json_decode($raw_input, true);
if (!is_array($data)) {
    $data = [];
}

$email = isset($data['email']) ? sanitize($data['email']) : (isset($_POST['email']) ? sanitize($_POST['email']) : '');

if (empty($email)) {
    send_error('Email wajib diisi.', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_error('Format email tidak valid.', 400);
}

// Cari user berdasarkan email
$stmt = $conn->prepare("SELECT id, nama, email FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$user = $result->fetch_assoc();
$stmt->close();

if ($user) {
    // Generate secure token (64 karakter hex)
    $token = bin2hex(random_bytes(32));

    // Simpan token ke DB dengan masa berlaku 1 jam
    $updateStmt = $conn->prepare("
        UPDATE users 
        SET reset_token = ?, 
            reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) 
        WHERE id = ?
    ");
    $updateStmt->bind_param("si", $token, $user['id']);
    $updateStmt->execute();
    $updateStmt->close();

    // Kirim email reset password
    $mailSent = send_password_reset_email($user['email'], $user['nama'], $token);

    if (!$mailSent) {
        error_log("[OSEAN Forgot Password] Gagal mengirim email reset ke {$user['email']}. Token: {$token}");
    }
}

// Selalu berikan pesan seragam untuk keamanan (mencegah user enumeration)
send_success([], 'Jika email kamu terdaftar, tautan reset password telah dikirim ke inbox atau folder spam emailmu.');
