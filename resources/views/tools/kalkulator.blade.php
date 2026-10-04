@extends('layouts.app')

@section('title', 'Kalkulator Operasional')
@section('subtitle', 'Hitung cepat tonnage, potongan, dan rendemen')

@section('content')
<div class="mx-auto max-w-3xl space-y-6" x-data="{
    neto: { bruto: null, tarra: null },
    potongan: { berat: null, persen: null },
    rendemen: { buah: null, cpo: null, kernel: null },
    konversi: { nilai: null, dari: 'ton' },
}">

    <!-- Tonnage Neto -->
    <div class="card card-pad">
        <h2 class="text-lg font-bold text-gray-800">
            <i class="fas fa-weight-hanging text-blue-600 mr-2"></i>
            Tonnage Neto (Timbang)
        </h2>
        <p class="mt-0.5 text-sm text-gray-500">Berat bersih kiriman = bruto − tarra.</p>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <label for="k-bruto" class="mb-1 block text-sm font-medium text-gray-700">Bruto (kg)</label>
                <input id="k-bruto" type="number" min="0" step="0.1" x-model.number="neto.bruto" class="input" placeholder="0">
            </div>
            <div>
                <label for="k-tarra" class="mb-1 block text-sm font-medium text-gray-700">Tarra (kg)</label>
                <input id="k-tarra" type="number" min="0" step="0.1" x-model.number="neto.tarra" class="input" placeholder="0">
            </div>
        </div>
        <div class="glass-subtle mt-4 rounded-2xl p-4 text-center" x-show="neto.bruto !== null && neto.tarra !== null && neto.bruto >= neto.tarra">
            <p class="text-3xl font-bold text-green-700" x-text="((neto.bruto || 0) - (neto.tarra || 0)).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' kg'"></p>
            <p class="mt-1 text-sm text-gray-500"
               x-text="(((neto.bruto || 0) - (neto.tarra || 0)) / 1000).toLocaleString('id-ID', { maximumFractionDigits: 3 }) + ' ton'"></p>
        </div>
        <p class="mt-3 text-sm font-medium text-rose-700" x-show="neto.bruto !== null && neto.tarra !== null && neto.bruto < neto.tarra">
            <i class="fas fa-triangle-exclamation mr-1"></i>Bruto harus lebih besar atau sama dengan tarra.
        </p>
    </div>

    <!-- Potongan Buah -->
    <div class="card card-pad">
        <h2 class="text-lg font-bold text-gray-800">
            <i class="fas fa-percent text-amber-600 mr-2"></i>
            Potongan Buah (kg)
        </h2>
        <p class="mt-0.5 text-sm text-gray-500">Konversi persentase potongan ke kilogram dari berat lot.</p>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <label for="k-berat" class="mb-1 block text-sm font-medium text-gray-700">Berat Lot (kg)</label>
                <input id="k-berat" type="number" min="0" step="0.1" x-model.number="potongan.berat" class="input" placeholder="0">
            </div>
            <div>
                <label for="k-persen" class="mb-1 block text-sm font-medium text-gray-700">Potongan (%)</label>
                <input id="k-persen" type="number" min="0" max="100" step="0.1" x-model.number="potongan.persen" class="input" placeholder="0">
            </div>
        </div>
        <div class="glass-subtle mt-4 rounded-2xl p-4 text-center" x-show="potongan.berat > 0 && potongan.persen !== null">
            <p class="text-3xl font-bold text-amber-700" x-text="((potongan.berat || 0) * (potongan.persen || 0) / 100).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' kg'"></p>
            <p class="mt-1 text-sm text-gray-500"
               x-text="'Bersih: ' + ((potongan.berat || 0) * (100 - (potongan.persen || 0)) / 100).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' kg'"></p>
        </div>
    </div>

    <!-- Rendemen / OER -->
    <div class="card card-pad">
        <h2 class="text-lg font-bold text-gray-800">
            <i class="fas fa-oil-can text-emerald-600 mr-2"></i>
            Rendemen / OER (%)
        </h2>
        <p class="mt-0.5 text-sm text-gray-500">Rendemen CPO = CPO keluar ÷ buah masuk × 100. Kernel ratio opsional.</p>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <label for="k-buah" class="mb-1 block text-sm font-medium text-gray-700">Buah Masuk (ton)</label>
                <input id="k-buah" type="number" min="0" step="0.01" x-model.number="rendemen.buah" class="input" placeholder="0">
            </div>
            <div>
                <label for="k-cpo" class="mb-1 block text-sm font-medium text-gray-700">CPO Keluar (ton)</label>
                <input id="k-cpo" type="number" min="0" step="0.01" x-model.number="rendemen.cpo" class="input" placeholder="0">
            </div>
            <div>
                <label for="k-kernel" class="mb-1 block text-sm font-medium text-gray-700">Kernel (ton, opsional)</label>
                <input id="k-kernel" type="number" min="0" step="0.01" x-model.number="rendemen.kernel" class="input" placeholder="0">
            </div>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2" x-show="rendemen.buah > 0 && rendemen.cpo !== null">
            <div class="glass-subtle rounded-2xl p-4 text-center">
                <p class="text-xs text-gray-500">Rendemen / OER</p>
                <p class="text-3xl font-bold text-emerald-700"
                   x-text="((rendemen.cpo || 0) / (rendemen.buah || 1) * 100).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + '%'"></p>
            </div>
            <div class="glass-subtle rounded-2xl p-4 text-center" x-show="rendemen.kernel !== null && rendemen.kernel > 0">
                <p class="text-xs text-gray-500">Kernel Ratio</p>
                <p class="text-3xl font-bold text-teal-700"
                   x-text="((rendemen.kernel || 0) / (rendemen.buah || 1) * 100).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + '%'"></p>
            </div>
        </div>
    </div>

    <!-- Konversi Satuan -->
    <div class="card card-pad">
        <h2 class="text-lg font-bold text-gray-800">
            <i class="fas fa-right-left text-purple-600 mr-2"></i>
            Konversi Satuan
        </h2>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="k-nilai" class="mb-1 block text-sm font-medium text-gray-700">Nilai</label>
                <input id="k-nilai" type="number" min="0" step="0.001" x-model.number="konversi.nilai" class="input" placeholder="0">
            </div>
            <div class="w-full sm:w-40">
                <label for="k-dari" class="mb-1 block text-sm font-medium text-gray-700">Dari Satuan</label>
                <select id="k-dari" x-model="konversi.dari" class="input">
                    <option value="ton">Ton</option>
                    <option value="kg">Kilogram</option>
                </select>
            </div>
        </div>
        <div class="glass-subtle mt-4 rounded-2xl p-4 text-center" x-show="konversi.nilai !== null">
            <p class="text-sm text-gray-600"
               x-text="konversi.dari === 'ton'
                   ? (konversi.nilai || 0).toLocaleString('id-ID', { maximumFractionDigits: 3 }) + ' ton = ' + ((konversi.nilai || 0) * 1000).toLocaleString('id-ID') + ' kg'
                   : (konversi.nilai || 0).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' kg = ' + ((konversi.nilai || 0) / 1000).toLocaleString('id-ID', { maximumFractionDigits: 3 }) + ' ton'"></p>
        </div>
    </div>

    <p class="text-center text-xs text-gray-500">
        <i class="fas fa-circle-info mr-1"></i>Semua perhitungan berjalan di perangkat Anda — tidak ada data yang dikirim ke server.
    </p>
</div>
@endsection
