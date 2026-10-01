@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-6 flex items-center justify-between">
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
                    class="px-4 py-2 {{ config('hq.enabled') ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-300 cursor-not-allowed' }} text-white rounded-lg transition">
                <i class="fas fa-sync-alt mr-2"></i>Sync Sekarang
            </button>
        </form>
    </div>

    @unless(config('hq.enabled'))
    <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-lg p-4">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        HQ sync <strong>tidak aktif</strong> — set <code>HQ_SYNC_ENABLED=true</code> beserta
        <code>HQ_API_URL</code> dan <code>HQ_API_TOKEN</code> di <code>.env</code>.
    </div>
    @endunless

    <!-- Status cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-gray-500 text-sm">Total Record Terkirim</p>
            <p class="text-3xl font-bold text-green-700">{{ number_format($totals['pushed']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-gray-500 text-sm">Run Gagal</p>
            <p class="text-3xl font-bold {{ $totals['failed_runs'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                {{ number_format($totals['failed_runs']) }}
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 md:col-span-2">
            <p class="text-gray-500 text-sm mb-2">Record Pending per Stasiun</p>
            <div class="grid grid-cols-4 gap-2 text-center">
                @foreach($pending as $station => $count)
                <div class="p-2 {{ $count > 0 ? 'bg-yellow-50' : 'bg-gray-50' }} rounded">
                    <p class="text-xs text-gray-500">{{ ucfirst($station) }}</p>
                    <p class="text-lg font-bold {{ $count > 0 ? 'text-yellow-700' : 'text-gray-400' }}">{{ $count }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    @if($lastRun)
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-3">Run Terakhir</h3>
        <div class="flex items-center justify-between text-sm">
            <div class="flex items-center space-x-3">
                @if($lastRun->status === 'success')
                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full font-semibold"><i class="fas fa-check-circle mr-1"></i>Success</span>
                @elseif($lastRun->status === 'failed')
                    <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full font-semibold"><i class="fas fa-times-circle mr-1"></i>Failed</span>
                @else
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full font-semibold"><i class="fas fa-spinner mr-1"></i>{{ ucfirst($lastRun->status) }}</span>
                @endif
                <span class="text-gray-600">{{ $lastRun->message }}</span>
            </div>
            <span class="text-gray-500">
                {{ $lastRun->started_at ? \Carbon\Carbon::parse($lastRun->started_at)->format('d/m/Y H:i:s') : '-' }}
                {{ $lastRun->finished_at ? '→ ' . \Carbon\Carbon::parse($lastRun->finished_at)->format('H:i:s') : '' }}
            </span>
        </div>
        @if($lastRun->status === 'failed' && $lastRun->message)
        <div class="mt-3 p-3 bg-red-50 text-red-800 rounded text-xs font-mono break-all">
            {{ $lastRun->message }}
        </div>
        @endif
    </div>
    @endif

    <!-- History -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-800">Riwayat Sinkronisasi</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu Mulai</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pushed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Failed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pesan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($logs as $log)
                    <tr class="hover:bg-gray-50 {{ $log->status === 'failed' ? 'bg-red-50' : '' }}">
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $log->id }}</td>
                        <td class="px-6 py-3 text-sm text-gray-900">
                            {{ $log->started_at ? \Carbon\Carbon::parse($log->started_at)->format('d/m H:i:s') : '-' }}
                        </td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold
                                {{ $log->status === 'success' ? 'bg-green-100 text-green-800' : ($log->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') }}">
                                {{ ucfirst($log->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm font-semibold text-green-700">{{ $log->records_pushed }}</td>
                        <td class="px-6 py-3 text-sm {{ $log->records_failed > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">{{ $log->records_failed }}</td>
                        <td class="px-6 py-3 text-xs text-gray-500 max-w-md truncate" title="{{ $log->message }}">{{ $log->message }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-satellite text-4xl mb-2"></i>
                            <p>Belum ada riwayat sinkronisasi</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t">
            {{ $logs->links() }}
        </div>
    </div>

</div>
@endsection
