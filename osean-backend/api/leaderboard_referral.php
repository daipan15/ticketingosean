<?php
// =============================================
// OSEAN - leaderboard_referral.php
// Endpoint publik untuk leaderboard referral 9 Himpunan FMIPA UNPAD
// HIFI, HIMAKA, HIMBIO, HIMATIKA, HIMASTA, PEDRA, HIMATIF, HMTE, HIMAKTU
// =============================================
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') send_error('Method tidak diizinkan.', 405);

// Definisi resmi 9 Himpunan Mahasiswa FMIPA UNPAD
$HIMPUNAN_DATA = [
    'HIFI'     => ['code' => 'HIFI',     'name' => 'HIFI',     'prodi' => 'Fisika',             'color' => '#EFC05E', 'badge' => ''],
    'HIMAKA'   => ['code' => 'HIMAKA',   'name' => 'HIMAKA',   'prodi' => 'Kimia',              'color' => '#C4CD6F', 'badge' => ''],
    'HIMBIO'   => ['code' => 'HIMBIO',   'name' => 'HIMBIO',   'prodi' => 'Biologi',            'color' => '#4ADE80', 'badge' => ''],
    'HIMATIKA' => ['code' => 'HIMATIKA', 'name' => 'HIMATIKA', 'prodi' => 'Matematika',          'color' => '#38BDF8', 'badge' => ''],
    'HIMASTA'  => ['code' => 'HIMASTA',  'name' => 'HIMASTA',  'prodi' => 'Statistika',         'color' => '#F472B6', 'badge' => ''],
    'PEDRA'    => ['code' => 'PEDRA',    'name' => 'PEDRA',    'prodi' => 'Geofisika',          'color' => '#FB923C', 'badge' => ''],
    'HIMATIF'  => ['code' => 'HIMATIF',  'name' => 'HIMATIF',  'prodi' => 'Teknik Informatika', 'color' => '#A78BFA', 'badge' => ''],
    'HMTE'     => ['code' => 'HMTE',     'name' => 'HMTE',     'prodi' => 'Teknik Elektro',     'color' => '#FACC15', 'badge' => ''],
    'HIMAKTU'  => ['code' => 'HIMAKTU',  'name' => 'HIMAKTU',  'prodi' => 'Aktuaria',           'color' => '#2DD4BF', 'badge' => ''],
];

// Agregasi jumlah tiket sah (sudah bayar: settlement, capture, atau verified) per referral_code
// Memperhitungkan paket bundle tiket: Duo = 2 tiket, Trio = 3 tiket
$sql = "
    SELECT UPPER(TRIM(p.referral_code)) AS ref,
           SUM(
               p.jumlah_tiket * (
                   CASE
                       WHEN LOWER(t.nama_tiket) LIKE '%trio%' THEN 3
                       WHEN LOWER(t.nama_tiket) LIKE '%duo%'  THEN 2
                       ELSE 1
                   END
               )
           ) AS total_tiket,
           COUNT(p.id) AS total_transaksi
    FROM payments p
    JOIN tickets t ON p.ticket_id = t.id
    WHERE p.status IN ('settlement', 'capture', 'verified')
      AND p.referral_code IS NOT NULL
      AND TRIM(p.referral_code) != ''
    GROUP BY UPPER(TRIM(p.referral_code))
";

$result = $conn->query($sql);
$tally = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $ref = strtoupper(trim($row['ref']));
        if ($ref === 'HTME') $ref = 'HMTE';
        if (!isset($tally[$ref])) {
            $tally[$ref] = ['tiket' => 0, 'transaksi' => 0];
        }
        $tally[$ref]['tiket'] += (int)$row['total_tiket'];
        $tally[$ref]['transaksi'] += (int)$row['total_transaksi'];
    }
}

// Susun list 9 himpunan lengkap
$leaderboard = [];
$total_all_tickets = 0;

foreach ($HIMPUNAN_DATA as $code => $info) {
    $tiketCount = isset($tally[$code]) ? $tally[$code]['tiket'] : 0;
    $trxCount   = isset($tally[$code]) ? $tally[$code]['transaksi'] : 0;
    $total_all_tickets += $tiketCount;

    $leaderboard[] = [
        'code'        => $code,
        'name'        => $info['name'],
        'prodi'       => $info['prodi'],
        'color'       => $info['color'],
        'badge'       => $info['badge'],
        'tiket'       => $tiketCount,
        'transaksi'   => $trxCount,
        'persentase'  => 0
    ];
}

// Hitung persentase
foreach ($leaderboard as &$item) {
    $item['persentase'] = $total_all_tickets > 0 
        ? round(($item['tiket'] / $total_all_tickets) * 100, 1) 
        : 0;
}
unset($item);

// Urutkan default berdasarkan tiket tertinggi DESC, lalu nama ASC
usort($leaderboard, function($a, $b) {
    if ($b['tiket'] === $a['tiket']) {
        return strcmp($a['name'], $b['name']);
    }
    return $b['tiket'] - $a['tiket'];
});

// Tambahkan ranking
for ($i = 0; $i < count($leaderboard); $i++) {
    $leaderboard[$i]['rank'] = $i + 1;
}

send_success([
    'total_himpunan'       => count($leaderboard),
    'total_tiket_referral' => $total_all_tickets,
    'leaderboard'          => $leaderboard
], 'Berhasil mengambil leaderboard referral himpunan.');
