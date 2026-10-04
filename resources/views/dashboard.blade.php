@extends('layouts.app')

@section('content')
@php
    $user = auth()->user();
    $statCards = [
        [
            'label' => 'Total Records Hari Ini',
            'value' => $stats['today_total'] ?? 0,
            'icon' => 'fa-database',
            'tile' => 'from-sky-400 to-blue-600',
            'glow' => 'bg-sky-400',
            'valueClass' => 'text-gray-800',
        ],
        [
            'label' => 'Data Flagged',
            'value' => $stats['flagged'] ?? 0,
            'icon' => 'fa-flag',
            'tile' => 'from-amber-400 to-orange-600',
            'glow' => 'bg-amber-400',
            'valueClass' => 'text-amber-600',
        ],
        [
            'label' => 'Belum Diverifikasi',
            'value' => $stats['unverified'] ?? 0,
            'icon' => 'fa-clock',
            'tile' => 'from-orange-400 to-red-500',
            'glow' => 'bg-orange-400',
            'valueClass' => 'text-orange-600',
        ],
        [
            'label' => 'User Aktif',
            'value' => $stats['active_users'] ?? 0,
            'icon' => 'fa-users',
            'tile' => 'from-emerald-400 to-green-700',
            'glow' => 'bg-emerald-400',
            'valueClass' => 'text-green-600',
        ],
    ];

    $hasQuickActions = $user->can('export-data')
        || $user->can('view-flagged-records')
        || $user->can('edit-validation-rules')
        || $user->can('access-full-dashboard')
        || $user->can('manage-users');
@endphp

<div class="space-y-6">

    {{-- Greeting banner — smoked glass hero --}}
    <div class="sidebar-glass glass-sheen animate-fade-up relative overflow-hidden rounded-4xl text-white">
        <div class="pointer-events-none absolute -right-10 -top-16 h-64 w-64 rounded-full bg-emerald-400/40 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-20 left-1/3 h-56 w-56 rounded-full bg-sky-400/30 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-4 -top-6 opacity-10">
            <i class="fas fa-leaf text-[10rem]"></i>
        </div>
        <div class="relative flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-7">
            <div>
                <p class="text-sm text-emerald-100/90">Selamat datang kembali,</p>
                <h2 class="text-2xl font-bold tracking-tight">{{ $user->name }}</h2>
                <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-emerald-100/80">
                    <span><i class="fas fa-user-shield mr-1"></i>{{ ucfirst($user->role) }}</span>
                    <span class="text-white/30">•</span>
                    <span><i class="fas fa-building mr-1"></i>{{ $user->plant_id }}</span>
                    <span class="text-white/30">•</span>
                    <span><i class="far fa-calendar mr-1"></i>{{ now()->timezone('Asia/Jakarta')->translatedFormat('l, d F Y') }}</span>
                </p>
            </div>
            @can('view-flagged-records')
            <a href="{{ route('flagged.records') }}"
               class="inline-flex min-h-[44px] items-center gap-2 self-start rounded-2xl border border-white/25 bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur-xl transition hover:bg-white/25 active:scale-[0.98]">
                <i class="fas fa-flag"></i> Lihat Data Flagged
            </a>
            @endcan
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($statCards as $card)
        <div class="card card-pad group relative overflow-hidden transition duration-200 hover:-translate-y-0.5 hover:shadow-glass-lg">
            <div class="pointer-events-none absolute -right-8 -top-10 h-24 w-24 rounded-full {{ $card['glow'] }} opacity-25 blur-2xl transition group-hover:opacity-40"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">{{ $card['label'] }}</p>
                    <p class="mt-1 text-3xl font-bold {{ $card['valueClass'] }}">{{ number_format($card['value']) }}</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br {{ $card['tile'] }} text-white shadow-float">
                    <i class="fas {{ $card['icon'] }} text-lg"></i>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    @if($hasQuickActions)
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Station summary --}}
        <div class="card card-pad lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-800"><i class="fas fa-industry mr-2 text-green-600"></i>Ringkasan per Stasiun</h2>
                <span class="badge border-white/60 bg-white/55 text-gray-600"><i class="fas fa-calendar-day"></i>Hari ini</span>
            </div>
            @php
                $visibleSummary = is_array($user->allowedStations())
                    ? collect($station_summary)->filter(fn ($count, $station) => in_array($station, $user->allowedStations()))
                    : collect($station_summary);
            @endphp
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @forelse($visibleSummary as $station => $count)
                <a href="{{ route('stations.' . $station) }}"
                   class="glass-subtle group flex items-center justify-between rounded-2xl p-4 transition hover:-translate-y-0.5 hover:shadow-glass">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-float">
                            <i class="fas fa-microchip text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ ucfirst($station) }}</p>
                            <p class="text-xl font-bold text-gray-800">{{ number_format($count) }}</p>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-green-500"></i>
                </a>
                @empty
                <p class="col-span-full py-6 text-center text-sm text-gray-500">Tidak ada stasiun yang dapat diakses.</p>
                @endforelse
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="card card-pad">
            <h2 class="mb-4 text-lg font-bold text-gray-800"><i class="fas fa-bolt mr-2 text-amber-500"></i>Aksi Cepat</h2>
            <div class="space-y-2">
                @can('export-data')
                <a href="{{ route('export.daily-report') }}" class="glass-subtle flex items-center gap-3 rounded-2xl p-3 transition hover:-translate-y-0.5 hover:shadow-glass">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-400 to-red-600 text-white shadow-float"><i class="fas fa-file-pdf"></i></span>
                    <span class="text-sm font-medium text-gray-700">Export Laporan Harian</span>
                </a>
                @endcan
                @can('view-flagged-records')
                <a href="{{ route('flagged.records') }}" class="glass-subtle flex items-center gap-3 rounded-2xl p-3 transition hover:-translate-y-0.5 hover:shadow-glass">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-orange-600 text-white shadow-float"><i class="fas fa-flag"></i></span>
                    <span class="text-sm font-medium text-gray-700">Data Flagged</span>
                </a>
                @endcan
                @can('access-full-dashboard')
                <a href="{{ route('analytics.overview') }}" class="glass-subtle flex items-center gap-3 rounded-2xl p-3 transition hover:-translate-y-0.5 hover:shadow-glass">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-float"><i class="fas fa-chart-line"></i></span>
                    <span class="text-sm font-medium text-gray-700">Analytics</span>
                </a>
                @endcan
                @can('edit-validation-rules')
                <a href="{{ route('settings.validation-rules') }}" class="glass-subtle flex items-center gap-3 rounded-2xl p-3 transition hover:-translate-y-0.5 hover:shadow-glass">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-400 to-gray-600 text-white shadow-float"><i class="fas fa-cog"></i></span>
                    <span class="text-sm font-medium text-gray-700">Aturan Validasi</span>
                </a>
                @endcan
                @can('manage-users')
                <a href="{{ route('settings.users') }}" class="glass-subtle flex items-center gap-3 rounded-2xl p-3 transition hover:-translate-y-0.5 hover:shadow-glass">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-400 to-purple-700 text-white shadow-float"><i class="fas fa-users-cog"></i></span>
                    <span class="text-sm font-medium text-gray-700">Manajemen User</span>
                </a>
                @endcan
            </div>
        </div>
    </div>
    @endif

    {{-- Recent activity --}}
    <div class="card card-pad">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-800"><i class="fas fa-stream mr-2 text-green-600"></i>Aktivitas Terbaru</h2>
            <span class="badge border-white/60 bg-white/55 text-gray-600">{{ $recent_logs->count() }} entri</span>
        </div>
        <div class="space-y-2">
            @forelse($recent_logs as $log)
            <div class="glass-subtle flex items-center justify-between rounded-2xl border-l-4 p-3 {{ $log->is_flagged ? 'border-l-amber-500' : 'border-l-green-500' }}">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-white/80 text-gray-500 shadow-inner">
                        <i class="fas fa-user text-sm"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">{{ $log->user?->name ?? 'Pengguna dihapus' }}</p>
                        <p class="text-xs text-gray-500">
                            {{ ucwords(str_replace('_', ' ', $log->getTable())) }} · {{ $log->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
                @if($log->is_flagged)
                <span class="badge border-amber-200/70 bg-amber-100 text-amber-800"><i class="fas fa-flag"></i>Flagged</span>
                @endif
            </div>
            @empty
            <div class="py-10 text-center text-gray-500">
                <i class="fas fa-inbox mb-2 text-3xl text-gray-300"></i>
                <p>Belum ada aktivitas hari ini</p>
            </div>
            @endforelse
        </div>
    </div>

</div>
@endsection
