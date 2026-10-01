@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-balance-scale text-green-700 mr-2"></i>
            Perbandingan Antar Pabrik
        </h2>
        <p class="text-gray-600 mt-1">Perbandingan kinerja seluruh pabrik (all-time data)</p>
    </div>

    <!-- Comparison Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plant</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Records</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Efficiency Score</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Losses Fiber</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($comparisonData as $plantId => $data)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap font-semibold text-gray-900">
                            <i class="fas fa-industry text-green-700 mr-2"></i>{{ $plantId }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ number_format($data['total_records']) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <div class="w-32 bg-gray-200 rounded-full h-2">
                                    <div class="{{ $data['efficiency'] >= 80 ? 'bg-green-600' : ($data['efficiency'] >= 60 ? 'bg-yellow-500' : 'bg-red-500') }} h-2 rounded-full"
                                         style="width: {{ min(100, $data['efficiency']) }}%"></div>
                                </div>
                                <span class="text-sm font-semibold text-gray-700">{{ $data['efficiency'] }}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 rounded text-xs font-semibold
                                {{ $data['losses'] <= 5 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ number_format($data['losses'], 2) }}%
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
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
