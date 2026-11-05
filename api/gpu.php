<?php
// GET ?q=&mfr=&min_vram=&year=&offset= -> pencarian dataset GPU (maks 30 baris per halaman)
require_once __DIR__ . '/_bootstrap.php';

run_api(function () {
    require_method('GET');
    $w = []; $p = [];
    $q = trim($_GET['q'] ?? '');
    if ($q !== '') { $w[] = 'product_name LIKE ?'; $p[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; }
    $mfr = trim($_GET['mfr'] ?? '');
    if (in_array($mfr, ['NVIDIA', 'AMD', 'Intel', 'ATI'], true)) { $w[] = 'manufacturer = ?'; $p[] = $mfr; }
    if (isset($_GET['min_vram']) && $_GET['min_vram'] !== '') { $w[] = 'mem_size_gb >= ?'; $p[] = (float)$_GET['min_vram']; }
    if (isset($_GET['year']) && $_GET['year'] !== '') { $w[] = 'release_year >= ?'; $p[] = (int)$_GET['year']; }
    $cond = $w ? 'WHERE ' . implode(' AND ', $w) : '';
    $off = max(0, (int)($_GET['offset'] ?? 0));
    $pdo = db();
    $c = $pdo->prepare("SELECT COUNT(*) FROM gpu_dataset $cond"); $c->execute($p);
    $st = $pdo->prepare("SELECT manufacturer, product_name, release_year, mem_size_gb, mem_bus_width, gpu_clock_mhz, mem_clock_mhz, unified_shader, bus
                         FROM gpu_dataset $cond ORDER BY release_year DESC, product_name LIMIT 30 OFFSET $off");
    $st->execute($p);
    json_out(['total' => (int)$c->fetchColumn(), 'rows' => $st->fetchAll()]);
});
