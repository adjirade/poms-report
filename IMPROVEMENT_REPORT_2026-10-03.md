# 🛠️ LAPORAN IMPROVEMENT & AUDIT MENYELURUH — POMS Report
**Tanggal:** 3 Oktober 2026
**Metode:** Skill `code-reviewer` (aturan universal + PHP + code_quality_checker) + review manual seluruh controller/service/view + test suite

---

## 🎯 RINGKASAN EKSEKUTIF

Sesi ini menemukan dan memperbaiki **1 akar masalah sistemik** (menyebabkan 8 test gagal), **5 bug backend** (termasuk 1 celah RBAC), dan **12 perbaikan UI/UX**. Test suite kembali **37/37 HIJAU (108 assertion)**, build Vite sukses, dan seluruh 41 route terverifikasi.

| Pemeriksaan | Hasil |
|---|---|
| `php vendor/bin/phpunit` | ✅ **OK (37 tests, 108 assertions)** |
| `php artisan route:list` (41 route) | ✅ Semua resolve |
| `php artisan view:cache` | ✅ 0 error template |
| `php -l` (7 file yang diubah) | ✅ 0 error sintaks |
| `npm run build` | ✅ CSS 118,8 kB / JS 315,2 kB, class Tailwind baru terdeteksi |
| code_quality_checker (35 file) | ✅ Rata-rata 97,2/100 (A), 0 pelanggaran SOLID |

> Catatan skor: rata-rata turun tipis (97,7 → 97,2) karena penambahan logika RBAC membuat `flaggedRecords()` lebih panjang (penalti heuristik "long function"). Perubahan ini sengaja dan dibenarkan oleh PRD §3.

---

## 🔴 AKAR MASALAH SISTEMIK: CONFIG CACHE BOCOR KE ENVIRONMENT TESTING

**Gejala:** 8 test gagal dengan 419 (CSRF) dan 401 (token API) — `HQSyncApiTest` (5), `HQSyncMonitorTest` (2), `LoginAndRbacTest` (1).

**Akar masalah:** `php artisan optimize` pernah dijalankan di mesin dev sehingga `bootstrap/cache/config.php` dibuat **dari `.env` lokal** (APP_ENV=local, token asli). Saat phpunit berjalan, **config cache menimpa env dari `phpunit.xml`**:
- `APP_ENV` tidak lagi `testing` → bypass CSRF unit test mati → semua POST kena 419;
- `HQ_API_TOKEN` bukan `test-token` → 5 test API kena 401.

**Fix:**
1. `php artisan optimize:clear` (cache dibersihkan; cache adalah artefak deploy, bukan isi repo).
2. **Pengaman permanen** di `tests/TestCase.php::setUp()` — file `bootstrap/cache/*.php` dihapus **sebelum aplikasi boot**, sehingga test tidak akan pernah lagi diam-diam membaca config cache basi.

---

## 🔒 BUG BACKEND YANG DIPERBAIKI

### 1. Resolusi model dinamis dari input user — `StationController::verify()`
`'App\\Models\\Log'.ucfirst($station)` membangun nama class dari input URL. Diganti ke whitelist `ValidationService::getStationModel()` (satu sumber kebenaran; stasiun tak dikenal → 404).

### 2. Celah RBAC: asisten bisa verifikasi data departemen lain (PRD §3)
- `StationController::verify()` — ditambah cek `getDepartmentStations()`; asisten hanya boleh verifikasi stasiun departemennya.
- `StationLogsTable::verifyRecord()` (Livewire) — cek sama, defense-in-depth.
- `DashboardController::flaggedRecords()` — **kebocoran data lintas departemen**: asisten lab bisa melihat flagged record stasiun lain. Sekarang daftar stasiun difilter `allowedStations()`.

### 3. Nilai non-numeric lolos validasi — `ValidationService::validate()`
`(float) "abc"` = 0.0 dan bisa lolos range check (mis. parameter min 0.0). Ditambah penolakan eksplisit `is_numeric()` dengan pesan jelas.

### 4. N+1 query — `DashboardController::losses()`
Tabel lab (30 hari data) tanpa eager load → query `users` per baris. Ditambah `->with('user')`.

### 5. DRY: mapping departemen→stasiun terduplikasi 3×
Sekarang hanya di `StationLogDepartmentTrait`, dipakai bersama oleh `User::allowedStations()`, `ProcessTelegramMessage::canSubmitToStation()`, dan kedua titik verifikasi. (Behavior identik — semua test RBAC tetap hijau.)

### 6. Pembersihan kecil
Dead code `$query = $query;` di `flaggedRecords()` dihapus; blok filter dirapikan.

---

## 🎨 PERBAIKAN UI/UX (12 item)

| # | Halaman | Masalah → Perbaikan |
|---|---|---|
| 1 | **Semua (layout)** | `@section('title'/'subtitle')` (halaman profil) tidak pernah terbaca layout → header selalu "Dashboard". Sekarang layout pakai `@hasSection/@yield` dengan fallback variabel. |
| 2 | **Semua (layout)** | Sidebar collapsed di desktop hilang penuh tapi konten `md:ml-20` → gap kosong 80px. Kini collapsed = **rail ikon `w-20`** (label tersembunyi, ikon center, subnav ikut tersembunyi), konsisten dengan margin konten. Backdrop mobile kini reaktif via `md:hidden`. |
| 3 | **Semua (layout)** | Jam header statis → **jam hidup WIB** (Alpine + `Intl.DateTimeFormat`, zona Asia/Jakarta). |
| 4 | **Semua (layout)** | Flash message tidak auto-hilang + tanpa ARIA role → auto-dismiss 6s (error 10s) + `role="status"`/`role="alert"`. |
| 5 | **Login** | Gradien ungu tidak nyambung dengan branding sawit → gradien hijau (green-900 → green-700 → green-500). |
| 6 | **Halaman stasiun** | Tombol Excel memakai hidden input "hari ini" sehingga **mengabaikan rentang tanggal yang dipilih user** → satu form dengan dua tombol `formaction` (PDF & Excel memakai input tanggal yang sama), plus header responsif (`flex-col → lg:flex-row`). |
| 7 | **Dashboard** | Kartu "Quick Actions" tampil kosong untuk role tanpa aksi apa pun → seluruh section disembunyikan bila tidak ada aksi. |
| 8 | **Dashboard** | `$log->user->name` NPE bila user dihapus → null-safe (`?->`), nama tabel diformat ramah ("Log Sterilizer" bukan "Log_sterilizer"). |
| 9 | **Tabel Livewire stasiun** | Kolom "Data" membocorkan field internal HQ sync (`hq_synced_at`, `hq_source_id`, `operator_name`) → masuk daftar exclusion. |
| 10 | **Tabel Livewire stasiun** | `$log->user->name` NPE bila user dihapus; selisih jam ditampilkan apa adanya (mis. `5.000000001h`) → `number_format(..., 1)` + `abs()`, unit "jam"; `colspan` empty-state kini adaptif kolom Aksi. |
| 11 | **Flagged records** | Link "View Details" ke stasiun luar departemen asisten (akan 403) → disembunyikan, diganti badge "Di luar departemen". |
| 12 | **Analytics overview** | Kartu "Verified / Quality checked" menyesatkan (nilainya = total − flagged, bukan `is_verified`) → dilabeli ulang "Clean Records / Tanpa anomali waktu". |

---

## 📁 FILE YANG DIUBAH

```
tests/TestCase.php                          ← pengaman config cache
app/Http/Controllers/StationController.php  ← whitelist + RBAC departemen
app/Livewire/StationLogsTable.php           ← RBAC departemen (verifyRecord)
app/Services/ValidationService.php          ← is_numeric guard
app/Http/Controllers/DashboardController.php← scoping flagged + eager load + cleanup
app/Models/User.php                         ← DRY mapping (trait)
app/Jobs/ProcessTelegramMessage.php         ← DRY mapping (trait)
resources/views/layouts/app.blade.php       ← title/subtitle yield, sidebar rail, jam hidup, flash ARIA
resources/views/auth/login.blade.php        ← gradien hijau
resources/views/stations/show.blade.php     ← form export terpadu
resources/views/dashboard.blade.php         ← quick actions kondisional + null-safe
resources/views/livewire/station-logs-table.blade.php ← exclusion field HQ + null-safe + format
resources/views/flagged-records.blade.php   ← link sesuai hak akses
resources/views/analytics/overview.blade.php← relabel kartu
```

---

## ⏳ SARAN BERIKUTNYA

1. **Commit perubahan** — working tree memuat perbaikan sesi ini + sesi sebelumnya yang belum ter-commit.
2. Rotasi password demo & `HQ_API_TOKEN` sebelum go-live.
3. Opsional: pecah `DashboardController::flaggedRecords()` menjadi service/DTO untuk menaikkan skor heuristik kualitas (fungsionalnya sudah benar).
4. Opsional: audit `scope="col"` di seluruh `<th>` untuk kelengkapan a11y.

**Laporan dibuat:** 3 Oktober 2026 — Buffy (Freebuff)
