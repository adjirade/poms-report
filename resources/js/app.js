import './bootstrap';

import './station-chart';

import Chart from 'chart.js/auto';

// Chart.js dipakai oleh inline script di halaman analytics
// (resources/views/analytics/*) via global `Chart`.
window.Chart = Chart;

// ---------------------------------------------------------------------------
// Helper global "Liquid Glass" untuk script chart inline.
//
//   new Chart(ctx, { data: { datasets: [{
//       borderColor: '#22c55e',
//       backgroundColor: PomsChart.area(ctx, '#22c55e'),
//   }] } })
//
// Didefinisikan SEBELUM tema di bawah dan tema dibungkus try/catch, supaya
// kegagalan konfigurasi Chart.js tidak pernah membuat PomsChart undefined
// (chart inline di halaman analytics bergantung padanya).
// ---------------------------------------------------------------------------
function hexToRgba(hex, alpha) {
    const clean = hex.replace('#', '');
    const full = clean.length === 3 ? clean.split('').map((c) => c + c).join('') : clean;
    const bigint = parseInt(full, 16);
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

window.PomsChart = {
    rgba: hexToRgba,
    // Isian gradient vertikal (pekat di atas → transparan di bawah).
    area(ctx, hex, topAlpha = 0.32, bottomAlpha = 0.02) {
        const h = (ctx.canvas && ctx.canvas.height) || 320;
        const gradient = ctx.createLinearGradient(0, 0, 0, h);
        gradient.addColorStop(0, hexToRgba(hex, topAlpha));
        gradient.addColorStop(1, hexToRgba(hex, bottomAlpha));
        return gradient;
    },
    // Skema warna khas POMS (emerald → teal → sky → violet → amber).
    palette: ['#10b981', '#14b8a6', '#0ea5e9', '#8b5cf6', '#f59e0b', '#ef4444', '#ec4899', '#64748b'],

    // Opsi sumbu siap pakai: grid sangat tipis, tanpa garis tepi.
    //
    // Chart.js 4 TIDAK mendukung override global untuk `scales.*.grid`
    // (objek scale adalah Proxy dan assignment langsung melempar TypeError),
    // jadi tiap chart memakai helper ini di `options.scales`:
    //   scales: { y: PomsChart.axis(), x: PomsChart.axis({ grid: false }) }
    axis(overrides = {}) {
        const grid = { color: 'rgba(15, 23, 42, 0.06)', drawTicks: false };
        if (overrides.grid === false) grid.display = false;
        else if (overrides.grid && typeof overrides.grid === 'object') Object.assign(grid, overrides.grid);
        return {
            ...overrides,
            grid,
            border: { display: false, ...(overrides.border || {}) },
            ticks: { padding: 8, ...(overrides.ticks || {}) },
        };
    },
};

// ---------------------------------------------------------------------------
// Tema Chart.js — "Liquid Glass"
// Garis tebal membulat, grid tipis, tooltip gelap translusen, isian gradient.
// ---------------------------------------------------------------------------
try {
    Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'SF Pro Text', Inter, 'Segoe UI', Roboto, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = 'rgba(51, 65, 85, 0.85)';
    Chart.defaults.borderColor = 'rgba(15, 23, 42, 0.07)';

    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.legend.labels.padding = 16;

    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.82)';
    Chart.defaults.plugins.tooltip.titleColor = '#ffffff';
    Chart.defaults.plugins.tooltip.bodyColor = 'rgba(255, 255, 255, 0.92)';
    Chart.defaults.plugins.tooltip.borderColor = 'rgba(255, 255, 255, 0.18)';
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 14;
    Chart.defaults.plugins.tooltip.boxPadding = 6;
    Chart.defaults.plugins.tooltip.usePointStyle = true;

    Chart.defaults.elements.line.tension = 0.42;
    Chart.defaults.elements.line.borderWidth = 3;
    Chart.defaults.elements.line.borderCapStyle = 'round';
    Chart.defaults.elements.point.radius = 0;
    Chart.defaults.elements.point.hoverRadius = 5;
    Chart.defaults.elements.point.hitRadius = 14;
    Chart.defaults.elements.point.borderWidth = 2;
    Chart.defaults.elements.point.backgroundColor = '#ffffff';
    Chart.defaults.elements.bar.borderRadius = 10;
} catch (e) {
    // Tema gagal diterapkan — chart tetap tampil dengan gaya bawaan Chart.js.
    console.warn('[POMS] Chart theme could not be applied:', e);
}

// Jangan impor/menjalankan Alpine di sini — Livewire 3 sudah membundel Alpine
// dan menjalankannya sendiri (window.Alpine). Menjalankan Alpine.start() kedua
// kali membuat wire:click/x-data tidak stabil.