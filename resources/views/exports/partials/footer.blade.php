{{-- Footer dokumen dengan nomor halaman (inline PHP dompdf).
     WAJIB: controller memanggil ->setOption(['isPhpEnabled' => true]).
     DomPDF 3.x menyuntikkan $pdf, $fontMetrics, $PAGE_NUM, $PAGE_COUNT. --}}
@if(isset($showPageFooter) ? $showPageFooter : true)
<script type="text/php">
if (isset($pdf) && isset($fontMetrics) && isset($PAGE_NUM)) {
    $font = $fontMetrics->get_font("helvetica", "normal");
    $size = 8;
    $y = $pdf->get_height() - 28;
    $color = [0.45, 0.50, 0.42];

    $pdf->text(24, $y, "Dicetak otomatis dari POMS Report - " . date("d/m/Y H:i") . " WIB", $font, $size, $color);

    $label = "Halaman " . $PAGE_NUM . " dari " . $PAGE_COUNT;
    $w = $fontMetrics->get_text_width($label, $font, $size);
    $pdf->text($pdf->get_width() - 24 - $w, $y, $label, $font, $size, $color);
}
</script>
@endif
