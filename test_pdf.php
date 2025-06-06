<?php
// Quick test to verify TCPDF is working
require_once 'vendor/autoload.php';

try {
    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'TCPDF Test - Working!', 0, 1, 'C');
    echo "TCPDF is properly installed and working!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
