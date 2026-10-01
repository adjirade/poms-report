<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Log {{ ucfirst($station) }} - {{ $plant }}</title>
    <style>
        @page {
            margin: 1.5cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #2d5016;
            padding-bottom: 8px;
        }
        .header h1 {
            margin: 5px 0;
            font-size: 14pt;
        }
        .header p {
            margin: 3px 0;
            font-size: 10pt;
        }
        .meta {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .meta div {
            display: table-cell;
            padding: 3px 5px;
            font-size: 9pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table th, table td {
            border: 1px solid #333;
            padding: 5px 6px;
            text-align: left;
            font-size: 8pt;
        }
        table th {
            background-color: #eaf2e0;
            font-weight: bold;
        }
        tr.flagged td {
            background-color: #fdf6d8;
        }
        .signatures {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        .signature-box {
            display: table-cell;
            width: 33%;
            text-align: center;
            padding: 10px;
            font-size: 9pt;
        }
        .signature-line {
            margin-top: 55px;
            border-top: 1px solid #000;
            padding-top: 5px;
        }
        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 8pt;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>LAPORAN DATA STASIUN {{ strtoupper($station) }}</h1>
        <p>Pabrik Kelapa Sawit {{ $plant }}</p>
        <p>Periode: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</p>
    </div>

    <div class="meta">
        <div><strong>Dicetak oleh:</strong> {{ $preparedBy }}</div>
        <div style="text-align: right;"><strong>Waktu Cetak:</strong> {{ now()->format('d/m/Y H:i:s') }} WIB</div>
        <div><strong>Total Data:</strong> {{ $logs->count() }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 6%;">ID</th>
                <th style="width: 14%;">Operator</th>
                <th style="width: 12%;">Waktu Kirim</th>
                <th style="width: 12%;">Waktu Server</th>
                @foreach($columns as $label => $column)
                <th>{{ $label }}</th>
                @endforeach
                <th style="width: 7%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $index => $log)
            <tr class="{{ $log->is_flagged ? 'flagged' : '' }}">
                <td>{{ $index + 1 }}</td>
                <td>#{{ $log->id }}</td>
                <td>{{ $log->user->name ?? 'N/A' }}</td>
                <td>{{ optional($log->timestamp_kirim)->format('d/m/Y H:i') }}</td>
                <td>{{ optional($log->timestamp_server)->format('d/m/Y H:i') }}</td>
                @foreach($columns as $column)
                <td>{{ $log->{$column} }}</td>
                @endforeach
                <td>
                    @if($log->is_flagged)<span style="color: #b45309;">⚑ Flag</span>@endif
                    {{ $log->is_verified ? '✓ Verified' : 'Pending' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ 6 + count($columns) }}" style="text-align: center;">Tidak ada data pada periode ini</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="signatures">
        <div class="signature-box">
            <div>Dibuat Oleh,</div>
            <div class="signature-line">
                <strong>{{ $preparedBy }}</strong><br>
            </div>
        </div>
        <div class="signature-box">
            <div>Diperiksa Oleh,</div>
            <div class="signature-line">
                (........................)<br>
                Asisten
            </div>
        </div>
        <div class="signature-box">
            <div>Disetujui Oleh,</div>
            <div class="signature-line">
                (........................)<br>
                Kepala Pabrik
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Dokumen dicetak otomatis dari sistem POMS Report - {{ now()->format('d/m/Y H:i') }} — Data flagged ditandai dengan latar kuning</p>
    </div>
</body>
</html>
