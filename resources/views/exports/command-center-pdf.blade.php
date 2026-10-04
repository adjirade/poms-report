<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Command Center - {{ $plant }}</title>
    <style>
        @page { margin: 1.6cm 1.4cm 2.1cm 1.4cm; }
        body { font-family: Arial, sans-serif; font-size: 9pt; color: #111827; }
        .info-grid { display: table; width: 100%; margin-bottom: 14px; }
        .info-grid > div { display: table-cell; padding: 4px 6px; font-size: 9pt; }
        .summary-row { display: table; width: 100%; margin-bottom: 16px; border-collapse: separate; border-spacing: 6px 0; }
        .summary-box {
            display: table-cell; width: 16%; border: 1px solid #d1d5db; border-top: 3px solid #14532d;
            border-radius: 4px; padding: 8px 6px; text-align: center; background-color: #f8faf7;
        }
        .summary-box .num { font-size: 15pt; font-weight: bold; color: #14532d; }
        .summary-box .lbl { font-size: 7pt; color: #4b5563; text-transform: uppercase; letter-spacing: 0.4px; }
        .section-title {
            font-size: 10.5pt; font-weight: bold; color: #14532d; border-left: 4px solid #14532d;
            padding-left: 8px; margin: 16px 0 8px 0; page-break-after: avoid;
        }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data th, table.data td { border: 1px solid #9ca3af; padding: 5px 6px; text-align: left; font-size: 8pt; }
        table.data th { background-color: #14532d; color: #ffffff; font-weight: bold; }
        table.data tbody tr:nth-child(even) td { background-color: #f0f5ec; }
        .ok { color: #047857; font-weight: bold; }
        .bad { color: #be123c; font-weight: bold; }
        .muted { color: #6b7280; }
        .bar-bg { width: 100%; background-color: #e5e7eb; height: 8px; }
        .bar-fill { height: 8px; background-color: #059669; }
        .bar-fill.bad { background-color: #e11d48; }
    </style>
</head>
<body>
    @include('exports.partials.kop', [
        'docTitle' => 'LAPORAN COMMAND CENTER',
        'docSubtitle' => 'Ringkasan operasional pabrik dalam satu lembar',
    ])

    <div class="info-grid">
        <div><strong>Pabrik:</strong> {{ $plantName }} ({{ $plant }})</div>
        <div><strong>Tanggal:</strong> {{ now()->format('d/m/Y') }}</div>
        <div style="text-align: right;"><strong>Dicetak oleh:</strong> {{ $preparedBy }} &nbsp;&nbsp; <strong>Waktu:</strong> {{ now()->format('H:i') }}</div>
    </div>

    {{-- KPI utama --}}
    <div class="summary-row">
        <div class="summary-box">
            <div class="num">{{ number_format($kpi['records_today']) }}</div>
            <div class="lbl">Record Hari Ini</div>
        </div>
        <div class="summary-box">
            <div class="num" style="color: #1d4ed8;">{{ number_format($kpi['tonnage_today'], 2) }}</div>
            <div class="lbl">Tonnage (ton)</div>
        </div>
        <div class="summary-box">
            <div class="num" style="color: {{ ($kpi['ffa_today'] ?? 0) > 5 ? '#be123c' : '#047857' }};">{{ $kpi['ffa_today'] !== null ? number_format($kpi['ffa_today'], 2) : '—' }}</div>
            <div class="lbl">FFA / ALB (%)</div>
        </div>
        <div class="summary-box">
            <div class="num">{{ $kpi['losses_fiber_today'] !== null ? number_format($kpi['losses_fiber_today'], 2) : '—' }}</div>
            <div class="lbl">Losses Fiber (%)</div>
        </div>
        <div class="summary-box">
            <div class="num" style="color: #6d28d9;">{{ $kpi['efficiency'] }}</div>
            <div class="lbl">Skor Efisiensi</div>
        </div>
        <div class="summary-box">
            <div class="num" style="color: #be123c;">{{ number_format($kpi['flagged_today']) }}</div>
            <div class="lbl">Flagged / {{ number_format($kpi['unverified_today']) }} Pending</div>
        </div>
    </div>

    {{-- Target vs realisasi --}}
    <div class="section-title">TARGET VS REALISASI KPI — HARI INI</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 26%;">KPI</th>
                <th style="width: 16%;">Target</th>
                <th style="width: 16%;">Realisasi</th>
                <th style="width: 14%;">Capaian</th>
                <th style="width: 28%;">Progress</th>
            </tr>
        </thead>
        <tbody>
            @forelse($kpiTargets as $t)
            @php
                $ok = $t['achieved'] === true;
                $none = $t['achieved'] === null;
            @endphp
            <tr>
                <td>{{ $t['label'] }}</td>
                <td>{{ $t['target_text'] }} @if($t['unit']){{ $t['unit'] }}@endif</td>
                <td>{{ $t['actual'] !== null ? number_format($t['actual'], 2) : '—' }}</td>
                <td>
                    @if($none)
                        <span class="muted">— Data</span>
                    @elseif($ok)
                        <span class="ok">✓ Tercapai</span>
                    @else
                        <span class="bad">✗ Belum</span>
                    @endif
                </td>
                <td>
                    <table style="width: 100%; border-collapse: collapse;"><tr>
                        <td style="border: none; width: 80%; padding: 0;">
                            <div class="bar-bg"><div class="bar-fill {{ $ok ? '' : 'bad' }}" style="width: {{ $t['progress'] }}%;"></div></div>
                        </td>
                        <td style="border: none; width: 20%; padding: 0 0 0 6px; font-size: 7.5pt;">{{ $t['progress'] }}%</td>
                    </tr></table>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align: center;">Belum ada target KPI terkonfigurasi</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Status stasiun --}}
    <div class="section-title">STATUS 8 STASIUN — HARI INI</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 40%;">Stasiun</th>
                <th style="width: 16%;">Record</th>
                <th style="width: 16%;">Flagged</th>
                <th style="width: 16%;">Pending</th>
                <th style="width: 12%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stationStatus as $st)
            @php
                $label = ['ok' => 'Normal', 'warning' => 'Ada Pending', 'danger' => 'Flagged', 'idle' => 'Belum Ada Data'][$st['status']] ?? '-';
                $cls = $st['status'] === 'danger' ? 'bad' : ($st['status'] === 'ok' ? 'ok' : 'muted');
            @endphp
            <tr>
                <td>{{ $st['title'] }}</td>
                <td>{{ number_format($st['total']) }}</td>
                <td class="{{ $st['flagged'] > 0 ? 'bad' : '' }}">{{ number_format($st['flagged']) }}</td>
                <td>{{ number_format($st['unverified']) }}</td>
                <td class="{{ $cls }}">{{ $label }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Tren 7 hari --}}
    <div class="section-title">TREN 7 HARI</div>
    <table class="data">
        <thead>
            <tr>
                <th>Tanggal</th>
                @foreach($dailyTrend as $day)
                    <th style="text-align: center;">{{ $day['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Record</strong></td>
                @foreach($dailyTrend as $day)
                    <td style="text-align: center;">{{ number_format($day['total']) }}</td>
                @endforeach
            </tr>
            <tr>
                <td><strong>Flagged</strong></td>
                @foreach($dailyTrend as $day)
                    <td style="text-align: center;" class="{{ $day['flagged'] > 0 ? 'bad' : '' }}">{{ number_format($day['flagged']) }}</td>
                @endforeach
            </tr>
            <tr>
                <td><strong>Tonnage (ton)</strong></td>
                @foreach($tonnageTrend as $day)
                    <td style="text-align: center;">{{ number_format($day['bruto'], 2) }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    @include('exports.partials.signatures', [
        'createdBy' => $preparedBy,
        'createdRole' => 'Operator / Administrasi',
        'checkedRole' => 'Asisten Departemen',
        'approvedRole' => 'Kepala Pabrik',
    ])

    @include('exports.partials.footer')
</body>
</html>
