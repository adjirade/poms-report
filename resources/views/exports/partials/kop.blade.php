{{-- Kop surat korporat — dipakai bersama semua view exports/*.blade.php.
     Variabel opsional: $docTitle, $docSubtitle --}}
@php
    $companyName = config('poms.company_name');
    $companyAddress = config('poms.company_address');
    $plantName = config('poms.plant_name');
@endphp
<table style="width: 100%; border-collapse: collapse; margin-bottom: 0;">
    <tr>
        <td style="width: 64px; vertical-align: middle; padding-right: 10px;">
            <div style="width: 60px; height: 60px; background-color: #14532d; color: #ffffff; text-align: center; vertical-align: middle; font-size: 13px; font-weight: bold; line-height: 60px; border-radius: 8px;">POMS</div>
        </td>
        <td style="vertical-align: middle;">
            <div style="font-size: 15pt; font-weight: bold; color: #14532d; line-height: 1.25;">{{ $companyName }}</div>
            <div style="font-size: 11pt; color: #1f2937; line-height: 1.3;">{{ $plantName }}</div>
            @if($companyAddress)
                <div style="font-size: 8pt; color: #4b5563; line-height: 1.3;">{{ $companyAddress }}</div>
            @endif
        </td>
        <td style="vertical-align: middle; text-align: right; font-size: 8pt; color: #4b5563;">
            <div>No. Dokumen</div>
            <div style="font-weight: bold; color: #111827;">{{ $docNumber ?? 'POMS/DOC/'.now()->format('Ymd/His') }}</div>
        </td>
    </tr>
</table>
<div style="border-bottom: 2.5px solid #14532d; margin-top: 6px;"></div>
<div style="border-bottom: 1px solid #14532d; margin-top: 2px; margin-bottom: 14px;"></div>

@if(isset($docTitle))
<div style="text-align: center; margin-bottom: 14px;">
    <div style="font-size: 13pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #111827;">{{ $docTitle }}</div>
    @if(isset($docSubtitle))
        <div style="font-size: 10pt; color: #374151; margin-top: 2px;">{{ $docSubtitle }}</div>
    @endif
</div>
@endif
