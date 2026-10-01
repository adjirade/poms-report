@extends('layouts.app')

@section('content')
<div class="space-y-6">
    
    <!-- Header KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-br from-red-500 to-red-600 text-white rounded-lg shadow-lg p-6">
            <p class="text-red-100 text-sm mb-2">Avg Losses Fiber</p>
            <p class="text-4xl font-bold">{{ number_format($avgLossesFiber, 2) }}%</p>
            <p class="text-red-100 text-xs mt-2">Target: < 5.0%</p>
        </div>
        
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-lg shadow-lg p-6">
            <p class="text-orange-100 text-sm mb-2">Avg Losses Jankos</p>
            <p class="text-4xl font-bold">{{ number_format($avgLossesJankos, 2) }}%</p>
            <p class="text-orange-100 text-xs mt-2">Target: < 1.0%</p>
        </div>
        
        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 text-white rounded-lg shadow-lg p-6">
            <p class="text-yellow-100 text-sm mb-2">Avg Kadar ALB (FFA)</p>
            <p class="text-4xl font-bold">{{ number_format($avgKadarAlb, 2) }}%</p>
            <p class="text-yellow-100 text-xs mt-2">Target: 2.0-5.0%</p>
        </div>
    </div>
    
    <!-- Losses Trend Chart -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-chart-area text-red-600 mr-2"></i>
            Losses Trend (14 Days)
        </h3>
        <canvas id="lossesTrendChart" height="100"></canvas>
    </div>
    
    <!-- Detailed Lab Data Table -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-800">
                <i class="fas fa-flask text-blue-600 mr-2"></i>
                Lab Quality Control Data (Last 30 Days)
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700">User</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700">Kadar ALB (FFA)</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700">Losses Fiber</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700">Losses Jankos</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($labData as $data)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-900">
                            {{ $data->timestamp_kirim->format('d M Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            {{ $data->user->name }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="px-2 py-1 rounded text-xs font-semibold
                                {{ $data->kadar_alb_cpo >= 2 && $data->kadar_alb_cpo <= 5 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ number_format($data->kadar_alb_cpo, 2) }}%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="px-2 py-1 rounded text-xs font-semibold
                                {{ $data->losses_fiber_persen < 5 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ number_format($data->losses_fiber_persen, 2) }}%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="px-2 py-1 rounded text-xs font-semibold
                                {{ $data->losses_jankos_persen < 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
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
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">
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
                borderColor: 'rgb(239, 68, 68)',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Losses Jankos (%)',
                data: {!! json_encode($lossesData->pluck('jankos')) !!},
                borderColor: 'rgb(249, 115, 22)',
                backgroundColor: 'rgba(249, 115, 22, 0.1)',
                tension: 0.4,
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
            y: { 
                beginAtZero: true,
                ticks: { 
                    callback: function(value) { return value + '%'; }
                }
            }
        }
    }
});
});
</script>
@endpush
