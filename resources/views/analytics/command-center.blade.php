@extends('layouts.app')

@section('title', 'Command Center')
@section('subtitle', 'Ringkasan operasional pabrik dalam satu layar')

@section('content')
@php
    $statusMeta = [
        'ok' => ['label' => 'Normal', 'ring' => 'ring-emerald-400/70', 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'bg' => 'bg-emerald-100/80'],
        'warning' => ['label' => 'Ada Pending', 'ring' => 'ring-amber-400/70', 'dot' => 'bg-amber-500', 'text' => 'text-amber-700', 'bg' => 'bg-amber-100/80'],
        'danger' => ['label' => 'Flagged', 'ring' => 'ring-rose-400/70', 'dot' => 'bg-rose-500', 'text' => 'text-rose-700', 'bg' => 'bg-rose-100/80'],
        'idle' => ['label' => 'Belum Ada Data', 'ring' => 'ring-slate-300', 'dot' => 'bg-slate-400', 'text' => 'text-slate-600', 'bg' => 'bg-slate-100/80'],
    ];
@endphp
<div class="space-y-6">

    <!-- Aksi -->
    <div class="flex flex-wrap items-center justify-end gap-2">
        @can('export-data')
        <a href="{{ route('export.command-center') }}" class="btn-primary !min-h-0 !px-3 !py-1.5 text-xs">
            <i class="fas fa-file-pdf"></i> Unduh PDF
        </a>
        @endcan
    </div>

    <!-- KPI utama hari ini -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 xl:grid-cols-6">
        <div class="card card-pad">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Record Hari Ini</p>
            <p class="mt-1 text-2xl font-bold text-gray-800">{{ number_format($kpi['records_today']) }}</p>
        </div>
        <div class="card card-pad">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Tonnage Bruto (ton)</p>
            <p class="mt-1 text-2xl font-bold text-blue-700">{{ number_format($kpi['tonnage_today'], 2) }}</p>
        </div>
        <div class="card card-pad">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">FFA / ALB CPO (%)</p>
            <p class="mt-1 text-2xl font-bold {{ ($kpi['ffa_today'] ?? 0) > 5 ? 'text-rose-700' : 'text-emerald-700' }}">
                {{ $kpi['ffa_today'] !== null ? number_format($kpi['ffa_today'], 2) : '—' }}
            </p>
        </div>
        <div class="card card-pad">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Losses Fiber (%)</p>
            <p class="mt-1 text-2xl font-bold {{ ($kpi['losses_fiber_today'] ?? 0) > 5 ? 'text-rose-700' : 'text-emerald-700' }}">
                {{ $kpi['losses_fiber_today'] !== null ? number_format($kpi['losses_fiber_today'], 2) : '—' }}
            </p>
        </div>
        <div class="card card-pad">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Skor Efisiensi</p>
            <p class="mt-1 text-2xl font-bold text-purple-700">{{ $kpi['efficiency'] }}/100</p>
        </div>
        <div class="card card-pad">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">🚩 Flagged / ⌛ Pending</p>
            <p class="mt-1 text-2xl font-bold text-gray-800">
                <span class="text-rose-700">{{ number_format($kpi['flagged_today']) }}</span>
                <span class="text-gray-600">/</span>
                <span class="text-amber-700">{{ number_format($kpi['unverified_today']) }}</span>
            </p>
        </div>
    </div>

    <!-- Target vs Realisasi KPI (A6) -->
    <div class="card card-pad">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-800">
                <i class="fas fa-bullseye text-green-700 mr-2"></i>
                Target vs Realisasi — Hari Ini
            </h2>
            <span class="text-xs text-gray-600">Rata-rata parameter hari ini</span>
        </div>
        @if(count($kpiTargets) === 0)
            <p class="text-sm text-gray-600">Belum ada target KPI terkonfigurasi.</p>
        @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($kpiTargets as $t)
            @php
                $ok = $t['achieved'] === true;
                $none = $t['achieved'] === null;
                $bar = $none ? 'bg-slate-300' : ($ok ? 'bg-emerald-500' : 'bg-rose-500');
            @endphp
            <div class="rounded-2xl border border-white/50 bg-white/60 p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">{{ $t['label'] }}</p>
                        <p class="text-xs text-gray-600">Target {{ $t['target_text'] }} @if($t['unit']){{ $t['unit'] }}@endif</p>
                    </div>
                    @if($none)
                        <span class="badge badge-muted">— Data</span>
                    @elseif($ok)
                        <span class="badge border-emerald-200 bg-emerald-100 text-emerald-800">✓ Tercapai</span>
                    @else
                        <span class="badge border-rose-200 bg-rose-100 text-rose-800">✗ Belum</span>
                    @endif
                </div>
                <p class="mt-2 text-2xl font-bold {{ $none ? 'text-gray-600' : ($ok ? 'text-emerald-700' : 'text-rose-700') }}">
                    {{ $t['actual'] !== null ? number_format($t['actual'], 2) : '—' }}
                    @if($t['unit'])<span class="text-sm font-medium text-gray-600">{{ $t['unit'] }}</span>@endif
                </p>
                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-200"
                     role="progressbar" aria-valuenow="{{ $t['progress'] }}" aria-valuemin="0" aria-valuemax="100"
                     aria-label="Capaian target {{ $t['label'] }}: {{ $t['progress'] }} persen">
                    <div class="h-full rounded-full {{ $bar }}" style="width: {{ $t['progress'] }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <!-- Status 8 stasiun -->
    <div class="card card-pad">
        <h2 class="mb-4 text-lg font-bold text-gray-800">
            <i class="fas fa-gauge-high text-green-700 mr-2"></i>
            Status Stasiun — Hari Ini
        </h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($stationStatus as $st)
            @php $meta = $statusMeta[$st['status']]; @endphp
            <a href="{{ route('analytics.station-performance', ['station' => $st['station'], 'range' => 7]) }}"
               class="rounded-2xl border border-white/50 bg-white/60 p-4 ring-2 transition hover:shadow-float {{ $meta['ring'] }}">
                <div class="flex items-center justify-between">
                    <p class="truncate font-semibold text-gray-800">{{ $st['title'] }}</p>
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $meta['dot'] }}"></span>
                </div>
                <p class="mt-1 text-xs font-medium {{ $meta['text'] }}">{{ $meta['label'] }}</p>
                <div class="mt-3 flex gap-3 text-xs text-gray-600">
                    <span><strong class="text-gray-800">{{ $st['total'] }}</strong> record</span>
                    @if($st['flagged'] > 0)<span class="text-rose-700"><strong>🚩 {{ $st['flagged'] }}</strong></span>@endif
                    @if($st['unverified'] > 0)<span class="text-amber-700"><strong>⌛ {{ $st['unverified'] }}</strong></span>@endif
                </div>
            </a>
            @endforeach
        </div>
    </div>

    <!-- Tren 7 hari -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card card-pad">
            <h2 class="mb-4 text-lg font-bold text-gray-800">
                <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                Aktivitas 7 Hari (record &amp; flagged)
            </h2>
            <div class="relative" style="height: 260px;">
                <canvas id="ccActivityChart" role="img" aria-label="Grafik garis aktivitas dan flagged tujuh hari terakhir"></canvas>
            </div>
        </div>
        <div class="card card-pad">
            <h2 class="mb-4 text-lg font-bold text-gray-800">
                <i class="fas fa-truck-fast text-emerald-600 mr-2"></i>
                Tonnage Bruto 7 Hari (ton)
            </h2>
            <div class="relative" style="height: 260px;">
                <canvas id="ccTonnageChart" role="img" aria-label="Grafik batang tonnage bruto tujuh hari terakhir"></canvas>
            </div>
        </div>
    </div>

    <!-- Alert terbaru -->
    <div class="card card-pad">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-800">
                <i class="fas fa-bell text-rose-600 mr-2"></i>
                Alert Flagged Terbaru
            </h2>
            <a href="{{ route('flagged.records') }}" class="inline-flex items-center py-1 text-sm font-medium text-green-700 hover:underline">Lihat semua →</a>
        </div>
        <ul class="space-y-2">
            @forelse($recentAlerts as $alert)
            <li class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-white/50 bg-white/60 px-4 py-3">
                <div class="min-w-0">
                    <span class="badge border-rose-200 bg-rose-100 text-rose-800">{{ ucfirst($alert['station']) }} #{{ $alert['id'] }}</span>
                    <span class="ml-2 text-sm text-gray-700">{{ $alert['user'] }}</span>
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-600">
                    <span><i class="far fa-clock"></i> {{ $alert['at']?->format('d/m H:i') }}</span>
                    @if($alert['diff'])<span><i class="fas fa-stopwatch"></i> {{ $alert['diff'] }} jam</span>@endif
                    @if($alert['verified'])
                        <span class="font-semibold text-emerald-700">✓ Verified</span>
                    @else
                        <span class="font-semibold text-amber-700">Pending</span>
                    @endif
                </div>
            </li>
            @empty
            <li class="rounded-2xl bg-emerald-100/60 px-4 py-6 text-center text-sm font-medium text-emerald-800">
                <i class="fas fa-check-circle mb-1 text-2xl text-emerald-600"></i><br>
                Tidak ada alert flagged.
            </li>
            @endforelse
        </ul>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') { return; }

    const activity = @json($dailyTrend);
    const tonnage = @json($tonnageTrend);

    new Chart(document.getElementById('ccActivityChart'), {
        type: 'line',
        data: {
            labels: activity.map((d) => d.label),
            datasets: [
                {
                    label: 'Record',
                    data: activity.map((d) => d.total),
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14, 165, 233, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                },
                {
                    label: 'Flagged',
                    data: activity.map((d) => d.flagged),
                    borderColor: '#f43f5e',
                    backgroundColor: 'rgba(244, 63, 94, 0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { grid: { display: false } },
            },
        },
    });

    new Chart(document.getElementById('ccTonnageChart'), {
        type: 'bar',
        data: {
            labels: tonnage.map((d) => d.label),
            datasets: [{
                label: 'Bruto (ton)',
                data: tonnage.map((d) => d.bruto),
                backgroundColor: 'rgba(16, 185, 129, 0.65)',
                borderColor: '#10b981',
                borderWidth: 1,
                borderRadius: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true },
                x: { grid: { display: false } },
            },
        },
    });
});
</script>
@endpush
