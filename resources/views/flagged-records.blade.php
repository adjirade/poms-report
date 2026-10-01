@extends('layouts.app')

@section('content')
<div class="space-y-6">
    
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-flag text-yellow-600 mr-2"></i>
                    Data Flagged Records
                </h2>
                <p class="text-gray-600 mt-1">
                    Data dengan selisih waktu > 4 jam yang memerlukan verifikasi
                </p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-lg font-semibold">
                    Total: {{ $flaggedRecords->count() }} records
                </span>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" action="{{ route('flagged.records') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Station</label>
                <select name="station" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">Semua Station</option>
                    <option value="timbang" {{ request('station') == 'timbang' ? 'selected' : '' }}>Timbang</option>
                    <option value="sortasi" {{ request('station') == 'sortasi' ? 'selected' : '' }}>Sortasi</option>
                    <option value="sterilizer" {{ request('station') == 'sterilizer' ? 'selected' : '' }}>Sterilizer</option>
                    <option value="press" {{ request('station') == 'press' ? 'selected' : '' }}>Press</option>
                    <option value="klarifikasi" {{ request('station') == 'klarifikasi' ? 'selected' : '' }}>Klarifikasi</option>
                    <option value="kernel" {{ request('station') == 'kernel' ? 'selected' : '' }}>Kernel</option>
                    <option value="lab" {{ request('station') == 'lab' ? 'selected' : '' }}>Lab</option>
                    <option value="maintenance" {{ request('station') == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
            </div>
        </form>
    </div>
    
    <!-- Flagged Records Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-yellow-50 border-b border-yellow-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Station</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Waktu Kirim</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Waktu Server</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Selisih</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($flaggedRecords as $record)
                    <tr class="bg-yellow-50 hover:bg-yellow-100">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 bg-gray-200 text-gray-800 rounded-full text-xs font-semibold">
                                {{ ucfirst($record['station']) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            #{{ $record['id'] }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-yellow-200 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-yellow-700 text-xs"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $record['user_name'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $record['department'] ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $record['timestamp_kirim']->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $record['timestamp_server']->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-bold">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                {{ round($record['time_diff_hours'], 1) }} jam
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($record['is_verified'])
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                                <i class="fas fa-check-circle mr-1"></i>Verified
                            </span>
                            @else
                            <span class="px-3 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-semibold">
                                <i class="fas fa-clock mr-1"></i>Pending
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <a href="{{ route('stations.' . $record['station']) }}" 
                               class="text-blue-600 hover:text-blue-800 font-medium">
                                <i class="fas fa-eye mr-1"></i>View Details
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-check-circle text-green-500 text-4xl mb-2"></i>
                            <p class="text-lg font-semibold">Tidak ada data flagged</p>
                            <p class="text-sm">Semua data dalam rentang waktu normal</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Info Box -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-blue-600 text-2xl mr-4 mt-1"></i>
            <div>
                <h3 class="text-lg font-semibold text-blue-900 mb-2">Tentang Data Flagged</h3>
                <p class="text-blue-800 mb-2">
                    Data ditandai (flagged) secara otomatis ketika selisih antara waktu kirim dan waktu server 
                    melebihi <strong>4 jam</strong>. Ini bisa mengindikasikan:
                </p>
                <ul class="list-disc list-inside text-blue-800 space-y-1">
                    <li>Operator mengirim data di area tanpa sinyal (normal)</li>
                    <li>Data tertunda di queue Telegram (perlu investigasi)</li>
                    <li>Manipulasi waktu sistem (perlu audit)</li>
                </ul>
                <p class="text-blue-800 mt-3">
                    <strong>Action:</strong> Verifikasi data flagged untuk memastikan validitas sebelum digunakan dalam laporan.
                </p>
            </div>
        </div>
    </div>
    
</div>
@endsection
