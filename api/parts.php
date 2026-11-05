<?php
// GET -> seluruh komponen untuk katalog (format sama dengan array PRODUCTS lama)
require_once __DIR__ . '/_bootstrap.php';

run_api(function () {
    require_method('GET');
    $key = ['CPU' => 'cpu', 'GPU' => 'vga', 'Motherboard' => 'mobo', 'RAM' => 'ram', 'Storage' => 'ssd',
            'PSU' => 'psu', 'Cooler' => 'case', 'Casing' => 'case'];
    $lbl = ['CPU' => 'Processor (CPU)', 'GPU' => 'VGA / Kartu Grafis', 'Motherboard' => 'Motherboard',
            'RAM' => 'RAM & Memory', 'Storage' => 'SSD & Storage', 'PSU' => 'Power Supply',
            'Cooler' => 'CPU Cooler', 'Casing' => 'Casing PC'];
    $out = [];
    foreach (db()->query('SELECT * FROM komponen ORDER BY popularitas DESC, id_part') as $r) {
        $pts = array_slice(array_values(array_filter(array_map('trim', explode(',', (string)$r['spesifikasi_singkat'])))), 0, 2);
        $war = 'Garansi ' . $r['garansi'] . ($r['stok_status'] !== 'Stok Tersedia' ? ' • ' . $r['stok_status'] : '');
        $out[] = ['id' => $r['kode_part'], 'cat' => $key[$r['kategori']], 'catLabel' => $lbl[$r['kategori']],
                  'war' => $war, 'name' => $r['nama_part'], 'spec' => $r['spesifikasi_singkat'],
                  'price' => (int)$r['harga'], 'pop' => (int)$r['popularitas'], 'pts' => $pts,
                  'habis' => $r['stok_status'] === 'Habis'];
    }
    json_out($out);
});
