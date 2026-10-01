<div class="bg-white rounded-lg shadow">
    <!-- Filters -->
    <div class="p-6 border-b border-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Date Range -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                <input type="date" 
                       wire:model.live="dateFrom" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
                <input type="date" 
                       wire:model.live="dateTo" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
            </div>
            
            <!-- Filters -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Filter</label>
                <select wire:model.live="filterType" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                    <option value="all">Semua Data</option>
                    <option value="flagged">Flagged Saja</option>
                    <option value="unverified">Belum Diverifikasi</option>
                    <option value="verified">Sudah Diverifikasi</option>
                </select>
            </div>
            
            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
                <input type="text" 
                       wire:model.live.debounce.300ms="search" 
                       placeholder="Cari user, ID..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
            </div>
        </div>
        
        <!-- Info -->
        <div class="mt-4 flex items-center justify-between text-sm text-gray-600">
            <span>Menampilkan {{ $logs->count() }} dari {{ $logs->total() }} data</span>
            <button wire:click="resetFilters" class="text-blue-600 hover:underline">
                <i class="fas fa-redo mr-1"></i>Reset Filter
            </button>
        </div>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu Kirim</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu Server</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    @can('verify-data')
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                    @endcan
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($logs as $log)
                <tr class="{{ $log->is_flagged ? 'bg-yellow-50' : '' }} hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        #{{ $log->id }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-gray-600 text-xs"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900">{{ $log->user->name }}</p>
                                <p class="text-xs text-gray-500">{{ $log->user->department ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900">
                            @foreach($log->getAttributes() as $key => $value)
                                @if(!in_array($key, ['id', 'user_id', 'plant_id', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes', 'created_at', 'updated_at']))
                                    <span class="inline-block mr-2">
                                        <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $log->timestamp_kirim->format('d/m/Y H:i:s') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $log->timestamp_server->format('d/m/Y H:i:s') }}
                        @if($log->is_flagged)
                            <br><span class="text-xs text-red-600">
                                <i class="fas fa-exclamation-triangle"></i>
                                Selisih: {{ $log->timestamp_server->diffInHours($log->timestamp_kirim) }}h
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col space-y-1">
                            @if($log->is_flagged)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                <i class="fas fa-flag mr-1"></i>Flagged
                            </span>
                            @endif
                            
                            @if($log->is_verified)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                <i class="fas fa-check-circle mr-1"></i>Verified
                            </span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                <i class="fas fa-clock mr-1"></i>Pending
                            </span>
                            @endif
                        </div>
                    </td>
                    @can('verify-data')
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if(!$log->is_verified)
                        <button type="button" wire:click="verifyRecord({{ $log->id }})" wire:confirm="Verifikasi data ini?"
                                class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs">
                            <i class="fas fa-check mr-1"></i>Verify
                        </button>
                        @else
                        <span class="text-xs text-gray-500">
                            by {{ $log->verifier->name ?? 'N/A' }}
                        </span>
                        @endif
                    </td>
                    @endcan
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-2"></i>
                        <p>Tidak ada data</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="px-6 py-4 border-t border-gray-200">
        {{ $logs->links() }}
    </div>
    
</div>
