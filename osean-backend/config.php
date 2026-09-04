<?php
// =============================================
// OSEAN - config.php
// Koneksi DB + Session + CORS
// Disesuaikan dengan struktur DB osean_db.sql
// =============================================

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// --- Konfigurasi Database ---
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'osean_db');
define('DB_CHARSET', 'utf8mb4');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset(DB_CHARSET);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Koneksi DB gagal: ' . $conn->connect_error]);
    exit();
}

// --- Nama kolom sesuai DB (osean_db.sql) ---
// users    : id, nama, email, password_hash, role, created_at
// tickets  : id, nama_tiket, deskripsi, harga, kuota, kuota_terjual, created_at
// payments : id, user_id, ticket_id, jumlah_tiket, total_bayar, metode_pembayaran,
//            bukti_transfer, status, created_at, verified_at
// questions: id, user_id, pertanyaan, jawaban, status (menunggu/dijawab), created_at, answered_at

// Konstanta path upload bukti transfer (Auto-detect base URL agar tidak 404)
$app_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$app_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$doc_root     = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : 'C:/xampp/htdocs';
$uploads_dir  = str_replace('\\', '/', realpath(__DIR__ . '/uploads'));

if ($uploads_dir && $doc_root && strpos($uploads_dir, $doc_root) === 0) {
    $rel_path = substr($uploads_dir, strlen($doc_root));
    $base_upload_url = $app_protocol . $app_host . rtrim($rel_path, '/') . '/';
} else {
    $base_upload_url = $app_protocol . $app_host . '/ticketingosean/osean-backend/uploads/';
}

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', $base_upload_url);

// =============================================
// HELPER FUNCTIONS
// =============================================

function send_success($data = [], $message = 'OK', $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
    exit();
}

function send_error($message = 'Error', $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        send_error('Unauthorized: Silakan login terlebih dahulu.', 401);
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        send_error('Forbidden: Akses hanya untuk admin.', 403);
    }
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function apply_curl_ssl_options($ch) {
    $ca_candidates = [
        'D:/laragon/etc/ssl/cacert.pem',
        'C:/laragon/etc/ssl/cacert.pem',
        ini_get('curl.cainfo'),
        ini_get('openssl.cafile')
    ];
    foreach ($ca_candidates as $path) {
        if (!empty($path) && file_exists($path)) {
            curl_setopt($ch, CURLOPT_CAINFO, $path);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            return;
        }
    }
    // Fallback jika sertifikat CA tidak ditemukan di mesin lokal
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
}

// =============================================
// KONFIGURASI SMTP GMAIL (PHPMailer)
// =============================================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'dhaiiidhaiiiii@gmail.com');      // Ganti dengan alamat Gmail kamu
define('SMTP_PASS', 'wskk mwpu bqdj ftue'); // Ganti dengan 16 karakter App Password dari Google Account
define('SMTP_FROM_NAME', 'OSEAN Ticketing');

// =============================================
// KONFIGURASI GOOGLE SIGN-IN / OAUTH
// =============================================
define('GOOGLE_CLIENT_ID', '99428380960-o7d0f18mo56lg5hgt5aa5krnl7otpv03.apps.googleusercontent.com');

// =============================================
// KONFIGURASI MIDTRANS
// =============================================
//Key punya diklink:
// Client Key : Mid-client-RbY7VHtGg38VoEzY
// Server Key : Mid-server-iuccEGkpNzYpm_GB1mW8RbMx
define('MIDTRANS_SERVER_KEY', 'Mid-server-J0HPyeWORq_fAnMbGG7mC5gR');
define('MIDTRANS_CLIENT_KEY', 'Mid-client-6lfZVo63M0TC-YI5');
define('MIDTRANS_IS_PRODUCTION', false); //jgn lupa ganti true nanti
define('MIDTRANS_SNAP_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions'); // ini juga ganti prod

require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_verification_email($toEmail, $toName, $token) {
    // Jika kredensial masih default/placeholder, log warning tapi jangan crash
    if (SMTP_USER === 'your_email@gmail.com' || SMTP_PASS === 'your_16_digit_app_password') {
        error_log("[OSEAN SMTP] Warning: Kredensial SMTP Gmail belum diisi. Token verifikasi untuk $toEmail: $token");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        // Deteksi domain secara dinamis (otomatis menyesuaikan diklink.com atau localhost)
        $isHttps   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                     || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
                     || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol  = $isHttps ? 'https://' : 'http://';
        $host      = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'diklink.com';

        // Jika di localhost XAMPP
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            $verifyLink = $protocol . $host . "/ticketingosean/osean-backend/api/verify.php?token=" . urlencode($token);
        } else {
            // Jika sudah di hosting (contoh: diklink.com atau subdomain/path hosting kamu)
            $scriptDir  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $verifyLink = $protocol . $host . $scriptDir . "/verify.php?token=" . urlencode($token);
        }

        $mail->isHTML(true);
        $mail->Subject = 'Verifikasi Akun OSEAN 2026';
        $mail->Body    = "
            <div style='background: #15140c; color: #e7e2d5; padding: 30px; font-family: sans-serif;'>
                <div style='max-width: 500px; margin: 0 auto; background: #212017; border: 2px solid #efc05e; padding: 24px;'>
                    <h2 style='color: #efc05e; margin-top: 0;'>Halo, " . htmlspecialchars($toName) . "!</h2>
                    <p>Terima kasih telah mendaftar di <b>OSEAN Opening & Closing Ceremony 2026</b>.</p>
                    <p>Tinggal satu langkah lagi untuk mengaktifkan akunmu. Silakan klik tombol di bawah ini untuk verifikasi email:</p>
                    <div style='margin: 30px 0; text-align: center;'>
                        <a href='{$verifyLink}' style='background: #efc05e; color: #15140c; padding: 12px 24px; text-decoration: none; font-weight: bold; text-transform: uppercase; display: inline-block;'>
                            Verifikasi Akun Saya
                        </a>
                    </div>
                    <p style='font-size: 12px; color: #ccc4cf;'>Atau salin tautan ini di browser kamu:<br><a href='{$verifyLink}' style='color: #c4cd6f;'>{$verifyLink}</a></p>
                    <hr style='border: 0; border-top: 1px dashed #4a454e; margin: 20px 0;'>
                    <p style='font-size: 11px; color: #958e99;'>Jika kamu tidak merasa mendaftar di OSEAN, abaikan email ini.</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Halo {$toName},\n\nSilakan klik tautan berikut untuk memverifikasi akun OSEAN kamu:\n{$verifyLink}\n\nTerima kasih,\nTim OSEAN";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("[OSEAN SMTP Error] " . $mail->ErrorInfo);
        return false;
    }
}

