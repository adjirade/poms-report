// ============================================================
// POMS — Audit UI/UX berkala via Chrome DevTools Protocol (CDP).
// ============================================================
// Memeriksa 20 halaman (desktop + mobile) dalam kondisi login:
//   - Kontras WCAG 1.4.3/1.4.11 (alpha compositing, termasuk
//     linear-gradient pada glass UI) — butuh 4.5:1 / 3:1 (teks besar)
//   - Target sentuh < 24px (WCAG 2.2, 2.5.8)
//   - Teks < 12px · img tanpa alt · input tanpa label
//   - Tombol tanpa nama · lompatan heading · overflow horizontal
//
// Pemakaian (lihat README.md di folder ini):
//   1. php artisan serve --port=8000
//   2. chrome --headless=new --remote-debugging-port=9222 --user-data-dir=%TEMP%\chrome-poms about:blank
//   3. php scripts/ui-audit/audit-users.php   (buat user audit)
//   4. node scripts/ui-audit/ui-audit.mjs
//   5. node scripts/ui-audit/ui-summary.mjs   (rangkuman)
//   6. php scripts/ui-audit/audit-users.php --remove   (hapus user audit)
//
// Variabel lingkungan (opsional):
//   AUDIT_BASE   (default http://127.0.0.1:8000)
//   AUDIT_CDP    (default http://127.0.0.1:9222)
//   AUDIT_PHONE  (default 628999000900)
//   AUDIT_PASS   (default AuditUi2026!)
import { writeFileSync, mkdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const BASE = process.env.AUDIT_BASE ?? 'http://127.0.0.1:8000';
const CDP = process.env.AUDIT_CDP ?? 'http://127.0.0.1:9222';
const PHONE = process.env.AUDIT_PHONE ?? '628999000900';
const PASS = process.env.AUDIT_PASS ?? 'AuditUi2026!';
const HERE = dirname(fileURLToPath(import.meta.url));
const OUTDIR = join(HERE, 'results');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function newTab() {
  const res = await fetch(`${CDP}/json/new?about:blank`, { method: 'PUT' });
  if (!res.ok) throw new Error('Gagal membuat tab CDP — pastikan Chrome headless berjalan (' + res.status + ')');
  return res.json();
}

class Cdp {
  constructor(ws) {
    this.ws = ws;
    this.id = 0;
    this.pending = new Map();
    ws.addEventListener('message', (ev) => {
      const msg = JSON.parse(ev.data);
      if (msg.id && this.pending.has(msg.id)) {
        const { resolve, reject } = this.pending.get(msg.id);
        this.pending.delete(msg.id);
        msg.error ? reject(new Error(JSON.stringify(msg.error))) : resolve(msg.result);
      }
    });
  }
  send(method, params = {}) {
    const id = ++this.id;
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params }));
    });
  }
}

async function goto(cdp, url) {
  await cdp.send('Page.navigate', { url });
  for (let i = 0; i < 60; i++) {
    await sleep(250);
    try {
      const r = await cdp.send('Runtime.evaluate', { expression: 'document.readyState', returnByValue: true });
      if (r.result?.value === 'complete') break;
    } catch { /* halaman sedang berpindah */ }
  }
  await sleep(1500); // beri waktu Alpine/Livewire/Chart.js merender
}

const AUDIT_JS = `
(() => {
  const out = {
    url: location.href,
    overflowX: false,
    contrast: [],
    tinyText: 0,
    tinyTextSamples: [],
    smallTargets: [],
    imgsNoAlt: 0,
    inputsNoLabel: [],
    buttonsNoName: 0,
    headings: {h1:0,h2:0,h3:0,h4:0,h5:0,h6:0},
    headingSkip: null,
    prevLevel: 0,
  };

  const doc = document.documentElement;
  out.overflowX = doc.scrollWidth > doc.clientWidth + 2;

  const lum = (r, g, b) => {
    const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  };
  const parse = (s) => {
    const m = s.match(/rgba?\\\\(([-\\\\d.]+),\\\\s*([-\\\\d.]+),\\\\s*([-\\\\d.]+)(?:,\\\\s*([\\\\d.]+))?\\\\)/);
    return m ? { r: +m[1], g: +m[2], b: +m[3], a: m[4] === undefined ? 1 : +m[4] } : null;
  };
  const ratio = (a, b) => {
    const l1 = lum(a.r, a.g, a.b), l2 = lum(b.r, b.g, b.b);
    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
  };

  // Efek background: komposit alpha dari rantai ancestor (glass UI memakai
  // bg-white/15 dsb di atas warna solid). linear-gradient diurai: stops
  // dikomposit berurutan menjadi satu layer sebelum digabung ke rantai.
  const stopsOf = (img) => {
    if (!img || img.indexOf('gradient') === -1) return null;
    // Ambil semua warna rgb/rgba dalam urutan kemunculan (split koma tidak
    // aman karena rgba() sendiri mengandung koma).
    const stops = [];
    const re = /rgba?\\\\([^)]*\\\\)/g;
    let m;
    while ((m = re.exec(img)) !== null) {
      const c = parse(m[0]);
      if (c) stops.push(c);
    }
    return stops.length ? stops : null;
  };
  const over = (top, under) => ({
    r: top.r * top.a + under.r * (1 - top.a),
    g: top.g * top.a + under.g * (1 - top.a),
    b: top.b * top.a + under.b * (1 - top.a),
    a: top.a + under.a * (1 - top.a),
  });
  const effectiveBg = (el) => {
    // Kumpulkan layer dari elemen -> root sampai ketemu layer opaque,
    // lalu komposit dari PALING LUAR ke dalam.
    const layers = [];
    let n = el;
    while (n && n.nodeType === 1) {
      const s = getComputedStyle(n);
      let layer = parse(s.backgroundColor);
      const stops = stopsOf(s.backgroundImage);
      if (stops) {
        // komposit stop pertama di atas stop terakhir (aproksimasi gradien)
        let grad = stops[stops.length - 1];
        for (let i = stops.length - 2; i >= 0; i--) grad = over(stops[i], grad);
        layer = layer && layer.a > 0 ? over(grad, layer) : grad;
      }
      if (layer && layer.a > 0) {
        layers.push(layer);
        if (layer.a >= 0.99) break;
      }
      n = n.parentElement;
    }
    let bg = layers.length ? layers[layers.length - 1] : { r: 255, g: 255, b: 255, a: 1 };
    for (let i = layers.length - 2; i >= 0; i--) bg = over(layers[i], bg);
    return bg;
  };

  const seen = new Set();
  const all = document.querySelectorAll('body *');
  for (const el of all) {
    // teks langsung
    let text = '';
    for (const n of el.childNodes) if (n.nodeType === 3) text += n.textContent;
    text = text.replace(/\\\\s+/g, ' ').trim();

    if (text && !el.classList.contains('sr-only') && !el.closest('.sr-only')) {
      const s = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      const visible = rect.width > 0 && rect.height > 0 && s.visibility !== 'hidden' && +s.opacity > 0.05;
      if (visible) {
        const size = parseFloat(s.fontSize);
        if (size < 12) {
          out.tinyText++;
          if (out.tinyTextSamples.length < 5) out.tinyTextSamples.push({ tag: el.tagName, size, text: text.slice(0, 30) });
        }
        const fg = parse(s.color);
        if (fg) {
          const bg = effectiveBg(el);
          const r = ratio(fg, bg);
          const large = size >= 24 || (size >= 18.66 && parseInt(s.fontWeight, 10) >= 700);
          const need = large ? 3 : 4.5;
          if (r < need) {
            const key = s.color + '|' + bg.r + ',' + bg.g + ',' + bg.b + '|' + text.slice(0, 20);
            if (!seen.has(key) && out.contrast.length < 30) {
              seen.add(key);
              out.contrast.push({
                tag: el.tagName.toLowerCase(),
                cls: String(el.className).split(' ').slice(0, 4).join(' '),
                fg: s.color, bg: 'rgb(' + Math.round(bg.r) + ',' + Math.round(bg.g) + ',' + Math.round(bg.b) + ')',
                ratio: Math.round(r * 100) / 100, need,
                size: Math.round(size * 10) / 10,
                text: text.slice(0, 40),
              });
            }
          }
        }
      }
    }

    // heading
    const hm = /^H([1-6])$/.exec(el.tagName);
    if (hm) {
      const lvl = +hm[1];
      out.headings['h' + lvl]++;
      if (out.prevLevel && lvl > out.prevLevel + 1 && !out.headingSkip) {
        out.headingSkip = 'h' + out.prevLevel + ' -> h' + lvl + ' (' + (el.textContent || '').trim().slice(0, 30) + ')';
      }
      out.prevLevel = lvl;
    }

    // target sentuh
    const tag = el.tagName.toLowerCase();
    const interactive = tag === 'a' || tag === 'button' || tag === 'select'
      || (tag === 'input' && !['hidden', 'checkbox', 'radio', 'submit'].includes(el.type));
    if (interactive && el.getClientRects().length && !el.classList.contains('sr-only')) {
      const r = el.getBoundingClientRect();
      if (r.width > 0 && r.height > 0 && (r.width < 24 || r.height < 24)) {
        out.smallTargets.push({
          tag, w: Math.round(r.width), h: Math.round(r.height),
          label: (el.getAttribute('aria-label') || el.textContent || el.value || '').trim().slice(0, 24),
        });
        if (out.smallTargets.length > 20) out.smallTargets.length = 20;
      }
    }
  }

  // semantik gambar
  out.imgsNoAlt = [...document.querySelectorAll('img')].filter((i) => !i.hasAttribute('alt')).length;

  // label form
  for (const f of document.querySelectorAll('input, select, textarea')) {
    if (['hidden', 'submit', 'button', 'checkbox', 'radio'].includes(f.type)) continue;
    const idOk = f.id && document.querySelector('label[for="' + CSS.escape(f.id) + '"]');
    const wrapped = f.closest('label');
    const named = f.getAttribute('aria-label') || f.getAttribute('aria-labelledby') || f.getAttribute('title');
    if (!idOk && !wrapped && !named) {
      out.inputsNoLabel.push({ tag: f.tagName.toLowerCase(), name: f.name || '', type: f.type });
      if (out.inputsNoLabel.length > 10) out.inputsNoLabel.length = 10;
    }
  }

  // tombol tanpa nama
  out.buttonsNoName = [...document.querySelectorAll('button')].filter((b) => {
    const named = (b.textContent || '').trim() || b.getAttribute('aria-label') || b.title
      || (b.querySelector('img[alt]') !== null);
    return !named && b.getClientRects().length;
  }).length;

  return JSON.stringify(out);
})();
`;

const SHOTS = join(OUTDIR, 'shots');
mkdirSync(SHOTS, { recursive: true });

async function snapshot(cdp, name, audit) {
  writeFileSync(join(OUTDIR, `results-${name}.json`), JSON.stringify(audit, null, 2));
  const shot = await cdp.send('Page.captureScreenshot', { format: 'jpeg', quality: 70 });
  writeFileSync(join(SHOTS, `${name}.jpg`), Buffer.from(shot.data, 'base64'));
}

async function setViewport(cdp, w, h) {
  await cdp.send('Emulation.setDeviceMetricsOverride', {
    width: w, height: h, deviceScaleFactor: 1, mobile: w < 500,
  });
}

async function run() {
  const tab = await newTab();
  const ws = new WebSocket(tab.webSocketDebuggerUrl);
  await new Promise((res) => (ws.onopen = res));
  const cdp = new Cdp(ws);
  await cdp.send('Page.enable');
  await cdp.send('Runtime.enable');
  await setViewport(cdp, 1440, 900);

  // --- Halaman login (belum login, desktop) ---
  await goto(cdp, `${BASE}/login`);
  await snapshot(cdp, 'login-desktop', JSON.parse(
    (await cdp.send('Runtime.evaluate', { expression: AUDIT_JS, returnByValue: true })).result.value
  ));

  // --- Login sebagai manager ---
  await cdp.send('Runtime.evaluate', { expression: `
    document.querySelector('#phone_number').value = '${PHONE}';
    document.querySelector('#password').value = '${PASS}';
    document.querySelector('form').submit(); true;
  `, returnByValue: true });
  await sleep(2500);
  for (let i = 0; i < 20; i++) {
    await sleep(300);
    const u = await cdp.send('Runtime.evaluate', { expression: 'location.pathname', returnByValue: true });
    if (u.result?.value !== '/login') break;
  }

  const managerPages = [
    ['dash', '/'],
    ['input', '/input'],
    ['station-press', '/stations/press'],
    ['flagged', '/flagged-records'],
    ['tickets', '/maintenance/tickets'],
    ['weekly-recap', '/analytics/weekly-recap'],
    ['command-center', '/analytics/command-center'],
    ['overview', '/analytics/overview'],
    ['losses', '/analytics/losses'],
    ['efficiency', '/analytics/efficiency'],
    ['station-performance', '/analytics/station-performance'],
    ['kpi-targets', '/settings/kpi-targets'],
    ['profile', '/profile'],
  ];

  for (const [name, path] of managerPages) {
    await goto(cdp, BASE + path);
    const raw = await cdp.send('Runtime.evaluate', { expression: AUDIT_JS, returnByValue: true });
    let audit;
    try { audit = JSON.parse(raw.result.value); } catch { audit = { url: path, error: String(raw.result?.value).slice(0, 200) }; }
    await snapshot(cdp, `mgr-${name}`, audit);
    console.log(`OK mgr-${name}`);
  }

  // --- Mobile (manager) ---
  await setViewport(cdp, 390, 844);
  for (const [name, path] of [['dash', '/'], ['input', '/input'], ['weekly-recap', '/analytics/weekly-recap'], ['command-center', '/analytics/command-center']]) {
    await goto(cdp, BASE + path);
    const raw = await cdp.send('Runtime.evaluate', { expression: AUDIT_JS, returnByValue: true });
    let audit;
    try { audit = JSON.parse(raw.result.value); } catch { audit = { url: path, error: String(raw.result?.value).slice(0, 200) }; }
    await snapshot(cdp, `mob-${name}`, audit);
    console.log(`OK mob-${name}`);
  }

  // --- Halaman khusus developer ---
  await setViewport(cdp, 1440, 900);
  await cdp.send('Runtime.evaluate', { expression: `
    fetch(window.location.origin + '/logout', { method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      keepalive: true }); true;
  `, returnByValue: true });
  await sleep(1500);
  await goto(cdp, `${BASE}/login`);
  await cdp.send('Runtime.evaluate', { expression: `
    document.querySelector('#phone_number').value = '${PHONE.replace(/0$/, '1')}';
    document.querySelector('#password').value = '${PASS}';
    document.querySelector('form').submit(); true;
  `, returnByValue: true });
  await sleep(2500);

  for (const [name, path] of [['users', '/settings/users'], ['env', '/settings/environment'], ['hq-sync', '/settings/hq-sync'], ['validation-rules', '/settings/validation-rules']]) {
    await goto(cdp, BASE + path);
    const raw = await cdp.send('Runtime.evaluate', { expression: AUDIT_JS, returnByValue: true });
    let audit;
    try { audit = JSON.parse(raw.result.value); } catch { audit = { url: path, error: String(raw.result?.value).slice(0, 200) }; }
    await snapshot(cdp, `dev-${name}`, audit);
    console.log(`OK dev-${name}`);
  }

  ws.close();
  console.log('SELESAI — hasil di scripts/ui-audit/results/');
}

run().catch((e) => { console.error('GAGAL:', e.message); process.exit(1); });
