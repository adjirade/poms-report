# Deployment Produksi — POMS Report

Panduan lengkap menjalankan POMS Report di server produksi pabrik (on-premise)
dengan supervisor untuk tiga proses latar belakang.

---

## 1. Prasyarat Server

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | 8.3+ | Ekstensi: `mbstring`, `pdo_pgsql`, `pgsql`, `redis`, `curl`, `openssl`, `zip`, `gd`, `fileinfo` |
| PostgreSQL | 16 | Database `poms_report` |
| Redis | Stack | Queue `telegram` + cache (offset polling) |
| Supervisor | 4.x | Manajer proses daemon |
| Node.js | 18+ | Hanya saat build asset (`npm run build`) |
| Composer | 2.x | Instalasi dependensi |

## 2. Instalasi Supervisor

```bash
# 1. Siapkan direktori log
sudo mkdir -p /var/log/poms-report
sudo chown www-data:www-data /var/log/poms-report

# 2. Sesuaikan path project di file conf (cari /var/www/poms-report), lalu:
sudo cp deploy/supervisor/poms-report.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start poms-report:*
```

**Verifikasi:**

```bash
sudo supervisorctl status
# poms-queue-worker:poms-queue-worker_00   RUNNING
# poms-queue-worker:poms-queue-worker_01   RUNNING
# poms-telegram-poll                       RUNNING
# poms-scheduler                           RUNNING
```

## 3. Tiga Proses yang Dikelola

| Program | Fungsi | Kapan aktif |
|---|---|---|
| `poms-queue-worker` (x2) | Memproses pesan Telegram dari queue `telegram` + job HQ sync dari queue `default` | Kontinu |
| `poms-telegram-poll` | Long polling Bot API; offset persisten di cache → restart aman, tanpa pesan dobel | Kontinu |
| `poms-scheduler` | Menjalankan `hq:sync` **per jam** (PRD 2.2) + task terjadwal lain | Kontinu (task per jam) |

> **Penting:** setelah mengubah kode, jalankan:
> ```bash
> cd /var/www/poms-report
> php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
> sudo supervisorctl restart poms-report:*
> ```
> `queue:work` **tidak** membaca perubahan kode otomatis — wajib restart.

## 4. Sinkronisasi HQ (PRD 2.2)

Aktifkan di `.env` sisi pabrik:

```env
HQ_SYNC_ENABLED=true
HQ_API_URL=https://<hq-host>/api/hq
HQ_API_TOKEN=<token-kuat-acak>        # sama dengan sisi HQ
HQ_SYNC_VERIFIED_ONLY=true
```

Kontrol manual:

```bash
php artisan hq:sync --dry-run    # lihat jumlah record pending per stasiun
php artisan hq:sync              # push sekarang (tanpa menunggu scheduler)
php artisan hq:sync --station=timbang
```

Status sinkronisasi tersimpan di tabel `sync_logs` (status: running /
success / failed / partial + jumlah record). Amati lewat tinker:

```php
DB::table('sync_logs')->latest('id')->limit(10)->get();
```

## 5. Logrotate

```bash
sudo cp deploy/logrotate/poms-report /etc/logrotate.d/poms-report
```

Rotasi harian, simpan 14 hari, kompresi, untuk log supervisor
(`/var/log/poms-report`) dan log Laravel (`storage/logs`).

## 6. Checklist Go-Live

- [ ] `php artisan migrate --force` (semua migrasi, termasuk tabel HQ sync)
- [ ] `php artisan db:seed --force` (validation rules + user pertama)
- [ ] Ganti password user developer dari seeder
- [ ] `npm run build` (asset lokal: Tailwind, Chart.js, Alpine, Font Awesome)
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] Supervisor: semua program RUNNING
- [ ] Tes bot: kirim `/lab 3.5 4.2 0.8` dari akun operator terdaftar
- [ ] Tes HQ sync: `php artisan hq:sync --dry-run` lalu satu push manual
- [ ] Pasang logrotate config
- [ ] Monitoring: pantau `/var/log/poms-report/*.log` dan tabel `sync_logs`
- [ ] Backup: `pg_dump` harian + snapshot `.env` di penyimpanan aman

## 7. Update Aplikasi (Rolling Update)

```bash
cd /var/www/poms-report
sudo -u www-data git pull
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci && sudo -u www-data npm run build
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart poms-report:*
```

## 8. Troubleshooting Cepat

| Gejala | Periksa |
|---|---|
| Pesan Telegram tidak diproses | `supervisorctl status` → worker & poll RUNNING? `php artisan queue:failed`? |
| Pesan diproses dobel | Pastikan hanya **satu** instance `telegram:poll` (`numprocs=1`) — dua poller berbagi offset menyebabkan konflik |
| HQ sync selalu failed | Cek `HQ_API_URL` (harus berakhir `/api/hq`), token di kedua sisi, firewall egress, tabel `sync_logs`.message |
| Worker mati berkala (memory) | Sudah dibatasi `--memory=256 --max-time=3600` + autorestart; kalau tetap, cek kebocoran di job |
| Jam data tidak cocok | Timezone: pastikan `APP_TIMEZONE=Asia/Jakarta` dan jam server tersinkronisasi (NTP) |
