@extends('layouts.app')

@section('title', 'HQ Sync Monitor')
@section('subtitle', 'Status sinkronisasi ke Cloud HQ')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="card card-pad flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-satellite-dish text-green-700 mr-2"></i>
                Monitoring HQ Cloud Sync
            </h2>
            <p class="text-gray-600 mt-1">
                Status sinkronisasi data ke Cloud HQ untuk plant <strong>{{ $plantId }}</strong> (PRD 2.2)
            </p>
        </div>
        <form method="POST" action="{{ route('settings.hq-sync.run') }}">
            @csrf
            <button type="submit" {{ config('hq.enabled') ? '' : 'disabled' }}
                    class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-2xl px-4 py-2 font-semibold transition sm:w-auto {{ config('hq.enabled') ? 'bg-gradient-to-br from-emerald-600 to-green-700 text-white shadow-float hover:brightness-105 active:scale-[0.98]' : 'cursor-not-allowed bg-gray-200 text-gray-600' }}">
                <i class="fas fa-sync-alt"></i>Sync Sekarang
            </button>
        </form>
    </div>

    @unless(config('hq.enabled'))
    <div class="rounded-2xl border border-amber-300/60 bg-amber-100/70 p-4 text-amber-900 backdrop-blur-xl">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        HQ sync <strong>tidak aktif</strong> — set <code>HQ_SYNC_ENABLED=true</code> beserta
        <code>HQ_API_URL</code> dan <code>HQ_API_TOKEN</code> di <code>.env</code>.
    </div>
    @endunless

    <!-- Status cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="card card-pad">
            <p class="text-gray-600 text-sm">Total Record Terkirim</p>
            <p class="text-3xl font-bold text-green-700">{{ number_format($totals['pushed']) }}</p>
        </div>
        <div class="card card-pad">
            <p class="text-gray-600 text-sm">Run Gagal</p>
            <p class="text-3xl font-bold {{ $totals['failed_runs'] > 0 ? 'text-red-600' : 'text-gray-600' }}">
                {{ number_format($totals['failed_runs']) }}
            </p>
        </div>
        <div class="card card-pad md:col-span-2">
            <p class="mb-2 text-sm text-gray-600">Record Pending per Stasiun</p>
            <div class="grid grid-cols-4 gap-2 text-center">
                @foreach($pending as $station => $count)
                <div class="glass-subtle rounded-2xl p-2">
                    <p class="text-xs text-gray-600">{{ ucfirst($station) }}</p>
                    <p class="text-lg font-bold {{ $count > 0 ? 'text-amber-700' : 'text-gray-600' }}">{{ $count }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    @if($lastRun)
    <div class="card card-pad">
        <h3 class="text-lg font-bold text-gray-800 mb-3">Run Terakhir</h3>
        <div class="flex items-center justify-between text-sm">
            <div class="flex items-center space-x-3">
                @if($lastRun->status === 'success')
                    <span class="badge border-green-200/70 bg-green-100 text-green-800"><i class="fas fa-check-circle"></i>Success</span>
                @elseif($lastRun->status === 'failed')
                    <span class="badge border-red-200/70 bg-red-100 text-red-800"><i class="fas fa-times-circle"></i>Failed</span>
                @else
                    <span class="badge border-sky-200/70 bg-sky-100 text-sky-800"><i class="fas fa-spinner"></i>{{ ucfirst($lastRun->status) }}</span>
                @endif
                <span class="text-gray-600">{{ $lastRun->message }}</span>
            </div>
            <span class="text-gray-600">
                {{ $lastRun->started_at ? \Carbon\Carbon::parse($lastRun->started_at)->format('d/m/Y H:i:s') : '-' }}
                {{ $lastRun->finished_at ? '→ ' . \Carbon\Carbon::parse($lastRun->finished_at)->format('H:i:s') : '' }}
            </span>
        </div>
        @if($lastRun->status === 'failed' && $lastRun->message)
        <div class="mt-3 break-all rounded-2xl border border-red-300/60 bg-red-100/70 p-3 font-mono text-xs text-red-800 backdrop-blur-xl">
            {{ $lastRun->message }}
        </div>
        @endif
    </div>
    @endif

    <!-- History -->
    <div class="card overflow-hidden">
        <div class="border-b border-white/50 px-6 py-4">
            <h3 class="text-lg font-bold text-gray-800"><i class="fas fa-clock-rotate-left mr-2 text-green-600"></i>Riwayat Sinkronisasi</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="glass-table w-full">
                <thead>
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left">#</th>
                        <th scope="col" class="px-6 py-3 text-left">Waktu Mulai</th>
                        <th scope="col" class="px-6 py-3 text-left">Status</th>
                        <th scope="col" class="px-6 py-3 text-left">Pushed</th>
                        <th scope="col" class="px-6 py-3 text-left">Failed</th>
                        <th scope="col" class="px-6 py-3 text-left">Pesan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/40">
                    @forelse($logs as $log)
                    <tr class="{{ $log->status === 'failed' ? 'bg-red-100/40' : '' }}">
                        <td class="px-6 py-3 text-sm text-gray-600">{{ $log->id }}</td>
                        <td class="px-6 py-3 text-sm text-gray-900">
                            {{ $log->started_at ? \Carbon\Carbon::parse($log->started_at)->format('d/m H:i:s') : '-' }}
                        </td>
                        <td class="px-6 py-3">
                            <span class="badge {{ $log->status === 'success' ? 'border-green-200/70 bg-green-100 text-green-800' : ($log->status === 'failed' ? 'border-red-200/70 bg-red-100 text-red-800' : 'border-sky-200/70 bg-sky-100 text-sky-800') }}">
                                {{ ucfirst($log->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm font-semibold text-green-700">{{ $log->records_pushed }}</td>
                        <td class="px-6 py-3 text-sm {{ $log->records_failed > 0 ? 'text-red-600 font-semibold' : 'text-gray-600' }}">{{ $log->records_failed }}</td>
                        <td class="px-6 py-3 text-xs text-gray-600 max-w-md truncate" title="{{ $log->message }}">{{ $log->message }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-600">
                            <i class="fas fa-satellite text-4xl mb-2"></i>
                            <p>Belum ada riwayat sinkronisasi</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-white/40 px-6 py-4">
            {{ $logs->links() }}
        </div>
    </div>

</div>
@endsection
