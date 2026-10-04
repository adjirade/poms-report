@extends('layouts.app')

@section('title', 'HQ Dashboard')
@section('subtitle', 'Ringkasan seluruh pabrik')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="card card-pad">
        <h2 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-building text-green-700 mr-2"></i>
            HQ Multi-Plant Dashboard
        </h2>
        <p class="text-gray-600 mt-1">Ringkasan seluruh pabrik dalam jaringan</p>
    </div>

    <!-- Plant Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($plantStats as $plant)
        <div class="card card-pad group transition hover:-translate-y-0.5 hover:shadow-glass-lg">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-800"><i class="fas fa-industry mr-2 text-green-600"></i>{{ $plant['plant_id'] }}</h3>
                <span class="badge border-white/60 bg-white/55 text-gray-600">
                    {{ $plant['today_total'] }} entri hari ini
                </span>
            </div>

            <div class="space-y-3">
                <div>
                    <div class="mb-1 flex justify-between text-sm text-gray-600">
                        <span>Efficiency (7 hari)</span>
                        <span class="font-semibold">{{ $plant['efficiency'] }}%</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-white/60 shadow-inner">
                        <div class="h-2 rounded-full bg-gradient-to-r {{ $plant['efficiency'] >= 80 ? 'from-emerald-400 to-green-600' : ($plant['efficiency'] >= 60 ? 'from-amber-300 to-yellow-500' : 'from-rose-400 to-red-600') }}"
                             style="width: {{ min(100, $plant['efficiency']) }}%"></div>
                    </div>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600"><i class="fas fa-flag mr-1 text-amber-500"></i>Data Flagged</span>
                    <span class="font-semibold {{ $plant['flagged'] > 0 ? 'text-amber-700' : 'text-gray-400' }}">
                        {{ $plant['flagged'] }}
                    </span>
                </div>
            </div>

            <div class="mt-4 border-t border-white/50 pt-4">
                <a href="{{ route('hq.comparison') }}" class="text-sm font-medium text-green-700 transition hover:text-green-800">
                    <i class="fas fa-balance-scale mr-1"></i>Bandingkan plant
                </a>
            </div>
        </div>
        @empty
        <div class="card col-span-full p-12 text-center text-gray-500">
            <i class="fas fa-industry text-4xl mb-2"></i>
            <p>Belum ada data plant</p>
        </div>
        @endforelse
    </div>

</div>
@endsection
