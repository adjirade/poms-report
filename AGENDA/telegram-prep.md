# Telegram — Status & Arah Integrasi

> Diperbarui: 2026-10-03. Keputusan final (user): **notifikasi event + menu interaktif**,
> dikirim ke **asisten departemen dengan opt-out per-user**; polling tetap mode default,
> webhook disiapkan untuk produksi.

## Status sekarang (sudah jalan sebelum sesi ini)
- Bot + token di `.env` (`TELEGRAM_BOT_TOKEN`), config `config/telegram.php`.
- `telegram:poll` — long polling dengan offset persist di cache.
- `ProcessTelegramMessage` — input data 8 stasiun via slash command.
- `TelegramService` — getUpdates/sendMessage/format Markdown.
- Web input (`LogInputController`) memakai `ValidationService` yang sama.

## Yang ditambahkan pada sesi ini

### 1. Notifikasi event (queued, opt-out per user)
| Event | Penerima | Kondisi |
|---|---|---|
| Record **flagged** masuk | Semua asisten aktif departemen pemilik stasiun (proses/maintenance/lab) | punya `telegram_user_id` + preferensi on |
| Record **diverifikasi** | Operator pengirim data | punya `telegram_user_id` + preferensi on (bukan verifikasi sendiri) |

- Migrasi: kolom `telegram_notif_enabled`, `telegram_notif_flagged`, `telegram_notif_verified`
  di tabel `users` (default true — opt-out).
- `app/Services/TelegramNotificationService.php` — routing penerima + format pesan.
- `app/Jobs/SendTelegramNotification.php` — kirim via queue `telegram`, tidak pernah
  menggagalkan alur utama (try/catch + log).
- Hook: `ProcessTelegramMessage` (Telegram), `LogInputController::store` (web),
  `StationController::verify`.
- Saklar global: `TELEGRAM_NOTIF_ENABLED` (.env).
- **Kontrol user**: perintah `/notif on|off` langsung dari bot (tanpa UI web).

### 2. Menu interaktif bot (reply keyboard, tanpa callback_query)
- `/start` — sambutan + bantuan + keyboard menu.
- `/menu` — keyboard: 📊 Ringkasan · 🚩 Flagged · ℹ️ Status Terakhir · ❔ Bantuan.
- `/ringkasan` — rekap input hari ini per stasiun dalam cakupan user
  (operator/asisten = departemennya; role lain = semua).
- `/flagged` — hingga 5 record flagged 7 hari terakhir + status verifikasi.
- `/status` — status record terakhir yang dikirim user.
- `/bantuan` — daftar perintah + contoh format input.
- `telegram:poll` kini meneruskan **semua** pesan teks (sebelumnya hanya `/command`)
  agar tombol keyboard menu bisa diproses.

### 3. Webhook siap produksi (opsional, polling tetap default)
- Route `POST /api/telegram/webhook` — tervalidasi `X-Telegram-Bot-Api-Secret-Token`
  (`TELEGRAM_WEBHOOK_SECRET`), hanya dispatch ke queue (Telegram butuh respons <2s).
- `php artisan telegram:webhook --set | --remove` (status tanpa argumen).
- Keputusan: **polling untuk dev/UAT**, webhook saat deploy produksi (butuh HTTPS publik).

### 4. UAT kecil
```bash
# 1. Pastikan user terdaftar: login sekali via bot, atau set telegram_user_id dari /debug-auth
php artisan telegram:poll --once        # catat chat_id dari log
php artisan telegram:test-notify 123456789   # kirim notifikasi uji

# 2. Uji menu: /start, /menu, /ringkasan, /flagged, /status, /notif off, /notif on
# 3. Uji notifikasi flagged: kirim /timbang dengan tanggal HP dimundurkan > 4 jam
#    -> asisten departemen proses menerima 🚩 notif
# 4. Verifikasi record di dashboard -> operator pengirim menerima ✅ notif
```

## Sisa / lanjutan opsional
- Broadcast (pengumuman massal) — belum diimplement (arah belum diprioritaskan).
- Toggle preferensi di halaman Profil web (saat ini hanya via `/notif`).
- Rate limit per chat jika grup besar; antrean khusus worker supervisor `--queue=telegram`.
