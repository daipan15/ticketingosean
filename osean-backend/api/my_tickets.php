<?php
// =============================================
// OSEAN - my_tickets.php
// Menampilkan tiket milik user yang login
// Termasuk data Midtrans (snap_token, payment_type, midtrans_order_id)
// =============================================
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') send_error('Method tidak diizinkan.', 405);

$user_id = $_SESSION['user_id'];

// =============================================
// Auto-Sync Status Midtrans untuk Tiket Pending
// (Sangat penting di localhost karena Midtrans Webhook tidak bisa tembus ke IP lokal)
// =============================================
if (defined('MIDTRANS_SERVER_KEY') && !str_contains(MIDTRANS_SERVER_KEY, 'XXXX')) {
    $pending_stmt = $conn->prepare("
        SELECT id, midtrans_order_id, jumlah_tiket, ticket_id, status 
        FROM payments 
        WHERE user_id = ? AND status = 'pending' AND midtrans_order_id IS NOT NULL
    ");
    $pending_stmt->bind_param("i", $user_id);
    $pending_stmt->execute();
    $pendings = $pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pending_stmt->close();

    foreach ($pendings as $p) {
        $order_id = $p['midtrans_order_id'];
        $auth = base64_encode(MIDTRANS_SERVER_KEY . ':');
        $ch = curl_init("https://api.sandbox.midtrans.com/v2/{$order_id}/status");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $auth]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
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
                $up = $conn->prepare("
                    UPDATE payments 
                    SET status = ?, 
                        midtrans_transaction_id = ?, 
                        payment_type = ?, 
                        metode_pembayaran = ?, 
                        verified_at = COALESCE(?, verified_at) 
                    WHERE id = ?
                ");
                $up->bind_param("sssssi", $new_status, $tx_id, $p_type, $p_type, $v_at, $p['id']);
                $up->execute();
                $up->close();

                if (in_array($new_status, ['settlement', 'capture'])) {
                    $u_t = $conn->prepare("UPDATE tickets SET kuota_terjual = kuota_terjual + ? WHERE id = ?");
                    $u_t->bind_param("ii", $p['jumlah_tiket'], $p['ticket_id']);
                    $u_t->execute();
                    $u_t->close();
                }
            }
        }
    }
}


$stmt = $conn->prepare("
    SELECT
        p.id            AS payment_id,
        p.jumlah_tiket,
        p.total_bayar,
        p.metode_pembayaran,
        p.bukti_transfer,
        p.status,
        p.created_at    AS tanggal_order,
        p.verified_at,
        p.snap_token,
        p.midtrans_order_id,
        p.midtrans_transaction_id,
        p.payment_type,
        t.id            AS ticket_id,
        t.nama_tiket,
        t.harga,
        t.deskripsi
    FROM payments p
    JOIN tickets t ON p.ticket_id = t.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$tikets = [];
while ($row = $result->fetch_assoc()) {
    $tikets[] = [
        'payment_id'              => (int)$row['payment_id'],
        'jumlah_tiket'            => (int)$row['jumlah_tiket'],
        'total_bayar'             => (int)$row['total_bayar'],
        'total_format'            => format_rupiah($row['total_bayar']),
        'metode_pembayaran'       => $row['metode_pembayaran'],
        'bukti_transfer'          => $row['bukti_transfer'] ? UPLOAD_URL . $row['bukti_transfer'] : null,
        'status'                  => $row['status'],
        'tanggal_order'           => $row['tanggal_order'],
        'verified_at'             => $row['verified_at'],
        'snap_token'              => $row['snap_token'],
        'midtrans_order_id'       => $row['midtrans_order_id'],
        'midtrans_transaction_id' => $row['midtrans_transaction_id'],
        'payment_type'            => $row['payment_type'],
        'ticket_id'               => (int)$row['ticket_id'],
        'nama_tiket'              => $row['nama_tiket'],
        'harga'                   => (int)$row['harga'],
        'harga_format'            => format_rupiah($row['harga']),
        'deskripsi'               => $row['deskripsi']
    ];
}

$stmt->close();
send_success(['tikets' => $tikets, 'total' => count($tikets)], 'Berhasil mengambil tiket kamu.');
