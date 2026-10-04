# POMS — Roadmap Pengembangan Lanjutan

> Rancangan fitur untuk membuat POMS lebih kaya informasi dan lebih banyak
> tools operasional. Status: proposal — pilih prioritas, lalu eksekusi per
> fase.

## A. INFORMASI (analytics & insight)

| # | Fitur | Deskripsi | Kompleksitas |
|---|-------|-----------|--------------|
| A1 | **Command Center Dashboard** | Satu layar KPI untuk manager: tonnage hari ini, FFA, losses fiber/jankos, efisiensi, alert flagged aktif, status tiap stasiun (hijau/kuning/merah berdasar rule) | Sedang |
| A2 | **Rekap otomatis ke Telegram** | Cron kirim rekap harian/mingguan (ringkasan per stasiun + flagged) ke asisten/askep/manager via bot | Kecil-Sedang |
| A3 | **Analisis per shift** | Perbandingan shift 1/2/3 per stasiun: volume, rata-rata parameter, jumlah flag — kolom `timestamp_kirim` sudah ada jam-nya | Kecil |
| A4 | **Statistik penyebab flagged** | Drill-down: rule apa yang paling sering memicu flag, tren bulanan, operator terdampak | Kecil |
| A5 | **Tren + deteksi anomali** | Moving average 7 hari per parameter + penanda hari anomali (deviasi > 2σ) di chart performa stasiun | Sedang |
| A6 | **Target vs Realisasi** | Definisikan target KPI per stasiun (mis. FFA < 5%) → progress bar + badge tercapai/tidak di dashboard & performa stasiun | Sedang |

## B. TOOLS (operasional)

| # | Fitur | Deskripsi | Kompleksitas |
|---|-------|-----------|--------------|
| B1 | **Kalkulator pabrik** | Kalkulator cepat: tonnage neto (bruto-tarra), potongan buah, estimasi rendemen/OER, konversi satuan — berdiri sendiri, aman untuk operator | Kecil |
| B2 | **Approval multi-level** | Alur verifikasi bertingkat operator → asisten → askep dengan catatan per tahap (saat ini single verify) | Besar |
| B3 | **Tiket maintenance** | Laporan kerusakan → tiket (open/dikerjakan/selesai) + notif Telegram ke departemen maintenance + riwayat per mesin | Sedang |
| B4 | **Laporan terjadwal (PDF otomatis)** | Cron generate PDF harian/mingguan → kirim file ke Telegram chat/group (fasilitas arsip) | Sedang |
| B5 | **Dry-run validation rules** | Uji rule baru terhadap data historis sebelum aktif + preview dampak | Kecil |

## Urutan yang disarankan (quick win dulu)

1. **A3 + A4** (analisis shift + statistik flagged) — data sudah ada, satu halaman analytics baru.
2. **A1** (Command Center) — nilai terbesar untuk manager.
3. **B1** (kalkulator) — kecil, langsung terasa.
4. **A2/B4** (laporan otomatis Telegram) — menyatukan dengan infra bot yang sudah siap.
5. Lanjut: A6 → A5 → B3 → B5 → B2.

## Status implementasi

- ✅ A1 Command Center — web (`analytics/command-center`) + PDF (`export.command-center`).
- ✅ A2 Rekap otomatis Telegram + perintah `/rekap` on-demand di bot.
- ✅ A3 Analisis per shift — halaman performa stasiun + PDF.
- ✅ A4 Statistik penyebab flagged.
- ✅ A5 Tren + deteksi anomali — moving average 7 hari (garis putus-putus) + penanda hari anomali (>2σ) di chart Performa Stasiun, ringkasan & tabel anomali di web + PDF.
- ✅ A6 Target vs Realisasi — `App\Support\KpiTargetConfig` + progress bar di Command Center & Performa Stasiun + PDF.
- ✅ B1 Kalkulator pabrik.
- ✅ B4 Laporan terjadwal PDF ke Telegram.
- 🆕 Environment Editor (role developer) — `settings/environment`: ubah token bot/API/username/identitas pabrik lewat UI + uji koneksi bot (`getMe`).
- Berikutnya: B3 (tiket maintenance) → B5 (dry-run validation) → B2 (approval multi-level).
