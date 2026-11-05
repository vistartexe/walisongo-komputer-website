<?php
// GET ?k=TOKEN -> hasil rekomendasi tersimpan
require_once __DIR__ . '/_bootstrap.php';

run_api(function () {
    require_method('GET');
    $k = strtoupper(trim($_GET['k'] ?? ''));
    if (!preg_match('/^[A-Z0-9]{8}$/', $k)) { throw new InvalidArgumentException('Kode rekomendasi tidak valid'); }
    $st = db()->prepare('SELECT hasil_json FROM riwayat_rekomendasi WHERE token = ?');
    $st->execute([$k]);
    $row = $st->fetch();
    if (!$row) { json_out(['error' => 'Rekomendasi tidak ditemukan'], 404); }
    echo $row['hasil_json'];
});
