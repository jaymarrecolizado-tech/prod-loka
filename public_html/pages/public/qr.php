<?php
/**
 * LOKA - Public QR Code Generator API
 */

require_once BASE_PATH . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';

$data = $_GET['data'] ?? '';

if (empty($data)) {
    http_response_code(400);
    die('Invalid request');
}

ob_end_clean(); // Ensure no prior output breaks the image header

$barcodeobj = new TCPDF2DBarcode($data, 'QRCODE,H');

// Output PNG natively
$barcodeobj->getBarcodePNG(4, 4, array(0,0,0));
exit;
