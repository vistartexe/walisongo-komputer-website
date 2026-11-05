<?php
// POST JSON {kodes:[...], work:"desain"} -> total, watt, hasil cek kompatibilitas (fitur "Tukar part")
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/ExpertSystem.php';

run_api(function () {
    require_method('POST');
    $in = json_in();
    $codes = array_values(array_filter((array)($in['kodes'] ?? []), 'is_string'));
    $es = new ExpertSystem(db());
    $r = $es->validate($codes, (string)($in['work'] ?? 'desain'));
    $parts = $es->partsByCode($codes);
    $r['harga'] = [];
    foreach ($parts as $p) { $r['harga'][$p['kode_part']] = $p['harga']; }
    json_out($r);
});
