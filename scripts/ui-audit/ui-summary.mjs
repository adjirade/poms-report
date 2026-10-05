// Rangkum hasil audit UI: agregasi pelanggaran kontras/target/semantik.
// Pemakaian: node scripts/ui-audit/ui-summary.mjs
import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = join(dirname(fileURLToPath(import.meta.url)), 'results');
const files = readdirSync(dir).filter((f) => f.startsWith('results-') && f.endsWith('.json'));

const contrast = new Map();
const smallTargets = new Map();
const summary = [];

for (const f of files) {
  const a = JSON.parse(readFileSync(join(dir, f), 'utf8'));
  if (a.error) { summary.push({ page: f, error: a.error }); continue; }

  for (const c of a.contrast ?? []) {
    const key = `${c.fg} on ${c.bg} | ${c.cls}`;
    if (!contrast.has(key)) contrast.set(key, { ...c, pages: new Set() });
    contrast.get(key).pages.add(f);
  }
  for (const t of a.smallTargets ?? []) {
    const key = `${t.tag} ${t.w}x${t.h} "${t.label}"`;
    smallTargets.set(key, (smallTargets.get(key) ?? 0) + 1);
  }
  summary.push({
    page: f.replace(/\.json$/, '').replace(/^results-/, ''),
    overflowX: a.overflowX,
    tinyText: a.tinyText,
    imgsNoAlt: a.imgsNoAlt,
    buttonsNoName: a.buttonsNoName,
    inputsNoLabel: (a.inputsNoLabel ?? []).length,
    headingSkip: a.headingSkip,
    contrastCount: (a.contrast ?? []).length,
    smallTargetCount: (a.smallTargets ?? []).length,
  });
}

console.log('=== RINGKASAN PER HALAMAN ===');
for (const s of summary) console.log(JSON.stringify(s));

console.log('\n=== KONTRAS (agregat, >=2 halaman) ===');
const worst = [...contrast.values()].filter((c) => c.pages.size >= 2)
  .sort((a, b) => a.ratio - b.ratio);
for (const c of worst.slice(0, 25)) {
  console.log(`${String(c.ratio).padEnd(5)} need ${c.need}  ${String(c.size).padEnd(5)}px  ${c.fg} on ${c.bg}  [${c.cls}] "${c.text}"  (${c.pages.size} hal)`);
}

console.log('\n=== KONTRAS (hanya 1 halaman, terburuk) ===');
for (const c of [...contrast.values()].filter((c) => c.pages.size === 1).sort((a, b) => a.ratio - b.ratio).slice(0, 15)) {
  console.log(`${String(c.ratio).padEnd(5)} need ${c.need}  ${c.fg} on ${c.bg}  [${c.cls}] "${c.text}"  (${[...c.pages][0]})`);
}

console.log('\n=== TARGET SENTUH <24px (agregat) ===');
for (const [k, n] of [...smallTargets.entries()].sort((a, b) => b[1] - a[1]).slice(0, 20)) {
  console.log(`${String(n).padEnd(3)}x ${k}`);
}
