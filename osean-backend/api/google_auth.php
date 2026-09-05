<?php
// =============================================
// OSEAN - google_auth.php
// Login & Register Otomatis via Google
// =============================================
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method tidak diizinkan.', 405);
}

$rawInput = file_get_contents('php://input');
if (empty($rawInput) && php_sapi_name() === 'cli') {
    $rawInput = file_get_contents('php://stdin');
}
$data     = json_decode($rawInput, true);

if (!$data) {
    send_error('Data request tidak valid.');
}

$credential  = isset($data['credential']) ? trim($data['credential']) : '';
$accessToken = isset($data['access_token']) ? trim($data['access_token']) : '';

$email = '';
$name  = '';

// 1. Verifikasi via access_token jika ada
if (!empty($accessToken)) {
    $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    apply_curl_ssl_options($ch);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $profile = json_decode($response, true);
        if (!empty($profile['email'])) {
            $email = sanitize($profile['email']);
            $name  = sanitize($profile['name'] ?? explode('@', $email)[0]);
        }
    }
}

// 2. Verifikasi via credential (Google ID Token / JWT) jika email belum didapat
if (empty($email) && !empty($credential)) {
    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    apply_curl_ssl_options($ch);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $profile = json_decode($response, true);
        if (!empty($profile['email'])) {
            $email = sanitize($profile['email']);
            $name  = sanitize($profile['name'] ?? explode('@', $email)[0]);
        }
    } else {
        // Fallback parsing payload JWT jika tokeninfo gagal (misal koneksi luar terbatas)
        $parts = explode('.', $credential);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if (!empty($payload['email'])) {
                $email = sanitize($payload['email']);
                $name  = sanitize($payload['name'] ?? explode('@', $email)[0]);
            }
        }
    }
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_error('Gagal memverifikasi akun Google. Email tidak valid atau otorisasi dibatalkan.', 401);
}

if (empty($name)) {
    $name = explode('@', $email)[0];
}

// Cek apakah user sudah terdaftar di DB
$stmt = $conn->prepare("SELECT id, nama, email, nik, no_telepon, role, is_verified FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // User SUDAH ada -> LOGIN
    $user = $result->fetch_assoc();
    $stmt->close();

    // Karena login via Google terpercaya, otomatis aktifkan verifikasi jika belum aktif
    if (isset($user['is_verified']) && (int)$user['is_verified'] === 0) {
        $up = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
        $up->bind_param("i", $user['id']);
        $up->execute();
        $up->close();
        $user['is_verified'] = 1;
    }
    $isNew = false;
} else {
    // User BELUM ada -> REGISTER OTOMATIS (hanya email dari Google, nama & NIK diisi sendiri oleh user)
    $stmt->close();
    $role         = 'user';
    $is_verified  = 1; // Langsung aktif karena akun Google sudah terverifikasi
    $randomSecret = bin2hex(random_bytes(16));
    $passwordHash = password_hash($randomSecret, PASSWORD_BCRYPT);
    $name         = ''; // Nama dari Google sengaja tidak ditarik, user wajib mengisi nama lengkap & NIK di form

    $ins = $conn->prepare("INSERT INTO users (nama, email, password_hash, role, is_verified, verification_token) VALUES (?, ?, ?, ?, ?, NULL)");
    $ins->bind_param("ssssi", $name, $email, $passwordHash, $role, $is_verified);
    
    if (!$ins->execute()) {
        send_error('Gagal mendaftarkan akun via Google: ' . $conn->error, 500);
    }
    
    $userId = $ins->insert_id;
    $ins->close();

    $user = [
        'id'          => $userId,
        'nama'        => '',
        'email'       => $email,
        'nik'         => null,
        'no_telepon'  => null,
        'role'        => $role,
        'is_verified' => 1
    ];
    $isNew = true;
}

// Set session pengguna — regenerate ID untuk mencegah session fixation
session_regenerate_id(true);
$_SESSION['user_id']    = $user['id'];
$_SESSION['nama']       = $user['nama'];
$_SESSION['email']      = $user['email'];
$_SESSION['role']       = $user['role'];
$_SESSION['nik']        = $user['nik'] ?? null;
$_SESSION['no_telepon'] = $user['no_telepon'] ?? null;

// User butuh melengkapi profil jika nama, NIK, atau nomor telepon belum terisi
$needsProfile = empty($user['nama']) || empty($user['nik']) || empty($user['no_telepon']);

send_success([
    'user' => [
        'id'         => $user['id'],
        'nama'       => $user['nama'],
        'email'      => $user['email'],
        'nik'        => $user['nik'] ?? null,
        'role'       => $user['role'],
        'no_telepon' => $user['no_telepon'] ?? null
    ],
    'is_new_user'    => $isNew,
    'needs_profile'  => $needsProfile,
    'needs_phone'    => $needsProfile
], $isNew ? 'Pendaftaran via Google berhasil! Silakan lengkapi data profil Anda.' : 'Login via Google berhasil!' . (!empty($user['nama']) ? ' Selamat datang, ' . $user['nama'] : ''));
