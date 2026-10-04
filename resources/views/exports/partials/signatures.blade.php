{{-- Blok tanda tangan 3 kolom — dipakai bersama semua dokumen PDF POMS.
     Variabel: $createdBy (nama), $createdRole, $checkedRole, $approvedRole --}}
<table style="width: 100%; border-collapse: collapse; margin-top: 34px; page-break-inside: avoid;">
    <tr>
        <td style="width: 33%; text-align: center; padding: 10px 6px; font-size: 9pt;">
            <div>Dibuat Oleh,</div>
            <div style="margin-top: 52px; border-top: 1px solid #111827; padding-top: 4px;">
                <strong>{{ $createdBy }}</strong><br>
                <span style="color: #4b5563;">{{ $createdRole ?? 'Operator Shift' }}</span>
            </div>
        </td>
        <td style="width: 33%; text-align: center; padding: 10px 6px; font-size: 9pt;">
            <div>Diperiksa Oleh,</div>
            <div style="margin-top: 52px; border-top: 1px solid #111827; padding-top: 4px;">
                (........................)<br>
                <span style="color: #4b5563;">{{ $checkedRole ?? 'Asisten' }}</span>
            </div>
        </td>
        <td style="width: 33%; text-align: center; padding: 10px 6px; font-size: 9pt;">
            <div>Disetujui Oleh,</div>
            <div style="margin-top: 52px; border-top: 1px solid #111827; padding-top: 4px;">
                (........................)<br>
                <span style="color: #4b5563;">{{ $approvedRole ?? 'Kepala Pabrik' }}</span>
            </div>
        </td>
    </tr>
</table>
