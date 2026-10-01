@extends('layouts.app')

@section('content')
<div class="space-y-6">
    
    <!-- Efficiency Score -->
    <div class="bg-gradient-to-br from-green-500 to-green-700 text-white rounded-lg shadow-xl p-8">
        <div class="text-center">
            <p class="text-green-100 text-lg mb-4">Overall Efficiency Score</p>
            <div class="relative inline-block">
                <svg class="w-48 h-48">
                    <circle cx="96" cy="96" r="80" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="12"/>
                    <circle cx="96" cy="96" r="80" fill="none" stroke="white" stroke-width="12" 
                            stroke-dasharray="{{ ($efficiencyScore/100) * 502.4 }} 502.4" 
                            transform="rotate(-90 96 96)" stroke-linecap="round"/>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-6xl font-bold">{{ round($efficiencyScore) }}%</span>
                </div>
            </div>
            <p class="text-green-100 mt-4">
                @if($efficiencyScore >= 90)
                    <i class="fas fa-trophy mr-2"></i>Excellent Performance!
                @elseif($efficiencyScore >= 75)
                    <i class="fas fa-thumbs-up mr-2"></i>Good Performance
                @else
                    <i class="fas fa-exclamation-triangle mr-2"></i>Needs Improvement
                @endif
            </p>
        </div>
    </div>
    
    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-gray-500 text-sm">Tekanan Press</p>
                <i class="fas fa-compress text-blue-600 text-xl"></i>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($avgTekananPress, 1) }}</p>
            <p class="text-xs text-gray-600 mt-1">Kg/cm² (Target: 60-75)</p>
            <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min(100, ($avgTekananPress/75)*100) }}%"></div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-gray-500 text-sm">Ampere Motor</p>
                <i class="fas fa-bolt text-yellow-600 text-xl"></i>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($avgAmpereMotor, 1) }}</p>
            <p class="text-xs text-gray-600 mt-1">A (Target: 35-45)</p>
            <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                <div class="bg-yellow-600 h-2 rounded-full" style="width: {{ min(100, ($avgAmpereMotor/45)*100) }}%"></div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-gray-500 text-sm">Tekanan Sterilizer</p>
                <i class="fas fa-tachometer-alt text-red-600 text-xl"></i>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($avgTekananSterilizer, 1) }}</p>
            <p class="text-xs text-gray-600 mt-1">Bar (Target: 1.5-3.2)</p>
            <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                <div class="bg-red-600 h-2 rounded-full" style="width: {{ min(100, ($avgTekananSterilizer/3.2)*100) }}%"></div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-gray-500 text-sm">Suhu Sterilizer</p>
                <i class="fas fa-thermometer-half text-orange-600 text-xl"></i>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($avgSuhuSterilizer, 0) }}°</p>
            <p class="text-xs text-gray-600 mt-1">Celsius (Target: 110-145)</p>
            <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                <div class="bg-orange-600 h-2 rounded-full" style="width: {{ min(100, ($avgSuhuSterilizer/145)*100) }}%"></div>
            </div>
        </div>
    </div>
    
    <!-- Performance Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Press Performance -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                Press Performance (Last 7 Days)
            </h3>
            <canvas id="pressChart" height="200"></canvas>
        </div>
        
        <!-- Sterilizer Performance -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-line text-red-600 mr-2"></i>
                Sterilizer Performance (Last 7 Days)
            </h3>
            <canvas id="sterilizerChart" height="200"></canvas>
        </div>
    </div>
    
    <!-- Detailed Data Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Press Data -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-800">Press Station Data</h3>
            </div>
            <div class="overflow-x-auto max-h-96">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Time</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Tekanan</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Ampere</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($pressData->take(20) as $data)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2">{{ $data->timestamp_kirim->format('d/m H:i') }}</td>
                            <td class="px-4 py-2">{{ $data->tekanan_hidrolik }}</td>
                            <td class="px-4 py-2">{{ $data->ampere_motor }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Sterilizer Data -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-800">Sterilizer Station Data</h3>
            </div>
            <div class="overflow-x-auto max-h-96">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Time</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Tekanan</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Suhu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($sterilizerData->take(20) as $data)
                        <tr class="hover:bg-gray-50">
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
// Press Chart
const pressCtx = document.getElementById('pressChart').getContext('2d');
new Chart(pressCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode($pressData->pluck('timestamp_kirim')->map(fn($t) => $t->format('d/m'))->take(20)) !!},
        datasets: [{
            label: 'Tekanan (Kg/cm²)',
            data: {!! json_encode($pressData->pluck('tekanan_hidrolik')->take(20)) !!},
            borderColor: 'rgb(59, 130, 246)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: false, min: 55, max: 80 } }
    }
});

// Sterilizer Chart
const sterilizerCtx = document.getElementById('sterilizerChart').getContext('2d');
new Chart(sterilizerCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode($sterilizerData->pluck('timestamp_kirim')->map(fn($t) => $t->format('d/m'))->take(20)) !!},
        datasets: [
            {
                label: 'Tekanan (Bar)',
                data: {!! json_encode($sterilizerData->pluck('tekanan_bar')->take(20)) !!},
                borderColor: 'rgb(239, 68, 68)',
                yAxisID: 'y'
            },
            {
                label: 'Suhu (°C)',
                data: {!! json_encode($sterilizerData->pluck('suhu_celcius')->take(20)) !!},
                borderColor: 'rgb(249, 115, 22)',
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { type: 'linear', position: 'left', min: 0, max: 5 },
            y1: { type: 'linear', position: 'right', min: 100, max: 150, grid: { drawOnChartArea: false } }
        }
    }
});
});
</script>
@endpush
