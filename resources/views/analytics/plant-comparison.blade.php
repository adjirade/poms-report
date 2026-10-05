@extends('layouts.app')

@section('title', 'Perbandingan Plant')
@section('subtitle', 'Kinerja antar pabrik')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="card card-pad">
        <h2 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-balance-scale text-green-700 mr-2"></i>
            Perbandingan Antar Pabrik
        </h2>
        <p class="text-gray-600 mt-1">Perbandingan kinerja seluruh pabrik (all-time data)</p>
    </div>

    <!-- Comparison Table -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="glass-table w-full">
                <thead>
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left">Plant</th>
                        <th scope="col" class="px-6 py-3 text-left">Total Records</th>
                        <th scope="col" class="px-6 py-3 text-left">Efficiency Score</th>
                        <th scope="col" class="px-6 py-3 text-left">Avg Losses Fiber</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/40">
                    @forelse($comparisonData as $plantId => $data)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap font-semibold text-gray-900">
                            <i class="fas fa-industry text-green-700 mr-2"></i>{{ $plantId }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ number_format($data['total_records']) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="h-2 w-32 overflow-hidden rounded-full bg-white/60 shadow-inner">
                                    <div class="h-2 rounded-full bg-gradient-to-r {{ $data['efficiency'] >= 80 ? 'from-emerald-400 to-green-600' : ($data['efficiency'] >= 60 ? 'from-amber-300 to-yellow-500' : 'from-rose-400 to-red-600') }}"
                                         style="width: {{ min(100, $data['efficiency']) }}%"></div>
                                </div>
                                <span class="text-sm font-semibold text-gray-700">{{ $data['efficiency'] }}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="badge {{ $data['losses'] <= 5 ? 'border-green-200/70 bg-green-100 text-green-800' : 'border-red-200/70 bg-red-100 text-red-800' }}">
                                {{ number_format($data['losses'], 2) }}%
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-600">
                            <i class="fas fa-inbox text-4xl mb-2"></i>
                            <p>Tidak ada data perbandingan</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
