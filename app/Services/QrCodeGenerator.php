<?php

declare(strict_types=1);

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use InvalidArgumentException;

/** Local QR encoding: OTP payloads are never sent to an external QR service. */
class QrCodeGenerator
{
    public const ECC_L = 1;
    public const ECC_M = 0;
    public const ECC_Q = 3;
    public const ECC_H = 2;

    /** $size is a minimum: dimensions snap up to whole modules for reliable scanning. */
    public static function svg(string $text, int $size = 220, int $margin = 4, int $eccLevel = self::ECC_M): string
    {
        if ($text === '' || $size < 1 || $margin < 4) {
            throw new InvalidArgumentException('QR content, size and a quiet zone of at least four modules are required.');
        }
        $level = match ($eccLevel) {
            self::ECC_L => ErrorCorrectionLevel::L(),
            self::ECC_M => ErrorCorrectionLevel::M(),
            self::ECC_Q => ErrorCorrectionLevel::Q(),
            self::ECC_H => ErrorCorrectionLevel::H(),
            default => throw new InvalidArgumentException('Unsupported QR error correction level.'),
        };
        // The maintained encoder handles RS block interleaving, capacity checks,
        // version information (including versions >= 7), and UTF-8 encoding.
        $matrix = Encoder::encode($text, $level, 'UTF-8')->getMatrix();
        $totalSize = $matrix->getWidth() + 2 * $margin;
        // Integral module pixels avoid resampling artifacts; retain at least four pixels/module.
        $renderSize = $totalSize * max(4, (int) ceil($size / $totalSize));
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" shape-rendering="crispEdges">',
            $totalSize, $totalSize, $renderSize, $renderSize
        );
        $svg .= '<rect width="100%" height="100%" fill="#FFFFFF"/><path fill="#0f172a" d="';
        for ($row = 0; $row < $matrix->getHeight(); $row++) {
            for ($column = 0; $column < $matrix->getWidth(); $column++) {
                if ($matrix->get($column, $row) === 1) {
                    $svg .= sprintf('M%d,%dh1v1h-1z ', $column + $margin, $row + $margin);
                }
            }
        }
        return $svg . '"/></svg>';
    }

    public static function dataUri(string $text, int $size = 220): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($text, $size));
    }
}
