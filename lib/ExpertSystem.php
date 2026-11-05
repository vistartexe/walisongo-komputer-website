<?php
/**
 * Mesin inferensi Sistem Pakar Rekomendasi Spesifikasi Komputer.
 *
 * Basis pengetahuan (di database):
 *   beban_kerja      -> syarat minimum (VRAM, RAM, SSD) & kolom skor
 *   aturan_alokasi   -> % budget per kategori untuk tiap beban kerja
 *   aturan_prioritas -> pengali bobot per prioritas pengguna
 *   komponen         -> fakta komponen (harga, skor, socket, tipe RAM, watt, dst)
 *   gpu_dataset      -> data spesifikasi GPU (pengayaan informasi)
 *   aturan_rakitan   -> paket rakitan rekomendasi pakar toko
 *
 * Alur forward chaining: fakta pengguna -> bobot kategori -> pilih part berurutan
 * (CPU -> Motherboard -> GPU -> RAM -> Storage -> Cooler -> Casing -> PSU), setiap
 * pilihan memicu aturan kompatibilitas bagi part berikutnya -> verifikasi akhir
 * -> koreksi bila melebihi budget.
 */
class ExpertSystem
{
    const ORDER = ['CPU', 'Motherboard', 'GPU', 'RAM', 'Storage', 'Cooler', 'Casing', 'PSU'];
    const ICON  = ['CPU' => 'chip', 'Motherboard' => 'board', 'GPU' => 'gpu', 'RAM' => 'ram',
                   'Storage' => 'disk', 'Cooler' => 'fan', 'Casing' => 'monitor', 'PSU' => 'plug'];
    const BUDGET_MAX = ['lt5' => 5000000, '5-10' => 10000000, '10-18' => 18000000, '18-28' => 28000000, 'gt28' => 40000000];
    const PRIO  = ['performa' => 'Performa Maksimal', 'upgrade' => 'Siap Upgrade', 'efisien' => 'Efisiensi Daya', 'hemat' => 'Budget Saver'];
    const BRAND = ['bebas' => 'Bebas', 'intel-nvidia' => 'Intel + NVIDIA', 'amd' => 'Full AMD'];
    const WL    = ['desain' => 'WL_CREATOR', 'gaming' => 'WL_GAMING', '3d' => 'WL_3D_AI', 'kantor' => 'WL_OFFICE'];
    const PARTMAP = ['CPU' => 'CPU', 'VGA' => 'GPU', 'RAM' => 'RAM', 'Mobo' => 'Motherboard',
                     'SSD' => 'Storage', 'PSU' => 'PSU', 'Cooler' => 'Cooler', 'Casing' => 'Casing'];
    // Penambahan bobot (poin persen) bila software tertentu dipakai.
    const SW_BOOST = [
        'Premiere' => ['CPU' => 3, 'RAM' => 3],
        'After'    => ['RAM' => 4, 'CPU' => 2],
        'Blender'  => ['GPU' => 12, 'CPU' => 4],
        'AutoCAD'  => ['CPU' => 5, 'RAM' => 2],
        'Game'     => ['GPU' => 12],
        'VS Code'  => ['CPU' => 4, 'RAM' => 3],
    ];

    private $db;
    private $parts = [];
    private $alok  = [];
    private $prio  = [];
    private $wls   = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->loadKnowledge();
    }

    /* ------------------------------------------------------------------
     * Memuat basis pengetahuan
     * ---------------------------------------------------------------- */
    private function loadKnowledge(): void
    {
        $sql = "SELECT k.*, g.mem_size_gb AS ds_vram, g.mem_bus_width AS ds_bus, g.release_year AS ds_year
                FROM komponen k
                LEFT JOIN gpu_dataset g ON g.product_name = k.dataset_nama AND g.manufacturer = k.brand
                WHERE k.stok_status <> 'Habis'";
        foreach (self::ORDER as $c) { $this->parts[$c] = []; }
        foreach ($this->db->query($sql) as $r) {
            foreach (['id_part', 'harga', 'watt_tdp', 'skor_gaming', 'skor_kreator', 'kapasitas', 'igpu'] as $k) {
                $r[$k] = (int)$r[$k];
            }
            $this->parts[$r['kategori']][] = $r;
        }
        foreach ($this->db->query("SELECT * FROM aturan_alokasi") as $r) {
            $this->alok[$r['kode_workload']][$r['kategori']] = (float)$r['persen'];
        }
        foreach ($this->db->query("SELECT * FROM aturan_prioritas") as $r) {
            $this->prio[$r['prioritas']][$r['kategori']] = (float)$r['pengali'];
        }
        foreach ($this->db->query("SELECT * FROM beban_kerja") as $r) {
            $this->wls[$r['kode_workload']] = $r;
        }
    }

    public function partsByCode(array $codes): array
    {
        $out = [];
        foreach ($this->parts as $cat => $list) {
            foreach ($list as $p) {
                if (in_array($p['kode_part'], $codes, true)) { $out[$cat] = $p; }
            }
        }
        return $out;
    }

    /* ------------------------------------------------------------------
     * API publik
     * ---------------------------------------------------------------- */
    public function run(array $in): array
    {
        $c = $this->normalize($in);
        $main = $this->summary($c, $this->build($c));

        $h = $c; $h['budget'] = (int)round($c['budget'] * 0.75, -5);
        $h['prio'] = 'hemat'; $h['label'] = 'Alternatif Hemat';
        $p = $c; $p['budget'] = (int)round($c['budget'] * 1.35, -5);
        $p['prio'] = $c['prio'] === 'performa' ? 'upgrade' : 'performa';
        $p['label'] = 'Alternatif Performa Tinggi';

        return [
            'input'       => [
                'work' => $c['workKey'], 'work_label' => $c['wl']['nama_workload'],
                'budget' => $c['budget'], 'budget_label' => $this->budgetLabel($c['budget']),
                'prio' => $c['prio'], 'prio_label' => self::PRIO[$c['prio']],
                'brand' => $c['brand'], 'brand_label' => self::BRAND[$c['brand']],
                'parts' => $c['cats'], 'sw' => $c['sw'],
            ],
            'utama'       => $main,
            'alternatif'  => [$this->summary($h, $this->build($h)), $this->summary($p, $this->build($p))],
            'paket_pakar' => $this->ruleMatch($c),
        ];
    }

    /** Validasi manual susunan part (dipakai fitur "Tukar part"). */
    public function validate(array $codes, string $workKey): array
    {
        if (!isset(self::WL[$workKey])) { throw new InvalidArgumentException('Beban kerja tidak valid'); }
        $ch = $this->partsByCode($codes);
        if (!$ch) { throw new InvalidArgumentException('Kode part tidak ditemukan'); }
        $wl = $this->wls[self::WL[$workKey]];
        $c = ['wl' => $wl, 'col' => $wl['kolom_skor'] === 'gaming' ? 'skor_gaming' : 'skor_kreator'];
        return $this->totals($ch) + ['checks' => $this->check($ch, $c)];
    }

    /* ------------------------------------------------------------------
     * Normalisasi input
     * ---------------------------------------------------------------- */
    private function normalize(array $in): array
    {
        $workKey = (string)($in['work'] ?? 'desain');
        if (!isset(self::WL[$workKey])) { throw new InvalidArgumentException('Beban kerja tidak valid'); }
        $bk = (string)($in['budget'] ?? '10-18');
        if (!isset(self::BUDGET_MAX[$bk])) { throw new InvalidArgumentException('Rentang budget tidak valid'); }
        $prio = (string)($in['prio'] ?? 'performa');
        if (!isset(self::PRIO[$prio])) { throw new InvalidArgumentException('Prioritas tidak valid'); }
        $brand = (string)($in['brand'] ?? 'bebas');
        if (!isset(self::BRAND[$brand])) { throw new InvalidArgumentException('Preferensi brand tidak valid'); }

        $cats = [];
        foreach ((array)($in['parts'] ?? []) as $p) {
            if (isset(self::PARTMAP[$p])) { $cats[] = self::PARTMAP[$p]; }
        }
        $cats = array_values(array_unique($cats));
        if (!$cats) { throw new InvalidArgumentException('Pilih minimal satu komponen'); }

        $exact = (int)($in['budget_exact'] ?? 0);
        if ($exact > 0 && $exact < 500000) { throw new InvalidArgumentException('Budget minimal Rp 500.000'); }
        $budget = $exact > 0 ? min($exact, 500000000) : self::BUDGET_MAX[$bk];

        $sw = [];
        foreach ((array)($in['sw'] ?? []) as $s) { $sw[] = mb_substr((string)$s, 0, 40); }

        $wl = $this->wls[self::WL[$workKey]] ?? null;
        if (!$wl) { throw new InvalidArgumentException('Data beban kerja belum ada di database'); }

        return ['workKey' => $workKey, 'wl' => $wl, 'budget' => $budget, 'prio' => $prio, 'brand' => $brand,
                'cats' => $cats, 'sw' => array_slice($sw, 0, 10), 'label' => 'Rekomendasi Utama',
                'col' => $wl['kolom_skor'] === 'gaming' ? 'skor_gaming' : 'skor_kreator'];
    }

    private function budgetLabel(int $b): string
    {
        if ($b <= 5000000)  { return '< 5 Juta'; }
        if ($b <= 10000000) { return '5 - 10 Juta'; }
        if ($b <= 18000000) { return '10 - 18 Juta'; }
        if ($b <= 28000000) { return '18 - 28 Juta'; }
        return '> 28 Juta';
    }

    private function minSpec(array $c): array
    {
        $b = $c['budget']; $wl = $c['wl'];
        return [
            'vram' => $b < 18000000 ? min((int)$wl['min_vram'], 8) : (int)$wl['min_vram'],
            'ram'  => $b < 8000000  ? min((int)$wl['min_ram'], 16) : (int)$wl['min_ram'],
            'ssd'  => $b < 8000000  ? min((int)$wl['min_ssd'], 500) : (int)$wl['min_ssd'],
        ];
    }

    /* ------------------------------------------------------------------
     * Inferensi utama
     * ---------------------------------------------------------------- */
    private function build(array $c): array
    {
        $wl = $c['wl']; $col = $c['col']; $min = $this->minSpec($c);
        $t = [];
        $t[] = "Fakta awal: beban kerja = {$wl['nama_workload']}; budget = " . $this->rp($c['budget'])
             . '; prioritas = ' . self::PRIO[$c['prio']] . '; brand = ' . self::BRAND[$c['brand']]
             . '; komponen dicari = ' . implode(', ', $c['cats']) . '.';

        // R1: bobot dasar dari aturan_alokasi
        $w = [];
        foreach ($c['cats'] as $cat) { $w[$cat] = $this->alok[$wl['kode_workload']][$cat] ?? 10.0; }
        // R2: tambahan bobot dari software
        foreach ($c['sw'] as $s) {
            foreach (self::SW_BOOST as $key => $boost) {
                if (stripos($s, $key) === false) { continue; }
                foreach ($boost as $cat => $v) { if (isset($w[$cat])) { $w[$cat] += $v; } }
            }
        }
        // R3: pengali prioritas
        foreach ($w as $cat => $v) { $w[$cat] = $v * ($this->prio[self::PRIO[$c['prio']]][$cat] ?? 1.0); }
        // R4: beban ringan tidak butuh GPU diskrit -> wajib CPU ber-iGPU
        $needIgpu = false;
        if (isset($w['GPU']) && $w['GPU'] < 5 && count($w) > 1) {
            unset($w['GPU']); $needIgpu = true;
            $t[] = 'Aturan R4: bobot GPU < 5% untuk beban kerja ini, VGA diskrit tidak diperlukan, CPU wajib memiliki iGPU.';
        }
        $sumW = array_sum($w);
        if ($sumW <= 0) { foreach ($w as $k => $v) { $w[$k] = 1.0; } $sumW = count($w); }
        $pct = [];
        foreach ($w as $k => $v) { $pct[$k] = $v / $sumW * 100; }
        $t[] = 'Aturan R1-R3: bobot alokasi budget = ' . implode(', ', array_map(function ($k) use ($pct) {
            return $k . ' ' . round($pct[$k]) . '%';
        }, array_keys($pct))) . '.';

        // Pemilihan part berurutan; dana tersisa dibagi ulang ke kategori berikutnya
        $ch = []; $notes = []; $remain = $c['budget']; $remW = $sumW; $caps = [];
        foreach (self::ORDER as $cat) {
            if (!isset($w[$cat])) { continue; }
            $cap = $remW > 0 ? $remain * $w[$cat] / $remW : 0;
            $cand = $this->candidates($cat, $ch, $c, $min, $needIgpu, $notes);
            if (!$cand) { $t[] = "Tidak ada $cat tersedia di database (stok habis / belum diisi)."; continue; }
            $pick = $this->pick($cand, $cap, $col, $c['prio']);
            $ch[$cat] = $pick; $caps[$cat] = $cap;
            $remain -= $pick['harga']; $remW -= $w[$cat];
            $t[] = "Aturan pilih-$cat: alokasi " . $this->rp((int)$cap) . ' -> ' . $pick['nama_part']
                 . ' (' . $this->rp($pick['harga']) . ', skor ' . $pick[$col] . ').';
        }
        foreach (array_unique($notes) as $n) { $t[] = 'Catatan: ' . $n . '.'; }

        // Koreksi bila melebihi budget: turunkan part dengan kehilangan skor terkecil per rupiah dihemat
        $guard = 0;
        while ($this->totals($ch)['total'] > $c['budget'] && $guard++ < 25) {
            $best = null; $bestRatio = INF;
            foreach ($ch as $cat => $p) {
                foreach ($this->parts[$cat] as $q) {
                    if ($q['harga'] >= $p['harga']) { continue; }
                    if ($cat === 'GPU' && $q['kapasitas'] < min($p['kapasitas'], $min['vram'])) { continue; }
                    if ($cat === 'RAM' && $q['kapasitas'] < min($p['kapasitas'], $min['ram'])) { continue; }
                    if ($cat === 'Storage' && $q['kapasitas'] < min($p['kapasitas'], $min['ssd'])) { continue; }
                    if (!$this->brandOk($cat, $q, $c['brand'])) { continue; }
                    $test = $ch; $test[$cat] = $q;
                    if ($this->errors($this->check($test, $c))) { continue; }
                    $ratio = max($p[$col] - $q[$col], 0.1) / ($p['harga'] - $q['harga']);
                    if ($ratio < $bestRatio) { $bestRatio = $ratio; $best = [$cat, $q, $p]; }
                }
            }
            if (!$best) { break; }
            $ch[$best[0]] = $best[1];
            $t[] = "Koreksi budget: {$best[0]} diturunkan dari {$best[2]['nama_part']} ke {$best[1]['nama_part']}.";
        }
        $tot = $this->totals($ch);
        $over = $tot['total'] > $c['budget'];
        $t[] = $over
            ? 'Hasil: budget belum cukup untuk konfigurasi minimum, total ' . $this->rp($tot['total']) . '.'
            : 'Hasil: total ' . $this->rp($tot['total']) . ' <= budget ' . $this->rp($c['budget']) . '. Semua aturan kompatibilitas terpenuhi.';

        return ['ch' => $ch, 'caps' => $caps, 'pct' => $pct, 'over' => $over, 'trace' => $t, 'min' => $min, 'needIgpu' => $needIgpu];
    }

    private function brandOk(string $cat, array $p, string $pref): bool
    {
        if ($pref === 'intel-nvidia') {
            if ($cat === 'CPU') { return $p['brand'] === 'Intel'; }
            if ($cat === 'GPU') { return $p['brand'] === 'NVIDIA'; }
        }
        if ($pref === 'amd') {
            if ($cat === 'CPU' || $cat === 'GPU') { return $p['brand'] === 'AMD'; }
        }
        return true;
    }

    private function candidates(string $cat, array $ch, array $c, array $min, bool $needIgpu, array &$notes): array
    {
        $l = $this->parts[$cat];
        $apply = function (array $list, callable $f, string $note, array &$notes) {
            $r = array_values(array_filter($list, $f));
            if ($r) { return $r; }
            $notes[] = $note;
            return $list;
        };
        if ($cat === 'CPU' || $cat === 'GPU') {
            $pref = $c['brand'];
            $l = $apply($l, function ($p) use ($cat, $pref) { return $this->brandOk($cat, $p, $pref); },
                        "preferensi brand tidak dapat dipenuhi untuk $cat, diabaikan", $notes);
        }
        if ($cat === 'CPU' && $needIgpu) {
            $l = $apply($l, function ($p) { return $p['igpu'] === 1; }, 'tidak ada CPU ber-iGPU', $notes);
        }
        if ($cat === 'Motherboard' && isset($ch['CPU'])) {
            $s = $ch['CPU']['socket'];
            $l = $apply($l, function ($p) use ($s) { return $p['socket'] === $s; }, "tidak ada motherboard socket $s", $notes);
        }
        if ($cat === 'GPU') {
            $v = $min['vram'];
            $l = $apply($l, function ($p) use ($v) { return $p['kapasitas'] >= $v; }, "tidak ada GPU dengan VRAM >= {$v}GB", $notes);
        }
        if ($cat === 'RAM') {
            if (isset($ch['Motherboard'])) {
                $tr = $ch['Motherboard']['tipe_ram'];
                $l = $apply($l, function ($p) use ($tr) { return $p['tipe_ram'] === $tr; }, "tidak ada RAM $tr", $notes);
            }
            $m = $min['ram'];
            $l = $apply($l, function ($p) use ($m) { return $p['kapasitas'] >= $m; }, "tidak ada RAM >= {$m}GB untuk tipe ini", $notes);
        }
        if ($cat === 'Storage') {
            $m = $min['ssd'];
            $l = $apply($l, function ($p) use ($m) { return $p['kapasitas'] >= $m; }, "tidak ada SSD >= {$m}GB", $notes);
        }
        if ($cat === 'Cooler' && isset($ch['CPU'])) {
            $tdp = $ch['CPU']['watt_tdp'];
            $l = $apply($l, function ($p) use ($tdp) { return $p['kapasitas'] >= $tdp; }, "tidak ada cooler untuk TDP {$tdp}W", $notes);
        }
        if ($cat === 'Casing' && isset($ch['Motherboard']) && $ch['Motherboard']['form_factor'] === 'ATX') {
            $l = $apply($l, function ($p) { return $p['form_factor'] === 'ATX'; }, 'tidak ada casing ATX', $notes);
        }
        if ($cat === 'PSU') {
            $need = $this->psuNeed($ch, $c['prio']);
            $l = $apply($l, function ($p) use ($need) { return $p['watt_tdp'] >= $need; }, "tidak ada PSU >= {$need}W", $notes);
            // jangan membeli PSU jauh lebih besar dari kebutuhan (maks 1.3x; 1.5x bila fokus upgrade/efisiensi)
            $fac = in_array($c['prio'], ['upgrade', 'efisien'], true) ? 1.5 : 1.3;
            $lim = max($need * $fac, min(array_column($l, 'watt_tdp')));
            $l = array_values(array_filter($l, function ($p) use ($lim) { return $p['watt_tdp'] <= $lim; }));
        }
        return $l;
    }

    private function psuNeed(array $ch, string $prio): int
    {
        $w = 0;
        foreach ($ch as $cat => $p) { if ($cat !== 'PSU') { $w += $p['watt_tdp']; } }
        return (int)(ceil($w * ($prio === 'upgrade' ? 1.6 : 1.4) / 50) * 50);
    }

    private function pick(array $cand, float $cap, string $col, string $prio): array
    {
        $in = array_values(array_filter($cand, function ($p) use ($cap) { return $p['harga'] <= $cap; }));
        if (!$in) {
            usort($cand, function ($a, $b) { return $a['harga'] <=> $b['harga']; });
            return $cand[0];
        }
        if ($prio === 'hemat') {
            $top = max(array_map(function ($p) use ($col) { return $p[$col]; }, $in));
            $in = array_values(array_filter($in, function ($p) use ($col, $top) { return $p[$col] >= 0.7 * $top; }));
            usort($in, function ($a, $b) use ($col) {
                return ($b[$col] / $b['harga']) <=> ($a[$col] / $a['harga']);
            });
        } else {
            usort($in, function ($a, $b) use ($col) {
                return ($b[$col] <=> $a[$col]) ?: ($a['harga'] <=> $b['harga']);
            });
        }
        return $in[0];
    }

    /* ------------------------------------------------------------------
     * Aturan kompatibilitas
     * ---------------------------------------------------------------- */
    private function check(array $ch, array $c): array
    {
        $r = []; $col = $c['col'];
        $add = function ($t, $m) use (&$r) { $r[] = ['t' => $t, 'm' => $m]; };
        if (isset($ch['CPU'], $ch['Motherboard'])) {
            $a = $ch['CPU']['socket']; $b = $ch['Motherboard']['socket'];
            $a === $b ? $add('ok', "Socket CPU $a cocok dengan motherboard")
                      : $add('err', "Socket CPU ($a) tidak cocok dengan motherboard ($b)");
        }
        if (isset($ch['Motherboard'], $ch['RAM'])) {
            $a = $ch['Motherboard']['tipe_ram']; $b = $ch['RAM']['tipe_ram'];
            $a === $b ? $add('ok', "Tipe RAM $a cocok dengan motherboard")
                      : $add('err', "Motherboard mendukung $a, RAM yang dipilih $b");
        }
        if (isset($ch['Motherboard'], $ch['Casing'])) {
            $mf = $ch['Motherboard']['form_factor']; $cf = $ch['Casing']['form_factor'];
            ($mf === 'ATX' && $cf !== 'ATX')
                ? $add('err', 'Motherboard ATX tidak muat di casing mATX')
                : $add('ok', "Form factor $mf muat di casing $cf");
        }
        if (isset($ch['CPU'], $ch['Cooler'])) {
            $tdp = $ch['CPU']['watt_tdp']; $cap = $ch['Cooler']['kapasitas'];
            $cap >= $tdp ? $add('ok', "Cooler ({$cap}W) mampu mendinginkan CPU ({$tdp}W)")
                         : $add('err', "Cooler ({$cap}W) kurang untuk CPU {$tdp}W");
        }
        if (isset($ch['PSU'])) {
            $watt = $this->totals($ch)['watt']; $cap = $ch['PSU']['watt_tdp'];
            if ($cap < $watt * 1.25)     { $add('err', "PSU {$cap}W kurang untuk beban ~{$watt}W"); }
            elseif ($cap < $watt * 1.4)  { $add('warn', "PSU {$cap}W cukup tetapi headroom tipis untuk beban ~{$watt}W"); }
            else                         { $add('ok', "PSU {$cap}W memiliki headroom aman untuk beban ~{$watt}W"); }
        }
        if (isset($ch['CPU']) && !isset($ch['GPU']) && $ch['CPU']['igpu'] === 0 && isset($ch['Motherboard'])) {
            $add('warn', 'CPU tanpa iGPU: sistem butuh kartu grafis agar ada output layar');
        }
        if (isset($ch['CPU'], $ch['GPU']) && $ch['CPU'][$col] > 0) {
            $ratio = $ch['GPU'][$col] / $ch['CPU'][$col];
            if ($ratio > 1.45)     { $add('warn', 'GPU jauh lebih kuat dari CPU: potensi bottleneck di CPU'); }
            elseif ($ratio < 0.55) { $add('warn', 'CPU jauh lebih kuat dari GPU: GPU akan menjadi pembatas performa'); }
            else                   { $add('ok', 'Keseimbangan CPU-GPU baik (bebas bottleneck signifikan)'); }
        }
        return $r;
    }

    private function errors(array $checks): array
    {
        return array_values(array_filter($checks, function ($x) { return $x['t'] === 'err'; }));
    }

    private function totals(array $ch): array
    {
        $total = 0; $watt = 0;
        foreach ($ch as $cat => $p) {
            $total += $p['harga'];
            if ($cat !== 'PSU') { $watt += $p['watt_tdp']; }
        }
        return ['total' => $total, 'watt' => $watt, 'psu_min' => (int)(ceil($watt * 1.4 / 50) * 50)];
    }

    /* ------------------------------------------------------------------
     * Penyusunan hasil
     * ---------------------------------------------------------------- */
    private function summary(array $c, array $b): array
    {
        $ch = $b['ch']; $col = $c['col']; $items = [];
        foreach (self::ORDER as $cat) {
            if (!isset($ch[$cat])) { continue; }
            $p = $ch[$cat];
            $items[] = [
                'cat' => $cat, 'ico' => self::ICON[$cat], 'kode' => $p['kode_part'], 'nama' => $p['nama_part'],
                'brand' => $p['brand'], 'label' => $p['label_peran'], 'spek' => $p['spesifikasi_singkat'],
                'harga' => $p['harga'], 'stok' => $p['stok_status'], 'garansi' => $p['garansi'],
                'alasan' => $this->reason($cat, $p, $ch, $b, $c),
                'alt' => $this->alternatives($cat, $ch, $c),
            ];
        }
        $tot = $this->totals($ch);
        $checks = $this->check($ch, $c);
        $why = [];
        $why[] = 'Syarat beban kerja: ' . $c['wl']['syarat_spesifikasi'] . '.';
        $why[] = 'Alokasi budget mengikuti aturan pakar untuk ' . $c['wl']['nama_workload'] . ' dengan prioritas '
               . self::PRIO[$c['prio']] . '.';
        $nErr = count($this->errors($checks));
        $why[] = $nErr === 0 ? 'Seluruh pengecekan kompatibilitas (socket, tipe RAM, form factor, pendingin, daya PSU) lolos.'
                             : "$nErr aturan kompatibilitas bermasalah, mohon konsultasikan dengan admin.";
        if ($b['over']) {
            $why[] = 'Budget lebih kecil dari estimasi minimum konfigurasi ini; total aktual ' . $this->rp($tot['total']) . '.';
        }
        $short = [];
        foreach (['CPU', 'GPU'] as $k) { if (isset($ch[$k])) { $short[] = trim(preg_replace('/\s*\(.*$/', '', $ch[$k]['nama_part'])); } }
        if (isset($ch['RAM'])) { $short[] = $ch['RAM']['kapasitas'] . 'GB ' . $ch['RAM']['tipe_ram']; }
        if (!$short) { foreach ($items as $i) { $short[] = $i['nama']; } }

        return [
            'label' => $c['label'], 'title' => implode(' + ', array_slice($short, 0, 3)),
            'budget' => $c['budget'], 'prio_label' => self::PRIO[$c['prio']],
            'items' => $items, 'total' => $tot['total'], 'watt' => $tot['watt'], 'psu_min' => $tot['psu_min'],
            'over_budget' => $b['over'], 'checks' => $checks, 'why' => $why, 'trace' => $b['trace'],
        ];
    }

    private function reason(string $cat, array $p, array $ch, array $b, array $c): string
    {
        $col = $c['col']; $min = $b['min'];
        $cap = isset($b['caps'][$cat]) ? ' Alokasi budget kategori ini ' . $this->rp((int)$b['caps'][$cat]) . '.' : '';
        switch ($cat) {
            case 'CPU':
                $s = "Skor performa {$p[$col]}/100, socket {$p['socket']}.";
                if ($b['needIgpu']) { $s .= ' Punya iGPU, sehingga VGA diskrit tidak perlu untuk beban kerja ini.'; }
                return $s . $cap;
            case 'Motherboard':
                return "Socket {$p['socket']} dan {$p['tipe_ram']} sesuai dengan komponen lain, form factor {$p['form_factor']}." . $cap;
            case 'GPU':
                $s = "VRAM {$p['kapasitas']}GB" . ($min['vram'] ? " (syarat minimal {$min['vram']}GB)" : '') . ", skor {$p[$col]}/100.";
                if (!empty($p['ds_year'])) {
                    $s .= ' Dataset GPU: rilis ' . $p['ds_year'] . (!empty($p['ds_bus']) ? ", bus memori {$p['ds_bus']}-bit" : '')
                        . ', VRAM ' . rtrim(rtrim((string)$p['ds_vram'], '0'), '.') . 'GB.';
                }
                return $s . $cap;
            case 'RAM':
                return "{$p['kapasitas']}GB {$p['tipe_ram']} (syarat beban kerja minimal {$min['ram']}GB)." . $cap;
            case 'Storage':
                return "{$p['kapasitas']}GB NVMe (syarat minimal {$min['ssd']}GB), skor kecepatan {$p[$col]}/100." . $cap;
            case 'Cooler':
                $tdp = isset($ch['CPU']) ? $ch['CPU']['watt_tdp'] : 0;
                return "Kapasitas pendinginan {$p['kapasitas']}W" . ($tdp ? " untuk CPU {$tdp}W." : '.') . $cap;
            case 'Casing':
                return "Form factor {$p['form_factor']} kompatibel dengan motherboard, skor airflow {$p[$col]}/100." . $cap;
            case 'PSU':
                $need = $this->psuNeed($ch, $c['prio']);
                return "Daya {$p['watt_tdp']}W memenuhi kebutuhan {$need}W (beban x1.4 headroom)." . $cap;
        }
        return '';
    }

    /** Hingga 3 part pengganti yang tidak merusak kompatibilitas. */
    private function alternatives(string $cat, array $ch, array $c): array
    {
        $cur = $ch[$cat]; $out = [];
        foreach ($this->parts[$cat] as $q) {
            if ($q['kode_part'] === $cur['kode_part']) { continue; }
            $test = $ch; $test[$cat] = $q;
            if ($this->errors($this->check($test, $c))) { continue; }
            $out[] = ['kode' => $q['kode_part'], 'nama' => $q['nama_part'], 'harga' => $q['harga'],
                      'd' => abs($q['harga'] - $cur['harga'])];
        }
        usort($out, function ($a, $b) { return $a['d'] <=> $b['d']; });
        return array_map(function ($x) { unset($x['d']); return $x; }, array_slice($out, 0, 3));
    }

    /** Paket rekomendasi pakar toko (tabel aturan_rakitan) bila cocok & semua part dipilih. */
    private function ruleMatch(array $c): ?array
    {
        if (count($c['cats']) < 8) { return null; }
        $st = $this->db->prepare(
            "SELECT a.* FROM aturan_rakitan a JOIN beban_kerja b ON b.id_workload = a.id_workload
             WHERE b.kode_workload = :wl AND a.rentang_budget = :bd
             ORDER BY (a.prioritas = :pr) DESC, (a.preferensi_brand = :br) DESC LIMIT 1");
        $st->execute([':wl' => $c['wl']['kode_workload'], ':bd' => $this->budgetLabel($c['budget']),
                      ':pr' => self::PRIO[$c['prio']], ':br' => self::BRAND[$c['brand']]]);
        $a = $st->fetch();
        if (!$a) { return null; }
        $q = $this->db->prepare("SELECT k.kode_part, k.nama_part, k.harga FROM detail_aturan_komponen d
                                 JOIN komponen k ON k.id_part = d.id_part WHERE d.id_aturan = :id");
        $q->execute([':id' => $a['id_aturan']]);
        $parts = $q->fetchAll();
        $total = 0; foreach ($parts as $p) { $total += (int)$p['harga']; }
        return [
            'nama' => $a['nama_paket'], 'judul' => $a['judul_kombinasi'], 'psu_watt' => (int)$a['rekomendasi_psu_watt'],
            'alasan' => array_values(array_filter([$a['alasan_pakar_1'], $a['alasan_pakar_2'], $a['alasan_pakar_3']])),
            'kodes' => array_column($parts, 'kode_part'), 'total' => $total,
        ];
    }

    private function rp(int $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }
}
