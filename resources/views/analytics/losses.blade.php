@extends('layouts.app')

@section('title', 'Analisis Losses')
@section('subtitle', 'Kehilangan minyak & mutu lab')

@section('content')
<div class="space-y-6">
    
    <!-- Header KPIs -->
    @php
        $lossKpis = [
            ['label' => 'Avg Losses Fiber', 'value' => number_format($avgLossesFiber, 2).'%', 'target' => 'Target: < 5.0%', 'icon' => 'fa-wind', 'tile' => 'from-rose-400 to-red-600'],
            ['label' => 'Avg Losses Jankos', 'value' => number_format($avgLossesJankos, 2).'%', 'target' => 'Target: < 1.0%', 'icon' => 'fa-seedling', 'tile' => 'from-orange-400 to-orange-600'],
            ['label' => 'Avg Kadar ALB (FFA)', 'value' => number_format($avgKadarAlb, 2).'%', 'target' => 'Target: 2.0-5.0%', 'icon' => 'fa-flask', 'tile' => 'from-amber-400 to-yellow-600'],
        ];
    @endphp
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        @foreach($lossKpis as $k)
        <div class="card card-pad group relative overflow-hidden transition hover:-translate-y-0.5 hover:shadow-glass-lg">
            <div class="pointer-events-none absolute -right-8 -top-10 h-24 w-24 rounded-full bg-gradient-to-br {{ $k['tile'] }} opacity-20 blur-2xl transition group-hover:opacity-35"></div>
            <div class="relative flex items-start justify-between">
                <div>
                    <p class="mb-2 text-sm text-gray-600">{{ $k['label'] }}</p>
                    <p class="text-4xl font-bold text-gray-800">{{ $k['value'] }}</p>
                    <p class="mt-2 text-xs text-gray-600">{{ $k['target'] }}</p>
                </div>
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br {{ $k['tile'] }} text-white shadow-float">
                    <i class="fas {{ $k['icon'] }}"></i>
                </span>
            </div>
        </div>
        @endforeach
    </div>
    
    <!-- Losses Trend Chart -->
    <div class="card card-pad">
        <h2 class="text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-chart-area text-red-600 mr-2"></i>
            Losses Trend (14 Days)
        </h2>
        <div class="relative" style="height: 280px;">
            <canvas id="lossesTrendChart"></canvas>
        </div>
    </div>
    
    <!-- Detailed Lab Data Table -->
    <div class="card">
        <div class="border-b border-white/50 px-6 py-4">
            <h2 class="text-lg font-bold text-gray-800">
                <i class="fas fa-flask mr-2 text-sky-600"></i>
                Lab Quality Control Data (Last 30 Days)
            </h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="glass-table w-full">
                <thead>
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left">Date</th>
                        <th scope="col" class="px-4 py-3 text-left">User</th>
                        <th scope="col" class="px-4 py-3 text-left">Kadar ALB (FFA)</th>
                        <th scope="col" class="px-4 py-3 text-left">Losses Fiber</th>
                        <th scope="col" class="px-4 py-3 text-left">Losses Jankos</th>
                        <th scope="col" class="px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/40">
                    @forelse($labTable as $data)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            {{ $data->timestamp_kirim->format('d M Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            {{ $data->user->name }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="badge {{ $data->kadar_alb_cpo >= 2 && $data->kadar_alb_cpo <= 5 ? 'border-green-200/70 bg-green-100 text-green-800' : 'border-red-200/70 bg-red-100 text-red-800' }}">
                                {{ number_format($data->kadar_alb_cpo, 2) }}%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="badge {{ $data->losses_fiber_persen < 5 ? 'border-green-200/70 bg-green-100 text-green-800' : 'border-red-200/70 bg-red-100 text-red-800' }}">
                                {{ number_format($data->losses_fiber_persen, 2) }}%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="badge {{ $data->losses_jankos_persen < 1 ? 'border-green-200/70 bg-green-100 text-green-800' : 'border-red-200/70 bg-red-100 text-red-800' }}">
                                {{ number_format($data->losses_jankos_persen, 2) }}%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if($data->is_verified)
                                <i class="fas fa-check-circle text-green-600"></i>
                            @else
                                <i class="fas fa-clock text-orange-600"></i>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-600">
                            No lab data available
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
</div>
@endsection

@push('scripts')
<script>
// Chart.js dimuat via Vite module (deferred) -> tunggu DOMContentLoaded
document.addEventListener('DOMContentLoaded', function () {
const lossesCtx = document.getElementById('lossesTrendChart').getContext('2d');
new Chart(lossesCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode($lossesData->pluck('date')) !!},
        datasets: [
            {
                label: 'Losses Fiber (%)',
                data: {!! json_encode($lossesData->pluck('fiber')) !!},
                borderColor: '#ef4444',
                backgroundColor: PomsChart.area(lossesCtx, '#ef4444'),
                pointBackgroundColor: '#ef4444',
                fill: true
            },
            {
                label: 'Losses Jankos (%)',
                data: {!! json_encode($lossesData->pluck('jankos')) !!},
                borderColor: '#f59e0b',
                backgroundColor: PomsChart.area(lossesCtx, '#f59e0b'),
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
            y: PomsChart.axis({
                beginAtZero: true,
                ticks: {
                    padding: 8,
                    callback: function(value) { return value + '%'; }
                }
            }),
            x: PomsChart.axis({ grid: false })
        }
    }
});
});
</script>
@endpush
