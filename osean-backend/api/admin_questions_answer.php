<?php
// =============================================
// OSEAN - admin_questions_answer.php
// Jawab atau update jawaban pertanyaan user
// Status diubah jadi 'dijawab' setelah diisi
// =============================================
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_error('Method tidak diizinkan.', 405);

$data        = json_decode(file_get_contents('php://input'), true);
$question_id = isset($data['question_id']) ? (int)$data['question_id']    : 0;
$jawaban     = isset($data['jawaban'])     ? sanitize($data['jawaban'])    : '';

if ($question_id <= 0)  send_error('question_id tidak valid.');
if (empty($jawaban))    send_error('Jawaban tidak boleh kosong.');

// Cek pertanyaan ada
$stmt = $conn->prepare("SELECT id FROM questions WHERE id = ?");
$stmt->bind_param("i", $question_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) send_error('Pertanyaan tidak ditemukan.', 404);
$stmt->close();

$answered_at = date('Y-m-d H:i:s');
$status      = 'dijawab';

$stmt = $conn->prepare("UPDATE questions SET jawaban = ?, status = ?, answered_at = ? WHERE id = ?");
$stmt->bind_param("sssi", $jawaban, $status, $answered_at, $question_id);

if ($stmt->execute()) {
    send_success(['question_id' => $question_id], 'Pertanyaan berhasil dijawab!');
} else {
    send_error('Gagal menyimpan jawaban: ' . $conn->error, 500);
}
