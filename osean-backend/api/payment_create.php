<?php
// =============================================
// OSEAN - payment_create.php
// Membuat transaksi pembayaran via Midtrans Snap
// Input JSON: ticket_id, jumlah_tiket
// Output: snap_token untuk popup Midtrans
// CATATAN: kode_unik digunakan sebagai Midtrans order_id
// =============================================
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_error('Method tidak diizinkan.', 405);

$raw_input     = file_get_contents('php://input');
$data          = json_decode($raw_input, true);
if (!is_array($data)) {
    $data = [];
}
$user_id       = $_SESSION['user_id'];
$ticket_id     = (int)($data['ticket_id'] ?? $_POST['ticket_id'] ?? 0);
$jumlah_tiket  = (int)($data['jumlah_tiket'] ?? $_POST['jumlah_tiket'] ?? 1);
$metode        = sanitize($data['metode_pembayaran'] ?? $_POST['metode_pembayaran'] ?? 'midtrans');
$raw_referral  = strtoupper(trim(sanitize($data['referral_code'] ?? $_POST['referral_code'] ?? '')));

// Daftar resmi 9 Himpunan FMIPA UNPAD
$valid_hima = ['HIFI', 'HIMAKA', 'HIMBIO', 'HIMATIKA', 'HIMASTA', 'PEDRA', 'HIMATIF', 'HMTE', 'HIMAKTU'];
$referral_code = in_array($raw_referral, $valid_hima) ? $raw_referral : null;

if ($ticket_id <= 0 || $jumlah_tiket <= 0) send_error('ticket_id dan jumlah_tiket wajib diisi.');

// Cek stok tiket (sisa = kuota - kuota_terjual)
$stmt = $conn->prepare("SELECT id, nama_tiket, harga, kuota, kuota_terjual FROM tickets WHERE id = ?");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) send_error('Tiket tidak ditemukan.', 404);
$tiket = $result->fetch_assoc();
$stmt->close();

$sisa_kuota = $tiket['kuota'] - $tiket['kuota_terjual'];
if ($sisa_kuota < $jumlah_tiket) {
    send_error("Stok tiket tidak cukup. Sisa: {$sisa_kuota} tiket.");
}

// Ambil data user untuk dikirim ke Midtrans
$stmt = $conn->prepare("SELECT nama, email, nik, no_telepon FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    send_error('Akun pengguna tidak ditemukan atau sesi telah berakhir. Silakan login kembali.', 401);
}

// Validasi data profil (nama, NIK, nomor telepon) wajib diisi sebelum bisa memesan tiket
if (empty(trim($user['nama'] ?? '')) || empty(trim($user['nik'] ?? '')) || empty(trim($user['no_telepon'] ?? ''))) {
    send_error('Data diri (nama lengkap, NIK, dan nomor telepon) wajib dilengkapi sebelum melakukan pemesanan tiket. Silakan lengkapi profil Anda terlebih dahulu.', 400);
}

// Generator kode unik tiket (booking code) ber-entropi tinggi & terjamin unik
function generate_unique_ticket_code($conn) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $len = strlen($chars);
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $p1 = '';
        $p2 = '';
        for ($j = 0; $j < 4; $j++) {
            $p1 .= $chars[random_int(0, $len - 1)];
            $p2 .= $chars[random_int(0, $len - 1)];
        }
        $code = "OSN-{$p1}-{$p2}";

        $stmt = $conn->prepare("SELECT id FROM payments WHERE kode_unik = ? LIMIT 1");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $exists = ($stmt->get_result()->num_rows > 0);
        $stmt->close();

        if (!$exists) return $code;
    }
    return 'OSN-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}

$kode_unik   = generate_unique_ticket_code($conn);
$total_bayar = $tiket['harga'] * $jumlah_tiket;

// Insert payment record (status: pending, belum increment kuota)
// kode_unik digunakan sebagai order_id ke Midtrans
$status = 'pending';
$stmt = $conn->prepare("
    INSERT INTO payments (kode_unik, user_id, ticket_id, jumlah_tiket, total_bayar, metode_pembayaran, referral_code, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("siiissss", $kode_unik, $user_id, $ticket_id, $jumlah_tiket, $total_bayar, $metode, $referral_code, $status);

try {
    if (!$stmt->execute()) {
        send_error('Gagal menyimpan data: ' . $conn->error, 500);
    }
} catch (mysqli_sql_exception $e) {
    send_error('Gagal menyimpan transaksi: ' . $e->getMessage(), 500);
}
$payment_id = $stmt->insert_id;
$stmt->close();

// =============================================
// Request Snap Token dari Midtrans
// kode_unik digunakan sebagai order_id (satu-satunya identifier)
// =============================================
$midtrans_params = [
    'transaction_details' => [
        'order_id'     => $kode_unik,    // kode_unik sebagai Midtrans order_id
        'gross_amount' => (int)$total_bayar
    ],
    'item_details' => [
        [
            'id'       => 'TICKET-' . $tiket['id'],
            'price'    => (int)$tiket['harga'],
            'quantity' => $jumlah_tiket,
            'name'     => substr($tiket['nama_tiket'], 0, 50) // Midtrans max 50 char
        ]
    ],
    'customer_details' => [
        'first_name' => $user['nama'],
        'email'      => $user['email'],
        'phone'      => $user['no_telepon']
    ]
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => MIDTRANS_SNAP_URL,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
    ],
    CURLOPT_POSTFIELDS     => json_encode($midtrans_params),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30
]);
apply_curl_ssl_options($ch);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    // Hapus record payment jika gagal request ke Midtrans
    $del_stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
    $del_stmt->bind_param("i", $payment_id);
    $del_stmt->execute();
    $del_stmt->close();
    send_error('Gagal terhubung ke Midtrans: ' . $curl_error, 500);
}

$midtrans_response = json_decode($response, true);

if ($http_code !== 201 || empty($midtrans_response['token'])) {
    // Hapus record payment jika Midtrans menolak
    $del = $conn->prepare("DELETE FROM payments WHERE id = ?");
    $del->bind_param("i", $payment_id);
    $del->execute();
    $del->close();

    $err_msg = $midtrans_response['error_messages'][0] ?? ($midtrans_response['message'] ?? 'Unknown error');
    send_error('Midtrans error: ' . $err_msg, 500);
}

$snap_token = $midtrans_response['token'];

// Simpan snap_token ke DB
$stmt = $conn->prepare("UPDATE payments SET snap_token = ? WHERE id = ?");
$stmt->bind_param("si", $snap_token, $payment_id);
$stmt->execute();
$stmt->close();

send_success([
    'payment_id'   => $payment_id,
    'snap_token'   => $snap_token,
    'kode_unik'    => $kode_unik,
    'nama_tiket'   => $tiket['nama_tiket'],
    'jumlah_tiket' => $jumlah_tiket,
    'total_bayar'  => $total_bayar,
    'total_format' => format_rupiah($total_bayar)
], 'Transaksi dibuat. Silakan selesaikan pembayaran.', 201);
