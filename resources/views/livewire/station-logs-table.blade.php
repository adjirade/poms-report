<div class="card overflow-hidden">
    @php
        $canVerify = auth()->user()->can('verify-data');
        $colspan = $canVerify ? 7 : 6;
        $metaExcluded = ['id', 'user_id', 'plant_id', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes', 'created_at', 'updated_at', 'hq_synced_at', 'hq_source_id', 'operator_name'];
    @endphp

    <!-- Filters -->
    <div class="border-b border-white/40 p-4 sm:p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="slt-from" class="mb-1 block text-sm font-medium text-gray-700">Tanggal Mulai</label>
                <input id="slt-from" type="date" wire:model.live="dateFrom" class="input">
            </div>
            <div>
                <label for="slt-to" class="mb-1 block text-sm font-medium text-gray-700">Tanggal Akhir</label>
                <input id="slt-to" type="date" wire:model.live="dateTo" class="input">
            </div>
            <div>
                <label for="slt-filter" class="mb-1 block text-sm font-medium text-gray-700">Filter</label>
                <select id="slt-filter" wire:model.live="filterType" class="input">
                    <option value="all">Semua Data</option>
                    <option value="flagged">Flagged Saja</option>
                    <option value="unverified">Belum Diverifikasi</option>
                    <option value="verified">Sudah Diverifikasi</option>
                </select>
            </div>
            <div>
                <label for="slt-search" class="mb-1 block text-sm font-medium text-gray-700">Cari</label>
                <div class="relative">
                    <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-600"></i>
                    <input id="slt-search" type="text" wire:model.live.debounce.300ms="search"
                           placeholder="Cari user, ID..." class="input pl-9">
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600">
            <span>Menampilkan <strong class="text-gray-800">{{ $logs->count() }}</strong> dari <strong class="text-gray-800">{{ $logs->total() }}</strong> data</span>
            <button wire:click="resetFilters" class="btn-ghost py-1.5">
                <i class="fas fa-redo"></i>Reset Filter
            </button>
        </div>
    </div>

    <!-- Tabel (desktop / layar lebar) -->
    <div class="hidden overflow-x-auto lg:block">
        <table class="glass-table min-w-full divide-y divide-white/40 text-sm">
            <thead>
                <tr class="text-left">
                    <th scope="col" class="px-4 py-3 font-semibold">ID</th>
                    <th scope="col" class="px-4 py-3 font-semibold">User</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Data</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Waktu Kirim</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Waktu Server</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                    @if($canVerify)
                    <th scope="col" class="px-4 py-3 font-semibold">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-white/40">
                @forelse($logs as $log)
                <tr class="{{ $log->is_flagged ? 'bg-amber-100/40' : '' }} transition">
                    <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900">
                        #{{ $log->id }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-slate-700 shadow-inner">
                                <i class="fas fa-user text-xs"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">{{ $log->user?->name ?? 'N/A' }}</p>
                                <p class="text-xs text-gray-600">{{ $log->user?->department ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex max-w-md flex-wrap gap-x-3 gap-y-1 text-xs text-gray-700">
                            @foreach($log->getAttributes() as $key => $value)
                                @if(! in_array($key, $metaExcluded, true))
                                    <span class="inline-flex items-center gap-1">
                                        <strong class="font-semibold text-gray-600">{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong>
                                        <span class="text-gray-800">{{ $value }}</span>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                        {{ $log->timestamp_kirim->format('d/m/Y H:i:s') }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                        {{ $log->timestamp_server->format('d/m/Y H:i:s') }}
                        @if($log->is_flagged)
                            <br><span class="text-xs text-red-600">
                                <i class="fas fa-exclamation-triangle"></i>
                                Selisih: {{ number_format(abs($log->timestamp_server->diffInHours($log->timestamp_kirim)), 1) }} jam
                            </span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <div class="flex flex-col items-start gap-1">
                            @if($log->is_flagged)
                            <span class="badge bg-amber-100 text-amber-800">
                                <i class="fas fa-flag"></i>Flagged
                            </span>
                            @endif

                            @if($log->is_verified)
                            <span class="badge bg-green-100 text-green-800">
                                <i class="fas fa-check-circle"></i>Verified
                            </span>
                            @else
                            <span class="badge bg-gray-100 text-gray-600">
                                <i class="fas fa-clock"></i>Pending
                            </span>
                            @endif
                        </div>
                    </td>
                    @if($canVerify)
                    <td class="whitespace-nowrap px-4 py-3">
                        @if(! $log->is_verified)
                        <button type="button" wire:click="verifyRecord({{ $log->id }})" wire:confirm="Verifikasi data ini?"
                                class="btn-primary px-3 py-1.5 text-xs">
                            <i class="fas fa-check"></i>Verify
                        </button>
                        @else
                        <span class="text-xs text-gray-600">
                            by {{ $log->verifier->name ?? 'N/A' }}
                        </span>
                        @endif
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="px-6 py-12 text-center text-gray-600">
                        <i class="fas fa-inbox mb-2 text-3xl text-gray-300"></i>
                        <p>Tidak ada data</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Kartu (mobile / tablet) -->
    <div class="divide-y divide-white/40 lg:hidden">
        @forelse($logs as $log)
        <div class="p-4 {{ $log->is_flagged ? 'bg-amber-100/40' : '' }}">
            <div class="flex items-start justify-between gap-2">
                <span class="font-semibold text-gray-900">#{{ $log->id }}</span>
                <div class="flex flex-wrap items-center justify-end gap-1">
                    @if($log->is_flagged)
                    <span class="badge bg-amber-100 text-amber-800">
                        <i class="fas fa-flag"></i>Flagged
                    </span>
                    @endif
                    @if($log->is_verified)
                    <span class="badge bg-green-100 text-green-800">
                        <i class="fas fa-check-circle"></i>Verified
                    </span>
                    @else
                    <span class="badge bg-gray-100 text-gray-600">
                        <i class="fas fa-clock"></i>Pending
                    </span>
                    @endif
                </div>
            </div>

            <div class="mt-3 flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700">
                    <i class="fas fa-user text-xs"></i>
                </div>
                <div class="min-w-0">
                    <p class="truncate font-medium text-gray-900">{{ $log->user?->name ?? 'N/A' }}</p>
                    <p class="text-xs text-gray-600">{{ $log->user?->department ?? 'N/A' }}</p>
                </div>
            </div>

            <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                @foreach($log->getAttributes() as $key => $value)
                    @if(! in_array($key, $metaExcluded, true))
                    <div class="min-w-0">
                        <dt class="text-gray-600">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                        <dd class="break-words font-medium text-gray-800">{{ $value }}</dd>
                    </div>
                    @endif
                @endforeach
            </dl>

            <div class="mt-3 grid grid-cols-2 gap-3 border-t border-white/40 pt-3 text-xs">
                <div>
                    <p class="text-gray-600">Waktu Kirim</p>
                    <p class="font-medium text-gray-800">{{ $log->timestamp_kirim->format('d/m/Y H:i:s') }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Waktu Server</p>
                    <p class="font-medium text-gray-800">{{ $log->timestamp_server->format('d/m/Y H:i:s') }}</p>
                    @if($log->is_flagged)
                    <p class="text-red-600">
                        <i class="fas fa-exclamation-triangle"></i>
                        Selisih {{ number_format(abs($log->timestamp_server->diffInHours($log->timestamp_kirim)), 1) }} jam
                    </p>
                    @endif
                </div>
            </div>

            @if($canVerify)
            <div class="mt-3">
                @if(! $log->is_verified)
                <button type="button" wire:click="verifyRecord({{ $log->id }})" wire:confirm="Verifikasi data ini?"
                        class="btn-primary w-full py-2">
                    <i class="fas fa-check"></i>Verifikasi
                </button>
                @else
                <p class="text-center text-xs text-gray-600">
                    <i class="fas fa-user-check mr-1"></i>Diverifikasi oleh {{ $log->verifier->name ?? 'N/A' }}
                </p>
                @endif
            </div>
            @endif
        </div>
        @empty
        <div class="p-8 text-center text-gray-600">
            <i class="fas fa-inbox mb-2 text-3xl text-gray-300"></i>
            <p>Tidak ada data</p>
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($logs->hasPages())
    <div class="border-t border-white/40 px-4 py-3">
        {{ $logs->links() }}
    </div>
    @endif
</div>
