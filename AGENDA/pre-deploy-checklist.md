# 🚀 Checklist Pra-Deploy POMS

> Dibuat otomatis setelah sesi 5 Okt 2026. **Update 5 Okt sore: §1–§6 selesai dijalankan
> di laptop produksi (PostgreSQL 18 lokal + 4 layanan Windows NSSM + backup otomatis).**
> Status: **166 test passed (679 assertions), audit UI 0 pelanggaran keras di 22 halaman,
> smoke bot via queue produksi OK (job DONE, 0 gagal).**

## 1. Environment & Konfigurasi (.env produksi) — ✅ SELESAI

- [x] `APP_ENV=production` · `APP_DEBUG=false`
- [ ] `APP_URL=https://<domain-pabrik>` — **SATU-SATUNYA item terbuka**: sekarang
      `http://localhost:8000`. Link web di bot & reset password berguna penuh di LAN
      (`http://<IP-laptop>:8000`); untuk akses luar kantor perlu domain publik + HTTPS.
- [x] `APP_KEY` dev tetap dipakai (data Telegram-link milik key ini — reset = semua user
      harus link ulang). Untuk server BARU: generate key baru lalu minta user re-link.
- [x] `APP_TIMEZONE=Asia/Jakarta`
- [x] `COMPANY_NAME` + `COMPANY_ADDRESS` (kop PDF)

## 2. Database — ✅ SELESAI (PostgreSQL 18 lokal)

- [x] PostgreSQL 18 (service `postgresql-x64-18`) + ekstensi PHP `pdo_pgsql/pgsql` aktif
- [x] DB `poms_report` + user khusus `poms` (password kuat, di `.env`, bukan superuser)
- [x] `php artisan migrate --force` → 20 tabel
- [x] **Data dipindah dari SQLite**: 28 user, 1.136 log stasiun, 93 validation rules;
      sequence id diperbaiki; seeder rules idempotent
- [x] `POMS_SEED_DEMO=false`; file SQLite lama tetap ada sebagai cadangan (`database/database.sqlite`)
- [x] Backup otomatis: `poms:backup` (pg_dump + arsip rekap, retensi 14 hari) — jadwal
      harian 01:00 WIB via poms-schedule; smoke test ✓ (414 KB dump)

## 3. Telegram (bot) — ✅ SELESAI (mode polling)

- [x] Mode **polling** via layanan Windows `poms-poll` (log: "Connected to bot: @POMS_1_bot")
      — webhook TIDAK dipakai (jangan aktifkan keduanya)
- [ ] Webhook HTTPS hanya bila nanti pindah ke server publik:
      `TELEGRAM_WEBHOOK_SECRET` + `php artisan telegram:webhook --set` (matikan poms-poll dulu)
- [x] Token/username terisi; notif + rekap harian + rekap mingguan enabled

## 4. Queue & Scheduler — ✅ SELESAI (4 layanan Windows via NSSM)

Layanan auto-start saat boot, auto-restart bila crash, log rotasi di `storage/logs/service-*.log`:

| Layanan | Tugas | Log |
|---|---|---|
| `poms-queue` | `queue:work --queue=telegram,default` (driver **database**) | service-queue.log |
| `poms-schedule` | `schedule:work` (rekap 17:30 & Senin 07:00, backup 01:00, HQ sync hourly) | service-schedule.log |
| `poms-poll` | `telegram:poll` bot | service-poll.log |
| `poms-web` | `artisan serve --host=0.0.0.0 --port=8000` (LAN) | service-web.log |

- [x] Smoke queue: dispatch `/rekap_mingguan` → worker DONE, 0 failed
- [x] Fix ditemukan saat smoke: `QUEUE_FAILED_DRIVER=database-uuids` (bukan `database`)
- [x] Junction `D:\poms-app` (tanpa spasi) untuk layanan; installer:
      `scripts/deploy/install-services.bat` (jalankan sebagai admin, butuh `nssm.exe` di folder yang sama)
- [ ] Deploy ulang kode = restart layanan: `nssm restart poms-queue` (atau semua)

## 5. Security — ✅ SELESAI (level lokal)

- [x] User audit dihapus (628999000900/628999000901)
- [x] `CORS_ALLOWED_ORIGINS` dibatasi (bukan `*`)
- [x] DB user non-superuser + password kuat; `.env` tidak ter-commit; backup di-gitignore
- [ ] `HQ_API_TOKEN` di-set saat fitur HQ sync diaktifkan (sekarang HQ_SYNC_ENABLED=false)
- [ ] HTTPS + domain publik: menyusul dengan poin 1 (satu-satunya item terbuka)

## 6. Cache & Optimasi — ✅ SELESAI

- [x] `php artisan optimize` (config+route+view+event cache) — **setiap ubah .env/config:
      `php artisan optimize` ulang**
- [x] `npm run build` (asset ter-build)
- [x] ⚠️ **Sebelum `php artisan test`: jalankan `php artisan config:clear` dulu** — config
      cache produksi akan mengabaikan env testing phpunit.xml (test bisa menulis ke DB produksi).
      Setelah test, `php artisan optimize` lagi.

## 7. Verifikasi pasca-deploy — ✅ SELESAI (smoke lokal)

- [x] Login page 200 (localhost + LAN IP)
- [x] Data PostgreSQL terbaca: weeklySummary 353 record minggu ini
- [x] Bot via queue produksi: `/rekap_mingguan` terkirim (termasuk link web) — job DONE
- [x] Backup manual sukses
- [ ] Nanti 17:30 WIB: rekap harian otomatis terkirim (bukti scheduler penuh)
- [ ] Nanti Senin 07:00 WIB: rekap mingguan otomatis terkirim

## 8. Yang TIDAK boleh dilakukan

- ❌ Jangan jalankan `telegram:poll` manual (nohup) — layanan `poms-poll` yang pegang
- ❌ Jangan commit `.env` / token bot / backup DB ke repo
- ❌ Jangan `php artisan test` tanpa `config:clear` (config cache produksi aktif)
- ❌ Jangan aktifkan webhook sambil poller jalan
- ❌ Jangan lupa `php artisan optimize` + restart `poms-queue` setelah tiap deploy kode

## Catatan Windows

- Jalankan layanan butuh 1× admin via `scripts/deploy/install-services.bat` (UAC).
- Path aplikasi ber-spasi → semua layanan memakai junction `D:\poms-app`.
- Matikan aplikasi sementara: `nssm stop poms-web` dst.; hapus: `nssm remove <nama> confirm`.

## Alat operasional: POMS Service Manager (GUI) — 2026-10-05

`scripts/deploy/poms-manager.py` — 1 file Python (tkinter bawaan, tanpa `pip install`) bertema
emerald POMS untuk mengelola 4 layanan NSSM dari satu jendela:

- **Launcher adaptif**: saat dibuka, layanan yang mati otomatis dinyalakan; PID + status tiap layanan tampil realtime.
- **Watchdog auto-restart**: layanan yang mati tanpa perintah user di-start ulang (konfirmasi 2 siklus, cooldown 30 dtk); user-stop lewat GUI tidak akan di-restart.
- **Web watchdog**: probe `APP_URL` tiap siklus; gagal 3× → bersihkan proses `php -S` zombie (parent mati); gagal 7× → restart `poms-web` (cooldown 120 dtk).
- **Auto-optimize**: perubahan `.env`/`app`/`config`/`routes`/`resources/views`/migrations → debounce 4 dtk → `config:clear` + `optimize` → restart queue+poll+schedule (web ikut bila `.env` berubah). Migrasi/composer hanya diberi peringatan, TIDAK dieksekusi otomatis.
- **Log realtime**: tab Aktivitas/Queue/Scheduler/Poller/Web/Laravel (tail 1,2 dtk, baris ERROR merah, cap 1200 baris).
- Hak akses: kontrol layanan butuh Administrator — aplikasi menawarkan elevasi UAC saat dibuka; tanpa admin jadi mode monitor (aksi per klik tetap bisa via UAC).
- Uji tanpa GUI: `python scripts/deploy/poms-manager.py --selftest` (status layanan, path, web, fingerprint) atau `--smoke`.
- **Launcher**: `scripts/deploy/poms-manager.bat` — double-click → UAC sekali → GUI langsung Administrator via `pythonw.exe` (tanpa console). Deteksi pythonw dari `where python` (skip WindowsApps stub); mode uji: `set POMS_DRYRUN=1`. Shortcut desktop **POMS Manager** (icon shield) sudah dibuat menunjuk ke bat ini.

