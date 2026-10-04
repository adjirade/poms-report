import Chart from 'chart.js/auto';
import zoomPlugin from 'chartjs-plugin-zoom';

// Plugin zoom didaftarkan sekali pada instance Chart.js global (chart.js/auto
// di-dedupe Vite, jadi instance ini sama dengan window.Chart di app.js).
Chart.register(zoomPlugin);

function hexToRgba(hex, alpha) {
    const clean = hex.replace('#', '');
    const full = clean.length === 3 ? clean.split('').map((c) => c + c).join('') : clean;
    const bigint = parseInt(full, 16);
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * Builder chart "Performa Stasiun" (AGENDA/chart-detail-plan.md).
 *
 *   PomsStationChart.render('canvasId', {
 *       labels: ['01 Oct', ...],
 *       series: [{ key, label, unit, color, data: [...] }],
 *       counts: { total: [...], flagged: [...] },   // opsional (info tooltip)
 *   })
 *
 * Fitur: multi-series (semua parameter stasiun), drag-zoom + Ctrl+wheel +
 * pan, tombol reset, tooltip yang menampilkan SEMUA series + jumlah record +
 * flag per hari. Render ulang pada canvas yang sama otomatis menghancurkan
 * chart lama.
 */
window.PomsStationChart = {
    _instances: {},

    render(canvasId, { labels = [], series = [], counts = null } = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') {
            return null;
        }

        this.destroy(canvasId);

        const axis = window.PomsChart?.axis
            ? window.PomsChart.axis.bind(window.PomsChart)
            : (o = {}) => o;

        // Bangun dataset: garis utama (dengan penanda anomali) + moving average
        // 7 hari (garis putus-putus) bila tersedia (A5).
        const datasets = [];
        series.forEach((s) => {
            const anomalyIdx = new Set(s.anomalies || (s.anomaly_points || []).map((p) => p.index));

            datasets.push({
                label: s.unit ? `${s.label} (${s.unit})` : s.label,
                data: s.data,
                borderColor: s.color,
                backgroundColor: window.PomsChart?.area
                    ? window.PomsChart.area(canvas.getContext('2d'), s.color)
                    : hexToRgba(s.color, 0.15),
                pointBackgroundColor: (s.data || []).map((_, i) => (anomalyIdx.has(i) ? '#ef4444' : s.color)),
                pointBorderColor: (s.data || []).map((_, i) => (anomalyIdx.has(i) ? '#b91c1c' : s.color)),
                pointRadius: (ctx) => {
                    if (ctx.dataIndex === undefined) return 0;
                    if (anomalyIdx.has(ctx.dataIndex)) return 5;
                    return labels.length > 45 ? 0 : 2;
                },
                pointHoverRadius: 6,
                fill: series.length === 1,
                spanGaps: true,
                meta: { label: s.label, unit: s.unit || '', ma: false },
                _anomalies: anomalyIdx,
            });

            if (Array.isArray(s.ma) && s.ma.some((v) => v !== null)) {
                datasets.push({
                    label: `MA7 ${s.label}`,
                    data: s.ma,
                    borderColor: s.color,
                    backgroundColor: 'transparent',
                    borderDash: [6, 4],
                    borderWidth: 1.5,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    fill: false,
                    spanGaps: true,
                    meta: { label: `MA7 ${s.label}`, unit: s.unit || '', ma: true },
                });
            }
        });

        const chart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels,
                datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        callbacks: {
                            title: (items) => (items[0] ? String(items[0].label) : ''),
                            label: (item) => {
                                const m = item.dataset.meta || {};
                                const value = item.parsed.y;
                                const unit = m.unit ? ` ${m.unit}` : '';
                                const anomaly = !m.ma && item.dataset._anomalies?.has(item.dataIndex)
                                    ? ' ⚠️ anomali'
                                    : '';
                                return `${m.label ?? item.dataset.label}: ${value ?? '—'}${unit}${anomaly}`;
                            },
                            afterBody: (items) => {
                                if (!counts) {
                                    return [];
                                }
                                const i = items[0]?.dataIndex;
                                if (i === undefined) {
                                    return [];
                                }
                                const lines = [`Record: ${counts.total?.[i] ?? 0}`];
                                if ((counts.flagged?.[i] ?? 0) > 0) {
                                    lines.push(`🚩 Flagged: ${counts.flagged[i]}`);
                                }
                                return lines;
                            },
                        },
                    },
                    zoom: {
                        pan: {
                            enabled: true,
                            mode: 'x',
                            modifierKey: 'ctrl',
                        },
                        zoom: {
                            drag: {
                                enabled: true,
                                backgroundColor: 'rgba(14, 165, 233, 0.12)',
                                borderColor: '#0ea5e9',
                                borderWidth: 1,
                            },
                            mode: 'x',
                            wheel: { enabled: true, modifierKey: 'ctrl' },
                            pinch: { enabled: true },
                        },
                        limits: { x: { min: 'original', max: 'original' } },
                    },
                },
                scales: {
                    y: axis({ beginAtZero: true }),
                    x: axis({ grid: false }),
                },
            },
        });

        this._instances[canvasId] = chart;

        return chart;
    },

    /**
     * Chart batang volume aktivitas harian: jumlah record masuk + flagged.
     *
     *   PomsStationChart.renderVolume('canvasId', {
     *       labels: [...],
     *       counts: { total: [...], flagged: [...] },
     *   })
     */
    renderVolume(canvasId, { labels = [], counts = null } = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined' || !counts) {
            return null;
        }

        this.destroy(canvasId);

        const axis = window.PomsChart?.axis
            ? window.PomsChart.axis.bind(window.PomsChart)
            : (o = {}) => o;

        const chart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Record Masuk',
                        data: counts.total ?? [],
                        backgroundColor: 'rgba(14, 165, 233, 0.65)',
                        borderColor: '#0ea5e9',
                        borderWidth: 1,
                        borderRadius: 4,
                    },
                    {
                        label: 'Flagged',
                        data: counts.flagged ?? [],
                        backgroundColor: 'rgba(244, 63, 94, 0.7)',
                        borderColor: '#f43f5e',
                        borderWidth: 1,
                        borderRadius: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: (item) => `${item.dataset.label}: ${item.parsed.y}`,
                        },
                    },
                },
                scales: {
                    y: axis({ beginAtZero: true, ticks: { precision: 0 } }),
                    x: axis({ grid: false }),
                },
            },
        });

        this._instances[canvasId] = chart;

        return chart;
    },

    /**
     * Chart doughnut status verifikasi untuk rentang yang dipilih.
     *
     *   PomsStationChart.renderStatus('canvasId', {
     *       verified: 12, pending: 4, flagged: 2,
     *   })
     */
    renderStatus(canvasId, { verified = 0, pending = 0, flagged = 0 } = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') {
            return null;
        }

        this.destroy(canvasId);

        const total = verified + pending + flagged;

        // Plugin kecil: tulis total record di tengah doughnut.
        const centerText = {
            id: 'centerText',
            afterDraw(c) {
                const { ctx, chartArea } = c;
                if (!chartArea) {
                    return;
                }
                const x = (chartArea.left + chartArea.right) / 2;
                const y = (chartArea.top + chartArea.bottom) / 2;
                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.font = '700 22px sans-serif';
                ctx.fillStyle = '#1f2937';
                ctx.fillText(String(total), x, y - 8);
                ctx.font = '500 11px sans-serif';
                ctx.fillStyle = '#6b7280';
                ctx.fillText('record', x, y + 12);
                ctx.restore();
            },
        };

        const chart = new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Terverifikasi', 'Menunggu Verifikasi', 'Flagged'],
                datasets: [
                    {
                        data: [verified, pending, flagged],
                        backgroundColor: ['#10b981', '#94a3b8', '#f43f5e'],
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: true, position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (item) => {
                                const v = item.parsed;
                                const pct = total > 0 ? ` (${Math.round((v / total) * 100)}%)` : '';
                                return ` ${item.label}: ${v}${pct}`;
                            },
                        },
                    },
                },
            },
            plugins: [centerText],
        });

        this._instances[canvasId] = chart;

        return chart;
    },

    /** Reset zoom/pan ke tampilan penuh. */
    reset(canvasId) {
        const chart = this._instances[canvasId];
        if (chart && typeof chart.resetZoom === 'function') {
            chart.resetZoom();
        }
    },

    destroy(canvasId) {
        if (this._instances[canvasId]) {
            this._instances[canvasId].destroy();
            delete this._instances[canvasId];
        }
    },
};
