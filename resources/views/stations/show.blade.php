@extends('layouts.app')

@section('title', $title)
@section('subtitle', 'Data logging stasiun '.strtoupper($station))

@section('content')
<div class="space-y-6" x-data="{ tab: 'data', chartRendered: false, chartData: @js($chart) }">

    <!-- Tab: Tabel Data | Grafik Performa -->
    <div class="card flex gap-2 p-2" role="tablist" aria-label="Tampilan stasiun">
        <button type="button" role="tab" id="tab-data" aria-controls="panel-data" :aria-selected="tab === 'data'"
                @click="tab = 'data'"
                class="tap-target flex-1 rounded-xl px-4 py-2 text-sm font-semibold transition sm:flex-none"
                :class="tab === 'data' ? 'bg-gradient-to-br from-emerald-700 to-green-800 text-white shadow-float' : 'text-gray-600 hover:bg-white/60'">
            <i class="fas fa-table-list mr-2"></i>Tabel Data
        </button>
        <button type="button" role="tab" id="tab-grafik" aria-controls="panel-grafik" :aria-selected="tab === 'grafik'"
                @click="tab = 'grafik'; if (!chartRendered) { $nextTick(() => { if (window.PomsStationChart) { PomsStationChart.render('stationTabChart', chartData); chartRendered = true; } }); }"
                class="tap-target flex-1 rounded-xl px-4 py-2 text-sm font-semibold transition sm:flex-none"
                :class="tab === 'grafik' ? 'bg-gradient-to-br from-emerald-700 to-green-800 text-white shadow-float' : 'text-gray-600 hover:bg-white/60'">
            <i class="fas fa-chart-line mr-2"></i>Grafik Performa
        </button>
    </div>

    <!-- Panel: Grafik Performa (30 hari) -->
    <div x-show="tab === 'grafik'" x-cloak class="space-y-6" role="tabpanel" id="panel-grafik" aria-labelledby="tab-grafik" tabindex="0">
        <div class="card card-pad">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                    Tren Harian — {{ $stationTitle }} (30 hari)
                </h2>
                <div class="flex items-center gap-2">
                    <span class="hidden text-xs text-gray-600 sm:inline">
                        <i class="fas fa-arrows-left-right"></i> Drag area untuk zoom &middot; Ctrl+scroll
                    </span>
                    <button type="button" onclick="PomsStationChart && PomsStationChart.reset('stationTabChart')"
                            class="btn-ghost !min-h-0 !px-3 !py-1.5 text-xs">
                        <i class="fas fa-rotate-left"></i> Reset zoom
                    </button>
                    <a href="{{ route('analytics.station-performance', ['station' => $station, 'range' => 30]) }}"
                       class="btn-ghost !min-h-0 !px-3 !py-1.5 text-xs">
                        <i class="fas fa-up-right-from-square"></i> Detail lengkap
                    </a>
                </div>
            </div>
            <div class="relative" style="height: 320px;">
                <canvas id="stationTabChart" role="img"
                        aria-label="Grafik garis tren harian {{ $stationTitle }} selama 30 hari terakhir"></canvas>
            </div>
            <p class="mt-3 text-xs text-gray-600">
                Nilai adalah rata-rata harian semua parameter stasiun. Detail per record tersedia di
                <a href="{{ route('analytics.station-performance', ['station' => $station, 'range' => 30]) }}" class="font-semibold text-green-700 underline decoration-green-300">halaman Performa Stasiun</a>.
            </p>
        </div>
    </div>

    <!-- Panel: Tabel Data (Livewire) -->
    <div x-show="tab === 'data'" role="tabpanel" id="panel-data" aria-labelledby="tab-data" tabindex="0">
        <!-- Station Header -->
    <div class="card card-pad">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-float">
                    <i class="fas fa-industry"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">{{ $title }}</h2>
                    <p class="text-sm text-gray-600">Data logging untuk stasiun {{ strtoupper($station) }}</p>
                </div>
            </div>

            @can('export-data')
            <!-- Satu form, dua tombol (formaction) → rentang tanggal yang dipilih
                 berlaku untuk PDF dan Excel sekaligus -->
            <form method="GET" class="flex w-full flex-col gap-3 lg:w-auto lg:flex-row lg:items-center">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <input type="date" name="date_from" value="{{ now()->startOfDay()->format('Y-m-d') }}" aria-label="Tanggal mulai" class="input sm:w-40">
                    <span class="hidden text-sm text-gray-600 sm:inline">s/d</span>
                    <input type="date" name="date_to" value="{{ now()->endOfDay()->format('Y-m-d') }}" aria-label="Tanggal akhir" class="input sm:w-40">
                </div>
                <div class="flex gap-2">
                    <button type="submit" formaction="{{ route('export.pdf', $station) }}"
                            class="inline-flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-rose-600 to-red-700 px-4 py-2 text-sm font-semibold text-white shadow-float transition hover:brightness-105 active:scale-[0.98] sm:flex-none">
                        <i class="fas fa-file-pdf"></i>PDF
                    </button>
                    <button type="submit" formaction="{{ route('export.excel', $station) }}"
                            class="inline-flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-emerald-700 to-green-800 px-4 py-2 text-sm font-semibold text-white shadow-float transition hover:brightness-105 active:scale-[0.98] sm:flex-none">
                        <i class="fas fa-file-excel"></i>Excel
                    </button>
                </div>
            </form>
            @endcan
        </div>
    </div>

    <!-- Livewire Data Table -->
    @livewire('station-logs-table', ['station' => $station])
    </div>

</div>
@endsection
