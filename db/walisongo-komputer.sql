CREATE DATABASE IF NOT EXISTS db_walisongo_pc;
USE db_walisongo_pc;

-- 1. TABEL MASTER KOMPONEN (Menyimpan 8 Kategori Part & Spesifikasi Kompatibilitas)
CREATE TABLE komponen (
    id_part INT AUTO_INCREMENT PRIMARY KEY,
    kode_part VARCHAR(10) UNIQUE NOT NULL,
    kategori ENUM('CPU', 'Motherboard', 'GPU', 'RAM', 'Storage', 'PSU', 'Cooler', 'Casing') NOT NULL,
    label_peran VARCHAR(50), -- Contoh: 'Otak Komputasi', 'Soket & Konektivitas'
    brand VARCHAR(30) NOT NULL, -- Intel, AMD, NVIDIA, ASRock, Kingston, dll
    nama_part VARCHAR(150) NOT NULL,
    spesifikasi_singkat VARCHAR(200),
    harga DECIMAL(12,0) NOT NULL,
    socket VARCHAR(20) DEFAULT NULL, -- Khusus CPU & Motherboard (LGA1700 / AM5 / AM4)
    tipe_ram VARCHAR(10) DEFAULT NULL, -- Khusus Motherboard & RAM (DDR4 / DDR5)
    watt_tdp INT DEFAULT 0, -- Konsumsi daya (atau kapasitas watt khusus untuk PSU)
    stok_status ENUM('Stok Tersedia', 'Stok Terbatas', 'Habis') DEFAULT 'Stok Tersedia'
);

-- 2. TABEL BEBAN KERJA (Sesuai 4 Pilihan Kartu di Langkah 1 Form)
CREATE TABLE beban_kerja (
    id_workload INT AUTO_INCREMENT PRIMARY KEY,
    kode_workload VARCHAR(20) UNIQUE NOT NULL,
    nama_workload VARCHAR(100) NOT NULL,
    deskripsi VARCHAR(255),
    syarat_spesifikasi VARCHAR(150)
);

-- 3. TABEL ATURAN PAKAR / PAKET RAKITAN (Rule Base Sistem Pakar)
CREATE TABLE aturan_rakitan (
    id_aturan INT AUTO_INCREMENT PRIMARY KEY,
    kode_aturan VARCHAR(15) UNIQUE NOT NULL,
    id_workload INT NOT NULL,
    nama_paket VARCHAR(100) NOT NULL, -- Contoh: 'Creator Studio Pro'
    judul_kombinasi VARCHAR(150) NOT NULL, -- Contoh: 'Intel Core i5-14500 + RTX 4060 8GB + 32GB DDR5'
    rentang_budget ENUM('< 5 Juta', '5 - 10 Juta', '10 - 18 Juta', '18 - 28 Juta', '> 28 Juta') NOT NULL,
    prioritas ENUM('Performa Maksimal', 'Siap Upgrade', 'Efisiensi Daya', 'Budget Saver') NOT NULL,
    preferensi_brand ENUM('Bebas', 'Intel + NVIDIA', 'Full AMD') NOT NULL,
    rekomendasi_psu_watt INT NOT NULL,
    alasan_pakar_1 TEXT,
    alasan_pakar_2 TEXT,
    alasan_pakar_3 TEXT,
    FOREIGN KEY (id_workload) REFERENCES beban_kerja(id_workload) ON DELETE CASCADE
);

-- 4. TABEL RELASI DETAIL PART PER ATURAN (Memungkinkan Fitur Pilih Komponen Modular)
CREATE TABLE detail_aturan_komponen (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_aturan INT NOT NULL,
    id_part INT NOT NULL,
    FOREIGN KEY (id_aturan) REFERENCES aturan_rakitan(id_aturan) ON DELETE CASCADE,
    FOREIGN KEY (id_part) REFERENCES komponen(id_part) ON DELETE CASCADE
);

-- ==========================================
-- INSERT DATA BEBAN KERJA
-- ==========================================
INSERT INTO beban_kerja (kode_workload, nama_workload, deskripsi, syarat_spesifikasi) VALUES
('WL_CREATOR', 'Desain Grafis & Video 4K', 'Photoshop, Premiere Pro, After Effects, Illustrator, Canva, rendering video mulus tanpa patah.', 'VRAM GPU >= 8GB, RAM 32GB Recommended, SSD NVMe Kencang'),
('WL_GAMING', 'Gaming & Streaming', 'Game eSports & AAA visual tinggi, frame rate stabil tinggi, encoder NVENC / AV1 untuk streaming.', 'High Clock CPU, Ray Tracing / DLSS, 144Hz Ready'),
('WL_3D_AI', 'Arsitektur, 3D Render & AI', 'Blender, AutoCAD, SketchUp, rendering 3D, simulasi, pemrograman berat & machine learning.', 'Banyak Core/Threads, CUDA Cores Tinggi, RAM 32-64GB+'),
('WL_OFFICE', 'Kantor, Kuliah & Multitasking', 'Aplikasi kasir, akuntansi, dokumen spreadsheet ratusan baris, multitasking browser stabil harian.', 'Hemat Daya, iGPU Cukup, Multitasking Stabil');

-- ==========================================
-- INSERT DATA KOMPONEN (Sesuai UI Walisongo Komputer)
-- ==========================================
INSERT INTO komponen (kode_part, kategori, label_peran, brand, nama_part, spesifikasi_singkat, harga, socket, tipe_ram, watt_tdp) VALUES
-- Komponen Paket Utama (Creator Studio Pro - Intel + NVIDIA)
('CPU01', 'CPU', 'Otak Komputasi', 'Intel', 'Intel Core i5-14500 (14 Core / 20 Thread)', 'Socket LGA1700, Up to 5.0GHz, Intel UHD 770', 3850000, 'LGA1700', NULL, 154),
('MOB01', 'Motherboard', 'Soket & Konektivitas', 'ASRock', 'ASRock B760M Pro RS WiFi DDR5', 'mATX, LGA1700, 4x DDR5, PCIe 4.0, 3x M.2 NVMe', 2350000, 'LGA1700', 'DDR5', 50),
('GPU01', 'GPU', 'Akselerasi Visual & AI', 'NVIDIA', 'ZOTAC Gaming RTX 4060 8GB GDDR6', 'Twin Edge OC, DLSS 3, NVENC Gen 8, Ray Tracing', 4850000, NULL, NULL, 115),
('RAM01', 'RAM', 'Memori Multitasking', 'Kingston', 'Kingston FURY Beast 32GB (2x16GB) DDR5 6000MHz', 'Dual Channel Kit, Intel XMP 3.0 & AMD EXPO, CL36', 1850000, NULL, 'DDR5', 15),
('SSD01', 'Storage', 'Penyimpanan Cepat', 'Kingston', 'Kingston KC3000 1TB NVMe PCIe 4.0', 'Read Speed up to 7000MB/s, Write 6000MB/s, Graphene Heatsink', 1350000, NULL, NULL, 10),
('PSU01', 'PSU', 'Suplai Daya Stabil', 'Corsair', 'Corsair CX650 650W 80+ Bronze Certified', 'Flat Black Cables, Proteksi OVP/OPP/SCP Lengkap, Garansi 5 Tahun', 1050000, NULL, NULL, 650),
('CLR01', 'Cooler', 'Pendingin Prosesor', 'Thermalright', 'Thermalright Assassin Spirit 120 V2', '4x Heatpipes, 120mm PWM Quiet Fan, Suhu Terjaga < 70C', 320000, NULL, NULL, 5),
('CAS01', 'Casing', 'Sirkulasi Udara', 'Montech', 'Montech Air 100 ARGB Mesh (4x ARGB Fan)', 'High Airflow Mesh Front Panel, Tempered Glass Side Panel', 850000, NULL, NULL, 20),

-- Komponen Tambahan untuk Paket Alternatif Hemat (Creator Starter AMD)
('CPU02', 'CPU', 'Otak Komputasi', 'AMD', 'AMD Ryzen 5 5600X (6 Core / 12 Thread)', 'Socket AM4, Up to 4.6GHz, Efisiensi Tinggi', 2150000, 'AM4', NULL, 65),
('MOB02', 'Motherboard', 'Soket & Konektivitas', 'MSI', 'MSI B550M PRO-VDH WIFI', 'mATX, Socket AM4, 4x DDR4, PCIe 4.0 M.2', 1750000, 'AM4', 'DDR4', 45),
('GPU02', 'GPU', 'Akselerasi Visual & AI', 'NVIDIA', 'GeForce RTX 3060 12GB GDDR6 (VRAM Lega)', '12GB VRAM untuk aset tekstur & video 4K, CUDA Cores', 4450000, NULL, NULL, 170),
('RAM02', 'RAM', 'Memori Multitasking', 'Corsair', 'Corsair Vengeance LPX 32GB (2x16GB) DDR4 3600MHz', 'Dual Channel DDR4, Low Profile Heatspreader', 1150000, NULL, 'DDR4', 10),

-- Komponen Tambahan untuk Paket Alternatif Performa Tinggi (Creator Ultra X)
('CPU03', 'CPU', 'Otak Komputasi', 'AMD', 'AMD Ryzen 7 7700X (8 Core / 16 Thread)', 'Socket AM5, Up to 5.4GHz, Zen 4 Architecture', 4950000, 'AM5', NULL, 105),
('MOB03', 'Motherboard', 'Soket & Konektivitas', 'Gigabyte', 'Gigabyte B650M AORUS ELITE AX', 'mATX, Socket AM5, DDR5, WiFi 6E, VRM Tangguh', 3450000, 'AM5', 'DDR5', 55),
('GPU03', 'GPU', 'Akselerasi Visual & AI', 'NVIDIA', 'GeForce RTX 4070 Super 12GB GDDR6X', 'Performa Render 3D & AI Ekstrem, Dual AV1 Encoder', 9850000, NULL, NULL, 220),
('PSU02', 'PSU', 'Suplai Daya Stabil', 'MSI', 'MSI MAG A750GL PCIE5 750W 80+ Gold Modular', 'Full Modular, ATX 3.0 Ready, 16-pin PCIe 5.0 Cable', 1650000, NULL, NULL, 750);

-- ==========================================
-- INSERT DATA ATURAN PAKAR (RULE BASE)
-- ==========================================
INSERT INTO aturan_rakitan (
    kode_aturan, id_workload, nama_paket, judul_kombinasi, rentang_budget, prioritas, preferensi_brand, rekomendasi_psu_watt,
    alasan_pakar_1, alasan_pakar_2, alasan_pakar_3
) VALUES
-- Rule 1: Paket Utama di Halaman Hasil (10 - 18 Juta)
('RULE_01', (SELECT id_workload FROM beban_kerja WHERE kode_workload = 'WL_CREATOR'), 'Creator Studio Pro', 'Intel Core i5-14500 + RTX 4060 8GB + 32GB DDR5', '10 - 18 Juta', 'Performa Maksimal', 'Bebas', 650,
 'CPU Intel i5-14500 (14 Core/20 Thread) sangat optimal untuk timeline scrub Premiere Pro dengan fitur Intel QuickSync tanpa drop frame.',
 'GPU RTX 4060 8GB NVIDIA Gen-4 dilengkapi akselerasi hardware NVENC/AV1 berkecepatan tinggi serta dukungan AI CUDA pada Adobe Creative Cloud.',
 'RAM 32GB DDR5 6000MHz Dual Channel memberikan bandwidth melimpah untuk buka aplikasi desain grafis berat dan preview rendering mulus.'),

-- Rule 2: Paket Alternatif Lebih Hemat (10 - 18 Juta / ~13 Jutaan)
('RULE_02', (SELECT id_workload FROM beban_kerja WHERE kode_workload = 'WL_CREATOR'), 'Creator Starter AMD', 'AMD Ryzen 5 5600X + RTX 3060 12GB + 32GB DDR4', '10 - 18 Juta', 'Budget Saver', 'Bebas', 550,
 'Kombinasi Ryzen 5 5600X dan RAM 32GB DDR4 memberikan rasio harga-per-performa terbaik untuk editing video 1080p hingga 4K ringan.',
 'Kapasitas VRAM 12GB pada RTX 3060 sangat membantu menampung aset grafik beresolusi tinggi tanpa kehabisan memori grafis.',
 'Sangat efisien daya dan mudah di-upgrade tanpa perlu mengganti sistem pendingin kelas berat.'),

-- Rule 3: Paket Alternatif Performa Tinggi (18 - 28 Juta / ~24 Jutaan)
('RULE_03', (SELECT id_workload FROM beban_kerja WHERE kode_workload = 'WL_CREATOR'), 'Creator Ultra X', 'AMD Ryzen 7 7700X + RTX 4070 Super 12GB + 32GB DDR5', '18 - 28 Juta', 'Siap Upgrade', 'Bebas', 750,
 'Platform Socket AM5 menjamin jalur upgrade prosesor generasi mendatang tanpa perlu ganti motherboard.',
 'RTX 4070 Super 12GB memangkas waktu rendering 3D Blender dan ekspor video 4K After Effects hingga 45% lebih cepat.',
 'Didukung PSU 750W 80+ Gold Full Modular untuk kestabilan arus listrik pada beban kerja studio profesional.');

-- ==========================================
-- INSERT RELASI KOMPONEN KE ATURAN PAKAR
-- ==========================================
-- Isi 8 Komponen untuk RULE_01 (Creator Studio Pro)
INSERT INTO detail_aturan_komponen (id_aturan, id_part)
SELECT a.id_aturan, k.id_part
FROM aturan_rakitan AS a
CROSS JOIN komponen AS k
WHERE a.kode_aturan = 'RULE_01'
    AND k.kode_part IN ('CPU01', 'MOB01', 'GPU01', 'RAM01', 'SSD01', 'PSU01', 'CLR01', 'CAS01');

-- Isi 8 Komponen untuk RULE_02 (Creator Starter AMD)
INSERT INTO detail_aturan_komponen (id_aturan, id_part)
SELECT a.id_aturan, k.id_part
FROM aturan_rakitan AS a
CROSS JOIN komponen AS k
WHERE a.kode_aturan = 'RULE_02'
    AND k.kode_part IN ('CPU02', 'MOB02', 'GPU02', 'RAM02', 'SSD01', 'PSU01', 'CLR01', 'CAS01');

-- Isi 8 Komponen untuk RULE_03 (Creator Ultra X)
INSERT INTO detail_aturan_komponen (id_aturan, id_part)
SELECT a.id_aturan, k.id_part
FROM aturan_rakitan AS a
CROSS JOIN komponen AS k
WHERE a.kode_aturan = 'RULE_03'
    AND k.kode_part IN ('CPU03', 'MOB03', 'GPU03', 'RAM01', 'SSD01', 'PSU02', 'CLR01', 'CAS01');

