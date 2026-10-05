@extends('layouts.app')

@section('title', 'Input Data Laporan')
@section('subtitle', 'Isi data stasiun langsung dari web')

@section('content')
<div class="space-y-6">

    <!-- Intro -->
    <div class="card card-pad">
        <div class="flex items-start gap-3">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-float">
                <i class="fas fa-pen-to-square"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-800">Pilih stasiun untuk mengisi data</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Data divalidasi memakai aturan yang sama dengan bot Telegram. Telegram tetap tersedia
                    sebagai cadangan, tetapi pengisian lewat web adalah kanal utama.
                </p>
            </div>
        </div>
    </div>

    <!-- Station cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @forelse($stations as $key => $label)
        <a href="{{ route('input.create', $key) }}"
           class="card card-pad group flex items-center justify-between transition hover:-translate-y-0.5 hover:shadow-glass-lg">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-float">
                    <i class="fas fa-industry"></i>
                </div>
                <div>
                    <p class="font-semibold text-gray-800">{{ $label }}</p>
                    <p class="text-xs text-gray-600">Isi data</p>
                </div>
            </div>
            <i class="fas fa-chevron-right text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-green-500"></i>
        </a>
        @empty
        <div class="card col-span-full p-8 text-center text-gray-600">
            <i class="fas fa-user-slash mb-2 text-3xl text-gray-300"></i>
            <p>Akun Anda belum terhubung ke departemen/stasiun mana pun. Hubungi developer.</p>
        </div>
        @endforelse
    </div>

    <!-- Recent submissions -->
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-white/40 px-4 py-3 sm:px-6">
            <h3 class="font-bold text-gray-800"><i class="fas fa-clock-rotate-left mr-2 text-green-600"></i>Input Terbaru Anda</h3>
            <span class="badge border-white/60 bg-white/55 text-gray-600">{{ count($recent) }} entri</span>
        </div>

        @if(count($recent) > 0)
        <ul class="divide-y divide-white/40">
            @foreach($recent as $row)
            <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/70 text-green-700 shadow-inner">
                        <i class="fas fa-industry text-xs"></i>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-gray-800">
                            {{ \App\Http\Controllers\LogInputController::STATIONS[$row['station']] ?? $row['station'] }}
                            <span class="text-gray-600">#{{ $row['id'] }}</span>
                        </p>
                        <p class="text-xs text-gray-600">{{ $row['timestamp']?->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    @if($row['is_flagged'])
                    <span class="badge border-amber-200/70 bg-amber-100 text-amber-800"><i class="fas fa-flag"></i>Flagged</span>
                    @endif
                    @if($row['is_verified'])
                    <span class="badge border-green-200/70 bg-green-100 text-green-800"><i class="fas fa-check-circle"></i>Verified</span>
                    @else
                    <span class="badge border-white/60 bg-white/55 text-gray-600"><i class="fas fa-clock"></i>Pending</span>
                    @endif
                </div>
            </li>
            @endforeach
        </ul>
        @else
        <div class="p-8 text-center text-gray-600">
            <i class="fas fa-inbox mb-2 text-3xl text-gray-300"></i>
            <p>Belum ada data yang Anda input.</p>
        </div>
        @endif
    </div>

</div>
@endsection
