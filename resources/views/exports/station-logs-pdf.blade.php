<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Log {{ ucfirst($station) }} - {{ $plant }}</title>
    <style>
        @page {
            margin: 1.6cm 1.4cm 2.1cm 1.4cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            color: #111827;
        }
        .meta {
            display: table;
            width: 100%;
            margin-bottom: 12px;
            background-color: #f0f5ec;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        .meta div {
            display: table-cell;
            padding: 6px 8px;
            font-size: 8.5pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table th, table td {
            border: 1px solid #9ca3af;
            padding: 5px 6px;
            text-align: left;
            font-size: 8pt;
        }
        table th {
            background-color: #14532d;
            color: #ffffff;
            font-weight: bold;
        }
        table tbody tr:nth-child(even) td {
            background-color: #f0f5ec;
        }
        tr.flagged td {
            background-color: #fef3c7;
        }
        .flag-badge {
            color: #b45309;
            font-weight: bold;
        }
        .ok-badge {
            color: #047857;
        }
        .pend-badge {
            color: #6b7280;
        }
    </style>
</head>
<body>
    @include('exports.partials.kop', [
        'docTitle' => 'Laporan Data Stasiun ' . strtoupper($station),
        'docSubtitle' => 'Rekapitulasi record logging operator',
    ])

    <div class="meta">
        <div><strong>Dicetak oleh:</strong> {{ $preparedBy }}</div>
        <div><strong>Periode:</strong> {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
        <div style="text-align: right;"><strong>Total Data:</strong> {{ $logs->count() }} record &nbsp;&nbsp; <strong>Cetak:</strong> {{ now()->format('d/m/Y H:i:s') }} WIB</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 6%;">ID</th>
                <th style="width: 14%;">Operator</th>
                <th style="width: 12%;">Waktu Kirim</th>
                <th style="width: 12%;">Waktu Server</th>
                @foreach($columns as $col)
                <th>{{ $col['label'] }}@if(! empty($col['unit'])) ({{ $col['unit'] }})@endif</th>
                @endforeach
                <th style="width: 9%;">Status</th>
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
                @foreach($columns as $col)
                <td>{{ $log->{$col['key']} }}</td>
                @endforeach
                <td>
                    @if($log->is_flagged)<span class="flag-badge">⚑ Flag</span>@endif
                    @if($log->is_verified)
                        <span class="ok-badge">✓ Verified</span>
                    @else
                        <span class="pend-badge">Pending</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ 6 + count($columns) }}" style="text-align: center;">Tidak ada data pada periode ini</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @include('exports.partials.signatures', [
        'createdBy' => $preparedBy,
        'createdRole' => 'Operator Shift',
        'checkedRole' => 'Asisten',
        'approvedRole' => 'Kepala Pabrik',
    ])

    @include('exports.partials.footer')
</body>
</html>
