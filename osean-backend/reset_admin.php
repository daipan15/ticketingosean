<?php
// =============================================
// OSEAN - reset_admin.php
// HAPUS FILE INI SETELAH DIPAKAI!
// Akses: http://localhost/ticketingosean/osean-backend/reset_admin.php
// =============================================

require_once __DIR__ . '/config.php';

$email         = 'admin@osean.com';
$nama          = 'Admin OSEAN';
$password_baru = 'admin123';  // ← ganti sesuai keinginan
$hash          = password_hash($password_baru, PASSWORD_BCRYPT);

// Cek apakah admin sudah ada
$cek = $conn->prepare("SELECT id FROM users WHERE email = ?");
$cek->bind_param("s", $email);
$cek->execute();
$cek->store_result();

if ($cek->num_rows > 0) {
    // Update password
    $stmt = $conn->prepare("UPDATE users SET password_hash = ?, nama = ?, role = 'admin' WHERE email = ?");
    $stmt->bind_param("sss", $hash, $nama, $email);
    $stmt->execute();
    echo "<h2 style='font-family:monospace;color:green'>Password admin DIPERBARUI!</h2>";
} else {
    // Insert admin baru
    $stmt = $conn->prepare("INSERT INTO users (nama, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
    $stmt->bind_param("sss", $nama, $email, $hash);
    $stmt->execute();
    echo "<h2 style='font-family:monospace;color:green'>Akun admin DIBUAT!</h2>";
}

echo "<pre style='font-family:monospace;background:#111;color:#efc05e;padding:20px'>";
echo "Email    : {$email}\n";
echo "Password : {$password_baru}\n";
echo "Hash     : {$hash}\n";
echo "</pre>";
echo "<p style='font-family:monospace;color:red'><b>[PERINGATAN] HAPUS FILE INI SEKARANG: osean-backend/reset_admin.php</b></p>";
echo "<p><a href='../osean-frontend/login.html'>→ Pergi ke Login</a></p>";
?>
