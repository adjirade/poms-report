@extends('layouts.app')

@section('content')
<div class="space-y-6">
    
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-sliders-h mr-2"></i>
                    Validation Rules Management
                </h2>
                <p class="text-gray-600 mt-1">
                    Kelola batas minimum dan maksimum parameter per stasiun untuk plant {{ auth()->user()->plant_id }}
                </p>
            </div>
            <span class="px-4 py-2 bg-green-100 text-green-800 rounded-lg font-semibold">
                {{ $rules->flatten()->count() }} Rules Active
            </span>
        </div>
    </div>
    
    <!-- Rules by Station -->
    @foreach($rules as $stationName => $stationRules)
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-green-600 text-white px-6 py-4">
            <h3 class="text-xl font-bold">
                <i class="fas fa-industry mr-2"></i>
                {{ ucfirst($stationName) }}
            </h3>
        </div>
        
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Parameter</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Data Type</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Min Value</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Max Value</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Enum Values</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Last Updated</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($stationRules as $rule)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ ucwords(str_replace('_', ' ', $rule->parameter_name)) }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-semibold">
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
                                            <span class="px-2 py-0.5 bg-gray-200 rounded text-xs">{{ trim($val) }}</span>
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
                                        class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded">                                        <i class="fas fa-edit mr-1"></i>Edit
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
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="bg-green-600 text-white px-6 py-4 rounded-t-lg">
            <h3 class="text-xl font-bold">Edit Validation Rule</h3>
        </div>
        
        <form id="editForm" method="POST" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Parameter Name</label>
                <input type="text" id="paramName" readonly 
                       class="w-full px-3 py-2 border border-gray-300 bg-gray-50 rounded-lg">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Min Value</label>
                    <input type="number" name="min_value" id="minValue" step="0.01" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Max Value</label>
                    <input type="number" name="max_value" id="maxValue" step="0.01" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Warning:</strong> Perubahan akan langsung mempengaruhi validasi data baru yang masuk.
                </p>
            </div>
            
            <div class="flex items-center space-x-3">
                <button type="submit" class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold">
                    <i class="fas fa-save mr-2"></i>Save Changes
                </button>
                <button type="button" onclick="closeEditModal()" 
                        class="flex-1 px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg font-semibold">
                    <i class="fas fa-times mr-2"></i>Cancel
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
