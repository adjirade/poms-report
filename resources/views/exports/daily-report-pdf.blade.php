<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Produksi Harian - {{ $plant }}</title>
    <style>
        @page {
            margin: 2cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 5px 0;
            font-size: 16pt;
        }
        .header p {
            margin: 3px 0;
            font-size: 10pt;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            width: 30%;
            padding: 5px;
            font-weight: bold;
        }
        .info-value {
            display: table-cell;
            padding: 5px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h2 {
            background-color: #2d5016;
            color: white;
            padding: 8px;
            font-size: 12pt;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table th, table td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .signatures {
            margin-top: 50px;
            display: table;
            width: 100%;
        }
        .signature-box {
            display: table-cell;
            width: 33%;
            text-align: center;
            padding: 10px;
        }
        .signature-line {
            margin-top: 60px;
            border-top: 1px solid #000;
            padding-top: 5px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>LAPORAN PRODUKSI HARIAN</h1>
        <p>Pabrik Kelapa Sawit {{ $plant }}</p>
        <p>Tanggal: {{ \Carbon\Carbon::parse($date)->format('d F Y') }}</p>
    </div>

    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Dibuat Oleh:</div>
            <div class="info-value">{{ $preparedBy }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Waktu Cetak:</div>
            <div class="info-value">{{ now()->format('d/m/Y H:i:s') }}</div>
        </div>
    </div>

    <div class="section">
        <h2>1. PENERIMAAN BUAH (WEIGHTBRIDGE)</h2>
        <table>
            <tr>
                <th>Parameter</th>
                <th>Nilai</th>
                <th>Satuan</th>
            </tr>
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
        </table>
    </div>

    <div class="section">
        <h2>2. STERILIZER (PEREBUSAN)</h2>
        <table>
            <tr>
                <th>Parameter</th>
                <th>Rata-rata</th>
                <th>Satuan</th>
            </tr>
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
        </table>
    </div>

    <div class="section">
        <h2>3. SCREW PRESS (PENGEPRESAN)</h2>
        <table>
            <tr>
                <th>Parameter</th>
                <th>Rata-rata</th>
                <th>Satuan</th>
            </tr>
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
        </table>
    </div>

    <div class="section">
        <h2>4. LABORATORIUM (QC & LOSSES)</h2>
        <table>
            <tr>
                <th>Parameter</th>
                <th>Rata-rata</th>
                <th>Satuan</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>Kadar ALB (FFA) CPO</td>
                <td>{{ number_format($data['lab']->avg_ffa ?? 0, 2) }}</td>
                <td>%</td>
                <td style="color: {{ ($data['lab']->avg_ffa ?? 0) > 5 ? 'red' : 'green' }}">
                    {{ ($data['lab']->avg_ffa ?? 0) > 5 ? '⚠ Tinggi' : '✓ Normal' }}
                </td>
            </tr>
            <tr>
                <td>Losses Fiber</td>
                <td>{{ number_format($data['lab']->avg_losses_fiber ?? 0, 2) }}</td>
                <td>%</td>
                <td style="color: {{ ($data['lab']->avg_losses_fiber ?? 0) > 5 ? 'red' : 'green' }}">
                    {{ ($data['lab']->avg_losses_fiber ?? 0) > 5 ? '⚠ Tinggi' : '✓ Normal' }}
                </td>
            </tr>
            <tr>
                <td>Losses Jankos</td>
                <td>{{ number_format($data['lab']->avg_losses_jankos ?? 0, 2) }}</td>
                <td>%</td>
                <td style="color: {{ ($data['lab']->avg_losses_jankos ?? 0) > 1 ? 'red' : 'green' }}">
                    {{ ($data['lab']->avg_losses_jankos ?? 0) > 1 ? '⚠ Tinggi' : '✓ Normal' }}
                </td>
            </tr>
        </table>
    </div>

    <div class="signatures">
        <div class="signature-box">
            <div>Dibuat Oleh,</div>
            <div class="signature-line">
                <strong>{{ $preparedBy }}</strong><br>
                Operator Shift
            </div>
        </div>
        <div class="signature-box">
            <div>Diperiksa Oleh,</div>
            <div class="signature-line">
                (........................)<br>
                Asisten Proses
            </div>
        </div>
        <div class="signature-box">
            <div>Disetujui Oleh,</div>
            <div class="signature-line">
                (........................)<br>
                Manager Pabrik
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Dokumen ini dicetak secara otomatis dari sistem POMS Report - {{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>
