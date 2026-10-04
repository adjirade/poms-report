# 📱 Panduan Setup Telegram Bot — POMS Report

> **Plug-and-play**: seluruh kode (bot interaktif + notifikasi event + webhook +
> queue) sudah siap. Anda hanya perlu **mengisi token bot** lalu menjalankan
> beberapa perintah.

---

## 1. Buat Bot (sekali saja)

1. Buka Telegram, cari **@BotFather**.
2. Kirim `/newbot` → ikuti instruksi (nama & username bot, mis. `poms_report_bot`).
3. BotFather memberi **token** berformat `1234567890:AA...xyz`. Salin token itu.

## 2. Isi `.env` (kunci plug-and-play)

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
> ganti token cukup edit file dan `config:clear`.

## 3. Jalankan Komponen yang Dibutuhkan

| Komponen | Perintah | Kapan |
|---|---|---|
| Long polling bot | `php artisan telegram:poll` | Dev/UAT (terminal terpisah, biarkan jalan) |
| Queue worker notifikasi | `php artisan queue:work --queue=telegram` | Selalu (dev & prod) |
| Webhook (produksi) | `php artisan telegram:webhook --set` | Prod dengan HTTPS publik |

**Cek status webhook kapan pun:** `php artisan telegram:webhook` (tanpa flag).
**Hapus webhook (kembali ke polling):** `php artisan telegram:webhook --remove`.

> ⚠️ Polling dan webhook **tidak boleh jalan bersamaan** (Telegram menolak
> dengan konflik 409). Pilih salah satu.

## 4. Hubungkan Akun User ke Bot

1. User buka bot di Telegram → kirim **`/start`**.
2. Bot menampilkan tombol **"📱 Hubungkan Nomor Saya"** → user tinggal tap
   (share contact). Nomor HP dibandingkan dengan yang terdaftar di sistem.
3. Setelah cocok, akun Telegram **tersimpan otomatis** ke kolom
   `users.telegram_user_id` (terlihat di halaman Profil → status "Terhubung")
   dan bot menyambut dengan menu utama (📊 Ringkasan, 🚩 Flagged, ℹ️ Status).

Atau uji pengiriman manual ke chat tertentu:

```bash
php artisan telegram:test-notify <chat_id>
```

## 5. Mode Produksi (Webhook HTTPS)

```bash
# 1. Generate secret acak:
php -r "echo bin2hex(random_bytes(24));"

# 2. Isi di .env:
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
| `/start`, `/menu` | Menu utama (keyboard tombol: 📊 Ringkasan, 🚩 Flagged, ⚙️ Status) |
| `/ringkasan` | Ringkasan hari ini per stasiun (scoped ke departemen user) |
| `/flagged` | Daftar record flagged terbaru |
| `/status` | Status akun + stasiun yang diizinkan |
| `/bantuan` | Bantuan |
| `/notif on` / `/notif off` | Saklar notifikasi (sama dengan toggle di halaman Profil) |

**Notifikasi event otomatis** (di-queue, tidak pernah menggagalkan alur utama):
- Record **flagged** masuk → semua asisten departemen pemilik stasiun yang
  terhubung & opt-in.
- Record **diverifikasi** → operator pengirim data (bukan self-verify).

Preferensi per-user bisa diatur di **Profil → Notifikasi Telegram** (web) atau
`/notif` (bot) — keduanya menulis kolom yang sama.

## 7. Checklist UAT (setelah token diisi)

- [ ] `telegram:poll` jalan, kirim `/start` → bot balas menu.
- [ ] Hubungkan nomor HP → status Profil berubah "Terhubung".
- [ ] Kirim data via bot yang melanggar rule → record flagged → asisten
      departemen menerima notif (queue worker jalan).
- [ ] Verifikasi data via web → operator pengirim menerima notif.
- [ ] `/notif off` lalu buat record flagged → **tidak** ada notif masuk.
- [ ] Toggle di halaman Profil → `/notif` di bot menunjukkan status sama.
- [ ] `telegram:test-notify <chat_id>` terkirim.
- [ ] (Produksi) `telegram:webhook --set` → kirim `/status` → bot balas.

## 8. Troubleshooting

| Gejala | Penyebab & Solusi |
|---|---|
| Bot diam total | Token salah / `config:cache` memegang nilai lama → `config:clear`, cek `telegram:poll` |
| HTTP 401 di log | Token invalid → minta ulang ke BotFather (`/revoke`) |
| HTTP 409 conflict | Polling & webhook bersamaan → matikan salah satu |
| Notif tidak terkirim | Queue worker belum jalan → `php artisan queue:work --queue=telegram`; cek `failed_jobs` |
| Notif tidak ke user | User belum `/start` (belum terhubung) atau opt-out (`/notif`, kolom `users.telegram_notif_*`) |
| Reset password tidak sampai | Sama seperti di atas — tautan reset dikirim via Telegram |
