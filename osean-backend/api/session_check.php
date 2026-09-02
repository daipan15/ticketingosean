<?php
// =============================================
// OSEAN - session_check.php
// =============================================
require_once __DIR__ . '/../config.php';

if (isset($_SESSION['user_id'])) {
    send_success([
        'logged_in' => true,
        'user' => [
            'id'    => $_SESSION['user_id'],
            'nama'  => $_SESSION['nama'],
            'email' => $_SESSION['email'],
            'role'  => $_SESSION['role']
        ]
    ], 'Session aktif.');
} else {
    send_success(['logged_in' => false], 'Tidak ada session aktif.');
}
