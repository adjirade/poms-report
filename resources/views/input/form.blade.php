@extends('layouts.app')

@section('title', 'Input '.$stationLabel)
@section('subtitle', 'Isi data stasiun '.$stationLabel)

@section('content')
<div class="mx-auto max-w-2xl space-y-6">

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="{{ route('input.index') }}"
           class="btn-icon"
           aria-label="Kembali">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-xl font-bold text-gray-800">{{ $stationLabel }}</h2>
            <p class="text-sm text-gray-500">Data divalidasi otomatis sesuai rentang standar stasiun.</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <form method="POST" action="{{ route('input.store', $station) }}" class="card-pad space-y-5">
            @csrf

            <!-- Daftar error umum (mis. di luar rentang) -->
            @if($errors->any())
            <div role="alert" class="rounded-2xl border border-red-300/60 bg-red-100/70 p-4 text-sm text-red-800 backdrop-blur-xl">
                <p class="mb-1 font-semibold"><i class="fas fa-exclamation-circle mr-1"></i>Periksa kembali data Anda:</p>
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach($fields as $field)
                @php
                    $name = $field['name'];
                    $isWide = in_array($name, ['keterangan_perbaikan', 'notes'], true);
                @endphp
                <div class="{{ $isWide ? 'sm:col-span-2' : '' }}">
                    <label for="f-{{ $name }}" class="mb-1 block text-sm font-medium text-gray-700">
                        {{ $field['label'] }}
                        @if($field['required'])<span class="text-red-500">*</span>@endif
                        @if($field['unit'])<span class="text-gray-400">({{ $field['unit'] }})</span>@endif
                    </label>

                    @if($field['type'] === 'enum')
                        <select id="f-{{ $name }}" name="{{ $name }}" class="input" {{ $field['required'] ? 'required' : '' }}>
                            <option value="">— Pilih —</option>
                            @foreach($field['allowed'] as $option)
                            <option value="{{ $option }}" {{ old($name) === $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                            @endforeach
                        </select>
                    @elseif($isWide)
                        <textarea id="f-{{ $name }}" name="{{ $name }}" rows="3" class="input" placeholder="{{ $field['required'] ? '' : 'Opsional' }}">{{ old($name) }}</textarea>
                    @elseif($field['type'] === 'numeric' || $field['type'] === 'integer')
                        <input id="f-{{ $name }}" type="number" step="any" inputmode="decimal"
                               name="{{ $name }}" value="{{ old($name) }}"
                               @if($field['min'] !== null) min="{{ $field['min'] }}" @endif
                               @if($field['max'] !== null) max="{{ $field['max'] }}" @endif
                               class="input" {{ $field['required'] ? 'required' : '' }}>
                    @else
                        <input id="f-{{ $name }}" type="text" name="{{ $name }}" value="{{ old($name) }}"
                               class="input" {{ $field['required'] ? 'required' : '' }}>
                    @endif

                    @if($field['min'] !== null && $field['max'] !== null && in_array($field['type'], ['numeric', 'integer'], true))
                    <p class="text-xs text-slate-600 mt-1">Rentang standar: {{ $field['min'] }} – {{ $field['max'] }} {{ $field['unit'] }}</p>
                    @endif

                    @error($name)<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                </div>
                @endforeach
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-white/50 pt-4 sm:flex-row sm:justify-end">
                <a href="{{ route('input.index') }}" class="btn-ghost">
                    Batal
                </a>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i>Simpan Data
                </button>
            </div>
        </form>
    </div>

    <p class="text-center text-xs text-gray-400">
        <i class="fas fa-circle-info mr-1"></i>
        Data yang tersimpan akan berstatus <strong>Pending</strong> sampai diverifikasi asisten/atasan.
    </p>

</div>
@endsection
