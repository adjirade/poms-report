@extends('layouts.app')

@section('title', 'Profil Saya')
@section('subtitle', 'Kelola akun dan keamanan Anda')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <!-- Info Akun -->
    <div class="card overflow-hidden">
        <div class="sidebar-glass glass-sheen relative flex items-center gap-4 p-6">
            <div class="pointer-events-none absolute -right-8 -top-12 h-40 w-40 rounded-full bg-emerald-400/30 blur-3xl"></div>
            <div class="relative flex h-16 w-16 items-center justify-center rounded-3xl border border-white/25 bg-white/15 text-2xl font-bold text-white shadow-float backdrop-blur-xl">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="relative text-white">
                <p class="text-lg font-bold">{{ $user->name }}</p>
                <p class="text-sm text-emerald-100/90">{{ ucfirst($user->role) }} · {{ $user->plant_id }}</p>
            </div>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold">Nomor Telepon</p>
                <p class="text-gray-800 font-medium mt-0.5">{{ $user->phone_number }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold">Departemen</p>
                <p class="text-gray-800 font-medium mt-0.5">{{ $user->department ? ucfirst($user->department) : '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold">Status Akun</p>
                <span class="badge mt-1 {{ $user->status === 'active' ? 'border-green-200/70 bg-green-100 text-green-700' : 'border-red-200/70 bg-red-100 text-red-700' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $user->status === 'active' ? 'bg-green-500' : 'bg-red-500' }}"></span>
                    {{ ucfirst($user->status) }}
                </span>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold">Telegram</p>
                <p class="text-gray-800 font-medium mt-0.5">
                    @if($user->telegram_user_id)
                        <span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Terhubung</span>
                    @else
                        <span class="text-gray-400">Belum terhubung — kirim pesan apa pun ke bot untuk menghubungkan.</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 px-6 pb-6">
            <div class="glass-subtle rounded-2xl p-4 text-center">
                <p class="text-2xl font-bold text-gray-800">{{ number_format($totalSubmitted) }}</p>
                <p class="text-xs text-gray-500">Total record dikirim</p>
            </div>
            <div class="glass-subtle rounded-2xl p-4 text-center">
                <p class="text-2xl font-bold text-gray-800">{{ number_format($totalVerifiedBy) }}</p>
                <p class="text-xs text-gray-500">Record diverifikasi</p>
            </div>
        </div>
    </div>

    <!-- Preferensi Notifikasi Telegram -->
    <div class="card" x-data="{ master: {{ $user->telegram_notif_enabled ? 'true' : 'false' }} }">
        <div class="border-b border-white/50 px-6 py-4">
            <h2 class="font-bold text-gray-800"><i class="fab fa-telegram-plane mr-2 text-sky-500"></i>Notifikasi Telegram</h2>
            <p class="text-xs text-gray-500 mt-0.5">Atur pemberitahuan yang dikirim bot ke Telegram Anda.</p>
        </div>
        <form method="POST" action="{{ route('profile.telegram-notifications') }}" class="p-6 space-y-5">
            @csrf
            @method('PUT')

            <!-- Status koneksi bot -->
            <div class="rounded-2xl border {{ $user->telegram_user_id ? 'border-sky-200/70 bg-sky-50/80' : 'border-amber-200/70 bg-amber-50/80' }} p-4 text-sm">
                @if($user->telegram_user_id)
                    <p class="text-sky-900"><i class="fas fa-circle-check mr-2"></i><strong>Terhubung</strong> — notifikasi dikirim ke chat Telegram Anda.</p>
                @else
                    <p class="text-amber-900"><i class="fas fa-circle-info mr-2"></i><strong>Belum terhubung.</strong> Buka bot POMS di Telegram dan kirim pesan apa pun (mis. <code class="rounded bg-amber-100 px-1">/start</code>) untuk menghubungkan akun.</p>
                @endif
            </div>

            <!-- Master switch -->
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="font-semibold text-gray-800">Terima notifikasi</p>
                    <p class="text-xs text-gray-500">Saklar utama semua notifikasi Telegram. Sama dengan perintah <code class="rounded bg-gray-100 px-1">/notif on</code> di bot.</p>
                </div>
                <label class="inline-flex shrink-0 cursor-pointer items-center">
                    <input type="hidden" name="telegram_notif_enabled" value="0">
                    <input type="checkbox" name="telegram_notif_enabled" value="1" x-model="master"
                           class="peer sr-only">
                    <span class="relative h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-green-600 peer-focus-visible:ring-2 peer-focus-visible:ring-green-500 peer-focus-visible:ring-offset-2 peer-checked:[&>span]:translate-x-5">
                        <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"></span>
                    </span>
                </label>
            </div>

            <div class="space-y-4 border-l-2 pl-4 transition"
                 :class="master ? 'border-green-400/70 opacity-100' : 'border-gray-200 opacity-40'">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="font-medium text-gray-800">🚩 Data Flagged</p>
                        <p class="text-xs text-gray-500">Beritahu saat record di departemen Anda terdeteksi anomali. (Asisten)</p>
                    </div>
                    <label class="inline-flex shrink-0 cursor-pointer items-center">
                        <input type="hidden" name="telegram_notif_flagged" value="0">
                        <input type="checkbox" name="telegram_notif_flagged" value="1" @checked($user->telegram_notif_flagged)
                               class="peer sr-only" :disabled="!master">
                        <span class="relative h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-green-600 peer-checked:[&>span]:translate-x-5">
                            <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"></span>
                        </span>
                    </label>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="font-medium text-gray-800">✓ Verifikasi Data</p>
                        <p class="text-xs text-gray-500">Beritahu Anda saat data yang Anda kirim diverifikasi.</p>
                    </div>
                    <label class="inline-flex shrink-0 cursor-pointer items-center">
                        <input type="hidden" name="telegram_notif_verified" value="0">
                        <input type="checkbox" name="telegram_notif_verified" value="1" @checked($user->telegram_notif_verified)
                               class="peer sr-only" :disabled="!master">
                        <span class="relative h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-green-600 peer-checked:[&>span]:translate-x-5">
                            <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"></span>
                        </span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-bell"></i> Simpan Preferensi
            </button>
        </form>
    </div>

    <!-- Ganti Password -->
    <div class="card">
        <div class="border-b border-white/50 px-6 py-4">
            <h2 class="font-bold text-gray-800"><i class="fas fa-shield-alt mr-2 text-green-600"></i>Ganti Password</h2>
            <p class="text-xs text-gray-500 mt-0.5">Gunakan password minimal 8 karakter yang kuat.</p>
        </div>
        <form method="POST" action="{{ route('profile.password') }}" class="p-6 space-y-4 max-w-md">
            @csrf
            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Password Saat Ini</label>
                <input id="current_password" name="current_password" type="password" required autofocus
                       class="input @error('current_password') border-red-400 @enderror">
                @error('current_password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password Baru</label>
                <input id="password" name="password" type="password" required
                       class="input @error('password') border-red-400 @enderror">
                @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Ulangi Password Baru</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                       class="input">
            </div>
            <button type="submit" class="btn-primary">
                <i class="fas fa-key"></i> Simpan Password Baru
            </button>
        </form>
    </div>

</div>
@endsection
