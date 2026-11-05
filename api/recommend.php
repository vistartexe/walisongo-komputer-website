<?php
// POST JSON {work,budget,budget_exact,prio,brand,parts[],sw[]} -> hasil rekomendasi + token riwayat
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/ExpertSystem.php';

run_api(function () {
    require_method('POST');
    $in = json_in();
    $pdo = db();
    $result = (new ExpertSystem($pdo))->run($in);

    // simpan ke riwayat dengan token acak 8 karakter (dapat dibagikan lewat link)
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $st = $pdo->prepare('INSERT INTO riwayat_rekomendasi (token, input_json, hasil_json, total_harga) VALUES (?,?,?,?)');
    for ($try = 0; $try < 5; $try++) {
        $token = '';
        for ($i = 0; $i < 8; $i++) { $token .= $alphabet[random_int(0, strlen($alphabet) - 1)]; }
        $result['token'] = $token;
        try {
            $st->execute([$token, json_encode($result['input'], JSON_UNESCAPED_UNICODE),
                          json_encode($result, JSON_UNESCAPED_UNICODE), $result['utama']['total']]);
            json_out($result);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') { throw $e; } // 23000 = token bentrok, coba lagi
        }
    }
    throw new RuntimeException('Gagal membuat token riwayat');
});
