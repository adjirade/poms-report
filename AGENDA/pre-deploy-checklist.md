# 🚀 Checklist Pra-Deploy POMS

> Dibuat otomatis setelah sesi 5 Okt 2026. Centang tiap item saat deploy.
> Status saat ini: **CI hijau — 166 test passed (679 assertions), audit UI 0 pelanggaran keras di 22 halaman.**

## 1. Environment & Konfigurasi (.env produksi)

- [ ] `APP_ENV=production` (jangan `local`)
- [ ] `APP_DEBUG=false` — jangan pernah true di produksi
- [ ] `APP_URL=https://<domain-pabrik>` — **wajib domain publik**: dipakai
      link "Buka dashboard web" di pesan bot `/rekap_mingguan` dan link reset password.
      Selama masih `http://localhost:8000`, link di bot mengarah ke localhost (tidak bisa dibuka).
- [ ] `APP_KEY` sudah tergenerate (`php artisan key:generate`) — jangan pakai key dev
- [ ] `APP_TIMEZONE=Asia/Jakarta` (semua agregasi rekap bergantung ini)
- [ ] `APP_FORCE_HTTPS=true` (jika di belakang reverse proxy TLS) + set `TRUSTED_PROXIES`/`APP_TRUSTED_PROXIES`
- [ ] `COMPANY_NAME` + `COMPANY_ADDRESS` — identitas kop semua PDF
- [ ] `PLANT_ID` / `PLANT_NAME` sesuai pabrik

## 2. Database

- [ ] **SQLite dev → ganti ke PostgreSQL/MySQL** untuk produksi (`DB_CONNECTION=pgsql`,
      sesuai `.env.example`). SQLite hanya untuk dev/testing.
- [ ] `php artisan migrate --force` di server produksi
- [ ] `php artisan db:seed --force` (FirstUserSeeder: 1 user developer awal;
      set `ADMIN_INITIAL_PASSWORD` agar password tidak acak & tidak tercetak ke log deploy)
- [ ] **Jangan** aktifkan `POMS_SEED_DEMO=true` di produksi (data demo palsu)
- [ ] Siapkan backup rutin (cron dump DB + `storage/` — PDF rekap tersimpan di `storage/app/recaps/`)

## 3. Telegram (bot)

- [ ] Pilih mode: **webhook (disarankan produksi)** atau polling. Tidak boleh keduanya.
- [ ] Webhook: domain HTTPS publik + `TELEGRAM_WEBHOOK_SECRET` (acak 48 hex) lalu
      `php artisan telegram:webhook --set`
- [ ] Jika polling: `php artisan telegram:poll` dijaga supervisor (lihat §5)
- [ ] `TELEGRAM_BOT_TOKEN` + `TELEGRAM_BOT_USERNAME` terisi
- [ ] `TELEGRAM_NOTIF_ENABLED=true`, `TELEGRAM_RECAP_ENABLED=true`,
      `TELEGRAM_RECAP_CHAT_IDS=<chat arsip,opsional>`, `TELEGRAM_WEEKLY_RECAP_ENABLED=true`
- [ ] Jadwal rekap: harian 17:30 WIB, mingguan Senin 07:00 WIB — butuh scheduler hidup (§5)

## 4. Queue & Scheduler (krusial — fitur bot tidak jalan tanpa ini)

- [ ] **Queue worker**: `php artisan queue:work --queue=telegram` dijaga supervisor/systemd
      (queue connection produksi = redis — sesuai .env.example `QUEUE_CONNECTION=redis`;
      dengan `sync` pesan bot terkirim sinkron tapi lambat & tanpa retry)
- [ ] **Scheduler**: `php artisan schedule:work` (atau cron `* * * * * php artisan schedule:run`)
      — tanpa ini rekap harian/mingguan + sinkron HQ tidak pernah jalan
- [ ] `REDIS_HOST/PORT/PASSWORD` terisi bila pakai redis untuk cache/session/queue
- [ ] Deploy ulang = restart worker (`php artisan queue:restart` setelah release)

## 5. Security

- [ ] Semua user audit sudah dihapus (✅ sudah: 628999000900/628999000901 dihapus —
      **jangan commit password audit ke repo**)
- [ ] Password seed produksi kuat & unik; wajib ganti setelah login pertama
- [ ] `CORS_ALLOWED_ORIGINS` dibatasi ke domain HQ (bukan `*`)
- [ ] `HQ_API_TOKEN` di-set (sinkron HQ) — sama dengan sisi HQ
- [ ] HTTPS aktif (Let's Encrypt); cookie session aman
- [ ] `storage/` dan `.env` tidak bisa diakses publik dari web (root server = `public/`)
- [ ] Review role/gate: hanya developer yang bisa `/settings/environment` & manage-users

## 6. Cache & Optimasi Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
# atau satu langkah:
php artisan optimize
```

- [ ] `npm run build` (asset Vite sudah ter-build ke `public/build`)
- [ ] `composer install --no-dev --optimize-autoloader` (tanpa paket dev)

## 7. Verifikasi pasca-deploy

- [ ] Login web OK per role (operator → form input; manager → dashboard)
- [ ] Halaman `/analytics/weekly-recap` & tombol **Unduh PDF** terunduh
- [ ] `/export/command-center`, `/export/weekly-recap` OK
- [ ] Bot: `/start` → penautan akun → `/rekap_mingguan` (link web muncul untuk askep+)
- [ ] `/pdf_mingguan` mengirim PDF valid
- [ ] Tunggu rekap harian 17:30 WIB terkirim otomatis (bukti scheduler + queue hidup)
- [ ] `php artisan about` — tidak ada error konfigurasi
- [ ] Log: `storage/logs/laravel.log` bersih setelah smoke test

## 8. Yang TIDAK boleh dilakukan

- ❌ Jangan jalankan `telegram:poll` dan webhook bersamaan (pesan dobel/hilang)
- ❌ Jangan commit `.env` / token bot ke repo (sudah di .gitignore)
- ❌ Jangan `APP_DEBUG=true` di produksi (bocor stack trace & env)
- ❌ Jangan pakai `POMS_SEED_DEMO=true` (data produksi tercampur demo)
- ❌ Jangan lupa `queue:restart` setelah tiap deploy (worker memegang kode lama)
