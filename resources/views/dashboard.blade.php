@extends('layouts.app')

@section('content')
<div class="space-y-6">
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Records Today -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Records Hari Ini</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $stats['today_total'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-database text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Flagged Records -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Data Flagged</p>
                    <p class="text-3xl font-bold text-yellow-600">{{ $stats['flagged'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-flag text-yellow-600 text-xl"></i>
                </div>
            </div>
            @can('view-flagged-records')
            <a href="{{ route('flagged.records') }}" class="text-sm text-blue-600 hover:underline mt-2 inline-block">
                Lihat Detail →
            </a>
            @endcan
        </div>
        
        <!-- Unverified Records -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Belum Diverifikasi</p>
                    <p class="text-3xl font-bold text-orange-600">{{ $stats['unverified'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-clock text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Active Users -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">User Aktif</p>
                    <p class="text-3xl font-bold text-green-600">{{ $stats['active_users'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-users text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @can('export-data')
            <a href="{{ route('export.daily-report') }}" class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-file-pdf text-3xl text-red-600 mb-2"></i>
                <span class="text-sm text-gray-700">Export PDF</span>
            </a>
            @endcan
            
            @can('view-flagged-records')
            <a href="{{ route('flagged.records') }}" class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-flag text-3xl text-yellow-600 mb-2"></i>
                <span class="text-sm text-gray-700">Flagged Data</span>
            </a>
            @endcan
            
            @can('edit-validation-rules')
            <a href="{{ route('settings.validation-rules') }}" class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-cog text-3xl text-gray-600 mb-2"></i>
                <span class="text-sm text-gray-700">Settings</span>
            </a>
            @endcan
            
            @can('access-full-dashboard')
            <a href="{{ route('analytics.overview') }}" class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                <i class="fas fa-chart-line text-3xl text-blue-600 mb-2"></i>
                <span class="text-sm text-gray-700">Analytics</span>
            </a>
            @endcan
        </div>
    </div>
    
    <!-- Station Summary -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Ringkasan per Stasiun (Hari Ini)</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            @php
                $userStations = auth()->user()->allowedStations();
                $visibleSummary = is_array($userStations)
                    ? collect($station_summary)->filter(fn ($count, $station) => in_array($station, $userStations))
                    : collect($station_summary);
            @endphp
            @foreach($visibleSummary as $station => $count)
            <a href="{{ route('stations.' . $station) }}" 
               class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                <div>
                    <p class="text-sm text-gray-500">{{ ucfirst($station) }}</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $count }}</p>
                </div>
                <i class="fas fa-chevron-right text-gray-400"></i>
            </a>
            @endforeach
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Aktivitas Terbaru</h2>
        <div class="space-y-3">
            @forelse($recent_logs as $log)
            <div class="flex items-center justify-between p-3 border-l-4 {{ $log->is_flagged ? 'border-yellow-500 bg-yellow-50' : 'border-green-500 bg-gray-50' }}">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-gray-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">{{ $log->user->name }}</p>
                        <p class="text-sm text-gray-600">{{ ucfirst($log->getTable()) }} - {{ $log->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                @if($log->is_flagged)
                <span class="px-3 py-1 bg-yellow-200 text-yellow-800 text-xs font-semibold rounded-full">
                    <i class="fas fa-flag mr-1"></i>Flagged
                </span>
                @endif
            </div>
            @empty
            <p class="text-center text-gray-500 py-8">Belum ada aktivitas hari ini</p>
            @endforelse
        </div>
    </div>
    
    <!-- System Info -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-2">Plant ID</h3>
            <p class="text-3xl font-bold">{{ auth()->user()->plant_id }}</p>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-2">Role</h3>
            <p class="text-3xl font-bold">{{ ucfirst(auth()->user()->role) }}</p>
        </div>
        
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-2">Department</h3>
            <p class="text-3xl font-bold">{{ ucfirst(auth()->user()->department ?? 'All') }}</p>
        </div>
    </div>
    
</div>
@endsection
