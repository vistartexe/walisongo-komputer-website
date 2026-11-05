<?php
/**
 * Impor dataset GPU (CSV) ke tabel gpu_dataset.
 * Pemakaian (terminal / XAMPP Shell):
 *     php db/import_gpu_dataset.php                       -> memakai dataset/gpu_specs_v7.csv
 *     php db/import_gpu_dataset.php dataset/gpu_specs_v6.csv
 *
 * Pembersihan data: BOM dibuang, teks di-trim, sel kosong -> NULL, baris duplikat
 * (manufacturer + productName sama) digabung dengan mempertahankan baris paling lengkap.
 * Kolom memType & gpuChip TIDAK diimpor karena ditemukan tidak konsisten pada dataset
 * (contoh: RTX 4060 tercatat berchip "TU102").
 */
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Jalankan lewat terminal.'); }
require_once __DIR__ . '/../config/database.php';

$file = $argv[1] ?? __DIR__ . '/../dataset/gpu_specs_v7.csv';
if (!is_readable($file)) { fwrite(STDERR, "File tidak ditemukan: $file\n"); exit(1); }

$fh = fopen($file, 'r');
$head = fgetcsv($fh);
$head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
$head = array_map('trim', $head);

$num = function ($v) { $v = trim((string)$v); return ($v === '' || !is_numeric($v)) ? null : $v + 0; };
$txt = function ($v) { $v = trim((string)$v); return $v === '' ? null : $v; };

$rows = []; $read = 0;
while (($r = fgetcsv($fh)) !== false) {
    if (count($r) < count($head)) { continue; }
    $read++;
    $d = array_combine($head, array_slice($r, 0, count($head)));
    $row = [
        $txt($d['manufacturer']), $txt($d['productName']), $num($d['releaseYear']), $num($d['memSize']),
        $num($d['memBusWidth']), $num($d['gpuClock']), $num($d['memClock']), $num($d['unifiedShader']),
        $num($d['tmu']), $num($d['rop']), $txt($d['bus']),
    ];
    if ($row[0] === null || $row[1] === null) { continue; }
    $key = $row[0] . '|' . $row[1];
    $filled = count(array_filter($row, function ($x) { return $x !== null; }));
    if (!isset($rows[$key]) || $filled > $rows[$key][1]) { $rows[$key] = [$row, $filled]; }
}
fclose($fh);

$pdo = db();
$pdo->beginTransaction();
$pdo->exec('DELETE FROM gpu_dataset');
$st = $pdo->prepare('INSERT INTO gpu_dataset (manufacturer, product_name, release_year, mem_size_gb, mem_bus_width,
                     gpu_clock_mhz, mem_clock_mhz, unified_shader, tmu, rop, bus) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
foreach ($rows as $v) { $st->execute($v[0]); }
$pdo->commit();

$matched = $pdo->query('SELECT COUNT(*) FROM komponen k JOIN gpu_dataset g
                        ON g.product_name = k.dataset_nama AND g.manufacturer = k.brand')->fetchColumn();
echo "Baris dibaca        : $read\n";
echo "Baris unik diimpor  : " . count($rows) . " (duplikat digabung: " . ($read - count($rows)) . ")\n";
echo "GPU katalog cocok   : $matched part terhubung ke dataset\n";
