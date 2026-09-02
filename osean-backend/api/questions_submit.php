<?php
// =============================================
// OSEAN - questions_submit.php
// Kolom DB: pertanyaan, status (default: menunggu)
// =============================================
require_once __DIR__ . '/../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_error('Method tidak diizinkan.', 405);

$data       = json_decode(file_get_contents('php://input'), true);
$pertanyaan = isset($data['pertanyaan']) ? sanitize($data['pertanyaan']) : '';

if (empty($pertanyaan))          send_error('Pertanyaan tidak boleh kosong.');
if (strlen($pertanyaan) > 1000)  send_error('Pertanyaan terlalu panjang (maks 1000 karakter).');

$user_id = $_SESSION['user_id'];
$status  = 'menunggu';

$stmt = $conn->prepare("INSERT INTO questions (user_id, pertanyaan, status) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $user_id, $pertanyaan, $status);

if ($stmt->execute()) {
    send_success(['question_id' => $stmt->insert_id], 'Pertanyaan berhasil dikirim! Admin akan segera menjawab.', 201);
} else {
    send_error('Gagal mengirim pertanyaan: ' . $conn->error, 500);
}
