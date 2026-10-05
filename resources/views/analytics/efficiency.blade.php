@extends('layouts.app')

@section('title', 'Efisiensi Produksi')
@section('subtitle', 'Kinerja press & sterilizer')

@section('content')
<div class="space-y-6">

    {{-- Efficiency Score --}}
    <div class="card glass-sheen relative overflow-hidden p-6 text-center sm:p-8">
        <div class="pointer-events-none absolute -left-16 -top-20 h-64 w-64 rounded-full bg-emerald-400/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-16 -bottom-24 h-64 w-64 rounded-full bg-sky-400/25 blur-3xl"></div>
        <div class="relative">
            <p class="mb-4 text-lg text-gray-600">Overall Efficiency Score</p>
            <div class="relative inline-block">
                <svg class="h-48 w-48" viewBox="0 0 192 192">
                    <defs>
                        <linearGradient id="effGrad" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#34d399"/>
                            <stop offset="55%" stop-color="#10b981"/>
                            <stop offset="100%" stop-color="#0d9488"/>
                        </linearGradient>
                    </defs>
                    <circle cx="96" cy="96" r="80" fill="none" stroke="rgba(15, 23, 42, 0.08)" stroke-width="12"/>
                    <circle cx="96" cy="96" r="80" fill="none" stroke="url(#effGrad)" stroke-width="12"
                            stroke-dasharray="{{ ($efficiencyScore/100) * 502.4 }} 502.4"
                            transform="rotate(-90 96 96)" stroke-linecap="round"/>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-6xl font-bold text-gray-800">{{ round($efficiencyScore) }}%</span>
                </div>
            </div>
            <p class="mt-4 text-gray-600">
                @if($efficiencyScore >= 90)
                    <i class="fas fa-trophy mr-2 text-amber-500"></i>Excellent Performance!
                @elseif($efficiencyScore >= 75)
                    <i class="fas fa-thumbs-up mr-2 text-green-600"></i>Good Performance
                @else
                    <i class="fas fa-exclamation-triangle mr-2 text-orange-500"></i>Needs Improvement
                @endif
            </p>
        </div>
    </div>

    {{-- Key Metrics --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
        @php
            $metrics = [
                ['label' => 'Tekanan Press', 'value' => number_format($avgTekananPress, 1), 'unit' => 'Kg/cm² (Target: 60-75)', 'icon' => 'fa-compress', 'tile' => 'from-sky-400 to-blue-600', 'bar' => 'from-sky-400 to-blue-500', 'pct' => min(100, ($avgTekananPress/75)*100)],
                ['label' => 'Ampere Motor', 'value' => number_format($avgAmpereMotor, 1), 'unit' => 'A (Target: 35-45)', 'icon' => 'fa-bolt', 'tile' => 'from-amber-400 to-yellow-600', 'bar' => 'from-amber-400 to-yellow-500', 'pct' => min(100, ($avgAmpereMotor/45)*100)],
                ['label' => 'Tekanan Sterilizer', 'value' => number_format($avgTekananSterilizer, 1), 'unit' => 'Bar (Target: 1.5-3.2)', 'icon' => 'fa-tachometer-alt', 'tile' => 'from-rose-400 to-red-600', 'bar' => 'from-rose-400 to-red-500', 'pct' => min(100, ($avgTekananSterilizer/3.2)*100)],
                ['label' => 'Suhu Sterilizer', 'value' => number_format($avgSuhuSterilizer, 0).'°', 'unit' => 'Celsius (Target: 110-145)', 'icon' => 'fa-thermometer-half', 'tile' => 'from-orange-400 to-orange-600', 'bar' => 'from-orange-400 to-orange-500', 'pct' => min(100, ($avgSuhuSterilizer/145)*100)],
            ];
        @endphp
        @foreach($metrics as $m)
        <div class="card card-pad transition hover:-translate-y-0.5 hover:shadow-glass-lg">
            <div class="mb-2 flex items-center justify-between">
                <p class="text-sm text-gray-600">{{ $m['label'] }}</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-2xl bg-gradient-to-br {{ $m['tile'] }} text-white shadow-float">
                    <i class="fas {{ $m['icon'] }} text-sm"></i>
                </span>
            </div>
            <p class="text-3xl font-bold text-gray-800">{{ $m['value'] }}</p>
            <p class="mt-1 text-xs text-gray-600">{{ $m['unit'] }}</p>
            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-white/60 shadow-inner">
                <div class="h-2 rounded-full bg-gradient-to-r {{ $m['bar'] }}" style="width: {{ $m['pct'] }}%"></div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Performance Charts --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card card-pad">
            <h2 class="mb-4 text-lg font-bold text-gray-800">
                <i class="fas fa-chart-line mr-2 text-sky-600"></i>
                Press Performance (Last 7 Days)
            </h2>
            <div class="relative" style="height: 280px;">
                <canvas id="pressChart"></canvas>
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="mb-4 text-lg font-bold text-gray-800">
                <i class="fas fa-chart-line mr-2 text-red-500"></i>
                Sterilizer Performance (Last 7 Days)
            </h2>
            <div class="relative" style="height: 280px;">
                <canvas id="sterilizerChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Detailed Data Tables --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b border-white/50 px-6 py-4">
                <h2 class="text-lg font-bold text-gray-800"><i class="fas fa-compress mr-2 text-sky-600"></i>Press Station Data</h2>
            </div>
            <div class="max-h-96 overflow-x-auto">
                <table class="glass-table w-full text-sm">
                    <thead class="sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left">Time</th>
                            <th class="px-4 py-2 text-left">Tekanan</th>
                            <th class="px-4 py-2 text-left">Ampere</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/40">
                        @foreach($pressTable as $data)
                        <tr>
                            <td class="px-4 py-2">{{ $data->timestamp_kirim->format('d/m H:i') }}</td>
                            <td class="px-4 py-2">{{ $data->tekanan_hidrolik }}</td>
                            <td class="px-4 py-2">{{ $data->ampere_motor }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-white/50 px-6 py-4">
                <h2 class="text-lg font-bold text-gray-800"><i class="fas fa-fire mr-2 text-red-500"></i>Sterilizer Station Data</h2>
            </div>
            <div class="max-h-96 overflow-x-auto">
                <table class="glass-table w-full text-sm">
                    <thead class="sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left">Time</th>
                            <th class="px-4 py-2 text-left">Tekanan</th>
                            <th class="px-4 py-2 text-left">Suhu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/40">
                        @foreach($sterTable as $data)
                        <tr>
                            <td class="px-4 py-2">{{ $data->timestamp_kirim->format('d/m H:i') }}</td>
                            <td class="px-4 py-2">{{ $data->tekanan_bar }}</td>
                            <td class="px-4 py-2">{{ $data->suhu_celcius }}°C</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// Chart.js dimuat via Vite module (deferred) -> tunggu DOMContentLoaded
document.addEventListener('DOMContentLoaded', function () {
// Press Chart — rata-rata harian (kronologis)
const pressCtx = document.getElementById('pressChart').getContext('2d');
new Chart(pressCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode($dailyPress->pluck('label')) !!},
        datasets: [
            {
                label: 'Tekanan (Kg/cm²)',
                data: {!! json_encode($dailyPress->pluck('tekanan')) !!},
                borderColor: '#0ea5e9',
                backgroundColor: PomsChart.area(pressCtx, '#0ea5e9'),
                pointBackgroundColor: '#0ea5e9',
                fill: true
            },
            {
                label: 'Ampere Motor (A)',
                data: {!! json_encode($dailyPress->pluck('ampere')) !!},
                borderColor: '#f59e0b',
                backgroundColor: PomsChart.area(pressCtx, '#f59e0b'),
                pointBackgroundColor: '#f59e0b',
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'top' } },
        scales: {
            y: PomsChart.axis({ beginAtZero: false }),
            x: PomsChart.axis({ grid: false })
        }
    }
});

// Sterilizer Chart — rata-rata harian (kronologis), dual-axis
const sterilizerCtx = document.getElementById('sterilizerChart').getContext('2d');
new Chart(sterilizerCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode($dailySterilizer->pluck('label')) !!},
        datasets: [
            {
                label: 'Tekanan (Bar)',
                data: {!! json_encode($dailySterilizer->pluck('tekanan')) !!},
                borderColor: '#ef4444',
                backgroundColor: PomsChart.area(sterilizerCtx, '#ef4444'),
                pointBackgroundColor: '#ef4444',
                yAxisID: 'y',
                fill: true
            },
            {
                label: 'Suhu (°C)',
                data: {!! json_encode($dailySterilizer->pluck('suhu')) !!},
                borderColor: '#f97316',
                backgroundColor: PomsChart.area(sterilizerCtx, '#f97316'),
                pointBackgroundColor: '#f97316',
                yAxisID: 'y1',
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: PomsChart.axis({ type: 'linear', position: 'left', min: 0, max: 5 }),
            y1: PomsChart.axis({ type: 'linear', position: 'right', min: 100, max: 150, grid: { drawOnChartArea: false } }),
            x: PomsChart.axis({ grid: false })
        }
    }
});
});
</script>
@endpush
