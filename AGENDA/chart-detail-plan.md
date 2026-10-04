# Chart Detail & Performa Stasiun — Status Implementasi

> Diperbarui: 2026-10-03. Keputusan final (user): halaman terpisah **+** tab di halaman stasiun,
> pakai **chartjs-plugin-zoom**, akses mengikuti role (asisten = departemennya, askep+ = semua).

## Yang sudah diimplementasikan

### 1. Halaman "Performa Stasiun" (halaman terpisah)
- Route: `GET /analytics/station-performance` → `analytics.station-performance`
  (middleware `can:view-department-data` — asisten masuk tapi dibatasi stasiun departemennya).
- Kontrol:
  - **Stasiun**: dropdown, opsi difilter per `User::allowedStations()`.
  - **Time range picker**: chip 7 / 30 / 90 hari (default 7), tervalidasi server.
- Kartu **perbandingan hari ini vs kemarin** per parameter (delta %).
- **Chart multi-series**: semua parameter numerik stasiun dalam satu chart,
  warna per series dari `StationChartConfig::COLORS`.
- **Zoom**: drag-select area + Ctrl+wheel + pinch, tombol *Reset zoom*.
- **Tooltip lengkap**: semua series + satuan + jumlah record + 🚩 jumlah flagged per hari.
- **Tabel detail** di bawah chart: seluruh kolom stasiun (no_spb, parameter, dll),
  operator, status (Flagged / Terverifikasi / Menunggu), + pagination.

### 2. Tab "Grafik Performa" di halaman stasiun
- `stations/show` sekarang punya 2 tab (Alpine): **Tabel Data** (Livewire, semula) dan
  **Grafik Performa** (chart 30 hari, lazy-render saat tab dibuka, tombol reset zoom,
  link ke halaman detail lengkap).

### 3. Arsitektur
- `app/Support/StationChartConfig.php` — satu sumber kebenaran: series, label, satuan,
  warna, kolom tabel, rentang yang didukung per stasiun.
- `app/Services/StationAnalyticsService.php` — agregasi DB (AVG harian per stasiun,
  1 query agregat per stasiun, bukan per hari), pengisian hari kosong dengan null,
  perbandingan kemarin, dan paginator tabel detail.
- `resources/js/station-chart.js` — builder chart global `PomsStationChart`
  (render/destroy idempoten, zoom via `chartjs-plugin-zoom` yang didaftarkan ke
  instance Chart.js yang sama dengan `window.Chart`).
- Navigasi: link **Performa Stasiun** ditambahkan di submenu Analytics.

## Keputusan yang sudah final
| Pertanyaan | Keputusan |
|---|---|
| Zoom plugin? | Ya — `chartjs-plugin-zoom` (sudah di package.json + npm install) |
| Range default | 7 hari (opsi 7/30/90, tervalidasi) |
| Siapa akses | `view-department-data`; asisten = stasiun departemen, askep+ = semua |
| Bentuk halaman | Halaman terpisah **dan** tab grafik di halaman stasiun |

## Sisa / lanjutan opsional
- Export PDF/Excel dari tabel detail performa (sudah ada export per stasiun yang bisa dipakai).
- Jika data >90 hari terasa berat, tambahkan pre-aggregation tabel harian.
- Tooltip: pertimbangkan adapter tanggal (`chartjs-adapter-date-fns`) jika ingin sumbu waktu asli.
