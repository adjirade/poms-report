<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Tiket Maintenance {{ $kodeMesin !== '' ? $kodeMesin : 'Semua Mesin' }} - {{ $plant }}</title>
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
            vertical-align: top;
        }
        table.data th {
            background-color: #14532d;
            color: #ffffff;
            font-weight: bold;
        }
        table.data tbody tr:nth-child(even) td {
            background-color: #f0f5ec;
        }
        .badge-open { color: #be123c; font-weight: bold; }
        .badge-progress { color: #b45309; font-weight: bold; }
        .badge-done { color: #047857; font-weight: bold; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    @include('exports.partials.kop', [
        'docTitle' => 'LAPORAN TIKET MAINTENANCE',
        'docSubtitle' => $kodeMesin !== '' ? 'Riwayat Mesin: '.$kodeMesin : 'Arsip Workshop — Semua Mesin',
    ])

    <div class="info-grid">
        <div><strong>Filter mesin:</strong> {{ $kodeMesin !== '' ? $kodeMesin : 'Semua mesin' }}</div>
        <div><strong>Status:</strong> {{ $statusFilter ? ucfirst($statusFilter) : 'Semua' }}</div>
        <div style="text-align: right;"><strong>Dicetak oleh:</strong> {{ $preparedBy }} &nbsp;&nbsp; <strong>Waktu:</strong> {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    @php
        $total = $tickets->count();
        $openCount = $tickets->where('status', 'open')->count();
        $progressCount = $tickets->where('status', 'dikerjakan')->count();
        $doneCount = $tickets->where('status', 'selesai')->count();
    @endphp

    <div class="summary-row">
        <div class="summary-box">
            <div class="num">{{ number_format($total) }}</div>
            <div class="lbl">Total Tiket</div>
        </div>
        <div class="summary-box" style="border-top-color: #e11d48;">
            <div class="num" style="color: #be123c;">{{ number_format($openCount) }}</div>
            <div class="lbl">Open</div>
        </div>
        <div class="summary-box" style="border-top-color: #d97706;">
            <div class="num" style="color: #b45309;">{{ number_format($progressCount) }}</div>
            <div class="lbl">Dikerjakan</div>
        </div>
        <div class="summary-box" style="border-top-color: #059669;">
            <div class="num" style="color: #047857;">{{ number_format($doneCount) }}</div>
            <div class="lbl">Selesai</div>
        </div>
    </div>

    <div class="section-title">RINGKASAN PER MESIN</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 34%;">Kode Mesin</th>
                <th style="width: 16%;">Total</th>
                <th style="width: 16%;">Open</th>
                <th style="width: 17%;">Dikerjakan</th>
                <th style="width: 17%;">Selesai</th>
            </tr>
        </thead>
        <tbody>
            @forelse($perMachine as $machine => $counts)
            <tr>
                <td>{{ $machine }}</td>
                <td>{{ number_format($counts['total']) }}</td>
                <td>{{ number_format($counts['open']) }}</td>
                <td>{{ number_format($counts['dikerjakan']) }}</td>
                <td>{{ number_format($counts['selesai']) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align: center;">Tidak ada tiket pada filter ini</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">DAFTAR TIKET</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th style="width: 14%;">Mesin</th>
                <th style="width: 30%;">Masalah</th>
                <th style="width: 10%;">Prioritas</th>
                <th style="width: 11%;">Status</th>
                <th style="width: 13%;">Pelapor</th>
                <th style="width: 16%;">Waktu / Selesai</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tickets as $ticket)
            <tr>
                <td>{{ $ticket->id }}</td>
                <td>{{ $ticket->kode_mesin }}</td>
                <td>
                    <strong>{{ $ticket->judul }}</strong>
                    @if($ticket->deskripsi && $ticket->deskripsi !== $ticket->judul)
                        <div class="muted">{{ $ticket->deskripsi }}</div>
                    @endif
                    @if($ticket->resolution_note)
                        <div class="badge-done">✓ {{ $ticket->resolution_note }}</div>
                    @endif
                </td>
                <td>{{ $ticket->priorityLabel() }}</td>
                <td class="{{ $ticket->status === 'open' ? 'badge-open' : ($ticket->status === 'dikerjakan' ? 'badge-progress' : 'badge-done') }}">
                    {{ $ticket->statusLabel() }}
                </td>
                <td>
                    {{ $ticket->reporter?->name ?? '—' }}
                    <div class="muted">Teknisi: {{ $ticket->assignee?->name ?? '—' }}</div>
                </td>
                <td>
                    {{ $ticket->created_at?->format('d/m/Y H:i') ?? '—' }}
                    @if($ticket->resolved_at)
                        <div class="badge-done">selesai {{ $ticket->resolved_at->format('d/m/Y H:i') }}</div>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align: center;">Tidak ada tiket pada filter ini</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('exports.partials.signatures', [
        'createdBy' => $preparedBy,
        'createdRole' => 'Pelapor / Asisten',
        'checkedRole' => 'Asisten Maintenance',
        'approvedRole' => 'Kepala Pabrik',
    ])

    @include('exports.partials.footer')
</body>
</html>
