# PRODUCT REQUIREMENT DOCUMENT (PRD)
## Sistem Pelaporan Digital Pabrik Kelapa Sawit (POMS-Report)

### 1. INFORMASI DOKUMEN & VERSI
* **Nama Proyek:** Palm Oil Mill Digital Reporting System (POMS-Report)
* **Status Infrastruktur:** On-Premise Server (Lokasi di Pabrik) dengan Sinkronisasi Cloud Kantor Pusat (HQ Server).
* **Versi Dokumen:** 1.0 (Enterprise Production Ready)
* **Target Stack:** Laravel 11 (PHP 8.3+), PostgreSQL 16, Redis Stack, Tailwind CSS, Livewire v3 / Inertia.js (React).

---

### 2. ARSITEKTUR SISTEM & DIAGRAM ALUR DATA

#### 2.1 Alur Pengumpulan Data Lapangan (Data Ingestion Queue Flow)
Sistem menggunakan metode **Long Polling (Opsi B)** yang berjalan secara internal di server pabrik. Keuntungannya adalah server lokal tidak memerlukan IP Publik maupun *tunneling* (Ngrok/Cloudflare), sehingga 100% aman dari pemindaian port (*port scanning*) di internet.

Gunakan kode dengan hati-hati.
+-------------------------------------------------------------------------+
| AREA PABRIK (BLANK SPOT / OFFLINE)                                      |
| Operator mengetik perintah kaku di Telegram:                             |
| Contoh: /sterilizer 02 3.0 130 90                                       |
+-------------------------------------------------------------------------+
│
(Kondisi Ponsel Tidak Ada Sinyal)
▼
+-------------------------------------------------------------------------+
| ANTRIAN LOKAL APLIKASI TELEGRAM (Client-Side Queue)                     |
| Pesan tertahan di HP operator dengan ikon jam dinding                    |
+-------------------------------------------------------------------------+
│
(Operator Berjalan ke Area Bersinyal)
▼
+-------------------------------------------------------------------------+
| SERVER CLOUD TELEGRAM (Official Telegram Bot API)                       |
| Pesan terkirim otomatis dan disimpan sementara di server cloud Telegram  |
+-------------------------------------------------------------------------+
│
(Ditarik secara berkala via Jaringan Internet Pabrik)
▼
+-------------------------------------------------------------------------+
| SERVER LOKAL PABRIK (On-Premise Mill Server)                            |
| 1. Laravel Command Worker (php artisan telegram:poll) setiap 3 detik     |
| 2. Ambil Payload -> Masukkan ke BUFFER ANTRIAN LOKAL (Redis Queue)      |
+-------------------------------------------------------------------------+
│
(Diproses oleh Queue Worker)
▼
+-------------------------------------------------------------------------+
| ENGINE VALIDASI ANTI-FRAUD (Laravel Service Layer)                      |
| Masing-masing parameter dicocokkan dengan tabel 'validation_rules'       |
+-------------------------------------------------------------------------+
╱                                   ╲
(Lolos Validasi Parameter)             (Gagal Validasi Parameter)
╱                                       ╲
▼                                           ▼
+-----------------------------+         +-----------------------------+
| SIMPAN KE DATABASE PABRIK   |         | TOLAK & KEMBALIKAN NOTIFIKASI|
| - Simpan ke PostgreSQL      |         | Kirim pesan error via Bot:  |
| - Double Timestamping       |         | "Data Ditolak! Tekanan      |
| - Set 'is_flagged' jika     |         | 4.5 Bar melebihi batas      |
|   deviasi waktu > 4 Jam     |         | standar max (3.2 Bar)"      |
+-----------------------------+         +-----------------------------+

#### 2.2 Sinkronisasi Hub-and-Spoke (Pabrik ke Cloud Kantor Pusat)
Setiap pabrik memiliki kode unik `plant_id`. Sinkronisasi menggunakan *scheduled jobs* terenkripsi (HTTPS + API Token) untuk mereplikasikan data yang sudah divalidasi ke Cloud Kantor Pusat.

+--------------------------+          +--------------------------+
|  Server Lokal Pabrik A   |          |  Server Lokal Pabrik B   |
|       (plant_id: PKS_01) |          |       (plant_id: PKS_02) |
+--------------------------+          +--------------------------+
│                                     │
(Kirim Data Terverifikasi)            (Kirim Data Terverifikasi)
(Setiap Jam / Akhir Shift)            (Setiap Jam / Akhir Shift)
╲                                     ╱
╲                                   ╱
▼                                 ▼
+-----------------------------------------------+
|    CLOUD HQ SERVER (Kantor Pusat Monitoring)   |
+-----------------------------------------------+
| - Konsolidasi Data Seluruh Anak Perusahaan     |
| - Komparasi Losses & Efisiensi Antar-Pabrik   |
| - Hak Akses Direksi & Tim Audit Internal      |
+-----------------------------------------------+

---

### 3. MATRIKS HAK AKSES USER (ROLE-BASED ACCESS CONTROL)

Hak akses dikunci di dua sisi: kebijakan web (*Laravel Policies/Gates*) dan *routing query* pada basis data berdasarkan `plant_id` dan `department`.

| Jabatan / Role | Akses Telegram Bot | Akses Web Dashboard | Ruang Lingkup Data & Fitur Operasional |
| :--- | :--- | :--- | :--- |
| **Operator** | Hanya Input Data Stasiun | **Ditolak (403)** | Hanya diizinkan memasukkan parameter stasiun tempat ditugaskan. |
| **Asisten** | Input & View Ringkasan Shift | View, Edit Terbatas, Validasi | Terbatas pada stasiun di bawah departemennya (Proses/Teknik/Lab). Wajib memvalidasi data operator sebelum dikunci di akhir *shift*. |
| **Asisten Kepala (Askep)** | View Ringkasan Shift Pabrik | Full Dashboard Akses | Seluruh stasiun di pabrik lokal. Fitur ekspor, cetak laporan produksi, dan *flagged audit trail*. |
| **Manager Pabrik** | View Ringkasan Eksekutif | Full Dashboard & Approval | Otoritas tertinggi pabrik lokal. Sistem persetujuan laporan bulanan, analisis *losses* pabrik. |
| **HQ Admin / Direksi** | **Ditolak** | View Dashboard Global | Akses *read-only* ke seluruh data dari semua pabrik. Tidak memiliki hak mengubah data lokal. |
| **Developer / Admin** | **Ditolak** | Konfigurasi Sistem | Hak penuh membuka halaman *Developer Settings Page* untuk mengubah batas aman angka kritis parameter. |

---

### 4. FUNGSIONALITAS TELEGRAM BOT & VALIDASI PARAMETER SPESIFIK

#### 4.1 Mekanisme Otentikasi dan Validasi Format
Saat server menangkap kiriman string dari operator:
1. Periksa nomor telepon pengirim atau ID Telegram. Jika tidak terdaftar di tabel `users` atau status `inactive`, bot langsung mengirim balasan: *"Nomor Anda belum terdaftar/aktif. Akses ditolak."*
2. String diurai (*parsing*) menggunakan spasi sebagai pembagi elemen perintah. Jika jumlah parameter tidak cocok dengan aturan stasiun, kirim balasan format eror.

#### 4.2 Dokumen & Aturan Angka Kritis Parameter (Default Validasi Seluruh Stasiun)

##### A. Stasiun Penerimaan Buah & Jembatan Timbang (Weightbridge)
* **Sintaks Perintah:** `/timbang [no_spb] [tonase_bruto] [tonase_tarra] [potongan_persen]`
* **Contoh:** `/timbang SPB10293 25300 9500 4.5`
* **Validasi Angka Kritis Default:**
  * `no_spb`: Alfanumerik (A-Z, 0-9).
  * `tonase_bruto`: Minimum `5000` kg, Maksimum `45000` kg.
  * `tonase_tarra`: Minimum `3000` kg, Maksimum `15000` kg.
  * `potongan_persen`: Minimum `1.0`%, Maksimum `10.0`%.

##### B. Stasiun Sortasi (Grading Ramp)
* **Sintaks Perintah:** `/sortasi [no_spb] [buah_mentah_persen] [buah_matang_persen] [jankos_persen] [tangkai_panjang_persen]`
* **Contoh:** `/sortasi SPB10293 2.5 85.0 0.5 1.5`
* **Validasi Angka Kritis Default:**
  * `buah_mentah_persen`: Minimum `0.0`%, Maksimum `15.0`%.
  * `buah_matang_persen`: Minimum `70.0`%, Maksimum `100.0`%.
  * `jankos_persen` (Tandan Kosong): Minimum `0.0`%, Maksimum `5.0`%.
  * `tangkai_panjang_persen`: Minimum `0.0`%, Maksimum `10.0`%.

##### C. Stasiun Sterilizer (Perebusan)
* **Sintaks Perintah:** `/sterilizer [no_rebusan] [tekanan_bar] [suhu_celcius] [durasi_menit]`
* **Contoh:** `/sterilizer 02 3.0 130 90`
* **Validasi Angka Kritis Default:**
  * `no_rebusan`: Numerik integer (1 - 10).
  * `tekanan_bar`: Minimum `1.5` Bar, Maksimum `3.2` Bar.
  * `suhu_celcius`: Minimum `110` °C, Maksimum `145` °C.
  * `durasi_menit`: Minimum `45` Menit, Maksimum `90` Menit.

##### D. Stasiun Pengepresan (Screw Press)
* **Sintaks Perintah:** `/press [no_press] [tekanan_hidrolik] [ampere_motor] [tambah_air_persen]`
* **Contoh:** `/press 04 65 40 7`
* **Validasi Angka Kritis Default:**
  * `no_press`: Numerik integer (1 - 12).
  * `tekanan_hidrolik`: Minimum `60` Kg/cm², Maksimum `75` Kg/cm².
  * `ampere_motor`: Minimum `35` Ampere, Maksimum `45` Ampere.
  * `tambah_air_persen` (Air Dilusi): Minimum `5.0`%, Maksimum `15.0`%.

##### E. Stasiun Klarifikasi (Clarification Tank)
* **Sintaks Perintah:** `/klarifikasi [no_tangki] [suhu_tangki_celcius] [level_minyak_cm] [kadar_air_persen]`
* **Contoh:** `/klarifikasi 01 92 180 0.25`
* **Validasi Angka Kritis Default:**
  * `no_tangki`: Numerik integer (1 - 5).
  * `suhu_tangki_celcius`: Minimum `85` °C, Maksimum `98` °C.
  * `level_minyak_cm`: Minimum `50` cm, Maksimum `300` cm.
  * `kadar_air_persen`: Minimum `0.10`%, Maksimum `0.50`%.

##### F. Stasiun Nut & Kernel (Inti Sawit)
* **Sintaks Perintah:** `/kernel [suhu_silo_celcius] [losses_inti_persen] [kadar_kotoran_persen]`
* **Contoh:** `/kernel 75 1.2 5.5`
* **Validasi Angka Kritis Default:**
  * `suhu_silo_celcius`: Minimum `60` °C, Maksimum `85` °C.
  * `losses_inti_persen`: Minimum `0.5`%, Maksimum `2.5`%.
  * `kadar_kotoran_persen`: Minimum `4.0`%, Maksimum `8.0`%.

##### G. Stasiun Laboratorium (QC Mutu & Losses Utama)
* **Sintaks Perintah:** `/lab [kadar_alb_cpo] [losses_fiber_persen] [losses_jankos_persen]`
* **Contoh:** `/lab 3.5 4.2 0.8`
* **Validasi Angka Kritis Default:**
  * `kadar_alb_cpo` (FFA): Minimum `2.0`%, Maksimum `5.0`%.
  * `losses_fiber_persen`: Minimum `1.0`%, Maksimum `5.0`%.
  * `losses_jankos_persen`: Minimum `0.1`%, Maksimum `1.0`%.

##### H. Stasiun Maintenance (Teknik & Bengkel)
* **Sintaks Perintah:** `/maintenance [kode_mesin] [jam_jalan_hm] [status_kondisi] [keterangan_perbaikan]`
* **Contoh:** `/maintenance GENSET_02 4850 normal Aman_tidak_ada_kendala`
* **Validasi Angka Kritis Default:**
  * `kode_mesin`: String terdaftar di data inventaris mesin.
  * `jam_jalan_hm` (Hour Meter): Numerik angka jam akumulatif berjalan.
  * `status_kondisi`: Hanya boleh berisi string `normal`, `breakdown`, atau `maintenance`.
  * `keterangan_perbaikan`: String teks (gunakan underscore `_` untuk spasi agar aman dari *parsing* inline command).

---

### 5. SISTEM INTEGRITAS DATA & ANTI-FRAUD

1. **Mekanisme Double Timestamping:**
   Setiap data log tabel wajib merekam dua variabel penunjuk waktu:
   * `timestamp_kirim`: Menarik properti waktu dari internal pesan chat Telegram (`message.date`). Parameter ini menjadi indikator waktu asli ketika operator mengirim di lapangan, terlepas dari status koneksi jaringan ponsel.
   * `timestamp_server`: Berisi nilai penunjuk waktu saat database server pabrik melakukan penulisan rekaman (`CURRENT_TIMESTAMP`).
2. **Flagged Engine (Deteksi Manipulasi Jam Kerja):**
   Jika ekspresi logika `timestamp_server` dikurangi `timestamp_kirim` menghasilkan angka selisih **lebih dari 4 jam**, row tersebut secara otomatis dilabeli bendera status `is_flagged = true`. Halaman web dasbor asisten wajib menampilkan baris ini dengan visualisasi latar belakang warna kuning terang serta ikon peringatan untuk kebutuhan penelusuran lebih lanjut (*audit trail*).

---

### 6. KHUSUS DASHBOARD WEB & DOKUMEN CETAK LEGALITAS

#### 6.1 Tampilan Antarmuka (Responsive UI Layout)
* Menggunakan Framework CSS **Tailwind CSS** dengan pendekatan tata letak *grid* adaptif.
* Menu navigasi utama wajib responsif dan dapat disembunyikan (*collapsible sidebar*) untuk kenyamanan pembacaan grafik di gawai berukuran layar kecil.

#### 6.2 Visualisasi Grafik Berjenjang
* **Level Asisten:** Menampilkan satu grafik tren garis (*Line Chart*) per parameter untuk meninjau kestabilan operasi stasiun per jam dalam rentang satu *shift*.
* **Level Askep & Manager:** Menampilkan dasbor agregat pabrik lengkap dengan grafik komparatif volume olah buah harian, diagram batang kerugian produksi (*Losses Bar Chart*), serta rekapitulasi waktu henti mesin (*stoppage time*).
* **Level Kantor Pusat (HQ Cloud):** Halaman monitoring peta anak perusahaan yang menyajikan matriks perbandingan efisiensi dan pencapaian target produksi antar-pabrik kelapa sawit di bawah korporasi pada satu layar terpadu.

#### 6.3 Fitur Cetak & Dokumentasi Fisik
* Sistem menyediakan fungsi cetak bersih dokumen laporan produksi terformat resmi ke kertas ukuran F4/A4 menggunakan konfigurasi CSS `@media print` atau modul ekspor file **PDF/Excel**.
* Bagian kaki dokumen wajib menyertakan kolom pengesahan bertanda tangan sebagai berikut:

+-------------------------------------------------------------------------+
|                                                                         |
|  Dibuat Oleh,             Diperiksa Oleh,             Disetujui Oleh,    |
|                                                                         |
|                                                                         |
|  (..................)     (..................)     (..................) |
|    Operator Shift            Asisten Proses           Manager Pabrik    |
|                                                                         |
+-------------------------------------------------------------------------+

---

### 7. STRUKTUR CETAKAN BASIS DATA (POSTGRESQL DDL)

Berikut rancangan skema relasi tabel SQL lengkap untuk mendukung performa pengolahan data *time-series* pabrik:

```sql
-- 1. TABEL PENGGUNA
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) UNIQUE NOT NULL,
    telegram_user_id VARCHAR(50) UNIQUE NULL,
    role VARCHAR(30) NOT NULL, -- 'operator', 'asisten', 'askep', 'manager', 'hq_admin', 'developer'
    department VARCHAR(30) NULL, -- 'proses', 'maintenance', 'lab'
    plant_id VARCHAR(10) NOT NULL, -- Contoh: 'PKS_01', 'PKS_02'
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. TABEL PENGATURAN AMBANG BATAS VALIDASI PARAMETER
CREATE TABLE validation_rules (
    id SERIAL PRIMARY KEY,
    plant_id VARCHAR(10) NOT NULL,
    station_name VARCHAR(50) NOT NULL,
    parameter_name VARCHAR(50) NOT NULL,
    min_value NUMERIC(7,2) NOT NULL,
    max_value NUMERIC(7,2) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_plant_station_param UNIQUE (plant_id, station_name, parameter_name)
);

-- 3. TABEL DATA LOG STASIUN STERILIZER (CONTOH REPRESENTATIF MODEL)
CREATE TABLE log_sterilizer (
    id BIGSERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE RESTRICT,
    plant_id VARCHAR(10) NOT NULL,
    no_rebusan INT NOT NULL,
    tekanan_bar NUMERIC(4,2) NOT NULL,
    suhu_celcius INT NOT NULL,
    durasi_menit INT NOT NULL,
    timestamp_kirim TIMESTAMP NOT NULL,
    timestamp_server TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_flagged BOOLEAN DEFAULT FALSE,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by INT REFERENCES users(id) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Buat indeks untuk optimasi pencarian grafik laporan harian pabrik
CREATE INDEX idx_sterilizer_plant_date ON log_sterilizer (plant_id, timestamp_kirim);
```