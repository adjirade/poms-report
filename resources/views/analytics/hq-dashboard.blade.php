@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-building text-green-700 mr-2"></i>
            HQ Multi-Plant Dashboard
        </h2>
        <p class="text-gray-600 mt-1">Ringkasan seluruh pabrik dalam jaringan</p>
    </div>

    <!-- Plant Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($plantStats as $plant)
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800">{{ $plant['plant_id'] }}</h3>
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                    {{ $plant['today_total'] }} entri hari ini
                </span>
            </div>

            <div class="space-y-3">
                <div>
                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span>Efficiency (7 hari)</span>
                        <span class="font-semibold">{{ $plant['efficiency'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="{{ $plant['efficiency'] >= 80 ? 'bg-green-600' : ($plant['efficiency'] >= 60 ? 'bg-yellow-500' : 'bg-red-500') }} h-2 rounded-full"
                             style="width: {{ min(100, $plant['efficiency']) }}%"></div>
                    </div>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600"><i class="fas fa-flag text-yellow-600 mr-1"></i>Data Flagged</span>
                    <span class="font-semibold {{ $plant['flagged'] > 0 ? 'text-yellow-700' : 'text-gray-400' }}">
                        {{ $plant['flagged'] }}
                    </span>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t">
                <a href="{{ route('hq.comparison') }}" class="text-sm text-blue-600 hover:underline">
                    <i class="fas fa-balance-scale mr-1"></i>Bandingkan plant
                </a>
            </div>
        </div>
        @empty
        <div class="col-span-full bg-white rounded-lg shadow p-12 text-center text-gray-500">
            <i class="fas fa-industry text-4xl mb-2"></i>
            <p>Belum ada data plant</p>
        </div>
        @endforelse
    </div>

</div>
@endsection
