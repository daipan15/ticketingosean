<?php
// =============================================
// OSEAN - payment_status.php
// Cek status pembayaran (untuk polling setelah Snap popup ditutup)
// GET: ?payment_id=123
// CATATAN: kode_unik digunakan sebagai Midtrans order_id
// =============================================
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') send_error('Method tidak diizinkan.', 405);

$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
if ($payment_id <= 0) send_error('payment_id tidak valid.');

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT p.id AS payment_id, p.kode_unik, p.status, p.payment_type,
           p.snap_token, p.total_bayar, p.jumlah_tiket, p.created_at,
           p.verified_at, t.nama_tiket
    FROM payments p
    JOIN tickets t ON p.ticket_id = t.id
    WHERE p.id = ? AND p.user_id = ?
");
$stmt->bind_param("ii", $payment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) send_error('Pembayaran tidak ditemukan.', 404);

$payment = $result->fetch_assoc();
$stmt->close();

// Auto-sync ke Midtrans jika masih pending
// kode_unik digunakan sebagai order_id saat request status ke Midtrans
if ($payment['status'] === 'pending' && !empty($payment['kode_unik']) && defined('MIDTRANS_SERVER_KEY') && !str_contains(MIDTRANS_SERVER_KEY, 'XXXX')) {
    $order_id = $payment['kode_unik']; // kode_unik = Midtrans order_id
    $auth = base64_encode(MIDTRANS_SERVER_KEY . ':');
    $ch = curl_init("https://api.sandbox.midtrans.com/v2/{$order_id}/status");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $auth]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    apply_curl_ssl_options($ch);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $res) {
        $mid_data = json_decode($res, true);
        $tx_status = $mid_data['transaction_status'] ?? '';
        $fraud_status = $mid_data['fraud_status'] ?? '';
        $tx_id = $mid_data['transaction_id'] ?? '';
        $p_type = $mid_data['payment_type'] ?? '';

        $new_status = null;
        if ($tx_status === 'settlement') {
            $new_status = 'settlement';
        } elseif ($tx_status === 'capture') {
            $new_status = ($fraud_status === 'accept') ? 'settlement' : 'deny';
        } elseif (in_array($tx_status, ['expire', 'cancel', 'deny'])) {
            $new_status = $tx_status;
        }

        if ($new_status && $new_status !== 'pending') {
            $v_at = in_array($new_status, ['settlement', 'capture']) ? date('Y-m-d H:i:s') : null;
            $up = $conn->prepare("UPDATE payments SET status = ?, midtrans_transaction_id = ?, payment_type = ?, metode_pembayaran = ?, verified_at = COALESCE(?, verified_at) WHERE id = ?");
            $up->bind_param("sssssi", $new_status, $tx_id, $p_type, $p_type, $v_at, $payment_id);
            $up->execute();
            $up->close();

            if (in_array($new_status, ['settlement', 'capture'])) {
                $u_t = $conn->prepare("UPDATE tickets SET kuota_terjual = kuota_terjual + ? WHERE id = (SELECT ticket_id FROM payments WHERE id = ?)");
                $u_t->bind_param("ii", $payment['jumlah_tiket'], $payment_id);
                $u_t->execute();
                $u_t->close();
            }

            $payment['status'] = $new_status;
            $payment['payment_type'] = $p_type;
            $payment['verified_at'] = $v_at ?? $payment['verified_at'];
        }
    }
}

send_success([
    'payment_id'   => (int)$payment['payment_id'],
    'kode_unik'    => $payment['kode_unik'],
    'status'       => $payment['status'],
    'payment_type' => $payment['payment_type'],
    'snap_token'   => $payment['snap_token'],
    'total_bayar'  => (int)$payment['total_bayar'],
    'total_format' => format_rupiah($payment['total_bayar']),
    'jumlah_tiket' => (int)$payment['jumlah_tiket'],
    'nama_tiket'   => $payment['nama_tiket'],
    'created_at'   => $payment['created_at'],
    'verified_at'  => $payment['verified_at']
], 'Status pembayaran berhasil diambil.');
