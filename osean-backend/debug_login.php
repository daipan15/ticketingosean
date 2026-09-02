<?php
// OSEAN - debug_login.php (HAPUS SETELAH DIPAKAI!)
// Akses: http://localhost/ticketingosean/osean-backend/debug_login.php

require_once __DIR__ . '/config.php';

$email    = 'admin@osean.com';
$password = 'admin123';

echo "<pre style='font-family:monospace;background:#111;color:#e7e2d5;padding:20px;font-size:14px'>";
echo "=== OSEAN DEBUG LOGIN ===\n\n";

// 1. Cek koneksi DB
echo "1. Koneksi DB : ";
if ($conn->connect_error) {
    echo "❌ GAGAL — " . $conn->connect_error . "\n";
    exit;
} else {
    echo "✓ OK (osean_db)\n";
}

// 2. Cek tabel users ada
$tbl = $conn->query("SHOW TABLES LIKE 'users'");
echo "2. Tabel users: ";
if ($tbl->num_rows === 0) {
    echo "❌ TIDAK ADA — Import osean_db.sql dulu!\n";
    exit;
} else {
    echo "✓ Ada\n";
}

// 3. Cek user admin di DB
$stmt = $conn->prepare("SELECT id, nama, email, password_hash, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

echo "3. Cari email  : {$email}\n";
if ($result->num_rows === 0) {
    echo "   Status      : ❌ EMAIL TIDAK DITEMUKAN di database!\n";
    echo "\n   → Jalankan reset_admin.php dulu!\n";
    exit;
}

$user = $result->fetch_assoc();
echo "   Status       : ✓ Ditemukan\n";
echo "   ID           : " . $user['id']   . "\n";
echo "   Nama         : " . $user['nama'] . "\n";
echo "   Role         : " . $user['role'] . "\n";
echo "   Hash (DB)    : " . substr($user['password_hash'], 0, 30) . "...\n";

// 4. Verifikasi password
$cocok = password_verify($password, $user['password_hash']);
echo "\n4. Test password '{$password}':\n";
echo "   Cocok?       : " . ($cocok ? "✓ YA — Login seharusnya berhasil!" : "❌ TIDAK COCOK") . "\n";

if (!$cocok) {
    // Auto-fix
    $new_hash = password_hash($password, PASSWORD_BCRYPT);
    $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $upd->bind_param("ss", $new_hash, $email);
    $upd->execute();
    echo "\n   ✓ AUTO-FIX: Password direset ke 'admin123'!\n";
    echo "   Hash baru    : " . substr($new_hash, 0, 30) . "...\n";
}

echo "\n=== SELESAI ===\n";
if ($cocok) {
    echo "\nCoba login dengan:\n";
    echo "Email    : {$email}\n";
    echo "Password : {$password}\n";
} else {
    echo "\nPassword sudah di-fix! Coba login sekarang:\n";
    echo "Email    : {$email}\n";
    echo "Password : {$password}\n";
}
echo "</pre>";
echo "<p><a href='../osean-frontend/login.html' style='color:#efc05e'>→ Buka Login</a></p>";
echo "<p style='color:red;font-family:monospace'><b>HAPUS file ini setelah selesai!</b></p>";
?>
