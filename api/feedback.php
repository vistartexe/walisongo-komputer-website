<?php
// POST JSON {k, pas(0/1), rating(1-5, opsional), komentar(opsional)} -> simpan umpan balik pengguna
require_once __DIR__ . '/_bootstrap.php';

run_api(function () {
    require_method('POST');
    $in = json_in();
    $k = strtoupper(trim((string)($in['k'] ?? '')));
    if (!preg_match('/^[A-Z0-9]{8}$/', $k)) { throw new InvalidArgumentException('Kode rekomendasi tidak valid'); }
    $pas = !empty($in['pas']) ? 1 : 0;
    $rating = isset($in['rating']) ? max(1, min(5, (int)$in['rating'])) : null;
    $kom = isset($in['komentar']) ? mb_substr(trim((string)$in['komentar']), 0, 500) : null;
    $pdo = db();
    $ex = $pdo->prepare('SELECT 1 FROM riwayat_rekomendasi WHERE token = ?');
    $ex->execute([$k]);
    if (!$ex->fetchColumn()) { json_out(['error' => 'Rekomendasi tidak ditemukan'], 404); }
    $pdo->prepare('INSERT INTO feedback_rekomendasi (token, pas, rating, komentar) VALUES (?,?,?,?)')
        ->execute([$k, $pas, $rating, $kom]);
    json_out(['ok' => true]);
});
