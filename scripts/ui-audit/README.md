# 🔍 Audit UI Berkala (Chrome DevTools Protocol)

Skrip ini memeriksa halaman web POMS **secara programatik** dalam kondisi login:
kontras WCAG (termasuk gradien glass UI), ukuran target sentuh WCAG 2.2,
semantik form/gambar/tombol, hierarki heading, dan overflow horizontal —
di 20+ halaman (desktop 1440px + mobile 390px).

## Cara menjalankan

```bash
# 1. Server Laravel
php artisan serve --port=8000

# 2. Chrome headless dengan remote debugging (terminal terpisah)
"/c/Program Files/Google/Chrome/Application/chrome.exe" \
  --headless=new --remote-debugging-port=9222 \
  --user-data-dir="$TEMP/chrome-poms" --no-first-run --disable-gpu about:blank

# 3. Buat user audit (manager + developer khusus audit)
php scripts/ui-audit/audit-users.php

# 4. Jalankan audit (±2 menit)
node scripts/ui-audit/ui-audit.mjs

# 5. Lihat rangkuman temuan
node scripts/ui-audit/ui-summary.mjs

# 6. Hapus user audit
php scripts/ui-audit/audit-users.php --remove
```

Atau satu perintah untuk langkah 4: `npm run audit:ui`.

## Hasil

- `results/results-*.json` — temuan per halaman
- `results/shots/*.jpg` — screenshot tiap halaman (bukti visual)

## Kriteria yang diperiksa

| Pemeriksaan | Ambang | Acuan | Sifat |
|---|---|---|---|
| Kontras teks vs latar (termasuk alpha compositing gradien) | 4.5:1 (teks normal), 3:1 (teks ≥24px / bold ≥18.66px) | WCAG 1.4.3 | keras |
| Target sentuh interaktif | ≥ 24×24 px | WCAG 2.5.8 | keras |
| Ukuran teks | catat bila < 12px | keterbacaan | informatif |
| Form tanpa label/aria-label | 0 | WCAG 3.3.2 | keras |
| Gambar tanpa alt, tombol tanpa nama | 0 | WCAG 1.1.1 / 4.1.2 | keras |
| Lompatan level heading | tidak ada | WCAG 1.3.1 | keras |
| Overflow horizontal | tidak ada | responsif | keras |

## Gate CI (`ui-gate.mjs`)

`ui-gate.mjs` membaca semua hasil audit dan **gagal (exit 1)** bila ada
pelanggaran keras pada tabel di atas. `tinyText` hanya peringatan
informatif (bukan kegagalan WCAG — caption 11px adalah keputusan desain).

```bash
node scripts/ui-audit/ui-gate.mjs   # atau: npm run audit:ui:gate
```

## CI otomatis (GitHub Actions)

Workflow `.github/workflows/ci.yml` menjalankan dua job tiap push/PR:

1. **Test Suite** — `php artisan test` (sqlite in-memory).
2. **Audit UI (a11y)** — serve + Chrome headless + user audit + audit +
   gate. Screenshot & hasil JSON diunggah sebagai artifact `ui-audit-results`
   (dipertahankan 14 hari), dan user audit selalu dihapus setelah selesai.

## Jadwal berkala

CI sudah berjalan otomatis tiap push/PR. Untuk jadwal tambahan (mis. malam),
tambahkan blok `schedule: cron` di workflow atau jalankan manual tiap akhir
sprint. Skrip **tidak mengubah data aplikasi**; user audit dibuat saat
dibutuhkan dan dihapus setelah selesai (`--remove`).
