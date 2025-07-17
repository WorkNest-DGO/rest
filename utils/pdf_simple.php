<?php
function pdf_simple_escape($text) {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function generar_pdf_simple($archivo, $titulo, array $lineas) {
    $y = 760;
    $contenido = "BT\n/F1 16 Tf\n50 $y Td\n(" . pdf_simple_escape($titulo) . ") Tj\nET\n";
    $y -= 30;
    foreach ($lineas as $l) {
        $l = pdf_simple_escape($l);
        $contenido .= "BT\n/F1 12 Tf\n50 $y Td\n($l) Tj\nET\n";
        $y -= 14;
    }

    $objs = [];
    $objs[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objs[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objs[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";
    $objs[] = "<< /Length " . strlen($contenido) . " >>\nstream\n" . $contenido . "endstream";
    $objs[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

    $pdf = "%PDF-1.4\n";
    $pos = [];
    foreach ($objs as $i => $o) {
        $pos[$i + 1] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n" . $o . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objs); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $pos[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";

    file_put_contents($archivo, $pdf);
}

