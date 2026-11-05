-- =====================================================================
-- UPGRADE v2 - Sistem Pakar Rekomendasi Spesifikasi Komputer
-- Jalankan SETELAH db/walisongo-komputer.sql (tidak menghapus data lama).
-- Harga komponen di bawah adalah DATA CONTOH: sesuaikan dengan harga toko.
-- =====================================================================
USE db_walisongo_pc;

-- 1. Kolom tambahan untuk basis pengetahuan ---------------------------
ALTER TABLE komponen
  ADD COLUMN skor_gaming  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Skor 0-100 untuk beban gaming',
  ADD COLUMN skor_kreator TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Skor 0-100 untuk beban kreator/3D/kantor',
  ADD COLUMN kapasitas    INT NOT NULL DEFAULT 0 COMMENT 'GPU=VRAM GB, RAM/Storage=GB, Cooler=watt pendingin',
  ADD COLUMN form_factor  VARCHAR(10) DEFAULT NULL COMMENT 'mATX / ATX (Motherboard & Casing)',
  ADD COLUMN igpu         TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = CPU punya grafis bawaan',
  ADD COLUMN garansi      VARCHAR(30) NOT NULL DEFAULT '1 Tahun',
  ADD COLUMN popularitas  TINYINT UNSIGNED NOT NULL DEFAULT 5,
  ADD COLUMN dataset_nama VARCHAR(150) DEFAULT NULL COMMENT 'product_name pada tabel gpu_dataset';

ALTER TABLE beban_kerja
  ADD COLUMN min_vram   INT NOT NULL DEFAULT 0,
  ADD COLUMN min_ram    INT NOT NULL DEFAULT 16,
  ADD COLUMN min_ssd    INT NOT NULL DEFAULT 500,
  ADD COLUMN kolom_skor ENUM('gaming','kreator') NOT NULL DEFAULT 'kreator';

-- 2. Tabel baru -------------------------------------------------------
CREATE TABLE IF NOT EXISTS gpu_dataset (
    id_gpu         INT AUTO_INCREMENT PRIMARY KEY,
    manufacturer   VARCHAR(30)  NOT NULL,
    product_name   VARCHAR(150) NOT NULL,
    release_year   SMALLINT NULL,
    mem_size_gb    DECIMAL(6,1) NULL,
    mem_bus_width  SMALLINT NULL,
    gpu_clock_mhz  INT NULL,
    mem_clock_mhz  INT NULL,
    unified_shader INT NULL,
    tmu            INT NULL,
    rop            INT NULL,
    bus            VARCHAR(40) NULL,
    UNIQUE KEY uq_gpu (manufacturer, product_name),
    KEY idx_gpu_name (product_name),
    KEY idx_gpu_year (release_year)
);

-- Aturan alokasi budget (% budget per kategori) per beban kerja
CREATE TABLE IF NOT EXISTS aturan_alokasi (
    id_alokasi    INT AUTO_INCREMENT PRIMARY KEY,
    kode_workload VARCHAR(20) NOT NULL,
    kategori ENUM('CPU','Motherboard','GPU','RAM','Storage','PSU','Cooler','Casing') NOT NULL,
    persen        DECIMAL(5,2) NOT NULL,
    UNIQUE KEY uq_alok (kode_workload, kategori),
    FOREIGN KEY (kode_workload) REFERENCES beban_kerja(kode_workload) ON DELETE CASCADE
);

-- Pengali bobot per prioritas (kategori yang tidak tercantum = 1.0)
CREATE TABLE IF NOT EXISTS aturan_prioritas (
    id_prioritas INT AUTO_INCREMENT PRIMARY KEY,
    prioritas ENUM('Performa Maksimal','Siap Upgrade','Efisiensi Daya','Budget Saver') NOT NULL,
    kategori  ENUM('CPU','Motherboard','GPU','RAM','Storage','PSU','Cooler','Casing') NOT NULL,
    pengali   DECIMAL(4,2) NOT NULL,
    UNIQUE KEY uq_prio (prioritas, kategori)
);

CREATE TABLE IF NOT EXISTS riwayat_rekomendasi (
    id_riwayat INT AUTO_INCREMENT PRIMARY KEY,
    token      CHAR(8) NOT NULL UNIQUE,
    input_json LONGTEXT NOT NULL,
    hasil_json LONGTEXT NOT NULL,
    total_harga DECIMAL(12,0) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS feedback_rekomendasi (
    id_feedback INT AUTO_INCREMENT PRIMARY KEY,
    token       CHAR(8) NOT NULL,
    pas         TINYINT(1) NOT NULL,
    rating      TINYINT NULL,
    komentar    VARCHAR(500) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (token) REFERENCES riwayat_rekomendasi(token) ON DELETE CASCADE
);

-- 3. Basis pengetahuan: beban kerja & aturan ---------------------------
UPDATE beban_kerja SET min_vram=8,  min_ram=32, min_ssd=1000, kolom_skor='kreator' WHERE kode_workload='WL_CREATOR';
UPDATE beban_kerja SET min_vram=8,  min_ram=16, min_ssd=500,  kolom_skor='gaming'  WHERE kode_workload='WL_GAMING';
UPDATE beban_kerja SET min_vram=12, min_ram=32, min_ssd=1000, kolom_skor='kreator' WHERE kode_workload='WL_3D_AI';
UPDATE beban_kerja SET min_vram=0,  min_ram=16, min_ssd=500,  kolom_skor='kreator' WHERE kode_workload='WL_OFFICE';

INSERT INTO aturan_alokasi (kode_workload, kategori, persen) VALUES
('WL_CREATOR','CPU',22),('WL_CREATOR','Motherboard',12),('WL_CREATOR','GPU',28),('WL_CREATOR','RAM',10),
('WL_CREATOR','Storage',9),('WL_CREATOR','PSU',8),('WL_CREATOR','Cooler',4),('WL_CREATOR','Casing',7),
('WL_GAMING','CPU',17),('WL_GAMING','Motherboard',11),('WL_GAMING','GPU',36),('WL_GAMING','RAM',7),
('WL_GAMING','Storage',8),('WL_GAMING','PSU',8),('WL_GAMING','Cooler',4),('WL_GAMING','Casing',9),
('WL_3D_AI','CPU',26),('WL_3D_AI','Motherboard',12),('WL_3D_AI','GPU',26),('WL_3D_AI','RAM',12),
('WL_3D_AI','Storage',8),('WL_3D_AI','PSU',8),('WL_3D_AI','Cooler',4),('WL_3D_AI','Casing',4),
('WL_OFFICE','CPU',30),('WL_OFFICE','Motherboard',16),('WL_OFFICE','GPU',0),('WL_OFFICE','RAM',14),
('WL_OFFICE','Storage',14),('WL_OFFICE','PSU',10),('WL_OFFICE','Cooler',6),('WL_OFFICE','Casing',10);

INSERT INTO aturan_prioritas (prioritas, kategori, pengali) VALUES
('Performa Maksimal','CPU',1.15),('Performa Maksimal','GPU',1.15),('Performa Maksimal','Casing',0.80),('Performa Maksimal','Cooler',0.80),
('Siap Upgrade','Motherboard',1.40),('Siap Upgrade','PSU',1.50),('Siap Upgrade','CPU',0.95),
('Efisiensi Daya','Cooler',1.80),('Efisiensi Daya','PSU',1.30),('Efisiensi Daya','Casing',1.20),('Efisiensi Daya','GPU',0.90),
('Budget Saver','Casing',0.60),('Budget Saver','Cooler',0.70),('Budget Saver','Motherboard',0.90);

-- 4. Lengkapi 16 komponen lama dengan atribut basis pengetahuan --------
UPDATE komponen SET skor_gaming=70,skor_kreator=76,igpu=1,garansi='3 Tahun',popularitas=8  WHERE kode_part='CPU01';
UPDATE komponen SET skor_gaming=62,skor_kreator=58,igpu=0,garansi='3 Tahun',popularitas=7  WHERE kode_part='CPU02';
UPDATE komponen SET skor_gaming=82,skor_kreator=84,igpu=1,garansi='3 Tahun',popularitas=9  WHERE kode_part='CPU03';
UPDATE komponen SET skor_gaming=65,skor_kreator=65,form_factor='mATX',garansi='3 Tahun',popularitas=8 WHERE kode_part='MOB01';
UPDATE komponen SET skor_gaming=55,skor_kreator=55,form_factor='mATX',garansi='3 Tahun',popularitas=6 WHERE kode_part='MOB02';
UPDATE komponen SET skor_gaming=75,skor_kreator=75,form_factor='mATX',garansi='3 Tahun',popularitas=7 WHERE kode_part='MOB03';
UPDATE komponen SET skor_gaming=60,skor_kreator=62,kapasitas=8, garansi='Resmi 3 Tahun',popularitas=12,dataset_nama='GeForce RTX 4060 AD106' WHERE kode_part='GPU01';
UPDATE komponen SET skor_gaming=52,skor_kreator=60,kapasitas=12,garansi='Resmi 3 Tahun',popularitas=9, dataset_nama='GeForce RTX 3060' WHERE kode_part='GPU02';
UPDATE komponen SET skor_gaming=80,skor_kreator=85,kapasitas=12,garansi='Resmi 3 Tahun',popularitas=8, dataset_nama='GeForce RTX 4070 SUPER' WHERE kode_part='GPU03';
UPDATE komponen SET skor_gaming=80,skor_kreator=80,kapasitas=32,garansi='Lifetime',popularitas=11 WHERE kode_part='RAM01';
UPDATE komponen SET skor_gaming=60,skor_kreator=60,kapasitas=32,garansi='Lifetime',popularitas=7  WHERE kode_part='RAM02';
UPDATE komponen SET skor_gaming=85,skor_kreator=85,kapasitas=1000,garansi='5 Tahun',popularitas=7 WHERE kode_part='SSD01';
UPDATE komponen SET skor_gaming=50,skor_kreator=50,garansi='3 Tahun',popularitas=6 WHERE kode_part='PSU01';
UPDATE komponen SET skor_gaming=80,skor_kreator=80,garansi='5 Tahun',popularitas=6 WHERE kode_part='PSU02';
UPDATE komponen SET skor_gaming=55,skor_kreator=55,kapasitas=160,garansi='2 Tahun',popularitas=5 WHERE kode_part='CLR01';
UPDATE komponen SET skor_gaming=60,skor_kreator=60,form_factor='mATX',garansi='1 Tahun',popularitas=4 WHERE kode_part='CAS01';

-- 5. Komponen baru (HARGA CONTOH, sesuaikan dengan toko) --------------
INSERT INTO komponen (kode_part,kategori,brand,nama_part,spesifikasi_singkat,harga,socket,tipe_ram,watt_tdp,
                      skor_gaming,skor_kreator,kapasitas,form_factor,igpu,garansi,popularitas,dataset_nama) VALUES
-- CPU
('CPU04','CPU','Intel','Intel Core i3-12100 (4 Core / 8 Thread)','Socket LGA1700, hingga 4.3GHz, iGPU UHD 730, hemat daya',1650000,'LGA1700',NULL,89,40,36,0,NULL,1,'3 Tahun',7,NULL),
('CPU05','CPU','AMD','AMD Ryzen 5 5500 (6 Core / 12 Thread)','Socket AM4, hingga 4.2GHz, tanpa iGPU, nilai terbaik entry-level',1350000,'AM4',NULL,65,52,50,0,NULL,0,'3 Tahun',8,NULL),
('CPU06','CPU','AMD','AMD Ryzen 5 5600G (6 Core / 12 Thread)','Socket AM4, iGPU Radeon Vega 7, cocok kantor tanpa VGA',1750000,'AM4',NULL,65,50,52,0,NULL,1,'3 Tahun',7,NULL),
('CPU07','CPU','Intel','Intel Core i5-14600K (14 Core / 20 Thread)','Socket LGA1700, hingga 5.3GHz, iGPU UHD 770, unlocked',3850000,'LGA1700',NULL,181,78,82,0,NULL,1,'3 Tahun',9,NULL),
('CPU08','CPU','AMD','AMD Ryzen 5 7600X (6 Core / 12 Thread)','Socket AM5, hingga 5.3GHz, platform DDR5 jangka panjang',3950000,'AM5',NULL,105,74,70,0,NULL,1,'3 Tahun',10,NULL),
('CPU09','CPU','AMD','AMD Ryzen 7 7800X3D (8 Core / 16 Thread)','Socket AM5, 3D V-Cache, CPU gaming tercepat di kelasnya',7650000,'AM5',NULL,120,95,80,0,NULL,1,'3 Tahun',9,NULL),
('CPU10','CPU','Intel','Intel Core i7-14700K (20 Core / 28 Thread)','Socket LGA1700, hingga 5.6GHz, multi-core tinggi untuk render',6250000,'LGA1700',NULL,253,85,92,0,NULL,1,'3 Tahun',7,NULL),
-- Motherboard
('MOB04','Motherboard','Gigabyte','Gigabyte H610M S2H DDR4','mATX, LGA1700, 2x DDR4, M.2 NVMe, kantor/entry-level',1150000,'LGA1700','DDR4',40,40,40,0,'mATX',0,'3 Tahun',7,NULL),
('MOB05','Motherboard','ASRock','ASRock A520M-HDV','mATX, Socket AM4, 2x DDR4, entry-level',900000,'AM4','DDR4',35,35,35,0,'mATX',0,'3 Tahun',6,NULL),
('MOB06','Motherboard','MSI','MSI PRO B760M-E DDR4','mATX, LGA1700, 2x DDR4, PCIe 4.0, 2x M.2',1850000,'LGA1700','DDR4',45,55,55,0,'mATX',0,'3 Tahun',7,NULL),
('MOB07','Motherboard','ASRock','ASRock A620M-HDV/M.2','mATX, Socket AM5, 2x DDR5, jalur upgrade AM5 hemat',1450000,'AM5','DDR5',45,50,50,0,'mATX',0,'3 Tahun',6,NULL),
('MOB08','Motherboard','ASUS','ASUS TUF Gaming Z790-PLUS WIFI DDR5','ATX, LGA1700, 4x DDR5, VRM kuat untuk CPU K-series, WiFi 6E',4650000,'LGA1700','DDR5',60,85,85,0,'ATX',0,'3 Tahun',5,NULL),
-- GPU
('GPU04','GPU','AMD','Sapphire Pulse Radeon RX 6600 8GB GDDR6','Hemat daya, cocok gaming 1080p',2650000,NULL,NULL,132,48,32,8,NULL,0,'Resmi 2 Tahun',8,NULL),
('GPU05','GPU','AMD','Sapphire Pulse Radeon RX 7600 8GB GDDR6','RDNA 3, AV1 encoder, gaming 1080p tinggi',3450000,NULL,NULL,165,58,38,8,NULL,0,'Resmi 2 Tahun',8,NULL),
('GPU06','GPU','NVIDIA','ASUS Dual GeForce RTX 4060 Ti 16GB GDDR6','VRAM 16GB untuk render, AI, dan video 4K',7450000,NULL,NULL,165,68,74,16,NULL,0,'Resmi 3 Tahun',6,'GeForce RTX 4060 Ti 16 GB'),
('GPU07','GPU','AMD','Sapphire Nitro+ Radeon RX 7800 XT 16GB','VRAM 16GB, gaming 1440p tinggi',8100000,NULL,NULL,263,86,62,16,NULL,0,'Resmi 2 Tahun',7,'Radeon RX 7800 XT'),
('GPU08','GPU','NVIDIA','MSI GeForce RTX 4070 Ti SUPER 16GB GDDR6X','Render 3D & AI kelas atas, gaming 4K',14750000,NULL,NULL,285,90,95,16,NULL,0,'Resmi 3 Tahun',5,'GeForce RTX 4070 Ti SUPER'),
('GPU09','GPU','NVIDIA','ZOTAC GeForce RTX 3050 6GB','Entry-level NVIDIA, tanpa konektor daya tambahan',2650000,NULL,NULL,70,38,40,6,NULL,0,'Resmi 3 Tahun',6,'GeForce RTX 3050 6 GB'),
-- RAM
('RAM03','RAM','Kingston','Kingston FURY Beast 16GB (2x8GB) DDR4 3200MHz','Dual channel, plug & play',520000,NULL,'DDR4',8,40,38,16,NULL,0,'Lifetime',9,NULL),
('RAM04','RAM','Kingston','Kingston FURY Beast 16GB (2x8GB) DDR5 5600MHz','Dual channel DDR5, XMP 3.0',850000,NULL,'DDR5',10,55,52,16,NULL,0,'Lifetime',8,NULL),
('RAM05','RAM','Corsair','Corsair Vengeance 64GB (2x32GB) DDR5 6000MHz CL30','Kapasitas besar untuk 3D, simulasi, dan AI',3650000,NULL,'DDR5',20,82,95,64,NULL,0,'Lifetime',5,NULL),
-- Storage
('SSD02','Storage','Kingston','Kingston NV2 500GB NVMe PCIe 4.0','Baca hingga 3500MB/s, hemat untuk OS & aplikasi',520000,NULL,NULL,8,45,45,500,NULL,0,'3 Tahun',8,NULL),
('SSD03','Storage','Kingston','Kingston NV2 1TB NVMe PCIe 4.0','Baca hingga 3500MB/s, kapasitas lega',850000,NULL,NULL,8,60,60,1000,NULL,0,'3 Tahun',9,NULL),
('SSD04','Storage','Samsung','Samsung 990 PRO 2TB NVMe PCIe 4.0','Baca hingga 7450MB/s, untuk proyek video & aset besar',3150000,NULL,NULL,10,95,95,2000,NULL,0,'5 Tahun',5,NULL),
-- PSU (kapasitas watt disimpan di kolom watt_tdp)
('PSU06','PSU','Corsair','Corsair CV450 450W 80+ Bronze','Proteksi dasar, cukup untuk PC kantor & entry-level gaming',620000,NULL,NULL,450,45,45,0,NULL,0,'3 Tahun',6,NULL),
('PSU03','PSU','Corsair','Corsair CX550 550W 80+ Bronze','Proteksi OVP/OPP/SCP, kabel non-modular',820000,NULL,NULL,550,48,48,0,NULL,0,'3 Tahun',6,NULL),
('PSU04','PSU','Corsair','Corsair RM850e 850W 80+ Gold Modular ATX 3.0','Full modular, 12VHPWR siap untuk GPU kelas atas',1950000,NULL,NULL,850,88,88,0,NULL,0,'7 Tahun',5,NULL),
('PSU05','PSU','Corsair','Corsair RM1000e 1000W 80+ Gold Modular ATX 3.0','Headroom besar untuk CPU & GPU flagship',2450000,NULL,NULL,1000,90,90,0,NULL,0,'7 Tahun',3,NULL),
-- Cooler (kapasitas = kemampuan pendinginan dalam watt)
('CLR02','Cooler','Deepcool','Deepcool AK400','Single tower, 4 heatpipe, kipas 120mm',420000,NULL,NULL,5,62,62,220,NULL,0,'2 Tahun',8,NULL),
('CLR03','Cooler','Deepcool','Deepcool LE520 AIO 240mm','Liquid cooler 240mm ARGB',1150000,NULL,NULL,12,80,80,280,NULL,0,'3 Tahun',6,NULL),
('CLR04','Cooler','Arctic','Arctic Liquid Freezer III 360 ARGB','Liquid cooler 360mm, suhu rendah untuk CPU flagship',2150000,NULL,NULL,15,95,95,380,NULL,0,'6 Tahun',4,NULL),
-- Casing
('CAS02','Casing','Lian Li','Lian Li Lancool 216 Mesh ATX','Airflow tinggi, muat motherboard ATX, 2 kipas 160mm',1750000,NULL,NULL,20,85,85,0,'ATX',0,'1 Tahun',6,NULL),
('CAS03','Casing','Standar','Casing Office mATX Standar','Micro-ATX, ringkas, untuk kantor/kuliah',450000,NULL,NULL,10,35,35,0,'mATX',0,'1 Tahun',5,NULL);

-- Label peran untuk komponen baru
UPDATE komponen SET label_peran = CASE kategori
    WHEN 'CPU' THEN 'Otak Komputasi' WHEN 'Motherboard' THEN 'Soket & Konektivitas'
    WHEN 'GPU' THEN 'Akselerasi Visual & AI' WHEN 'RAM' THEN 'Memori Multitasking'
    WHEN 'Storage' THEN 'Penyimpanan Cepat' WHEN 'PSU' THEN 'Suplai Daya Stabil'
    WHEN 'Cooler' THEN 'Pendingin Prosesor' ELSE 'Sirkulasi Udara' END
WHERE label_peran IS NULL;
