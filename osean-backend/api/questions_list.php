<?php
// =============================================
// OSEAN - questions_list.php
// Kolom DB: id, user_id, pertanyaan, jawaban, status, created_at, answered_at
// =============================================
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') send_error('Method tidak diizinkan.', 405);

$stmt = $conn->prepare("
    SELECT q.id, q.pertanyaan, q.jawaban, q.status, q.created_at, q.answered_at,
           u.nama AS ditanya_oleh
    FROM questions q
    JOIN users u ON q.user_id = u.id
    WHERE q.status = 'dijawab'
    ORDER BY q.answered_at DESC
");
$stmt->execute();
$result = $stmt->get_result();

$pertanyaan = [];
while ($row = $result->fetch_assoc()) {
    $pertanyaan[] = [
        'id'            => (int)$row['id'],
        'pertanyaan'    => $row['pertanyaan'],
        'jawaban'       => $row['jawaban'],
        'status'        => $row['status'],
        'ditanya_oleh'  => $row['ditanya_oleh'],
        'created_at'    => $row['created_at'],
        'answered_at'   => $row['answered_at']
    ];
}

$stmt->close();
send_success(['pertanyaan' => $pertanyaan, 'total' => count($pertanyaan)], 'Berhasil mengambil FAQ.');
