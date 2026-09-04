<?php
// =============================================
// OSEAN - config.php
// Koneksi DB + Session + CORS
// Versi: Production-Ready
// =============================================

// Set default timezone ke Waktu Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta');

// =============================================
// LOAD ENVIRONMENT VARIABLES dari .env
// =============================================
function osean_load_env(string $envFile): void {
    if (!file_exists($envFile)) return;
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (!empty($key) && !array_key_exists($key, $_ENV)) {
            $_ENV[$key]   = $value;
            putenv("$key=$value");
        }
    }
}

// .env ada di root project: ticketingosean/.env
// config.php ada di: ticketingosean/osean-backend/config.php
$env_file = dirname(__DIR__) . '/.env';
osean_load_env($env_file);

function env(string $key, $default = null) {
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null) return $default;
    return $val;
}

// =============================================
// CORS — hanya izinkan origin yang terdaftar
// =============================================
$_allowed_origins_raw = env('APP_ALLOWED_ORIGINS', 'http://localhost');
$_allowed_origins     = array_map('trim', explode(',', $_allowed_origins_raw));
$_request_origin      = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($_request_origin, $_allowed_origins, true)) {
    header("Access-Control-Allow-Origin: {$_request_origin}");
    header("Vary: Origin");
} elseif (empty($_request_origin)) {
    // Server-to-server atau request langsung (webhook Midtrans, dll.)
    // Tidak perlu header CORS
} else {
    // Origin tidak dikenal — tolak preflight, biarkan request biasa lewat (akan dicek session)
    header("Access-Control-Allow-Origin: null");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Security headers standar
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// =============================================
// SESSION — Secure Cookie
// =============================================
$_is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'secure'   => $_is_https,   // true di HTTPS production, false di localhost
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// =============================================
// KONFIGURASI DATABASE
// =============================================
define('DB_HOST',    env('DB_HOST',    'localhost'));
define('DB_USER',    env('DB_USER',    'root'));
define('DB_PASS',    env('DB_PASS',    ''));
define('DB_NAME',    env('DB_NAME',    'osean_db'));
define('DB_CHARSET', 'utf8mb4');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset(DB_CHARSET);
$conn->query("SET time_zone = '+07:00'");

if ($conn->connect_error) {
    error_log('[OSEAN DB] Koneksi gagal: ' . $conn->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.']);
    exit();
}

// =============================================
// UPLOAD PATH
// =============================================
$app_protocol = $_is_https ? "https://" : "http://";
$app_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$doc_root     = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$uploads_dir  = str_replace('\\', '/', realpath(__DIR__ . '/uploads'));

if ($uploads_dir && $doc_root && str_starts_with($uploads_dir, $doc_root)) {
    $rel_path        = substr($uploads_dir, strlen($doc_root));
    $base_upload_url = $app_protocol . $app_host . rtrim($rel_path, '/') . '/';
} else {
    $base_upload_url = $app_protocol . $app_host . '/ticketingosean/osean-backend/uploads/';
}

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL',  $base_upload_url);

// =============================================
// KONFIGURASI SMTP GMAIL (PHPMailer)
// =============================================
define('SMTP_HOST',      env('SMTP_HOST',      'smtp.gmail.com'));
define('SMTP_PORT',      (int) env('SMTP_PORT', 587));
define('SMTP_USER',      env('SMTP_USER',      ''));
define('SMTP_PASS',      env('SMTP_PASS',      ''));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'OSEAN Ticketing'));

// =============================================
// KONFIGURASI GOOGLE SIGN-IN / OAUTH
// =============================================
define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));

// =============================================
// KONFIGURASI MIDTRANS
// =============================================
define('MIDTRANS_SERVER_KEY',    env('MIDTRANS_SERVER_KEY',    ''));
define('MIDTRANS_CLIENT_KEY',    env('MIDTRANS_CLIENT_KEY',    ''));
define('MIDTRANS_IS_PRODUCTION', filter_var(env('MIDTRANS_IS_PRODUCTION', 'false'), FILTER_VALIDATE_BOOLEAN));
define('MIDTRANS_SNAP_URL',
    MIDTRANS_IS_PRODUCTION
        ? 'https://app.midtrans.com/snap/v1/transactions'
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions'
);
define('MIDTRANS_API_URL',
    MIDTRANS_IS_PRODUCTION
        ? 'https://api.midtrans.com/v2'
        : 'https://api.sandbox.midtrans.com/v2'
);

// =============================================
// HELPER FUNCTIONS
// =============================================

function send_success(array $data = [], string $message = 'OK', int $code = 200): never {
    http_response_code($code);
    echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
    exit();
}

function send_error(string $message = 'Error', int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        send_error('Unauthorized: Silakan login terlebih dahulu.', 401);
    }
}

function require_admin(): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        send_error('Forbidden: Akses hanya untuk admin.', 403);
    }
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)));
}

function format_rupiah(int|float $angka): string {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

/**
 * Terapkan SSL options pada cURL handle.
 * Di production (HTTPS), SSL selalu diverifikasi.
 * Di localhost tanpa SSL, cari cacert lokal.
 */
function apply_curl_ssl_options($ch): void {
    // Selalu coba verifikasi SSL terlebih dahulu
    $ca_candidates = [
        ini_get('curl.cainfo'),
        ini_get('openssl.cafile'),
        'D:/laragon/etc/ssl/cacert.pem',
        'C:/laragon/etc/ssl/cacert.pem',
        'C:/xampp/php/extras/ssl/cacert.pem',
    ];
    foreach ($ca_candidates as $path) {
        if (!empty($path) && file_exists($path)) {
            curl_setopt($ch, CURLOPT_CAINFO, $path);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            return;
        }
    }

    // Di production server, SSL sudah diurus oleh OS — aktifkan verifikasi
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if ($is_https) {
        // Server production pasti punya system CA bundle
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    } else {
        // Hanya di localhost — boleh nonaktifkan, log warning
        error_log('[OSEAN SSL] Warning: cacert tidak ditemukan, SSL verification dinonaktifkan (localhost only)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
}

// =============================================
// PHPMailer
// =============================================
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_verification_email(string $toEmail, string $toName, string $token): bool {
    if (empty(SMTP_USER) || empty(SMTP_PASS)
        || SMTP_USER === 'your_email@gmail.com'
        || SMTP_PASS === 'your_16_digit_app_password') {
        error_log("[OSEAN SMTP] Warning: Kredensial SMTP belum diisi. Token untuk $toEmail: $token");
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

        // Deteksi URL verifikasi secara dinamis
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isHttps ? 'https://' : 'http://';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
        if ($isLocal) {
            $verifyLink = $protocol . $host . '/ticketingosean/osean-backend/api/verify.php?token=' . urlencode($token);
        } else {
            $scriptDir  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $verifyLink = $protocol . $host . $scriptDir . '/verify.php?token=' . urlencode($token);
        }

        $mail->isHTML(true);
        $mail->Subject = 'Verifikasi Akun OSEAN 2026';
        $mail->Body    = "
            <div style='background: #15140c; color: #e7e2d5; padding: 30px; font-family: sans-serif;'>
                <div style='max-width: 500px; margin: 0 auto; background: #212017; border: 2px solid #efc05e; padding: 24px;'>
                    <h2 style='color: #efc05e; margin-top: 0;'>Halo, " . htmlspecialchars($toName) . "!</h2>
                    <p>Terima kasih telah mendaftar di <b>OSEAN Opening &amp; Closing Ceremony 2026</b>.</p>
                    <p>Tinggal satu langkah lagi untuk mengaktifkan akunmu. Silakan klik tombol di bawah ini:</p>
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
        error_log('[OSEAN SMTP Error] ' . $mail->ErrorInfo);
        return false;
    }
}
