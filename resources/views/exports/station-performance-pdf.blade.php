<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Performa Stasiun {{ ucfirst($station) }} - {{ $plant }}</title>
    <style>
        @page {
            margin: 1.6cm 1.4cm 2.1cm 1.4cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            color: #111827;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 14px;
        }
        .info-grid > div {
            display: table-cell;
            padding: 4px 6px;
            font-size: 9pt;
        }
        .summary-row {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            border-collapse: separate;
            border-spacing: 6px 0;
        }
        .summary-box {
            display: table-cell;
            width: 25%;
            border: 1px solid #d1d5db;
            border-top: 3px solid #14532d;
            border-radius: 4px;
            padding: 8px 6px;
            text-align: center;
            background-color: #f8faf7;
        }
        .summary-box .num {
            font-size: 16pt;
            font-weight: bold;
            color: #14532d;
        }
        .summary-box .lbl {
            font-size: 7.5pt;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-title {
            font-size: 10.5pt;
            font-weight: bold;
            color: #14532d;
            border-left: 4px solid #14532d;
            padding-left: 8px;
            margin: 16px 0 8px 0;
            page-break-after: avoid;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.data th, table.data td {
            border: 1px solid #9ca3af;
            padding: 5px 6px;
            text-align: left;
            font-size: 8pt;
        }
        table.data th {
            background-color: #14532d;
            color: #ffffff;
            font-weight: bold;
        }
        table.data tbody tr:nth-child(even) td {
            background-color: #f0f5ec;
        }
        .chart-wrap {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 6px;
            text-align: center;
            background-color: #ffffff;
        }
        .chart-wrap img {
            width: 100%;
        }
        .chart-caption {
            font-size: 7.5pt;
            color: #6b7280;
            margin-top: 4px;
            text-align: center;
        }
        .flagged-cell {
            color: #b45309;
            font-weight: bold;
        }
    </style>
</head>
<body>
    @include('exports.partials.kop', [
        'docTitle' => 'Laporan Performa Stasiun ' . strtoupper($station),
        'docSubtitle' => 'Analisis tren harian parameter stasiun',
    ])

    <div class="info-grid">
        <div><strong>Stasiun:</strong> {{ $stationTitle }}</div>
        <div><strong>Periode:</strong> {{ $from->format('d/m/Y') }} s/d {{ $until->format('d/m/Y') }} ({{ $range }} hari)</div>
        <div style="text-align: right;"><strong>Dicetak oleh:</strong> {{ $preparedBy }} &nbsp;&nbsp; <strong>Waktu:</strong> {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    {{-- Ringkasan status verifikasi --}}
    <div class="summary-row">
        <div class="summary-box">
            <div class="num">{{ number_format($status['total']) }}</div>
            <div class="lbl">Total Record</div>
        </div>
        <div class="summary-box" style="border-top-color: #059669;">
            <div class="num" style="color: #047857;">{{ number_format($status['verified']) }}</div>
            <div class="lbl">Terverifikasi</div>
        </div>
        <div class="summary-box" style="border-top-color: #64748b;">
            <div class="num" style="color: #475569;">{{ number_format($status['pending']) }}</div>
            <div class="lbl">Menunggu Verifikasi</div>
        </div>
        <div class="summary-box" style="border-top-color: #e11d48;">
            <div class="num" style="color: #be123c;">{{ number_format($status['flagged']) }}</div>
            <div class="lbl">Flagged</div>
        </div>
    </div>

    @if(! empty($chartImage))
    <div class="section-title">GRAFIK TREN HARIAN</div>
    <div class="chart-wrap">
        <img src="{{ $chartImage }}" alt="Grafik tren harian {{ $stationTitle }}">
        <div class="chart-caption">Grafik diambil langsung dari dashboard Performa Stasiun (rata-rata harian per parameter, rentang {{ $range }} hari).</div>
    </div>
    @endif

    <div class="section-title">PERBANDINGAN HARI INI VS KEMARIN</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 34%;">Parameter</th>
                <th style="width: 18%;">Hari Ini</th>
                <th style="width: 18%;">Kemarin</th>
                <th style="width: 14%;">Perubahan</th>
                <th style="width: 16%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($comparison as $c)
            <tr>
                <td>{{ $c['label'] }}@if($c['unit']) ({{ $c['unit'] }})@endif</td>
                <td>{{ $c['today'] !== null ? number_format($c['today'], 2) : '—' }}</td>
                <td>{{ $c['yesterday'] !== null ? number_format($c['yesterday'], 2) : '—' }}</td>
                <td>
                    @if($c['delta_pct'] !== null)
                        {{ ($c['delta_pct'] >= 0 ? '+' : '') . $c['delta_pct'] }}%
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if($c['delta_pct'] === null)
                        <span style="color: #6b7280;">Tidak ada pembanding</span>
                    @elseif($c['delta_pct'] >= 0)
                        <span style="color: #047857;">▲ Naik</span>
                    @else
                        <span style="color: #be123c;">▼ Turun</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align: center;">Tidak ada konfigurasi series</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">REKAPITULASI HARIAN (RATA-RATA PER PARAMETER)</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 16%;">Tanggal</th>
                @foreach($seriesConfig as $s)
                    <th>{{ $s['label'] }}@if($s['unit']) ({{ $s['unit'] }})@endif</th>
                @endforeach
                <th style="width: 10%;">Record</th>
                <th style="width: 10%;">Flagged</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dailyRows as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                @foreach($seriesConfig as $s)
                    <td>{{ $row[$s['key']] ?? '—' }}</td>
                @endforeach
                <td>{{ number_format($row['total']) }}</td>
                <td class="{{ $row['flagged'] > 0 ? 'flagged-cell' : '' }}">{{ $row['flagged'] > 0 ? '⚑ ' . number_format($row['flagged']) : '0' }}</td>
            </tr>
            @empty
            <tr><td colspan="{{ count($seriesConfig) + 3 }}" style="text-align: center;">Tidak ada data pada periode ini</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('exports.partials.signatures', [
        'createdBy' => $preparedBy,
        'createdRole' => 'Analis / Asisten',
        'checkedRole' => 'Asisten Departemen',
        'approvedRole' => 'Kepala Pabrik',
    ])

    @include('exports.partials.footer')
</body>
</html>
