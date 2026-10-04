# POMS — Sesi Berikutnya

> ✅ 2026-10-03: Ketiga item agenda (kontras AA, chart detail, Telegram) selesai.
> Detail: `AGENDA/chart-detail-plan.md`, `AGENDA/telegram-prep.md`.

> ✅ 2026-10-04: Empat item agenda kedua selesai.
> - (1) **Audit a11y halaman baru** — Performa Stasiun + tab stasiun: chip aktif
>   & tab `bg-green-700`/`emerald-700→green-800` (AA ≥4.5), canvas chart
>   `role="img"` + `aria-label` + ringkasan sr-only, tab ARIA lengkap
>   (tablist/tab/tabpanel + aria-selected), heading h1→h2 tanpa lompatan,
>   `scope="col"` + caption tabel, label aksesibel input tanggal.
> - (2) **Toggle notifikasi Telegram di Profil** — master switch + per-event
>   (flagged/verifikasi) via `PUT /profile/telegram-notifications`; sinkron
>   dengan `/notif` di bot.
> - (3) **Visualisasi tambahan per stasiun** — chart volume harian (bar:
>   record masuk vs flagged) + doughnut status verifikasi (verified/pending/
>   flagged dengan total di tengah); agregasi via `StationAnalyticsService::
>   statusBreakdown()` (1 query).
> - (4) **PDF korporat + grafik** — kop surat + nomor dokumen + footer nomor
>   halaman (dompdf inline PHP) + zebra table + blok tanda tangan konsisten di
>   SEMUA PDF; baru: `Laporan Performa Stasiun` dengan **grafik Chart.js
>   ter-embed** (canvas → PNG base64 → POST `/export/station-performance`).
>   Kop pakai `COMPANY_NAME`/`COMPANY_ADDRESS` (config `poms`).
> - (5) **Setup Telegram plug-and-play** — panduan lengkap:
>   `AGENDA/telegram-setup-guide.md`. Tinggal isi `TELEGRAM_BOT_TOKEN` +
>   `TELEGRAM_BOT_USERNAME` lalu `config:clear`.

## Prioritas berikutnya (opsional)
1. **Telegram live UAT** — tunggu token bot dari user; jalankan checklist UAT
   §7 di `AGENDA/telegram-setup-guide.md`.
2. **Chart**: adapter tanggal untuk sumbu waktu asli; pre-aggregation tabel
   harian jika data >90 hari terasa berat.
3. **Telegram lanjutan**: broadcast pengumuman; rate limit per chat untuk grup.
4. **A11y lanjutan**: audit otomatis (Axe/Lighthouse headless) di CI untuk
   menjaga regresi kontras.

## Catatan operasional
- Worker queue Telegram: `php artisan queue:work --queue=telegram` (supervisor).
- Dev/UAT Telegram: `php artisan telegram:poll`; produksi:
  `php artisan telegram:webhook --set` (butuh `TELEGRAM_WEBHOOK_SECRET` +
  HTTPS publik). Jangan jalankan polling & webhook bersamaan.
- Kop PDF: atur `COMPANY_NAME`/`COMPANY_ADDRESS` di `.env`.
