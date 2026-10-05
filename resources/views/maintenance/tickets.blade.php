@extends('layouts.app')

@section('title', 'Tiket Maintenance')
@section('subtitle', 'Laporan kerusakan mesin — open → dikerjakan → selesai')

@section('content')
@php
    $statusBadge = [
        'open' => 'border-rose-200 bg-rose-100 text-rose-800',
        'dikerjakan' => 'border-amber-200 bg-amber-100 text-amber-800',
        'selesai' => 'border-emerald-200 bg-emerald-100 text-emerald-800',
    ];
    $priorityBadge = [
        'tinggi' => 'border-rose-200 bg-rose-100 text-rose-800',
        'sedang' => 'border-amber-200 bg-amber-100 text-amber-800',
        'rendah' => 'border-sky-200 bg-sky-100 text-sky-800',
    ];
    $statusLabels = ['open' => 'Open', 'dikerjakan' => 'Dikerjakan', 'selesai' => 'Selesai'];
@endphp

<div class="space-y-6">

    <div class="card card-pad">
        <h2 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-screwdriver-wrench text-green-700 mr-2"></i>
            Tiket Maintenance
        </h2>
        <p class="mt-1 text-sm text-gray-600">
            Laporkan kerusakan mesin, lalu kelola status penanganannya. Notifikasi
            otomatis dikirim ke departemen maintenance (Telegram) dan ke pelapor
            saat status berubah.
        </p>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <span class="badge {{ $statusBadge['open'] }}">Open: {{ (int) ($counts->open_count ?? 0) }}</span>
            <span class="badge {{ $statusBadge['dikerjakan'] }}">Dikerjakan: {{ (int) ($counts->in_progress_count ?? 0) }}</span>
            <span class="badge {{ $statusBadge['selesai'] }}">Selesai: {{ (int) ($counts->done_count ?? 0) }}</span>
            @can('export-data')
                <a href="{{ route('export.maintenance-tickets', array_filter(['kode_mesin' => $mesin, 'status' => $status])) }}"
                   class="btn-ghost ml-auto">
                    <i class="fas fa-file-pdf"></i> Unduh PDF{{ $mesin !== '' ? " ({$mesin})" : '' }}
                </a>
            @endcan
        </div>
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

    {{-- Form laporan kerusakan --}}
    @can('report-maintenance')
    <div class="card card-pad">
        <h3 class="mb-4 text-lg font-bold text-gray-800">
            <i class="fas fa-triangle-exclamation text-amber-600 mr-2"></i>Laporkan Kerusakan
        </h3>
        <form method="POST" action="{{ route('maintenance.tickets.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @csrf
            <div>
                <label for="kode_mesin" class="mb-1 block text-xs font-semibold text-gray-600">Kode Mesin <span class="text-rose-700">*</span></label>
                <input id="kode_mesin" type="text" name="kode_mesin" value="{{ old('kode_mesin') }}"
                       class="input" placeholder="mis. KERNEL-01 / PRESS-02" required>
                @error('kode_mesin')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="prioritas" class="mb-1 block text-xs font-semibold text-gray-600">Prioritas</label>
                <select id="prioritas" name="prioritas" class="input">
                    <option value="rendah" {{ old('prioritas') === 'rendah' ? 'selected' : '' }}>Rendah</option>
                    <option value="sedang" {{ old('prioritas', 'sedang') === 'sedang' ? 'selected' : '' }}>Sedang</option>
                    <option value="tinggi" {{ old('prioritas') === 'tinggi' ? 'selected' : '' }}>Tinggi</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="judul" class="mb-1 block text-xs font-semibold text-gray-600">Judul Masalah <span class="text-rose-700">*</span></label>
                <input id="judul" type="text" name="judul" value="{{ old('judul') }}"
                       class="input" placeholder="mis. Kebocoran steam pada valve rebusan" required>
                @error('judul')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label for="deskripsi" class="mb-1 block text-xs font-semibold text-gray-600">Deskripsi <span class="text-rose-700">*</span></label>
                <textarea id="deskripsi" name="deskripsi" rows="3" class="input" placeholder="Detail kerusakan, gejala, dan dampak terhadap operasi" required>{{ old('deskripsi') }}</textarea>
                @error('deskripsi')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Kirim Tiket</button>
            </div>
        </form>
    </div>
    @endcan

    {{-- Filter --}}
    <div class="card card-pad">
        <form method="GET" action="{{ route('maintenance.tickets') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="fstatus" class="mb-1 block text-xs font-semibold text-gray-600">Status</label>
                <select id="fstatus" name="status" class="input !min-h-0 !py-1.5 text-xs">
                    <option value="">Semua</option>
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="fmesin" class="mb-1 block text-xs font-semibold text-gray-600">Kode Mesin (riwayat)</label>
                <input id="fmesin" type="text" name="kode_mesin" value="{{ $mesin }}" class="input !min-h-0 !py-1.5 text-xs" placeholder="mis. PRESS-01">
            </div>
            <button type="submit" class="btn-ghost"><i class="fas fa-filter"></i> Terapkan</button>
            @if($status !== '' || $mesin !== '')
                <a href="{{ route('maintenance.tickets') }}" class="text-xs text-gray-600 underline">Reset</a>
            @endif
        </form>
    </div>

    {{-- Daftar tiket --}}
    <div class="card card-pad">
        <h3 class="mb-4 text-lg font-bold text-gray-800">Daftar Tiket</h3>
        <div class="overflow-x-auto">
            <table class="glass-table w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-white/40">
                        <th scope="col" class="px-3 py-2.5">#</th>
                        <th scope="col" class="px-3 py-2.5">Mesin</th>
                        <th scope="col" class="px-3 py-2.5">Masalah</th>
                        <th scope="col" class="px-3 py-2.5">Prioritas</th>
                        <th scope="col" class="px-3 py-2.5">Status</th>
                        <th scope="col" class="px-3 py-2.5">Pelapor</th>
                        <th scope="col" class="px-3 py-2.5">Teknisi</th>
                        <th scope="col" class="px-3 py-2.5">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                    <tr class="border-b border-white/30 align-top">
                        <td class="px-3 py-2.5 font-mono text-xs text-gray-600">#{{ $ticket->id }}</td>
                        <td class="px-3 py-2.5 font-semibold text-gray-800">{{ $ticket->kode_mesin }}</td>
                        <td class="px-3 py-2.5">
                            <p class="font-semibold text-gray-800">{{ $ticket->judul }}</p>
                            <p class="mt-0.5 text-xs text-gray-600">{{ $ticket->deskripsi }}</p>
                            @if($ticket->resolution_note)
                                <p class="mt-1 text-xs text-emerald-700"><i class="fas fa-check mr-1"></i>{{ $ticket->resolution_note }}</p>
                            @endif
                            <p class="mt-1 text-[11px] text-gray-600">{{ $ticket->created_at?->format('d/m/Y H:i') }}</p>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="badge {{ $priorityBadge[$ticket->prioritas] ?? 'badge-muted' }}">{{ $ticket->priorityLabel() }}</span>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="badge {{ $statusBadge[$ticket->status] ?? 'badge-muted' }}">{{ $ticket->statusLabel() }}</span>
                            @if($ticket->resolved_at)
                                <p class="mt-1 text-[11px] text-gray-600">selesai {{ $ticket->resolved_at->format('d/m H:i') }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs text-gray-600">{{ $ticket->reporter?->name ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-xs text-gray-600">{{ $ticket->assignee?->name ?? '—' }}</td>
                        <td class="px-3 py-2.5">
                            @can('manage-maintenance')
                            <form method="POST" action="{{ route('maintenance.tickets.update', $ticket) }}" class="space-y-1.5">
                                @csrf
                                @method('PUT')
                                <select name="status" class="input !min-h-0 !py-1 text-xs">
                                    @foreach($statusLabels as $value => $label)
                                        <option value="{{ $value }}" {{ $ticket->status === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select name="assigned_to" class="input !min-h-0 !py-1 text-xs">
                                    <option value="">— Teknisi —</option>
                                    @foreach($technicians as $tech)
                                        <option value="{{ $tech->id }}" {{ (int) $ticket->assigned_to === $tech->id ? 'selected' : '' }}>{{ $tech->name }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="resolution_note" value="{{ $ticket->resolution_note }}"
                                       class="input !min-h-0 !py-1 text-xs" placeholder="Catatan penyelesaian">
                                <button type="submit" class="btn-primary !min-h-0 !py-1 text-xs"><i class="fas fa-save"></i> Simpan</button>
                            </form>
                            @else
                                <span class="text-xs text-gray-600">—</span>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-sm text-gray-600">Belum ada tiket maintenance.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $tickets->links() }}
        </div>
    </div>

    {{-- Riwayat per mesin --}}
    @if($history->isNotEmpty())
    <div class="card card-pad">
        <h3 class="mb-4 text-lg font-bold text-gray-800">
            <i class="fas fa-clock-rotate-left text-green-700 mr-2"></i>Riwayat Mesin: {{ $mesin }}
        </h3>
        <ul class="space-y-2">
            @foreach($history as $row)
            <li class="flex flex-wrap items-center gap-2 border-b border-white/30 pb-2 text-sm">
                <span class="font-mono text-xs text-gray-600">#{{ $row->id }}</span>
                <span class="badge {{ $statusBadge[$row->status] ?? 'badge-muted' }}">{{ $row->statusLabel() }}</span>
                <span class="text-gray-700">{{ $row->judul }}</span>
                <span class="text-xs text-gray-600">{{ $row->created_at?->format('d/m/Y H:i') }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

</div>
@endsection
