@extends('layouts.app')

@section('title', 'Rekap Mingguan')
@section('subtitle', $periodLabel.' — '.$summary['start']->format('d M').' s.d. '.$summary['end']->format('d M Y'))

@section('content')
<div class="space-y-6">

    <!-- Periode switcher -->
    <div class="card card-pad flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600">
            <i class="fas fa-calendar-week text-green-600 mr-2"></i>
            Pabrik <strong>{{ $summary['plant'] }}</strong> · {{ $summary['days'] }} hari
        </p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('export.weekly-recap', ['periode' => $periode]) }}"
               class="btn-primary !min-h-0 !px-3 !py-1.5 text-xs" download>
                <i class="fas fa-file-pdf"></i> Unduh PDF
            </a>
            <a href="{{ route('analytics.weekly-recap', ['periode' => '7d']) }}"
               class="{{ $periode === '7d' ? 'btn-primary' : 'btn-ghost' }} !min-h-0 !px-3 !py-1.5 text-xs">
                7 Hari Terakhir
            </a>
            <a href="{{ route('analytics.weekly-recap', ['periode' => 'minggu_lalu']) }}"
               class="{{ $periode === 'minggu_lalu' ? 'btn-primary' : 'btn-ghost' }} !min-h-0 !px-3 !py-1.5 text-xs">
                Minggu Lalu (Sen–Min)
            </a>
        </div>
    </div>

    <!-- KPI Utama -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="card card-pad">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-float">
                    <i class="fas fa-weight-hanging"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Tonnage Bruto</p>
                    <p class="truncate text-xl font-bold text-gray-800">{{ number_format($summary['tonnage'], 2) }} ton</p>
                    <p class="text-[11px] text-gray-600">avg {{ number_format($summary['tonnage'] / max(1, $summary['days']), 2) }} ton/hari</p>
                </div>
            </div>
        </div>
        <div class="card card-pad">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-float">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Record Masuk</p>
                    <p class="truncate text-xl font-bold text-gray-800">{{ number_format($summary['records']) }}</p>
                    <p class="text-[11px] text-gray-600">avg {{ number_format($summary['records'] / max(1, $summary['days']), 1) }}/hari</p>
                </div>
            </div>
        </div>
        <div class="card card-pad">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-orange-600 text-white shadow-float">
                    <i class="fas fa-flask"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">FFA (ALB CPO)</p>
                    <p class="truncate text-xl font-bold text-gray-800">{{ $summary['ffa'] !== null ? $summary['ffa'].'%' : '—' }}</p>
                    <p class="text-[11px] text-gray-600">rata-rata {{ $summary['days'] }} hari</p>
                </div>
            </div>
        </div>
        <div class="card card-pad">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-400 to-purple-600 text-white shadow-float">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Skor Efisiensi</p>
                    <p class="truncate text-xl font-bold text-gray-800">{{ $summary['efficiency'] }}/100</p>
                    <p class="text-[11px] text-gray-600">press + sterilizer</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafik tren 7 hari -->
    <div class="card card-pad">
        <h2 class="mb-1 text-lg font-bold text-gray-800">
            <i class="fas fa-chart-column text-green-600 mr-2"></i>
            Tren Tonnage &amp; Record Harian
        </h2>
        <p class="mb-4 text-xs text-gray-600">Batang = tonnage bruto (ton) · Garis = jumlah record · Sumbu kanan untuk record</p>
        <div class="h-80">
            <canvas id="weeklyTrendChart"></canvas>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Breakdown stasiun -->
        <div class="card card-pad lg:col-span-1">
            <h2 class="mb-4 text-lg font-bold text-gray-800">
                <i class="fas fa-industry text-purple-600 mr-2"></i>
                Record per Stasiun
            </h2>
            <div class="h-64">
                <canvas id="stationBreakdownChart"></canvas>
            </div>
        </div>

        <!-- Tabel per stasiun -->
        <div class="card lg:col-span-2">
            <div class="card-pad pb-0">
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-table-list text-blue-600 mr-2"></i>
                    Detail per Stasiun
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/50 text-left text-xs uppercase tracking-wide text-gray-600">
                            <th class="px-4 py-2.5">Stasiun</th>
                            <th class="px-4 py-2.5">Record</th>
                            <th class="px-4 py-2.5">Flagged</th>
                            <th class="px-4 py-2.5">Belum Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['stations'] as $st)
                        <tr class="border-b border-white/30">
                            <td class="px-4 py-2.5 font-medium text-gray-800">{{ $st['label'] }}</td>
                            <td class="px-4 py-2.5 text-gray-800">{{ number_format($st['total']) }}</td>
                            <td class="px-4 py-2.5">
                                @if ($st['flagged'] > 0)
                                <span class="badge border-amber-200/70 bg-amber-100 text-amber-800">🚩 {{ $st['flagged'] }}</span>
                                @else
                                <span class="text-gray-600">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($st['unverified'] > 0)
                                <span class="badge border-orange-200/70 bg-orange-100 text-orange-800">⌛ {{ $st['unverified'] }}</span>
                                @else
                                <span class="text-gray-600">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                        <tr class="bg-white/40 font-bold">
                            <td class="px-4 py-2.5 text-gray-800">Total</td>
                            <td class="px-4 py-2.5 text-gray-800">{{ number_format($summary['records']) }}</td>
                            <td class="px-4 py-2.5 text-amber-700">{{ number_format($summary['flagged']) }}</td>
                            <td class="px-4 py-2.5 text-gray-800">{{ number_format($summary['unverified']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Detail harian + tiket -->
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-pad pb-0">
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-calendar-day text-teal-600 mr-2"></i>
                    Rincian Harian
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/50 text-left text-xs uppercase tracking-wide text-gray-600">
                            <th class="px-4 py-2.5">Tanggal</th>
                            <th class="px-4 py-2.5">Record</th>
                            <th class="px-4 py-2.5">Tonnage Bruto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['daily'] as $day)
                        <tr class="border-b border-white/30">
                            <td class="px-4 py-2.5 text-gray-800">{{ $day['date']->translatedFormat('l, d M Y') }}</td>
                            <td class="px-4 py-2.5 text-gray-800">{{ number_format($day['records']) }}</td>
                            <td class="px-4 py-2.5 text-gray-800">{{ number_format($day['tonnage'], 2) }} ton</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="mb-4 text-lg font-bold text-gray-800">
                <i class="fas fa-screwdriver-wrench text-rose-600 mr-2"></i>
                Tiket Maintenance
            </h2>
            <div class="space-y-3">
                <div class="glass-subtle flex items-center justify-between rounded-2xl p-3">
                    <span class="text-sm font-medium text-gray-700">Tiket baru (periode ini)</span>
                    <span class="text-lg font-bold text-gray-800">{{ number_format($summary['tickets']['created']) }}</span>
                </div>
                <div class="glass-subtle flex items-center justify-between rounded-2xl p-3">
                    <span class="text-sm font-medium text-gray-700">Tiket selesai (periode ini)</span>
                    <span class="text-lg font-bold text-gray-800">{{ number_format($summary['tickets']['done']) }}</span>
                </div>
                <div class="glass-subtle flex items-center justify-between rounded-2xl p-3">
                    <span class="text-sm font-medium text-gray-700">Masih aktif (open / dikerjakan)</span>
                    <span class="text-lg font-bold {{ $summary['tickets']['active'] > 0 ? 'text-rose-700' : 'text-gray-800' }}">{{ number_format($summary['tickets']['active']) }}</span>
                </div>
            </div>
            <a href="{{ route('maintenance.tickets') }}" class="btn-ghost mt-4 w-full">
                <i class="fas fa-arrow-right"></i> Lihat semua tiket
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const labels = {!! json_encode(collect($summary['daily'])->map(fn ($d) => $d['date']->translatedFormat('D d/m'))->all()) !!};
    const tonnage = {!! json_encode(collect($summary['daily'])->map(fn ($d) => (float) $d['tonnage'])->all()) !!};
    const records = {!! json_encode(collect($summary['daily'])->map(fn ($d) => (int) $d['records'])->all()) !!};

    // Grafik tren: bar tonnage (sumbu kiri) + line record (sumbu kanan).
    const trendCtx = document.getElementById('weeklyTrendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    type: 'line',
                    label: 'Record',
                    data: records,
                    yAxisID: 'y1',
                    borderColor: '#0ea5e9',
                    backgroundColor: PomsChart.area(trendCtx, '#0ea5e9'),
                    pointBackgroundColor: '#0ea5e9',
                    tension: 0.35,
                    fill: true,
                },
                {
                    label: 'Tonnage (ton)',
                    data: tonnage,
                    yAxisID: 'y',
                    backgroundColor: PomsChart.area(trendCtx, '#10b981', 0.85, 0.45),
                    borderColor: '#059669',
                    borderWidth: 1,
                    borderRadius: 8,
                    maxBarThickness: 42,
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
                        label: function (ctx) {
                            const v = ctx.parsed.y ?? 0;
                            return ctx.dataset.yAxisID === 'y1'
                                ? ' ' + ctx.dataset.label + ': ' + v + ' record'
                                : ' ' + ctx.dataset.label + ': ' + v.toFixed(2) + ' ton';
                        },
                    },
                },
            },
            scales: {
                y: PomsChart.axis({ beginAtZero: true, title: { display: true, text: 'Ton' } }),
                y1: PomsChart.axis({
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'Record' },
                    ticks: { precision: 0, padding: 8 },
                }),
                x: PomsChart.axis({ grid: false }),
            },
        },
    });

    // Doughnut record per stasiun.
    const stCtx = document.getElementById('stationBreakdownChart').getContext('2d');
    new Chart(stCtx, {
        type: 'doughnut',
        data: {
            labels: {!! json_encode(collect($summary['stations'])->pluck('label')->values()->all()) !!},
            datasets: [{
                data: {!! json_encode(collect($summary['stations'])->pluck('total')->values()->all()) !!},
                backgroundColor: PomsChart.palette,
                borderWidth: 2,
                borderColor: 'rgba(255, 255, 255, 0.8)',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '58%',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, padding: 10 } } },
        },
    });
});
</script>
@endpush
