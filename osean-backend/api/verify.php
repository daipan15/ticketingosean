<?php
// =============================================
// OSEAN - verify.php
// Endpoint verifikasi token email
// =============================================
require_once __DIR__ . '/../config.php';

header("Content-Type: text/html; charset=UTF-8");

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    render_page(false, 'Token verifikasi tidak valid atau tidak ditemukan.');
    exit();
}

$stmt = $conn->prepare("SELECT id, nama, email, is_verified FROM users WHERE verification_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    render_page(false, 'Tautan verifikasi salah atau sudah kadaluarsa.');
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

if ((int)$user['is_verified'] === 1) {
    render_page(true, 'Akun kamu sudah aktif sebelumnya. Silakan langsung login.');
    exit();
}

// Update status user jadi verified
$updateStmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
$updateStmt->bind_param("i", $user['id']);

if ($updateStmt->execute()) {
    render_page(true, 'Selamat, email kamu berhasil diverifikasi! Sekarang kamu sudah bisa login.');
} else {
    render_page(false, 'Terjadi kesalahan sistem saat mengaktifkan akun.');
}
$updateStmt->close();

function render_page($isSuccess, $message) {
    $title     = $isSuccess ? 'Verifikasi Berhasil' : 'Verifikasi Gagal';
    $icon      = $isSuccess ? 'check_circle' : 'cancel';
    $iconColor = $isSuccess ? '#c4cd6f' : '#ffb4ab';
    $btnText   = $isSuccess ? 'Menuju Halaman Login' : 'Kembali ke Beranda';
    $btnHref   = $isSuccess ? '../../osean-frontend/login.html' : '../../osean-frontend/landing_page.html';

    echo "<!DOCTYPE html>
    <html lang='id'>
    <head>
      <meta charset='utf-8'>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'>
      <title>{$title} - OSEAN</title>
      <link href='https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&family=Syne:wght@700;800&display=swap' rel='stylesheet'>
      <link href='https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap' rel='stylesheet'>
      <style>
        body {
          margin: 0; background-color: #15140c; color: #e7e2d5;
          font-family: 'Manrope', sans-serif; display: flex; align-items: center; justify-content: center;
          min-height: 100vh; padding: 20px; box-sizing: border-box;
        }
        .card {
          background: #212017; border: 2px solid #4a454e;
          box-shadow: 8px 8px 0px 0px #0f0e07; max-width: 480px; width: 100%;
          padding: 40px 30px; text-align: center;
        }
        .icon {
          font-size: 64px; color: {$iconColor}; margin-bottom: 16px;
        }
        h1 {
          font-family: 'Syne', sans-serif; font-size: 26px; text-transform: uppercase;
          letter-spacing: -0.02em; margin: 0 0 12px 0; color: #e7e2d5;
        }
        p {
          color: #ccc4cf; font-size: 15px; line-height: 1.6; margin-bottom: 30px;
        }
        .btn {
          display: inline-block; background: #efc05e; color: #15140c;
          font-weight: 700; text-transform: uppercase; text-decoration: none;
          padding: 14px 28px; font-size: 13px; letter-spacing: 0.05em;
          box-shadow: 4px 4px 0px 0px #0f0e07; transition: transform 0.2s;
        }
        .btn:hover {
          transform: translate(-2px, -2px);
        }
      </style>
    </head>
    <body>
      <div class='card'>
        <span class='material-symbols-outlined icon'>{$icon}</span>
        <h1>{$title}</h1>
        <p>{$message}</p>
        <a href='{$btnHref}' class='btn'>{$btnText}</a>
      </div>
    </body>
    </html>";
}
