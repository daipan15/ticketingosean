<?php
// =============================================
// OSEAN - reset_password.php
// Validasi token dan pembaruan kata sandi baru
// =============================================
require_once __DIR__ . '/../config.php';

// Method GET: Verifikasi apakah token masih berlaku
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    if (empty($token)) {
        send_error('Token tidak ditemukan.', 400);
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $stmt->close();
        send_error('Tautan reset password sudah kedaluwarsa atau tidak valid. Silakan ajukan permintaan baru.', 400);
    }

    $stmt->close();
    send_success([], 'Token valid.');
}

// Method POST: Simpan password baru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    $data      = json_decode($raw_input, true);
    if (!is_array($data)) {
        $data = [];
    }

    $token    = isset($data['token']) ? trim($data['token']) : '';
    $password = isset($data['password']) ? $data['password'] : '';

    if (empty($token)) {
        send_error('Token reset password tidak boleh kosong.', 400);
    }

    if (empty($password) || strlen($password) < 6) {
        send_error('Password baru minimal harus 6 karakter.', 400);
    }

    // Cari user berdasarkan reset_token yang belum kadaluarsa
    $stmt = $conn->prepare("SELECT id, email, nama FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        send_error('Tautan reset password tidak valid atau sudah kedaluwarsa. Silakan ajukan permintaan reset baru.', 400);
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    $new_hash = password_hash($password, PASSWORD_BCRYPT);

    // Update password dan reset token agar tidak bisa dipakai ulang
    $up = $conn->prepare("
        UPDATE users 
        SET password_hash = ?, 
            reset_token = NULL, 
            reset_expires = NULL 
        WHERE id = ?
    ");
    $up->bind_param("si", $new_hash, $user['id']);

    if ($up->execute()) {
        $up->close();
        send_success([], 'Password kamu berhasil diperbarui! Silakan masuk dengan password barumu.');
    } else {
        $up->close();
        send_error('Gagal memperbarui password: ' . $conn->error, 500);
    }
}

send_error('Method tidak diizinkan.', 405);
