# 📱 Panduan Setup Telegram Bot — POMS Report

> **Plug-and-play**: seluruh kode (bot interaktif + notifikasi event + webhook +
> queue) sudah siap. Anda hanya perlu **mengisi token bot** lalu menjalankan
> beberapa perintah.

---

## 1. Buat Bot (sekali saja)

1. Buka Telegram, cari **@BotFather**.
2. Kirim `/newbot` → ikuti instruksi (nama & username bot, mis. `poms_report_bot`).
3. BotFather memberi **token** berformat `1234567890:AA...xyz`. Salin token itu.

## 2. Isi Token — dua cara

### Cara A — Environment Editor (disarankan, tanpa sentuh file)

Halaman ini mengubah `.env` lewat UI dan otomatis menjalankan `config:clear`
setelah menyimpan. Hanya **role `developer`** yang dapat mengaksesnya.

1. Login sebagai **developer** → sidebar **Sistem → Environment & Integrasi**
   (URL: `/settings/environment`).
2. Pada grup **Telegram & Bot**, isi:
   - `TELEGRAM_BOT_TOKEN` → token dari BotFather (field bertipe password,
     ditampilkan tersamar; **biarkan kosong bila tidak ingin mengubah** nilai
     yang sudah ada).
   - `TELEGRAM_BOT_USERNAME` → username bot **tanpa `@`** (mis. `poms_report_bot`).
   - (Opsional) `TELEGRAM_NOTIF_ENABLED=true`, `TELEGRAM_RECAP_ENABLED=true`,
     `TELEGRAM_RECAP_CHAT_IDS=<chat_id dipisah koma>`.
3. Klik **Simpan**. Aplikasi menyimpan ke `.env` **tanpa menghapus baris/komentar
   lain** dan menjalankan `config:clear` otomatis.
4. **Uji Koneksi Bot** — tombol ini memanggil Telegram `getMe` dan menampilkan
   `✅ Bot aktif: @username`. Bila gagal, periksa kembali token & koneksi
   internet (pesan `❌ Bot tidak dapat dihubungi`).
5. Kartu **Status Webhook** menampilkan `getWebhookInfo` bot (URL webhook aktif
   atau kosong = mode polling).

> Key rahasia (`TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, `HQ_API_TOKEN`)
> tidak akan terhapus bila dibiarkan kosong — aman untuk menyimpan perubahan
> key lain tanpa menimpa token.

### Cara B — Edit `.env` manual

```env
TELEGRAM_BOT_TOKEN=1234567890:AA...xyz   # token dari BotFather
TELEGRAM_BOT_USERNAME=poms_report_bot    # username bot tanpa @

# Opsional (mode produksi, lihat §5):
# TELEGRAM_WEBHOOK_URL=https://pabrik.example.com/api/telegram/webhook
# TELEGRAM_WEBHOOK_SECRET=<secret acak>
# Saklar global notifikasi (default true):
TELEGRAM_NOTIF_ENABLED=true
```

Lalu jalankan:

```bash
php artisan config:clear
```

> Token **tidak pernah di-hardcode** di kode — semuanya lewat `.env`, jadi
> ganti token cukup edit file (atau lewat Environment Editor) dan `config:clear`.

## 3. Jalankan Komponen yang Dibutuhkan

| Komponen | Perintah | Kapan |
|---|---|---|
| Long polling bot | `php artisan telegram:poll` | Dev/UAT (terminal terpisah, biarkan jalan) |
| Queue worker notifikasi | `php artisan queue:work --queue=telegram` | Selalu (dev & prod) |
| Webhook (produksi) | `php artisan telegram:webhook --set` | Prod dengan HTTPS publik |

**Cek status webhook kapan pun:** `php artisan telegram:webhook` (tanpa flag)
— atau lihat kartu Status Webhook di **Environment & Integrasi**.
**Hapus webhook (kembali ke polling):** `php artisan telegram:webhook --remove`.

> ⚠️ Polling dan webhook **tidak boleh jalan bersamaan** (Telegram menolak
> dengan konflik 409). Pilih salah satu.

## 4. Hubungkan Akun User ke Bot

1. User buka bot di Telegram → kirim **`/start`**.
2. Bot menampilkan tombol **"📱 Hubungkan Nomor Saya"** → user tinggal tap
   (share contact). Nomor HP dibandingkan dengan yang terdaftar di sistem.
3. Setelah cocok, akun Telegram **tersimpan otomatis** ke kolom
   `users.telegram_user_id` (terlihat di halaman Profil → status "Terhubung")
   dan bot menyambut dengan menu utama (📊 Ringkasan, 🚩 Flagged, 📨 Rekap Harian,
   ℹ️ Status).

Atau uji pengiriman manual ke chat tertentu:

```bash
php artisan telegram:test-notify <chat_id>
```

## 5. Mode Produksi (Webhook HTTPS)

```bash
# 1. Generate secret acak:
php -r "echo bin2hex(random_bytes(24));"

# 2. Isi di .env (atau lewat Environment Editor):
#    TELEGRAM_WEBHOOK_URL=https://<domain-publik>/api/telegram/webhook
#    TELEGRAM_WEBHOOK_SECRET=<secret-dari-langkah-1>

# 3. Daftarkan ke Telegram:
php artisan telegram:webhook --set
```

Endpoint webhook (`POST /api/telegram/webhook`) **memvalidasi header
`X-Telegram-Bot-Api-Secret-Token`** dengan `hash_equals` — request asing
ditolak 403, Telegram selalu menerima 200.

## 6. Fitur Bot yang Sudah Siap

| Perintah / Tombol | Fungsi |
|---|---|
| `/start`, `/menu` | Menu utama (keyboard tombol: 📊 Ringkasan, 🚩 Flagged, 📨 Rekap Harian, ⚙️ Status) |
| `/ringkasan` | Ringkasan hari ini per stasiun (scoped ke departemen user) |
| `/flagged` | Daftar record flagged terbaru |
| `/rekap` | Rekap harian on-demand (asisten/askep/manager/hq_admin/developer) |
| `/status` | Status akun + stasiun yang diizinkan |
| `/tiket` | Daftar tiket maintenance aktif (juga tombol 🛠️ Tiket Maintenance) |
| `/tiket KODE prioritas Judul [\| Deskripsi]` | Laporkan kerusakan → buat tiket (operator ke atas) |
| `/lapor` | Alias `/tiket` untuk melaporkan kerusakan |
| `/tiket_update ID status [catatan]` | Ubah status tiket (asisten ke atas) |
| `/bantuan` | Bantuan |
| `/notif on` / `/notif off` | Saklar notifikasi (sama dengan toggle di halaman Profil) |

**Notifikasi event otomatis** (di-queue, tidak pernah menggagalkan alur utama):
- Record **flagged** masuk → semua asisten departemen pemilik stasiun yang
  terhubung & opt-in.
- Record **diverifikasi** → operator pengirim data (bukan self-verify).
- **Tiket maintenance** dibuat → asisten/askep/manager departemen *maintenance*
  yang terhubung; saat status tiket berubah → pelapor tiket.

Preferensi per-user bisa diatur di **Profil → Notifikasi Telegram** (web) atau
`/notif` (bot) — keduanya menulis kolom yang sama.

## 7. Checklist UAT Live (setelah token diisi)

**A. Koneksi & identitas bot**
- [ ] Environment Editor menampilkan token tersamar (bukan kosong) → Simpan
      tidak mengubah token.
- [ ] Klik **Uji Koneksi Bot** → muncul `✅ Bot aktif: @<username>`.
- [ ] Kartu Status Webhook menampilkan kondisi sebenarnya (kosong = polling).

**B. Bot interaktif**
- [ ] `php artisan telegram:poll` jalan, kirim `/start` → bot balas menu.
- [ ] Hubungkan nomor HP → status di halaman Profil berubah "Terhubung".
- [ ] Kirim `/rekap` → bot membalas rekap harian (operator mendapat 🔒 Akses Ditolak).
- [ ] Kirim `/bantuan` → daftar perintah tampil (termasuk `/rekap` serta `/tiket`/`/tiket_update`).
- [ ] Kirim `/tiket PRESS-01 tinggi Kebocoran hidrolik` → bot mengonfirmasi tiket dibuat.
- [ ] Kirim `/tiket` (atau tap tombol 🛠️) → daftar tiket aktif tampil.
- [ ] Asisten kirim `/tiket_update <id> selesai <catatan>` → status tiket berubah.

**C. Notifikasi event (queue worker jalan)**
- [ ] Kirim data via bot yang melanggar rule → record flagged → asisten
      departemen menerima notif.
- [ ] Verifikasi data via web → operator pengirim menerima notif.
- [ ] Buat tiket maintenance → departemen maintenance menerima notif;
      ubah status tiket → pelapor menerima notif.
- [ ] `/notif off` lalu buat record flagged → **tidak** ada notif masuk.
- [ ] Toggle di halaman Profil → `/notif` di bot menunjukkan status sama.
- [ ] `php artisan telegram:test-notify <chat_id>` terkirim.

**D. Produksi (bila memakai webhook)**
- [ ] `telegram:webhook --set` → kartu Status Webhook menampilkan URL → kirim
      `/status` → bot balas.

## 8. Operasional Tambahan

### Target KPI — Import / Export CSV

Halaman **Sistem → Target KPI** (role `manager`/`developer`, URL
`/settings/kpi-targets`) mengatur target per stasiun **dan target plant-wide**.

- **Export CSV** (`GET /settings/kpi-targets/export`): mengunduh seluruh target
  **efektif** (default + override) satu pabrik. Kolom:
  `station,parameter,direction,min_value,max_value,unit,label,source`
  (`source` = `default`/`override`, hanya untuk informasi).
- **Impor CSV** (`POST /settings/kpi-targets/import`): menimpa target per baris
  `station,parameter`. Kolom `station` & `parameter` wajib; baris dengan
  stasiun/parameter tak dikenal **dilewati**. Kolom `source` diabaikan.
- Gunakan pola: **Export** pabrik sumber → edit di spreadsheet → **Impor** ke
  pabrik tujuan untuk menyalin konfigurasi target antar pabrik.
- **Target plant-wide** diedit pada section **"KPI Plant-Wide (Command Center)"**
  (baris `station = _plant`, parameter `ffa`, `losses_fiber`, `efficiency`).
  Nilai ini dipakai di Command Center.

### Tiket Maintenance (B3)

Menu **Tiket Maintenance** (`/maintenance/tickets`):

1. **Laporkan Kerusakan** — isi kode mesin, judul, deskripsi, prioritas →
   tiket dibuat berstatus **open** dan notifikasi dikirim ke departemen
   maintenance.
2. **Kelola status** — `open → dikerjakan → selesai` (role asisten ke atas),
   sekaligus menugaskan teknisi & menulis catatan penyelesaian. Saat **selesai**,
   `resolved_at` diisi dan pelapor diberi notifikasi.
3. **Riwayat per mesin** — isi filter *Kode Mesin* untuk melihat seluruh tiket
   mesin tersebut.

**Lewat bot Telegram** (tanpa buka dashboard):
- `/tiket` atau tombol 🛠️ Tiket Maintenance → daftar tiket aktif.
- `/tiket KODE prioritas Judul | Deskripsi` → buat tiket (operator ke atas).
- `/tiket_update ID status catatan` → ubah status (asisten ke atas).

**Arsip PDF per mesin** — tombol **Unduh PDF** di halaman Tiket Maintenance
(mengikuti filter status/mesin yang aktif) atau langsung ke
`/export/maintenance-tickets?kode_mesin=PRESS-01`. Laporan memuat ringkasan per
mesin + daftar tiket (masalah, prioritas, status, pelapor, teknisi, waktu).
Butuh gate `export-data` (askep ke atas).

## 9. Troubleshooting

| Gejala | Penyebab & Solusi |
|---|---|
| Bot diam total | Token salah / `config:cache` memegang nilai lama → simpan ulang lewat Environment Editor (auto `config:clear`) atau jalankan `config:clear`; cek `telegram:poll` |
| Uji Koneksi Bot gagal | Token invalid/koneksi internet → minta ulang ke BotFather (`/revoke`), tempel ulang di Environment Editor |
| HTTP 401 di log | Token invalid → minta ulang ke BotFather (`/revoke`) |
| HTTP 409 conflict | Polling & webhook bersamaan → matikan salah satu |
| Notif tidak terkirim | Queue worker belum jalan → `php artisan queue:work --queue=telegram`; cek `failed_jobs` |
| Notif tidak ke user | User belum `/start` (belum terhubung) atau opt-out (`/notif`, kolom `users.telegram_notif_*`) |
| Reset password tidak sampai | Sama seperti di atas — tautan reset dikirim via Telegram |
| Impor CSV "0 baris" | Header/kolom salah atau `station`/`parameter` tidak dikenal → pakai hasil **Export CSV** sebagai template |
