@extends('layouts.app')

@section('title', 'Performa Stasiun')
@section('subtitle', 'Chart detail multi-series '.$stationTitle)

@section('content')
<div class="space-y-6">

    <!-- Kontrol: stasiun + rentang waktu -->
    <div class="card card-pad">
        <form method="GET" action="{{ route('analytics.station-performance') }}" class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:gap-3">
                <label class="flex flex-col gap-1.5 text-sm font-medium text-gray-600">
                    Stasiun
                    <select name="station" onchange="this.form.submit()" class="input sm:w-56">
                        @foreach($stationKeys as $key)
                            <option value="{{ $key }}" {{ $key === $station ? 'selected' : '' }}>
                                {{ \App\Support\StationChartConfig::title($key) }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-center gap-2 pb-1 text-xs text-gray-500">
                    <i class="fas fa-clock text-green-600"></i>
                    Rentang: {{ $range }} hari terakhir &middot; {{ $totalInRange }} record
                    <span class="badge badge-neutral">🚩 {{ $flaggedInRange }} flagged</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach(\App\Support\StationChartConfig::RANGES as $r)
                    @if($r === $range)
                        <button type="button" aria-current="true" class="chip !border-green-700 !bg-green-700 !font-semibold !text-white">{{ $r }} hari</button>
                    @else
                        <a href="{{ route('analytics.station-performance', ['station' => $station, 'range' => $r]) }}"
                           class="chip transition hover:!border-green-600 hover:text-gray-900">{{ $r }} hari</a>
                    @endif
                @endforeach
            </div>
        </form>
    </div>

    <!-- Perbandingan hari ini vs kemarin -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @forelse($comparison as $c)
        <div class="card card-pad">
            <p class="text-sm font-medium text-gray-600">{{ $c['label'] }}</p>
            <p class="mt-1 text-2xl font-bold text-gray-800">
                {{ $c['today'] !== null ? number_format($c['today'], 2) : '—' }}
                @if($c['unit'])
                    <span class="text-sm font-medium text-gray-500">{{ $c['unit'] }}</span>
                @endif
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Kemarin: {{ $c['yesterday'] !== null ? number_format($c['yesterday'], 2) : '—' }}
                @if($c['delta_pct'] !== null)
                    @php
                        $up = $c['delta_pct'] >= 0;
                    @endphp
                    <span class="ml-1 font-semibold {{ $up ? 'text-emerald-700' : 'text-rose-700' }}">
                        <i class="fas fa-arrow-{{ $up ? 'up' : 'down' }}"></i> {{ abs($c['delta_pct']) }}%
                    </span>
                @endif
            </p>
        </div>
        @empty
        <div class="card card-pad col-span-4">
            <p class="text-sm text-gray-500">Konfigurasi series tidak tersedia untuk stasiun ini.</p>
        </div>
        @endforelse
    </div>

    <!-- Chart utama -->
    <div class="card card-pad">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold text-gray-800">
                <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                Tren Harian — {{ $stationTitle }}
            </h2>
            <div class="flex items-center gap-2">
                <span class="hidden text-xs text-gray-500 sm:inline">
                    <i class="fas fa-arrows-left-right"></i> Drag area untuk zoom &middot; Ctrl+scroll
                </span>
                <button type="button" onclick="PomsStationChart.reset('stationPerformanceChart')"
                        class="btn-ghost !min-h-0 !px-3 !py-1.5 text-xs">
                    <i class="fas fa-rotate-left"></i> Reset zoom
                </button>
                @can('export-data')
                {{-- Unduh PDF: chart dirender browser (PNG base64) lalu diposting ke ExportController --}}
                <form id="pdfExportForm" method="POST" action="{{ route('export.station-performance') }}" class="inline">
                    @csrf
                    <input type="hidden" name="station" value="{{ $station }}">
                    <input type="hidden" name="range" value="{{ $range }}">
                    <input type="hidden" name="chart_image" id="chartImageInput" value="">
                    <button type="submit" onclick="fillChartImage(event)"
                            class="btn-primary !min-h-0 !px-3 !py-1.5 text-xs">
                        <i class="fas fa-file-pdf"></i> Unduh PDF
                    </button>
                </form>
                @endcan
            </div>
        </div>
        <div class="relative" style="height: 340px;">
            <canvas id="stationPerformanceChart" role="img"
                    aria-label="Grafik garis tren harian {{ $stationTitle }} selama {{ $range }} hari terakhir"></canvas>
            <p class="sr-only">
                Ringkasan: {{ $totalInRange }} record pada rentang {{ $range }} hari, {{ $flaggedInRange }} di antaranya flagged.
                Grafik menampilkan rata-rata harian parameter: {{ collect($chart['series'])->pluck('label')->implode(', ') }}.
            </p>
        </div>
    </div>

    <!-- Volume harian + status verifikasi -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card card-pad">
            <h2 class="mb-4 text-lg font-bold text-gray-800">
                <i class="fas fa-chart-column text-rose-600 mr-2"></i>
                Volume Harian — Record Masuk & Flagged
            </h2>
            <div class="relative" style="height: 260px;">
                <canvas id="stationVolumeChart" role="img"
                        aria-label="Grafik batang jumlah record masuk dan flagged per hari"></canvas>
            </div>
        </div>
        <div class="card card-pad">
            <h2 class="mb-4 text-lg font-bold text-gray-800">
                <i class="fas fa-chart-pie text-emerald-600 mr-2"></i>
                Status Verifikasi — {{ $range }} hari
            </h2>
            <div class="relative" style="height: 260px;">
                <canvas id="stationStatusChart" role="img"
                        aria-label="Grafik doughnut status verifikasi record"></canvas>
            </div>
        </div>
    </div>

    <!-- Target vs Realisasi (A6) -->
    <div class="card card-pad">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold text-gray-800">
                <i class="fas fa-bullseye text-green-700 mr-2"></i>
                Target vs Realisasi — {{ $stationTitle }} ({{ $range }} hari)
            </h2>
            <span class="text-xs text-gray-500">Realisasi = rata-rata rentang {{ $range }} hari</span>
        </div>
        @if(count($targetProgress) === 0)
            <p class="text-sm text-gray-500">Stasiun ini belum memiliki target KPI terkonfigurasi.</p>
        @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($targetProgress as $t)
            @php
                $ok = $t['achieved'] === true;
                $none = $t['achieved'] === null;
                $bar = $none ? 'bg-slate-300' : ($ok ? 'bg-emerald-500' : 'bg-rose-500');
            @endphp
            <div class="rounded-2xl border border-white/50 bg-white/60 p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">{{ $t['label'] }}</p>
                        <p class="text-xs text-gray-500">Target {{ $t['target_text'] }} @if($t['unit']){{ $t['unit'] }}@endif</p>
                    </div>
                    @if($none)
                        <span class="badge badge-muted">— Data</span>
                    @elseif($ok)
                        <span class="badge border-emerald-200 bg-emerald-100 text-emerald-800">✓ Tercapai</span>
                    @else
                        <span class="badge border-rose-200 bg-rose-100 text-rose-800">✗ Belum</span>
                    @endif
                </div>
                <p class="mt-2 text-2xl font-bold {{ $none ? 'text-gray-500' : ($ok ? 'text-emerald-700' : 'text-rose-700') }}">
                    {{ $t['actual'] !== null ? number_format($t['actual'], 2) : '—' }}
                    @if($t['unit'])<span class="text-sm font-medium text-gray-500">{{ $t['unit'] }}</span>@endif
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

    <!-- Analisis per shift -->
    <div class="card card-pad">
        <h2 class="mb-4 text-lg font-bold text-gray-800">
            <i class="fas fa-layer-group text-amber-600 mr-2"></i>
            Analisis per Shift — {{ $stationTitle }} ({{ $range }} hari)
        </h2>
        <div class="overflow-x-auto">
            <table class="glass-table w-full text-left text-sm">
                <caption class="sr-only">Perbandingan performa per shift</caption>
                <thead>
                    <tr class="border-b border-white/40">
                        <th scope="col" class="px-3 py-2.5">Shift</th>
                        <th scope="col" class="px-3 py-2.5">Jam Kerja</th>
                        <th scope="col" class="px-3 py-2.5">Record</th>
                        <th scope="col" class="px-3 py-2.5">Flagged</th>
                        @foreach($chart['series'] as $s)
                            <th scope="col" class="px-3 py-2.5">{{ $s['label'] }}@if($s['unit']) ({{ $s['unit'] }})@endif</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($shiftBreakdown as $shift)
                    <tr class="border-b border-white/30">
                        <td class="whitespace-nowrap px-3 py-2.5 font-semibold text-gray-800">{{ $shift['label'] }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-500">{{ $shift['hours'] }}</td>
                        <td class="px-3 py-2.5 font-medium text-gray-800">{{ number_format($shift['total']) }}</td>
                        <td class="px-3 py-2.5">
                            @if($shift['flagged'] > 0)
                                <span class="badge border-rose-200 bg-rose-100 text-rose-800">🚩 {{ $shift['flagged'] }}</span>
                            @else
                                <span class="text-gray-500">0</span>
                            @endif
                        </td>
                        @foreach($chart['series'] as $s)
                            <td class="px-3 py-2.5 text-gray-800">
                                {{ $shift['avg'][$s['key']] !== null ? number_format($shift['avg'][$s['key']], 2) : '—' }}
                            </td>
                        @endforeach
                    </tr>
                    @empty
                    <tr><td colspan="{{ 4 + count($chart['series']) }}" class="px-3 py-8 text-center text-gray-500">Belum ada data pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabel detail -->
    <div class="card card-pad">
        <h2 class="mb-4 text-lg font-bold text-gray-800">
            <i class="fas fa-table-list text-purple-600 mr-2"></i>
            Detail Record — {{ $stationTitle }}
        </h2>
        <div class="overflow-x-auto">
            <table class="glass-table w-full text-left text-sm">
                <caption class="sr-only">Detail record {{ $stationTitle }} dalam {{ $range }} hari terakhir</caption>
                <thead>
                    <tr class="border-b border-white/40">
                        <th scope="col" class="px-3 py-2.5">Waktu Kirim</th>
                        @foreach($tableColumns as $col)
                            <th scope="col" class="px-3 py-2.5">{{ $col['label'] }}</th>
                        @endforeach
                        <th scope="col" class="px-3 py-2.5">Operator</th>
                        <th scope="col" class="px-3 py-2.5">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                    <tr class="border-b border-white/30">
                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-700">
                            {{ $record->timestamp_kirim->format('d/m/Y H:i') }}
                        </td>
                        @foreach($tableColumns as $col)
                            <td class="px-3 py-2.5 text-gray-800">
                                @php
                                    $val = $record->{$col['key']};
                                @endphp
                                @if($val === null || $val === '')
                                    <span class="text-gray-400">—</span>
                                @elseif(is_numeric($val) && ! in_array($col['key'], ['no_spb', 'no_rebusan', 'no_press', 'no_tangki'], true))
                                    {{ number_format((float) $val, 2) }} @if(! empty($col['unit']))<span class="text-gray-500">{{ $col['unit'] }}</span>@endif
                                @else
                                    {{ $val }}
                                @endif
                            </td>
                        @endforeach
                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-700">{{ $record->user->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5">
                            @if($record->is_flagged)
                                <span class="badge border-rose-200 bg-rose-100 text-rose-800">🚩 Flagged</span>
                            @elseif($record->is_verified)
                                <span class="badge border-emerald-200 bg-emerald-100 text-emerald-800">✓ Terverifikasi</span>
                            @else
                                <span class="badge badge-muted">Menunggu</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ count($tableColumns) + 3 }}" class="px-3 py-10 text-center text-gray-500">
                            Belum ada record pada rentang {{ $range }} hari terakhir.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $records->links() }}
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    PomsStationChart.render('stationPerformanceChart', {
        labels: @json($chart['labels']),
        series: @json($chart['series']),
        counts: @json($chart['counts']),
    });

    PomsStationChart.renderVolume('stationVolumeChart', {
        labels: @json($chart['labels']),
        counts: @json($chart['counts']),
    });

    PomsStationChart.renderStatus('stationStatusChart', @json($statusBreakdown));
});

// Kirim grafik utama sebagai PNG base64 ke ExportController (di-embed DomPDF).
function fillChartImage(event) {
    const input = document.getElementById('chartImageInput');
    const chart = PomsStationChart && PomsStationChart._instances['stationPerformanceChart'];
    if (input && chart && typeof chart.toBase64Image === 'function') {
        input.value = chart.toBase64Image('image/png', 1.0);
    }
}
</script>
@endpush
