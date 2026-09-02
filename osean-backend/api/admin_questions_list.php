<?php
// =============================================
// OSEAN - admin_questions_list.php
// Kolom DB: pertanyaan, jawaban, status (menunggu/dijawab)
// =============================================
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') send_error('Method tidak diizinkan.', 405);

$hanya_menunggu = isset($_GET['menunggu']) && $_GET['menunggu'] === '1';

if ($hanya_menunggu) {
    $stmt = $conn->prepare("
        SELECT q.id, q.pertanyaan, q.jawaban, q.status, q.created_at, q.answered_at,
               u.id AS user_id, u.nama, u.email
        FROM questions q
        JOIN users u ON q.user_id = u.id
        WHERE q.status = 'menunggu'
        ORDER BY q.created_at ASC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT q.id, q.pertanyaan, q.jawaban, q.status, q.created_at, q.answered_at,
               u.id AS user_id, u.nama, u.email
        FROM questions q
        JOIN users u ON q.user_id = u.id
        ORDER BY q.created_at DESC
    ");
}

$stmt->execute();
$result = $stmt->get_result();

$pertanyaan = [];
$total_menunggu = 0;

while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'menunggu') $total_menunggu++;
    $pertanyaan[] = [
        'id'             => (int)$row['id'],
        'pertanyaan'     => $row['pertanyaan'],
        'jawaban'        => $row['jawaban'],
        'status'         => $row['status'],
        'sudah_dijawab'  => $row['status'] === 'dijawab',
        'created_at'     => $row['created_at'],
        'answered_at'    => $row['answered_at'],
        'user_id'        => (int)$row['user_id'],
        'nama'           => $row['nama'],
        'email'          => $row['email']
    ];
}

$stmt->close();
send_success([
    'pertanyaan'      => $pertanyaan,
    'total'           => count($pertanyaan),
    'total_menunggu'  => $total_menunggu
], 'Berhasil mengambil daftar pertanyaan.');
