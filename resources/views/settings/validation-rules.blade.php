@extends('layouts.app')

@section('title', 'Aturan Validasi')
@section('subtitle', 'Batas parameter per stasiun')

@section('content')
<div class="space-y-6">
    
    <!-- Header -->
    <div class="card card-pad">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-sliders-h mr-2"></i>
                    Validation Rules Management
                </h2>
                <p class="text-gray-600 mt-1">
                    Kelola batas minimum dan maksimum parameter per stasiun untuk plant {{ auth()->user()->plant_id }}
                </p>
            </div>
            <span class="badge self-start border-green-200/70 bg-green-100 px-4 py-1.5 text-green-800 sm:self-auto">
                <i class="fas fa-sliders"></i>{{ $rules->flatten()->count() }} Rules Active
            </span>
        </div>
    </div>
    
    <!-- Rules by Station -->
    @foreach($rules as $stationName => $stationRules)
    <div class="card overflow-hidden">
        <div class="flex items-center gap-3 border-b border-white/50 px-6 py-4">
            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-float">
                <i class="fas fa-industry"></i>
            </span>
            <h3 class="text-xl font-bold text-gray-800">{{ ucfirst($stationName) }}</h3>
        </div>
        
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="glass-table w-full">
                    <thead>
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left">Parameter</th>
                            <th scope="col" class="px-4 py-3 text-left">Data Type</th>
                            <th scope="col" class="px-4 py-3 text-left">Min Value</th>
                            <th scope="col" class="px-4 py-3 text-left">Max Value</th>
                            <th scope="col" class="px-4 py-3 text-left">Enum Values</th>
                            <th scope="col" class="px-4 py-3 text-left">Last Updated</th>
                            <th scope="col" class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/40">
                        @foreach($stationRules as $rule)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ ucwords(str_replace('_', ' ', $rule->parameter_name)) }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="badge border-sky-200/70 bg-sky-100 text-sky-800">
                                    {{ strtoupper($rule->data_type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                @if($rule->data_type === 'numeric')
                                    {{ $rule->min_value }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                @if($rule->data_type === 'numeric')
                                    {{ $rule->max_value }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                @if($rule->data_type === 'enum' && $rule->allowed_values)
                                    <div class="flex flex-wrap gap-1">
                                        @foreach(explode(',', $rule->allowed_values) as $val)
                                            <span class="chip">{{ trim($val) }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $rule->updated_at->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3">
                                @if($rule->data_type === 'numeric')
                                <button onclick="openEditModal({{ $rule->id }}, '{{ $rule->parameter_name }}', {{ $rule->min_value }}, {{ $rule->max_value }})"
                                        class="btn-primary px-3 py-1.5 text-xs">
                                    <i class="fas fa-edit"></i>Edit
                                </button>
                                @else
                                <span class="text-xs text-gray-400">Enum</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach
    
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm">
    <div class="glass glass-sheen w-full max-w-md overflow-hidden shadow-glass-lg">
        <div class="flex items-center gap-3 border-b border-white/50 px-6 py-4">
            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-float">
                <i class="fas fa-sliders"></i>
            </span>
            <h3 class="text-xl font-bold text-gray-800">Edit Validation Rule</h3>
        </div>
        
        <form id="editForm" method="POST" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            
            <div>
                <label class="mb-2 block text-sm font-semibold text-gray-700">Parameter Name</label>
                <input type="text" id="paramName" readonly
                       class="input bg-white/50">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Min Value</label>
                    <input type="number" name="min_value" id="minValue" step="0.01" required class="input">
                </div>
                
                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Max Value</label>
                    <input type="number" name="max_value" id="maxValue" step="0.01" required class="input">
                </div>
            </div>
            
            <div class="rounded-2xl border border-amber-300/60 bg-amber-100/70 p-4 backdrop-blur-xl">
                <p class="text-sm text-amber-900">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Warning:</strong> Perubahan akan langsung mempengaruhi validasi data baru yang masuk.
                </p>
            </div>
            
            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary flex-1">
                    <i class="fas fa-save"></i>Save Changes
                </button>
                <button type="button" onclick="closeEditModal()" class="btn-ghost flex-1">
                    <i class="fas fa-times"></i>Cancel
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function openEditModal(id, paramName, minValue, maxValue) {
    document.getElementById('editForm').action = `/settings/validation-rules/${id}`;
    document.getElementById('paramName').value = paramName.replace(/_/g, ' ');
    document.getElementById('minValue').value = minValue;
    document.getElementById('maxValue').value = maxValue;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditModal();
    }
});
</script>
@endpush
