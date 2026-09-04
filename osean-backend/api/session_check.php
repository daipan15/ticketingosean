<?php
// =============================================
// OSEAN - session_check.php
// =============================================
require_once __DIR__ . '/../config.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id, nama, email, no_telepon, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $hasPhone = !empty($user['no_telepon']);
        send_success([
            'logged_in' => true,
            'user' => [
                'id'         => $user['id'],
                'nama'       => $user['nama'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'no_telepon' => $user['no_telepon'] ?? null,
                'has_phone'  => $hasPhone
            ]
        ], 'Session aktif.');
    } else {
        // User di DB sudah tidak ada (misal database di-reset/re-seed), hapus session mati
        session_unset();
        session_destroy();
        send_success(['logged_in' => false], 'Sesi sudah tidak valid.');
    }
} else {
    send_success(['logged_in' => false], 'Tidak ada session aktif.');
}
