<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Produksi Harian - {{ $plant }}</title>
    <style>
        @page {
            margin: 1.6cm 1.4cm 2.1cm 1.4cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #111827;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            background-color: #f0f5ec;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            width: 24%;
            padding: 6px 8px;
            font-weight: bold;
            font-size: 9pt;
        }
        .info-value {
            display: table-cell;
            padding: 6px 8px;
            font-size: 9pt;
        }
        .section-title {
            font-size: 10.5pt;
            font-weight: bold;
            color: #14532d;
            border-left: 4px solid #14532d;
            padding-left: 8px;
            margin: 18px 0 8px 0;
            page-break-after: avoid;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table th, table td {
            border: 1px solid #9ca3af;
            padding: 6px 8px;
            text-align: left;
            font-size: 9pt;
        }
        table th {
            background-color: #14532d;
            color: #ffffff;
            font-weight: bold;
        }
        table tbody tr:nth-child(even) td {
            background-color: #f0f5ec;
        }
        .status-ok {
            color: #047857;
            font-weight: bold;
        }
        .status-high {
            color: #b91c1c;
            font-weight: bold;
        }
    </style>
</head>
<body>
    @include('exports.partials.kop', [
        'docTitle' => 'Laporan Produksi Harian',
        'docSubtitle' => 'Rekapitulasi operasional seluruh stasiun utama',
    ])

    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Dibuat Oleh:</div>
            <div class="info-value">{{ $preparedBy }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Tanggal Laporan:</div>
            <div class="info-value">{{ \Carbon\Carbon::parse($date)->translatedFormat('d F Y') }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Waktu Cetak:</div>
            <div class="info-value">{{ now()->format('d/m/Y H:i:s') }} WIB</div>
        </div>
    </div>

    <div class="section-title">1. PENERIMAAN BUAH (WEIGHTBRIDGE)</div>
    <table>
        <thead>
            <tr>
                <th>Parameter</th>
                <th style="width: 30%;">Nilai</th>
                <th style="width: 20%;">Satuan</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Kiriman</td>
                <td>{{ number_format($data['timbang']->total_entries ?? 0) }}</td>
                <td>Kali</td>
            </tr>
            <tr>
                <td>Total Tonase Bruto</td>
                <td>{{ number_format($data['timbang']->total_bruto ?? 0, 2) }}</td>
                <td>Kg</td>
            </tr>
            <tr>
                <td>Total Tonase Tarra</td>
                <td>{{ number_format($data['timbang']->total_tarra ?? 0, 2) }}</td>
                <td>Kg</td>
            </tr>
            <tr>
                <td>Rata-rata Potongan</td>
                <td>{{ number_format($data['timbang']->avg_potongan ?? 0, 2) }}</td>
                <td>%</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">2. STERILIZER (PEREBUSAN)</div>
    <table>
        <thead>
            <tr>
                <th>Parameter</th>
                <th style="width: 30%;">Rata-rata</th>
                <th style="width: 20%;">Satuan</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Jumlah Rebusan</td>
                <td>{{ number_format($data['sterilizer']->total_entries ?? 0) }}</td>
                <td>Kali</td>
            </tr>
            <tr>
                <td>Tekanan</td>
                <td>{{ number_format($data['sterilizer']->avg_tekanan ?? 0, 2) }}</td>
                <td>Bar</td>
            </tr>
            <tr>
                <td>Suhu</td>
                <td>{{ number_format($data['sterilizer']->avg_suhu ?? 0, 1) }}</td>
                <td>°C</td>
            </tr>
            <tr>
                <td>Durasi</td>
                <td>{{ number_format($data['sterilizer']->avg_durasi ?? 0, 1) }}</td>
                <td>Menit</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">3. SCREW PRESS (PENGEPRESAN)</div>
    <table>
        <thead>
            <tr>
                <th>Parameter</th>
                <th style="width: 30%;">Rata-rata</th>
                <th style="width: 20%;">Satuan</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Jumlah Operasi Press</td>
                <td>{{ number_format($data['press']->total_entries ?? 0) }}</td>
                <td>Kali</td>
            </tr>
            <tr>
                <td>Tekanan Hidrolik</td>
                <td>{{ number_format($data['press']->avg_tekanan ?? 0, 2) }}</td>
                <td>Kg/cm²</td>
            </tr>
            <tr>
                <td>Ampere Motor</td>
                <td>{{ number_format($data['press']->avg_ampere ?? 0, 2) }}</td>
                <td>A</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">4. LABORATORIUM (QC &amp; LOSSES)</div>
    <table>
        <thead>
            <tr>
                <th>Parameter</th>
                <th style="width: 24%;">Rata-rata</th>
                <th style="width: 16%;">Satuan</th>
                <th style="width: 24%;">Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Kadar ALB (FFA) CPO</td>
                <td>{{ number_format($data['lab']->avg_ffa ?? 0, 2) }}</td>
                <td>%</td>
                <td class="{{ ($data['lab']->avg_ffa ?? 0) > 5 ? 'status-high' : 'status-ok' }}">
                    {{ ($data['lab']->avg_ffa ?? 0) > 5 ? '⚠ Tinggi' : '✓ Normal' }}
                </td>
            </tr>
            <tr>
                <td>Losses Fiber</td>
                <td>{{ number_format($data['lab']->avg_losses_fiber ?? 0, 2) }}</td>
                <td>%</td>
                <td class="{{ ($data['lab']->avg_losses_fiber ?? 0) > 5 ? 'status-high' : 'status-ok' }}">
                    {{ ($data['lab']->avg_losses_fiber ?? 0) > 5 ? '⚠ Tinggi' : '✓ Normal' }}
                </td>
            </tr>
            <tr>
                <td>Losses Jankos</td>
                <td>{{ number_format($data['lab']->avg_losses_jankos ?? 0, 2) }}</td>
                <td>%</td>
                <td class="{{ ($data['lab']->avg_losses_jankos ?? 0) > 1 ? 'status-high' : 'status-ok' }}">
                    {{ ($data['lab']->avg_losses_jankos ?? 0) > 1 ? '⚠ Tinggi' : '✓ Normal' }}
                </td>
            </tr>
        </tbody>
    </table>

    @include('exports.partials.signatures', [
        'createdBy' => $preparedBy,
        'createdRole' => 'Operator Shift',
        'checkedRole' => 'Asisten Proses',
        'approvedRole' => 'Manager Pabrik',
    ])

    @include('exports.partials.footer')
</body>
</html>
