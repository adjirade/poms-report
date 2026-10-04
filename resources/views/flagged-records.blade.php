@extends('layouts.app')

@section('title', 'Data Flagged')
@section('subtitle', 'Anomali waktu yang perlu diverifikasi')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="card card-pad">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-orange-600 text-white shadow-float">
                    <i class="fas fa-flag"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Data Flagged Records</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Data dengan selisih waktu &gt; 4 jam yang memerlukan verifikasi
                    </p>
                </div>
            </div>
            <span class="badge self-start border-amber-200/70 bg-amber-100 px-4 py-1.5 text-sm text-amber-800 sm:self-auto">
                <i class="fas fa-circle-exclamation"></i>{{ $flaggedRecords->count() }} record
            </span>
        </div>
    </div>

    <!-- Statistik Flagged -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="card card-pad">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Flagged</p>
            <p class="mt-1 text-2xl font-bold text-gray-800">{{ number_format($flagStats['total']) }}</p>
        </div>
        <div class="card card-pad">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">⏱ Selisih Waktu ({{ config('poms.time_discrepancy_hours') }} jam+)</p>
            <p class="mt-1 text-2xl font-bold text-rose-700">{{ number_format($flagStats['time_based']) }}</p>
        </div>
        <div class="card card-pad">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">⚠ Parameter di Luar Rule</p>
            <p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($flagStats['rule_based']) }}</p>
        </div>
        <div class="card card-pad">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">⌛ Belum Diverifikasi</p>
            <p class="mt-1 text-2xl font-bold text-orange-700">{{ number_format($flagStats['unverified']) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card card-pad">
            <h2 class="mb-4 text-lg font-bold text-gray-800">
                <i class="fas fa-chart-column text-rose-600 mr-2"></i>
                Flag 14 Hari Terakhir
            </h2>
            <div class="relative" style="height: 220px;">
                <canvas id="flagPerDayChart" role="img" aria-label="Grafik batang jumlah flagged per hari"></canvas>
            </div>
        </div>
        <div class="card card-pad">
            <h2 class="mb-4 text-lg font-bold text-gray-800">
                <i class="fas fa-chart-pie text-purple-600 mr-2"></i>
                Sebaran per Stasiun
            </h2>
            <div class="flex flex-wrap gap-2">
                @forelse($flagStats['per_station'] as $stationKey => $count)
                <span class="chip !border-rose-200 !bg-rose-50 text-rose-800">
                    {{ ucfirst($stationKey) }} <strong class="ml-1">{{ $count }}</strong>
                </span>
                @empty
                <p class="text-sm text-gray-500">Tidak ada flagged pada filter ini.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card card-pad">
        <form method="GET" action="{{ route('flagged.records') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="fr-station" class="mb-1 block text-sm font-medium text-gray-700">Stasiun</label>
                <select id="fr-station" name="station" class="input">
                    <option value="">Semua Stasiun</option>
                    @foreach(['timbang', 'sortasi', 'sterilizer', 'press', 'klarifikasi', 'kernel', 'lab', 'maintenance'] as $st)
                    <option value="{{ $st }}" {{ request('station') == $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="fr-from" class="mb-1 block text-sm font-medium text-gray-700">Tanggal Mulai</label>
                <input id="fr-from" type="date" name="date_from" value="{{ request('date_from') }}" class="input">
            </div>

            <div>
                <label for="fr-to" class="mb-1 block text-sm font-medium text-gray-700">Tanggal Akhir</label>
                <input id="fr-to" type="date" name="date_to" value="{{ request('date_to') }}" class="input">
            </div>

            <div class="flex items-end">
                <button type="submit" class="btn-primary w-full">
                    <i class="fas fa-filter"></i>Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Flagged Records Table -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="glass-table min-w-full divide-y divide-white/40 text-sm">
                <thead>
                    <tr class="text-left">
                        <th scope="col" class="px-4 py-3">Stasiun</th>
                        <th scope="col" class="px-4 py-3">ID</th>
                        <th scope="col" class="px-4 py-3">User</th>
                        <th scope="col" class="px-4 py-3">Waktu Kirim</th>
                        <th scope="col" class="px-4 py-3">Waktu Server</th>
                        <th scope="col" class="px-4 py-3">Selisih</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/40">
                    @php $allowedStations = auth()->user()->allowedStations(); @endphp
                    @forelse($flaggedRecords as $record)
                    <tr class="bg-amber-100/40 transition">
                        <td class="whitespace-nowrap px-4 py-3">
                            <span class="badge border-white/60 bg-white/60 text-gray-700">{{ ucfirst($record['station']) }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900">
                            #{{ $record['id'] }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/70 text-amber-600 shadow-inner">
                                    <i class="fas fa-user text-xs"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900">{{ $record['user_name'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $record['department'] ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                            {{ $record['timestamp_kirim']->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                            {{ $record['timestamp_server']->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span class="badge border-red-200/70 bg-red-100 text-red-800">
                                <i class="fas fa-exclamation-triangle"></i>{{ round($record['time_diff_hours'], 1) }} jam
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @if($record['is_verified'])
                            <span class="badge border-green-200/70 bg-green-100 text-green-800">
                                <i class="fas fa-check-circle"></i>Verified
                            </span>
                            @else
                            <span class="badge border-orange-200/70 bg-orange-100 text-orange-800">
                                <i class="fas fa-clock"></i>Pending
                            </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @if(! is_array($allowedStations) || in_array($record['station'], $allowedStations, true))
                            <a href="{{ route('stations.' . $record['station']) }}"
                               class="inline-flex items-center gap-1 text-sm font-medium text-green-700 hover:text-green-800">
                                <i class="fas fa-eye"></i>Lihat
                            </a>
                            @else
                            <span class="text-xs text-gray-400" title="Stasiun di luar departemen Anda">
                                <i class="fas fa-ban mr-1"></i>Di luar departemen
                            </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-check-circle mb-2 text-4xl text-green-500"></i>
                            <p class="text-lg font-semibold">Tidak ada data flagged</p>
                            <p class="text-sm">Semua data dalam rentang waktu normal</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Info Box -->
    <div class="glass-subtle rounded-4xl p-5 sm:p-6">
        <div class="flex items-start gap-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-float">
                <i class="fas fa-info-circle"></i>
            </span>
            <div>
                <h3 class="mb-2 text-lg font-semibold text-gray-800">Tentang Data Flagged</h3>
                <p class="mb-2 text-sm text-gray-600">
                    Data ditandai (flagged) secara otomatis ketika selisih antara waktu kirim dan waktu server
                    melebihi <strong>4 jam</strong>. Ini bisa mengindikasikan:
                </p>
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600">
                    <li>Operator mengirim data di area tanpa sinyal (normal)</li>
                    <li>Data tertunda di queue Telegram (perlu investigasi)</li>
                    <li>Manipulasi waktu sistem (perlu audit)</li>
                </ul>
                <p class="mt-3 text-sm text-gray-600">
                    <strong>Action:</strong> Verifikasi data flagged untuk memastikan validitas sebelum digunakan dalam laporan.
                </p>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') { return; }
    const el = document.getElementById('flagPerDayChart');
    if (!el) { return; }
    const data = @json($flagStats['per_day']);
    new Chart(el, {
        type: 'bar',
        data: {
            labels: data.map((d) => d.label),
            datasets: [{
                label: 'Flagged',
                data: data.map((d) => d.total),
                backgroundColor: 'rgba(244, 63, 94, 0.65)',
                borderColor: '#f43f5e',
                borderWidth: 1,
                borderRadius: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { grid: { display: false } },
            },
        },
    });
});
</script>
@endpush
