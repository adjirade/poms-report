@extends('layouts.app')

@section('title', 'Target KPI')
@section('subtitle', 'Atur target per parameter stasiun (manager)')

@section('content')
@php
    $directionLabels = [
        'lower' => 'Makin kecil makin baik (≤ maks)',
        'higher' => 'Makin besar makin baik (≥ min)',
        'range' => 'Rentang aman (min – maks)',
    ];
@endphp
<div class="space-y-6">

    <div class="card card-pad">
        <h2 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-bullseye text-green-700 mr-2"></i>
            Target vs Realisasi KPI
        </h2>
        <p class="mt-1 text-sm text-gray-600">
            Pabrik <strong>{{ $plantId }}</strong>. Nilai di sini menimpa default sistem dan dipakai di
            Command Center, Performa Stasiun, serta laporan PDF. Centang
            <em>Reset</em> pada satu baris untuk mengembalikannya ke default.
            Target <strong>plant-wide</strong> mengatur KPI pada Command Center.
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-300/60 bg-emerald-100/70 p-4 text-sm text-emerald-900">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-2xl border border-amber-300/60 bg-amber-100/70 p-4 text-sm text-amber-900">
            <i class="fas fa-triangle-exclamation mr-2"></i>{{ session('warning') }}
        </div>
    @endif

    {{-- Import / Export: salin target antar pabrik atau edit massal di spreadsheet. --}}
    <div class="card card-pad">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-file-csv text-green-700 mr-2"></i>Import / Export Target
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Ekspor memuat seluruh target efektif (default + override) dalam format CSV.
                    Impor menimpa target per baris <code class="font-mono text-xs">station, parameter</code>;
                    kolom <code class="font-mono text-xs">source</code> diabaikan saat impor.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('settings.kpi-targets.export') }}" class="btn-ghost">
                    <i class="fas fa-file-export"></i> Export CSV
                </a>
                <form method="POST" action="{{ route('settings.kpi-targets.import') }}"
                      enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="file" name="file" accept=".csv,text/csv" required aria-label="Pilih file CSV untuk diimpor"
                           class="input !min-h-0 !w-auto !py-1.5 text-xs">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-file-import"></i> Impor CSV
                    </button>
                </form>
            </div>
        </div>
        @error('file')
            <p class="mt-2 text-xs text-rose-700"><i class="fas fa-circle-exclamation mr-1"></i>{{ $message }}</p>
        @enderror
    </div>

    <form method="POST" action="{{ route('settings.kpi-targets.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        @forelse($stations as $station)
        <div class="card card-pad">
            <h3 class="mb-4 text-lg font-bold text-gray-800">
                <i class="fas fa-gauge-high text-green-700 mr-2"></i>{{ $station['title'] }}
            </h3>
            <div class="overflow-x-auto">
                <table class="glass-table w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/40">
                            <th scope="col" class="px-3 py-2.5">Parameter</th>
                            <th scope="col" class="px-3 py-2.5">Arah Target</th>
                            <th scope="col" class="px-3 py-2.5">Min</th>
                            <th scope="col" class="px-3 py-2.5">Maks</th>
                            <th scope="col" class="px-3 py-2.5">Satuan</th>
                            <th scope="col" class="px-3 py-2.5">Label</th>
                            <th scope="col" class="px-3 py-2.5">Sumber</th>
                            <th scope="col" class="px-3 py-2.5">Reset</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($station['parameters'] as $p)
                        @php $base = "targets[{$station['key']}][{$p['key']}]"; @endphp
                        <tr class="border-b border-white/30 align-top">
                            <td class="px-3 py-2.5">
                                <p class="font-semibold text-gray-800">{{ $p['label'] }}</p>
                                <p class="font-mono text-[11px] text-gray-600">{{ $p['key'] }}</p>
                            </td>
                            <td class="px-3 py-2.5">
                                <select name="{{ $base }}[direction]" aria-label="{{ $p['label'] }} — arah target" class="input !min-h-0 !py-1.5 text-xs">
                                    @foreach($directionLabels as $value => $label)
                                        <option value="{{ $value }}" {{ $p['direction'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2.5">
                                <input type="number" step="any" name="{{ $base }}[min]"
                                       value="{{ $p['min'] !== null ? $p['min'] : '' }}"
                                       aria-label="{{ $p['label'] }} — nilai minimum"
                                       class="input !min-h-0 !w-24 !py-1.5 text-xs" placeholder="—">
                            </td>
                            <td class="px-3 py-2.5">
                                <input type="number" step="any" name="{{ $base }}[max]"
                                       value="{{ $p['max'] !== null ? $p['max'] : '' }}"
                                       aria-label="{{ $p['label'] }} — nilai maksimum"
                                       class="input !min-h-0 !w-24 !py-1.5 text-xs" placeholder="—">
                            </td>
                            <td class="px-3 py-2.5">
                                <input type="text" name="{{ $base }}[unit]" value="{{ $p['unit'] }}"
                                       aria-label="{{ $p['label'] }} — satuan"
                                       class="input !min-h-0 !w-20 !py-1.5 text-xs" placeholder="—">
                            </td>
                            <td class="px-3 py-2.5">
                                <input type="text" name="{{ $base }}[label]" value="{{ $p['label'] }}"
                                       aria-label="{{ $p['key'] }} — label tampilan"
                                       class="input !min-h-0 !w-40 !py-1.5 text-xs">
                            </td>
                            <td class="px-3 py-2.5">
                                @if($p['is_override'])
                                    <span class="badge border-amber-200 bg-amber-100 text-amber-800">override</span>
                                @else
                                    <span class="badge badge-muted">default</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                <label class="inline-flex items-center gap-1.5 text-xs text-gray-600">
                                    <input type="checkbox" name="{{ $base }}[reset]" value="1"
                                           class="rounded border-gray-300 text-rose-600 focus:ring-rose-500">
                                    <span class="text-rose-700">Reset</span>
                                </label>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @empty
        <div class="card card-pad">
            <p class="text-sm text-gray-600">Belum ada stasiun dengan target terkonfigurasi.</p>
        </div>
        @endforelse

        <div class="flex items-center justify-end">
            <button type="submit" class="btn-primary">
                <i class="fas fa-floppy-disk"></i> Simpan Target
            </button>
        </div>
    </form>

</div>
@endsection
