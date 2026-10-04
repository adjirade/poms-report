@extends('layouts.app')

@section('title', 'Analytics Overview')
@section('subtitle', 'Ringkasan aktivitas 7 hari terakhir')

@section('content')
<div class="space-y-6">
    
    <!-- Header Stats -->
    @php
        $overviewStats = [
            ['label' => 'Total Records', 'value' => array_sum(array_column($dailyStats, 'total')), 'note' => 'Last 7 days', 'icon' => 'fa-database', 'tile' => 'from-sky-400 to-blue-600'],
            ['label' => 'Flagged', 'value' => array_sum(array_column($dailyStats, 'flagged')), 'note' => 'Needs review', 'icon' => 'fa-flag', 'tile' => 'from-amber-400 to-orange-600'],
            ['label' => 'Clean Records', 'value' => array_sum(array_column($dailyStats, 'verified')), 'note' => 'Tanpa anomali waktu', 'icon' => 'fa-check-circle', 'tile' => 'from-emerald-400 to-green-700'],
            ['label' => 'Stations', 'value' => count($stationBreakdown), 'note' => 'Active today', 'icon' => 'fa-industry', 'tile' => 'from-violet-400 to-purple-700'],
        ];
    @endphp
    <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
        @foreach($overviewStats as $s)
        <div class="card card-pad group relative overflow-hidden transition hover:-translate-y-0.5 hover:shadow-glass-lg">
            <div class="pointer-events-none absolute -right-8 -top-10 h-24 w-24 rounded-full bg-gradient-to-br {{ $s['tile'] }} opacity-20 blur-2xl transition group-hover:opacity-35"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">{{ $s['label'] }}</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $s['value'] }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ $s['note'] }}</p>
                </div>
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br {{ $s['tile'] }} text-lg text-white shadow-float">
                    <i class="fas {{ $s['icon'] }}"></i>
                </span>
            </div>
        </div>
        @endforeach
    </div>
    
    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Daily Trend Chart -->
        <div class="card card-pad">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                7 Days Trend
            </h3>
            <div class="relative" style="height: 280px;">
                <canvas id="dailyTrendChart"></canvas>
            </div>
        </div>
        
        <!-- Station Breakdown -->
        <div class="card card-pad">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-pie text-green-600 mr-2"></i>
                Station Breakdown (Today)
            </h3>
            <div class="relative" style="height: 280px;">
                <canvas id="stationBreakdownChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top Operators -->
    <div class="card card-pad">
        <h3 class="text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-users text-purple-600 mr-2"></i>
            Top Operators (All Time)
        </h3>
        <div class="space-y-3">
            @forelse($topOperators as $index => $operator)
            <div class="glass-subtle flex items-center justify-between rounded-2xl p-4">
                <div class="flex items-center space-x-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br font-bold text-white shadow-float
                        {{ $index === 0 ? 'from-amber-300 to-yellow-500' : ($index === 1 ? 'from-slate-300 to-slate-500' : ($index === 2 ? 'from-orange-400 to-orange-600' : 'from-gray-300 to-gray-400')) }}">
                        #{{ $index + 1 }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">{{ $operator['name'] }}</p>
                        <p class="text-sm text-gray-600">Total: {{ $operator['total'] }} records</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="h-2 w-32 overflow-hidden rounded-full bg-white/60 shadow-inner">
                        @php
                            $maxTotal = $topOperators->max('total') ?: 1;
                        @endphp
                        <div class="h-2 rounded-full bg-gradient-to-r from-emerald-400 to-teal-600"
                             style="width: {{ min(100, round(($operator['total'] / $maxTotal) * 100)) }}%"></div>
                    </div>
                </div>
            </div>
            @empty
            <p class="text-center text-gray-500 py-8">Belum ada data operator.</p>
            @endforelse
        </div>
    </div>
    
</div>
@endsection

@push('scripts')
<script>
// Chart.js dimuat via Vite module (deferred) -> tunggu DOMContentLoaded
document.addEventListener('DOMContentLoaded', function () {
// Daily Trend Chart
const dailyCtx = document.getElementById('dailyTrendChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode(array_column($dailyStats, 'date_label')) !!},
        datasets: [
            {
                label: 'Total Records',
                data: {!! json_encode(array_column($dailyStats, 'total')) !!},
                borderColor: '#0ea5e9',
                backgroundColor: PomsChart.area(dailyCtx, '#0ea5e9'),
                pointBackgroundColor: '#0ea5e9',
                fill: true
            },
            {
                label: 'Flagged',
                data: {!! json_encode(array_column($dailyStats, 'flagged')) !!},
                borderColor: '#f59e0b',
                backgroundColor: PomsChart.area(dailyCtx, '#f59e0b'),
                pointBackgroundColor: '#f59e0b',
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'top' }
        },
        scales: {
            y: PomsChart.axis({ beginAtZero: true, ticks: { precision: 0, padding: 8 } }),
            x: PomsChart.axis({ grid: false })
        }
    }
});

// Station Breakdown Chart
const stationCtx = document.getElementById('stationBreakdownChart').getContext('2d');
new Chart(stationCtx, {
    type: 'doughnut',
    data: {
        labels: {!! json_encode(array_map('ucfirst', array_keys($stationBreakdown))) !!},
        datasets: [{
            data: {!! json_encode(array_values($stationBreakdown)) !!},
            backgroundColor: PomsChart.palette,
            borderColor: 'rgba(255, 255, 255, 0.75)',
            borderWidth: 3,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        layout: { padding: 6 },
        plugins: {
            legend: { display: true, position: 'right' }
        }
    }
});
});
</script>
@endpush
