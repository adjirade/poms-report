@extends('layouts.app')

@section('title', 'Environment & Integrasi')
@section('subtitle', 'Ubah token, API, username, dan identitas pabrik tanpa edit file .env manual')

@section('content')
@php
    // Key boolean -> dirender sebagai select true/false.
    $booleanKeys = ['APP_DEBUG', 'TELEGRAM_NOTIF_ENABLED', 'TELEGRAM_RECAP_ENABLED', 'HQ_SYNC_ENABLED'];
    $envPath = $envPath ?? base_path('.env');
@endphp
<div class="space-y-6">

    <!-- Info sumber + status bot -->
    <div class="card card-pad">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-sliders text-green-700 mr-2"></i>
                    Environment &amp; Integrasi
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    File: <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ $envPath }}</code>
                    @if($envExists)
                        <span class="badge ml-1 border-emerald-200 bg-emerald-100 text-emerald-800">ada</span>
                    @else
                        <span class="badge ml-1 border-rose-200 bg-rose-100 text-rose-800">belum ada</span>
                    @endif
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    Hanya nilai yang ditampilkan di form ini yang dapat diubah. Baris lain di .env tidak disentuh.
                    Key rahasia dikosongkan = tidak diubah.
                </p>
            </div>

            <div class="flex flex-col items-start gap-2">
                @if($botInfo)
                    <div class="rounded-2xl border border-emerald-200/70 bg-emerald-100/70 px-4 py-2 text-sm text-emerald-900">
                        <i class="fas fa-robot mr-1"></i>
                        Bot aktif: <strong>@{{ $botInfo['username'] ?? '-' }}</strong>
                        @if(! empty($botInfo['username']))
                            &middot;
                            <a href="https://t.me/{{ $botInfo['username'] }}" target="_blank" rel="noopener"
                               class="font-semibold underline">buka di Telegram</a>
                        @endif
                    </div>
                @elseif(($values['TELEGRAM_BOT_TOKEN'] ?? '') !== '')
                    <div class="rounded-2xl border border-rose-200/70 bg-rose-100/70 px-4 py-2 text-sm text-rose-900">
                        <i class="fas fa-triangle-exclamation mr-1"></i> Token terisi, tetapi bot tidak merespons.
                    </div>
                @else
                    <div class="rounded-2xl border border-amber-200/70 bg-amber-100/70 px-4 py-2 text-sm text-amber-900">
                        <i class="fas fa-circle-info mr-1"></i> TELEGRAM_BOT_TOKEN belum diisi.
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.environment.test-bot') }}">
                    @csrf
                    <button type="submit" class="btn-ghost !min-h-0 !px-3 !py-1.5 text-xs">
                        <i class="fas fa-plug"></i> Uji Koneksi Bot (getMe)
                    </button>
                </form>
            </div>
        </div>

        @if($webhookInfo && ! empty($webhookInfo['result']))
        <div class="mt-4 rounded-2xl border border-white/50 bg-white/60 p-4 text-sm">
            <p class="font-semibold text-gray-800"><i class="fas fa-satellite-dish mr-1"></i> Status Webhook</p>
            <p class="mt-1 text-gray-600">
                URL: <code>{{ ($webhookInfo['result']['url'] ?? '') !== '' ? $webhookInfo['result']['url'] : '(kosong — mode long polling)' }}</code>
            </p>
            @if(($webhookInfo['result']['last_error_message'] ?? null))
                <p class="mt-1 text-rose-700">Error terakhir: {{ $webhookInfo['result']['last_error_message'] }}</p>
            @endif
            <p class="mt-1 text-xs text-gray-500">
                Pending updates: {{ $webhookInfo['result']['pending_update_count'] ?? 0 }}
            </p>
        </div>
        @endif
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

    <form method="POST" action="{{ route('settings.environment.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        @foreach($groups as $groupTitle => $keys)
        <div class="card card-pad">
            <h3 class="mb-4 text-lg font-bold text-gray-800">
                <i class="fas fa-layer-group text-green-700 mr-2"></i>{{ $groupTitle }}
            </h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach($keys as $key)
                    @continue(! array_key_exists($key, $values))
                    @php
                        $isSecret = isset($secrets[$key]);
                        $current = $values[$key];
                        $isBool = in_array($key, $booleanKeys, true);
                    @endphp
                    <label class="flex flex-col gap-1.5 text-sm font-medium text-gray-600">
                        <span class="font-mono text-xs text-gray-500">{{ $key }}</span>
                        @if($isBool)
                            <select name="env[{{ $key }}]" class="input">
                                <option value="true" {{ strtolower($current) === 'true' ? 'selected' : '' }}>true</option>
                                <option value="false" {{ strtolower($current) !== 'true' ? 'selected' : '' }}>false</option>
                            </select>
                        @elseif($isSecret)
                            <input type="password" name="env[{{ $key }}]" autocomplete="new-password"
                                   class="input font-mono"
                                   placeholder="{{ $current !== '' ? '•••••••• (tersimpan — kosongkan bila tidak diubah)' : 'belum diisi' }}">
                        @else
                            <input type="text" name="env[{{ $key }}]" value="{{ $current }}"
                                   class="input font-mono">
                        @endif
                    </label>
                @endforeach
            </div>
        </div>
        @endforeach

        <div class="flex items-center justify-end gap-3">
            <span class="text-xs text-gray-500">
                <i class="fas fa-shield-halved mr-1"></i> Perubahan langsung ditulis ke .env &amp; config cache dibersihkan.
            </span>
            <button type="submit" class="btn-primary">
                <i class="fas fa-floppy-disk"></i> Simpan Perubahan
            </button>
        </div>
    </form>

</div>
@endsection
