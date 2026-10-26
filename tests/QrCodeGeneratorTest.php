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

echo "QrCodeGenerator tests passed.\n";
