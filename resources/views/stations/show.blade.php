@extends('layouts.app')

@section('content')
<div class="space-y-6">
    
    <!-- Station Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">{{ $title }}</h2>
                <p class="text-gray-600">Data logging untuk stasiun {{ strtoupper($station) }}</p>
            </div>
            <div class="flex items-center space-x-4">
                @can('export-data')
                <form method="GET" action="{{ route('export.pdf', $station) }}" class="flex items-center space-x-2">
                    <input type="date" name="date_from" value="{{ now()->startOfDay()->format('Y-m-d') }}"
                           class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                    <span class="text-gray-400">s/d</span>
                    <input type="date" name="date_to" value="{{ now()->endOfDay()->format('Y-m-d') }}"
                           class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">
                        <i class="fas fa-file-pdf mr-1"></i>PDF
                    </button>
                </form>
                <form method="GET" action="{{ route('export.excel', $station) }}">
                    <input type="hidden" name="date_from" value="{{ now()->startOfDay()->format('Y-m-d') }}">
                    <input type="hidden" name="date_to" value="{{ now()->endOfDay()->format('Y-m-d') }}">
                    <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition">
                        <i class="fas fa-file-excel mr-1"></i>Excel
                    </button>
                </form>
                @endcan
            </div>
        </div>
    </div>
    
    <!-- Livewire Data Table -->
    @livewire('station-logs-table', ['station' => $station])
    
</div>
@endsection
