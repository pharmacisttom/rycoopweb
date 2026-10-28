<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\QrCodeGenerator;

$svg = QrCodeGenerator::svg('otpauth://totp/RayongCoop:admin@rayongcoop.com?secret=JBSWY3DPEHPK3PXP');

if (str_starts_with($svg, '<svg') && str_ends_with(trim($svg), '</svg>')) {
    echo "PASS: Valid SVG QR code generated locally (length: " . strlen($svg) . ")\n";
} else {
    echo "FAIL: Invalid SVG output\n";
    exit(1);
}

$dataUri = QrCodeGenerator::dataUri('otpauth://totp/RayongCoop:admin@rayongcoop.com?secret=JBSWY3DPEHPK3PXP');
if (str_starts_with($dataUri, 'data:image/svg+xml;base64,')) {
    echo "PASS: Valid Data URI SVG generated\n";
} else {
    echo "FAIL: Invalid Data URI\n";
    exit(1);
}


foreach ([['empty payload','',220,4,QrCodeGenerator::ECC_M],['missing quiet zone','fixture',220,0,QrCodeGenerator::ECC_M],['invalid ECC','fixture',220,4,99]] as [$label,$text,$size,$margin,$ecc]) {
    try { QrCodeGenerator::svg($text,$size,$margin,$ecc); }
    catch (InvalidArgumentException $e) { echo "PASS: {$label} rejected\n"; continue; }
    throw new RuntimeException("Expected rejection: {$label}");
}
$rejected = false;
try { QrCodeGenerator::svg(str_repeat('A',5000)); }
catch (\BaconQrCode\Exception\WriterException $e) { $rejected = true; }
if (!$rejected) throw new RuntimeException('Oversized QR payload was not rejected');
echo "PASS: oversized payload rejected instead of truncated\n";
echo "QrCodeGenerator tests passed (6 cases).\n";
