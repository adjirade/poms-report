@extends('layouts.app')

@section('content')
<div class="space-y-6">
    
    <!-- Header Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">Total Records</p>
                    <p class="text-3xl font-bold">{{ array_sum(array_column($dailyStats, 'total')) }}</p>
                    <p class="text-blue-100 text-xs mt-1">Last 7 days</p>
                </div>
                <i class="fas fa-database text-4xl text-blue-300"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 text-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-100 text-sm">Flagged</p>
                    <p class="text-3xl font-bold">{{ array_sum(array_column($dailyStats, 'flagged')) }}</p>
                    <p class="text-yellow-100 text-xs mt-1">Needs review</p>
                </div>
                <i class="fas fa-flag text-4xl text-yellow-300"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm">Verified</p>
                    <p class="text-3xl font-bold">{{ array_sum(array_column($dailyStats, 'verified')) }}</p>
                    <p class="text-green-100 text-xs mt-1">Quality checked</p>
                </div>
                <i class="fas fa-check-circle text-4xl text-green-300"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm">Stations</p>
                    <p class="text-3xl font-bold">{{ count($stationBreakdown) }}</p>
                    <p class="text-purple-100 text-xs mt-1">Active today</p>
                </div>
                <i class="fas fa-industry text-4xl text-purple-300"></i>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Daily Trend Chart -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                7 Days Trend
            </h3>
            <canvas id="dailyTrendChart" height="250"></canvas>
        </div>
        
        <!-- Station Breakdown -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-pie text-green-600 mr-2"></i>
                Station Breakdown (Today)
            </h3>
            <canvas id="stationBreakdownChart" height="250"></canvas>
        </div>
    </div>
    
    <!-- Top Operators -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-users text-purple-600 mr-2"></i>
            Top Operators (All Time)
        </h3>
        <div class="space-y-3">
            @foreach($topOperators as $index => $operator)
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white
                        {{ $index === 0 ? 'bg-yellow-500' : ($index === 1 ? 'bg-gray-400' : ($index === 2 ? 'bg-orange-500' : 'bg-gray-300')) }}">
                        #{{ $index + 1 }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">{{ $operator['name'] }}</p>
                        <p class="text-sm text-gray-600">Total: {{ $operator['total'] }} records</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="w-32 bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" 
                             style="width: {{ ($operator['total'] / $topOperators->first()['total']) * 100 }}%"></div>
                    </div>
                </div>
            </div>
            @endforeach
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
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Flagged',
                data: {!! json_encode(array_column($dailyStats, 'flagged')) !!},
                borderColor: 'rgb(234, 179, 8)',
                backgroundColor: 'rgba(234, 179, 8, 0.1)',
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
            y: { beginAtZero: true, ticks: { precision: 0 } }
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
            backgroundColor: [
                'rgb(239, 68, 68)',
                'rgb(249, 115, 22)',
                'rgb(234, 179, 8)',
                'rgb(34, 197, 94)',
                'rgb(59, 130, 246)',
                'rgb(139, 92, 246)',
                'rgb(236, 72, 153)',
                'rgb(107, 114, 128)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'right' }
        }
    }
});
});
</script>
@endpush
