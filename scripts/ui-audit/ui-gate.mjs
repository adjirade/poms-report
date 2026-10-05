// ============================================================
// POMS — Gate CI untuk audit UI (dipakai GitHub Actions).
// ============================================================
// Membaca semua results-*.json di scripts/ui-audit/results/ dan
// GAGAL (exit 1) jika ada pelanggaran keras:
//   - kontras < 4.5:1 / 3:1   (WCAG 1.4.3 / 1.4.11)
//   - target sentuh < 24px    (WCAG 2.2, 2.5.8)
//   - img tanpa alt, input tanpa label, tombol tanpa nama
//   - lompatan heading, overflow horizontal
//
// tinyText (teks < 12px, mis. caption KPI 11px) HANYA peringatan
// — bukan kegagalan WCAG dan merupakan keputusan desain yang
// disengaja (lihat AGENDA/ui-audit-2026-10-05.md).
//
// Pemakaian: node scripts/ui-audit/ui-gate.mjs
import { readdirSync, readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = join(dirname(fileURLToPath(import.meta.url)), 'results');
const files = readdirSync(dir).filter((f) => f.startsWith('results-') && f.endsWith('.json'));

if (files.length === 0) {
  console.error('GATE GAGAL: tidak ada hasil audit (results-*.json). Jalankan ui-audit.mjs dulu.');
  process.exit(1);
}

const violations = [];
const warnings = [];

for (const f of files) {
  const page = f.replace(/^results-/, '').replace(/\.json$/, '');
  const a = JSON.parse(readFileSync(join(dir, f), 'utf8'));

  if (a.error) {
    violations.push(`${page}: audit error — ${a.error}`);
    continue;
  }
  for (const c of a.contrast ?? []) {
    violations.push(`${page}: kontras ${c.ratio}:1 (butuh ${c.need}:1) — "${c.text}" [${c.cls}]`);
  }
  for (const t of a.smallTargets ?? []) {
    violations.push(`${page}: target sentuh ${t.w}x${t.h}px "${t.label}" (WCAG 2.5.8, min 24px)`);
  }
  if (a.imgsNoAlt > 0) violations.push(`${page}: ${a.imgsNoAlt} img tanpa alt`);
  if ((a.inputsNoLabel ?? []).length > 0) violations.push(`${page}: ${(a.inputsNoLabel ?? []).length} input tanpa label`);
  if (a.buttonsNoName > 0) violations.push(`${page}: ${a.buttonsNoName} tombol tanpa nama`);
  if (a.headingSkip) violations.push(`${page}: lompatan heading — ${a.headingSkip}`);
  if (a.overflowX) violations.push(`${page}: overflow horizontal`);
  if (a.tinyText > 0) warnings.push(`${page}: ${a.tinyText} teks < 12px (informatif, bukan kegagalan WCAG)`);
}

console.log(`=== UI GATE: ${files.length} halaman diperiksa ===`);
for (const w of warnings) console.log(`  [WARN] ${w}`);

if (violations.length > 0) {
  console.error(`\nGATE GAGAL — ${violations.length} pelanggaran:`);
  for (const v of violations) console.error(`  [X] ${v}`);
  process.exit(1);
}

console.log('\nGATE LOLOS: 0 pelanggaran aksesibilitas keras (kontras/target/semantik).');
