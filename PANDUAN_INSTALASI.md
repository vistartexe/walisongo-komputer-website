# Walisongo Komputer v2 — Sistem Pakar Rekomendasi Spesifikasi Komputer

## 1. Cara menjalankan (XAMPP / Laragon)
1. Salin folder proyek ke `htdocs` (mis. `C:\xampp\htdocs\walisongo-komputer-website`). Nyalakan **Apache** dan **MySQL**.
2. Buka phpMyAdmin, impor **berurutan**: `db/walisongo-komputer.sql` lalu `db/upgrade_v2.sql`.
3. Isi tabel `gpu_dataset` dari CSV (XAMPP Shell / terminal di folder proyek):
   `php db/impSxort_gpu_dataset.php` (default memakai `dataset/gpu_specs_v7.csv`).
4. Jika MySQL Anda memakai password, ubah `config/database.php` (atau set env `WS_DB_PASS`).
5. Buka `http://localhost/walisongo-komputer-website/`. **Jangan** buka lewat `file://` karena API butuh PHP.

Kebutuhan: PHP 7.1+ (ekstensi `pdo_mysql`), MySQL 5.7+ / MariaDB 10.3+.

## 2. Arsitektur sistem pakar
| Komponen sistem pakar | Implementasi |
|---|---|
| **Basis pengetahuan** | Tabel `beban_kerja` (syarat min. VRAM/RAM/SSD), `aturan_alokasi` (% budget per kategori), `aturan_prioritas` (pengali bobot), `komponen` (fakta: harga, skor, socket, tipe RAM, watt, kapasitas), `aturan_rakitan` (paket pakar toko), `gpu_dataset` (spesifikasi GPU) |
| **Mesin inferensi** | `lib/ExpertSystem.php` — forward chaining: fakta pengguna → bobot kategori (R1–R4) → pilih part berurutan CPU→Motherboard→GPU→RAM→Storage→Cooler→Casing→PSU, tiap pilihan memicu aturan kompatibilitas → verifikasi → koreksi bila melebihi budget |
| **Antarmuka** | `recommendation.html` (input fakta), `result.html` (hasil + jejak penalaran), `catalog.html`, `gpu-dataset.html` |
| **Penjelasan (explanation)** | Alasan per part, daftar cek kompatibilitas, dan "jejak penalaran" di halaman hasil |

Aturan kompatibilitas: socket CPU–motherboard, tipe RAM (DDR4/DDR5), form factor motherboard–casing, kapasitas cooler ≥ TDP CPU, PSU ≥ 1,25× beban (aman ≥ 1,4×), peringatan bottleneck CPU–GPU, dan iGPU bila tanpa VGA.

## 3. Fitur baru / revisi
- **Koneksi database** (PDO) + 7 endpoint di `api/`: `recommend`, `result`, `validate`, `feedback`, `parts`, `gpu`.
- **Form rekomendasi hidup**: ringkasan pilihan real-time, tombol "Pilih Semua", **input budget spesifik** (mengganti rentang), validasi.
- **Halaman hasil dinamis**: rekomendasi utama + 2 alternatif (Hemat & Performa Tinggi) yang bisa "Jadikan Utama", **tukar part per satuan** dengan validasi kompatibilitas ulang di server, paket pakar toko dari `aturan_rakitan`, pesan via WhatsApp, cetak/simpan PDF.
- **Riwayat & tautan berbagi**: setiap hasil disimpan dengan kode 8 karakter (`result.html?k=KODE`).
- **Umpan balik pengguna** (`feedback_rekomendasi`) — bahan evaluasi kepuasan untuk bab pengujian.
- **Katalog dari database** (sebelumnya array statis) — 49 komponen siap pakai.
- **Database GPU** (`gpu-dataset.html`): cari/filter dataset berdasarkan produsen, VRAM, tahun; data dataset ikut tampil pada alasan rekomendasi GPU.
- Perbaikan kecil: path favicon relatif, harga coret/diskon palsu dihapus (tidak ada data diskon), link `Konsultasi WhatsApp` di form kini berfungsi.

## 4. Hal yang perlu Anda ketahui
- **Harga & skor komponen adalah data contoh.** Sesuaikan harga di tabel `komponen` dengan harga toko. Kolom `skor_gaming` / `skor_kreator` (0–100) adalah penilaian relatif buatan peneliti — sebaiknya dijustifikasi dengan benchmark (mis. PassMark/Blender Open Data) di laporan.
- **Dataset GPU berkualitas campur**: 3.056 baris → 2.733 unik; kolom `memType` dan `gpuChip` banyak yang salah (mis. RTX 4060 tercatat chip "TU102", RTX 5090 bermemori "HBM2e") sehingga **tidak diimpor**. `memBusWidth` kosong pada ~94% baris. Dataset dipakai hanya sebagai pengayaan info, bukan penentu keputusan.
- **Belum diuji dengan PHP/MySQL sungguhan** (lingkungan pembuatan tidak memiliki keduanya). Yang sudah diverifikasi: sintaks JS, struktur SQL & jumlah kolom seed, pencocokan 7 GPU ke dataset, dan logika pemilihan lewat port Python (`db/uji_simulasi_engine.py`, 10 skenario: semua lolos aturan kompatibilitas). Mohon uji alur penuh di komputer Anda; jika ada error, lihat log Apache (`error.log`).
- Budget sangat kecil (mis. gaming < Rp 5 juta untuk 8 part) tidak mungkin dipenuhi; sistem tetap menampilkan konfigurasi minimum dengan penanda "melebihi budget".
- Bila hanya memilih sebagian komponen (mode upgrade), sistem tidak mengetahui part lama Anda sehingga tidak bisa memeriksa kompatibilitas dengannya.
- Belum ada panel admin untuk CRUD komponen — saat ini ubah data lewat phpMyAdmin. Ini kandidat pengembangan berikutnya.
