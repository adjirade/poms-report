<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Produksi Mingguan - {{ $plant }}</title>
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
        .muted {
            color: #6b7280;
            font-style: italic;
        }
    </style>
</head>
<body>
    @php /** @var array $summary */ @endphp

    @include('exports.partials.kop', [
        'docTitle' => 'Laporan Produksi Mingguan',
        'docSubtitle' => 'Rekapitulasi operasional periode '.$summary['start']->translatedFormat('d F').' – '.$summary['end']->translatedFormat('d F Y'),
    ])

    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Pabrik:</div>
            <div class="info-value">{{ $plant }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Periode:</div>
            <div class="info-value">{{ $summary['start']->format('d/m/Y') }} – {{ $summary['end']->format('d/m/Y') }} ({{ $summary['days'] }} hari)</div>
        </div>
        <div class="info-row">
            <div class="info-label">Dibuat Oleh:</div>
            <div class="info-value">{{ $preparedBy }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Waktu Cetak:</div>
            <div class="info-value">{{ now()->format('d/m/Y H:i:s') }} WIB</div>
        </div>
    </div>

    <div class="section-title">1. RINGKASAN PER STASIUN</div>
    <table>
        <thead>
            <tr>
                <th>Stasiun</th>
                <th style="width: 18%;">Record</th>
                <th style="width: 18%;">Flagged</th>
                <th style="width: 22%;">Belum Verifikasi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['stations'] as $st)
                <tr>
                    <td>{{ $st['label'] }}</td>
                    <td>{{ number_format($st['total']) }}</td>
                    <td class="{{ $st['flagged'] > 0 ? 'status-high' : '' }}">{{ number_format($st['flagged']) }}</td>
                    <td class="{{ $st['unverified'] > 0 ? 'status-high' : '' }}">{{ number_format($st['unverified']) }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>Total</strong></td>
                <td><strong>{{ number_format($summary['records']) }}</strong></td>
                <td class="status-high"><strong>{{ number_format($summary['flagged']) }}</strong></td>
                <td><strong>{{ number_format($summary['unverified']) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">2. PRODUKSI &amp; KUALITAS</div>
    <table>
        <thead>
            <tr>
                <th>Parameter</th>
                <th style="width: 30%;">Nilai</th>
                <th style="width: 20%;">Satuan</th>
                <th style="width: 24%;">Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Tonnage Bruto</td>
                <td>{{ number_format($summary['tonnage'], 2) }}</td>
                <td>Ton</td>
                <td class="muted">—</td>
            </tr>
            <tr>
                <td>Rata-rata Tonnage Harian</td>
                <td>{{ number_format($summary['tonnage'] / max(1, $summary['days']), 2) }}</td>
                <td>Ton/hari</td>
                <td class="muted">—</td>
            </tr>
            <tr>
                <td>Total Record Masuk</td>
                <td>{{ number_format($summary['records']) }}</td>
                <td>Record</td>
                <td class="muted">—</td>
            </tr>
            <tr>
                <td>Kadar ALB (FFA) CPO</td>
                <td>{{ $summary['ffa'] !== null ? number_format($summary['ffa'], 2) : '-' }}</td>
                <td>%</td>
                <td class="{{ ($summary['ffa'] ?? 0) > 5 ? 'status-high' : 'status-ok' }}">
                    {{ $summary['ffa'] === null ? '—' : (($summary['ffa'] > 5) ? '⚠ Tinggi' : '✓ Normal') }}
                </td>
            </tr>
            <tr>
                <td>Losses Fiber</td>
                <td>{{ $summary['losses_fiber'] !== null ? number_format($summary['losses_fiber'], 2) : '-' }}</td>
                <td>%</td>
                <td class="{{ ($summary['losses_fiber'] ?? 0) > 5 ? 'status-high' : 'status-ok' }}">
                    {{ $summary['losses_fiber'] === null ? '—' : (($summary['losses_fiber'] > 5) ? '⚠ Tinggi' : '✓ Normal') }}
                </td>
            </tr>
            <tr>
                <td>Skor Efisiensi</td>
                <td>{{ $summary['efficiency'] }}</td>
                <td>/100</td>
                <td class="{{ $summary['efficiency'] < 60 ? 'status-high' : 'status-ok' }}">
                    {{ $summary['efficiency'] < 60 ? '⚠ Perlu perhatian' : '✓ Baik' }}
                </td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">3. TREN HARIAN</div>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th style="width: 25%;">Record</th>
                <th style="width: 25%;">Tonnage Bruto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['daily'] as $day)
                <tr>
                    <td>{{ $day['date']->translatedFormat('l, d/m/Y') }}</td>
                    <td>{{ number_format($day['records']) }}</td>
                    <td>{{ number_format($day['tonnage'], 2) }} ton</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">4. TIKET MAINTENANCE</div>
    <table>
        <thead>
            <tr>
                <th>Indikator</th>
                <th style="width: 25%;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Tiket Baru (periode ini)</td>
                <td>{{ number_format($summary['tickets']['created']) }}</td>
            </tr>
            <tr>
                <td>Tiket Selesai (periode ini)</td>
                <td>{{ number_format($summary['tickets']['done']) }}</td>
            </tr>
            <tr>
                <td>Tiket Masih Aktif (open / dikerjakan)</td>
                <td class="{{ $summary['tickets']['active'] > 0 ? 'status-high' : 'status-ok' }}">{{ number_format($summary['tickets']['active']) }}</td>
            </tr>
        </tbody>
    </table>

    @include('exports.partials.signatures', [
        'createdBy' => $preparedBy,
        'createdRole' => 'Asisten Proses',
        'checkedRole' => 'Asisten Kepala',
        'approvedRole' => 'Manager Pabrik',
    ])

    @include('exports.partials.footer')
</body>
</html>
