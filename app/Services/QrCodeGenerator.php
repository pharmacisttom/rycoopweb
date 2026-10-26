<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Pure PHP QR Code Generator (SVG Output)
 * Generates standards-compliant QR Codes locally without any external dependencies or remote APIs.
 * Zero data leakage: OTP secrets and member URLs never leave the server.
 */
class QrCodeGenerator
{
    // Mode indicators
    private const MODE_BYTE = 0b0100;

    // Error Correction Levels: L (7%), M (15%), Q (25%), H (30%)
    public const ECC_L = 1;
    public const ECC_M = 0;
    public const ECC_Q = 3;
    public const ECC_H = 2;

    /**
     * Generate an inline SVG string for the given text.
     */
    public static function svg(string $text, int $size = 220, int $margin = 4, int $eccLevel = self::ECC_M): string
    {
        $matrix = self::generateMatrix($text, $eccLevel);
        $moduleCount = count($matrix);
        $totalSize = $moduleCount + ($margin * 2);

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" shape-rendering="crispEdges">',
            $totalSize,
            $totalSize,
            $size,
            $size
        );
        $svg .= sprintf('<rect width="100%%" height="100%%" fill="#FFFFFF"/>');
        $svg .= '<path fill="#0f172a" d="';

        for ($r = 0; $r < $moduleCount; $r++) {
            for ($c = 0; $c < $moduleCount; $c++) {
                if ($matrix[$r][$c]) {
                    $x = $c + $margin;
                    $y = $r + $margin;
                    $svg .= sprintf('M%d,%dh1v1h-1z ', $x, $y);
                }
            }
        }

        $svg .= '"/>';
        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Generate Data URI (SVG) for use directly in <img src="...">
     */
    public static function dataUri(string $text, int $size = 220): string
    {
        $svg = self::svg($text, $size);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    // -------------------------------------------------------------------------
    // QR Code Encoding Implementation
    // -------------------------------------------------------------------------

    private static function generateMatrix(string $data, int $eccLevel): array
    {
        $dataLen = strlen($data);
        $version = self::selectVersion($dataLen, $eccLevel);
        $size = 17 + (4 * $version);

        // 1. Initialize empty matrix and function pattern map
        $matrix = array_fill(0, $size, array_fill(0, $size, null));
        $isFunction = array_fill(0, $size, array_fill(0, $size, false));

        // 2. Finder patterns (top-left, top-right, bottom-left)
        self::addFinder($matrix, $isFunction, 0, 0);
        self::addFinder($matrix, $isFunction, $size - 7, 0);
        self::addFinder($matrix, $isFunction, 0, $size - 7);

        // 3. Separators around finders
        self::addSeparators($matrix, $isFunction, $size);

        // 4. Alignment patterns for Version 2+
        self::addAlignmentPatterns($matrix, $isFunction, $version);

        // 5. Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $val = ($i % 2 === 0);
            if ($matrix[6][$i] === null) {
                $matrix[6][$i] = $val;
                $isFunction[6][$i] = true;
            }
            if ($matrix[$i][6] === null) {
                $matrix[$i][6] = $val;
                $isFunction[$i][6] = true;
            }
        }

        // 6. Dark module
        $matrix[(4 * $version) + 9][8] = true;
        $isFunction[(4 * $version) + 9][8] = true;

        // 7. Reserve format information areas
        for ($i = 0; $i < 9; $i++) {
            if ($i !== 6) {
                $isFunction[8][$i] = true;
                $isFunction[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $isFunction[8][$size - 1 - $i] = true;
            $isFunction[$size - 1 - $i][8] = true;
        }

        // 8. Generate encoded bit stream
        $codewords = self::createCodewords($data, $version, $eccLevel);

        // 9. Place data bits and apply best mask
        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestMatrix = null;

        for ($mask = 0; $mask < 8; $mask++) {
            $testMatrix = $matrix;
            self::placeDataBits($testMatrix, $isFunction, $codewords, $mask);
            self::placeFormatInfo($testMatrix, $eccLevel, $mask);
            $penalty = self::calculatePenalty($testMatrix);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
                $bestMatrix = $testMatrix;
            }
        }

        return $bestMatrix;
    }

    private static function selectVersion(int $len, int $ecc): int
    {
        // Capacities for Byte mode (Versions 1-10) with ECC M
        // V1: 14, V2: 26, V3: 42, V4: 62, V5: 84, V6: 106, V7: 122, V8: 152, V9: 180, V10: 213
        $capacities = [
            1 => [1 => 19, 0 => 16, 3 => 13, 2 => 9],
            2 => [1 => 34, 0 => 28, 3 => 22, 2 => 16],
            3 => [1 => 55, 0 => 44, 3 => 34, 2 => 26],
            4 => [1 => 80, 0 => 64, 3 => 48, 2 => 36],
            5 => [1 => 108, 0 => 86, 3 => 62, 2 => 46],
            6 => [1 => 136, 0 => 108, 3 => 76, 2 => 60],
            7 => [1 => 156, 0 => 124, 3 => 88, 2 => 66],
            8 => [1 => 194, 0 => 154, 3 => 110, 2 => 86],
            9 => [1 => 232, 0 => 182, 3 => 132, 2 => 100],
            10 => [1 => 274, 0 => 216, 3 => 154, 2 => 122],
        ];

        foreach ($capacities as $v => $caps) {
            if ($len <= $caps[$ecc]) {
                return $v;
            }
        }

        return 10;
    }

    private static function addFinder(array &$matrix, array &$isFunc, int $r0, int $c0): void
    {
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                $isBlack = ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
                $matrix[$r0 + $r][$c0 + $c] = $isBlack;
                $isFunc[$r0 + $r][$c0 + $c] = true;
            }
        }
    }

    private static function addSeparators(array &$matrix, array &$isFunc, int $size): void
    {
        for ($i = 0; $i < 8; $i++) {
            // Top-left
            if ($i < 7) {
                $matrix[7][$i] = false;
                $isFunc[7][$i] = true;
                $matrix[$i][7] = false;
                $isFunc[$i][7] = true;
            }
            $matrix[7][7] = false;
            $isFunc[7][7] = true;

            // Top-right
            $matrix[7][$size - 1 - $i] = false;
            $isFunc[7][$size - 1 - $i] = true;
            if ($i < 7) {
                $matrix[$i][$size - 8] = false;
                $isFunc[$i][$size - 8] = true;
            }

            // Bottom-left
            $matrix[$size - 8][$i] = false;
            $isFunc[$size - 8][$i] = true;
            if ($i < 7) {
                $matrix[$size - 1 - $i][7] = false;
                $isFunc[$size - 1 - $i][7] = true;
            }
        }
    }

    private static function addAlignmentPatterns(array &$matrix, array &$isFunc, int $version): void
    {
        if ($version < 2) {
            return;
        }

        $alignPositions = [
            2 => [6, 18],
            3 => [6, 22],
            4 => [6, 26],
            5 => [6, 30],
            6 => [6, 34],
            7 => [6, 22, 38],
            8 => [6, 24, 42],
            9 => [6, 26, 46],
            10 => [6, 28, 50],
        ];

        $pos = $alignPositions[$version] ?? [];
        foreach ($pos as $r) {
            foreach ($pos as $c) {
                // Skip if overlapping finder patterns
                if ($isFunc[$r][$c]) {
                    continue;
                }

                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $isBlack = (abs($dr) === 2 || abs($dc) === 2 || ($dr === 0 && $dc === 0));
                        $matrix[$r + $dr][$c + $dc] = $isBlack;
                        $isFunc[$r + $dr][$c + $dc] = true;
                    }
                }
            }
        }
    }

    private static function createCodewords(string $data, int $version, int $ecc): array
    {
        $dataLen = strlen($data);
        $bits = '';

        // Mode indicator (Byte mode: 0100)
        $bits .= '0100';

        // Character count indicator (8 bits for V1-V9 in byte mode, 16 for V10+)
        $ccBits = $version >= 10 ? 16 : 8;
        $bits .= str_pad(decbin($dataLen), $ccBits, '0', STR_PAD_LEFT);

        // Data payload
        for ($i = 0; $i < $dataLen; $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Total data capacity in bits
        $totalCodewords = self::getTotalCodewords($version);
        $eccCodewords = self::getEccCodewords($version, $ecc);
        $dataCodewordsCount = $totalCodewords - $eccCodewords;
        $maxBits = $dataCodewordsCount * 8;

        // Terminator
        $bits .= '0000';
        if (strlen($bits) > $maxBits) {
            $bits = substr($bits, 0, $maxBits);
        }

        // Bit padding to multiple of 8
        $padLength = (8 - (strlen($bits) % 8)) % 8;
        $bits .= str_repeat('0', $padLength);

        // Pad bytes (0xEC, 0x11)
        $dataBytes = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $dataBytes[] = bindec(substr($bits, $i, 8));
        }

        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($dataBytes) < $dataCodewordsCount) {
            $dataBytes[] = $padBytes[$padIdx % 2];
            $padIdx++;
        }

        // Error correction codewords using Reed-Solomon
        $ecBytes = self::calculateReedSolomon($dataBytes, $eccCodewords);

        return array_merge($dataBytes, $ecBytes);
    }

    private static function getTotalCodewords(int $version): int
    {
        $totals = [
            1 => 26, 2 => 44, 3 => 70, 4 => 100, 5 => 134,
            6 => 172, 7 => 196, 8 => 242, 9 => 292, 10 => 346,
        ];
        return $totals[$version] ?? 26;
    }

    private static function getEccCodewords(int $version, int $ecc): int
    {
        // ECC Codewords by Version and Level [L=1, M=0, Q=3, H=2]
        $eccTable = [
            1 => [1 => 7, 0 => 10, 3 => 13, 2 => 17],
            2 => [1 => 10, 0 => 16, 3 => 22, 2 => 28],
            3 => [1 => 15, 0 => 26, 3 => 36, 2 => 44],
            4 => [1 => 20, 0 => 36, 3 => 52, 2 => 64],
            5 => [1 => 26, 0 => 48, 3 => 72, 2 => 88],
            6 => [1 => 36, 0 => 64, 3 => 96, 2 => 112],
            7 => [1 => 40, 0 => 72, 3 => 108, 2 => 130],
            8 => [1 => 48, 0 => 88, 3 => 132, 2 => 156],
            9 => [1 => 60, 0 => 110, 3 => 160, 2 => 192],
            10 => [1 => 72, 0 => 130, 3 => 192, 2 => 224],
        ];

        return $eccTable[$version][$ecc] ?? 10;
    }

    private static function calculateReedSolomon(array $data, int $eccCount): array
    {
        // Galois Field GF(256) tables
        static $gfExp = null;
        static $gfLog = null;

        if ($gfExp === null) {
            $gfExp = array_fill(0, 512, 0);
            $gfLog = array_fill(0, 256, 0);
            $val = 1;
            for ($i = 0; $i < 255; $i++) {
                $gfExp[$i] = $val;
                $gfExp[$i + 255] = $val;
                $gfLog[$val] = $i;
                $val <<= 1;
                if ($val & 0x100) {
                    $val ^= 0x11D;
                }
            }
        }

        // Generator polynomial for given eccCount
        $generator = [1];
        for ($i = 0; $i < $eccCount; $i++) {
            $term = [1, $gfExp[$i]];
            $next = array_fill(0, count($generator) + 1, 0);
            for ($j = 0; $j < count($generator); $j++) {
                for ($k = 0; $k < count($term); $k++) {
                    if ($generator[$j] !== 0 && $term[$k] !== 0) {
                        $p = ($gfLog[$generator[$j]] + $gfLog[$term[$k]]) % 255;
                        $next[$j + $k] ^= $gfExp[$p];
                    }
                }
            }
            $generator = $next;
        }

        // Polynomial division: data * x^eccCount / generator
        $result = array_merge($data, array_fill(0, $eccCount, 0));
        for ($i = 0; $i < count($data); $i++) {
            $coef = $result[$i];
            if ($coef !== 0) {
                $logCoef = $gfLog[$coef];
                for ($j = 0; $j < count($generator); $j++) {
                    if ($generator[$j] !== 0) {
                        $p = ($gfLog[$generator[$j]] + $logCoef) % 255;
                        $result[$i + $j] ^= $gfExp[$p];
                    }
                }
            }
        }

        return array_slice($result, count($data));
    }

    private static function placeDataBits(array &$matrix, array $isFunc, array $codewords, int $mask): void
    {
        $size = count($matrix);
        $bitStream = '';
        foreach ($codewords as $byte) {
            $bitStream .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $bitIdx = 0;
        $numBits = strlen($bitStream);
        $up = true;

        for ($right = $size - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right--; // Skip vertical timing pattern
            }

            for ($vert = 0; $vert < $size; $vert++) {
                $r = $up ? ($size - 1 - $vert) : $vert;

                for ($c = $right; $c >= $right - 1; $c--) {
                    if ($isFunc[$r][$c]) {
                        continue;
                    }

                    $bit = ($bitIdx < $numBits) ? ($bitStream[$bitIdx] === '1') : false;
                    $bitIdx++;

                    // Apply mask condition
                    $maskBit = self::isMasked($mask, $r, $c);
                    $matrix[$r][$c] = $bit ^ $maskBit;
                }
            }
            $up = !$up;
        }
    }

    private static function isMasked(int $mask, int $r, int $c): bool
    {
        return match ($mask) {
            0 => ($r + $c) % 2 === 0,
            1 => $r % 2 === 0,
            2 => $c % 3 === 0,
            3 => ($r + $c) % 3 === 0,
            4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
            5 => (($r * $c) % 2) + (($r * $c) % 3) === 0,
            6 => ((($r * $c) % 2) + (($r * $c) % 3)) % 2 === 0,
            7 => ((($r + $c) % 2) + (($r * $c) % 3)) % 2 === 0,
            default => false,
        };
    }

    private static function placeFormatInfo(array &$matrix, int $ecc, int $mask): void
    {
        $size = count($matrix);
        $formatBits = self::getFormatBits($ecc, $mask);

        // Top-left finder area
        $pairs1 = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8]
        ];

        // Bottom-left and Top-right
        $pairs2 = [
            [$size - 1, 8], [$size - 2, 8], [$size - 3, 8], [$size - 4, 8],
            [$size - 5, 8], [$size - 6, 8], [$size - 7, 8],
            [8, $size - 8], [8, $size - 7], [8, $size - 6], [8, $size - 5],
            [8, $size - 4], [8, $size - 3], [8, $size - 2], [8, $size - 1]
        ];

        for ($i = 0; $i < 15; $i++) {
            $bit = (($formatBits >> (14 - $i)) & 1) === 1;
            [$r1, $c1] = $pairs1[$i];
            $matrix[$r1][$c1] = $bit;

            [$r2, $c2] = $pairs2[$i];
            $matrix[$r2][$c2] = $bit;
        }
    }

    private static function getFormatBits(int $ecc, int $mask): int
    {
        $eccBits = match ($ecc) {
            self::ECC_L => 0b01,
            self::ECC_M => 0b00,
            self::ECC_Q => 0b11,
            self::ECC_H => 0b10,
            default => 0b00,
        };

        $data = ($eccBits << 3) | ($mask & 0x7);
        $bch = $data << 10;
        $poly = 0b10100110111;

        for ($i = 4; $i >= 0; $i--) {
            if ($bch & (1 << ($i + 10))) {
                $bch ^= ($poly << $i);
            }
        }

        $format = (($data << 10) | $bch) ^ 0b101010000010010;
        return $format;
    }

    private static function calculatePenalty(array $matrix): int
    {
        $size = count($matrix);
        $penalty = 0;

        // Penalty 1: 5 or more consecutive modules of the same color
        for ($r = 0; $r < $size; $r++) {
            $run = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($matrix[$r][$c] === $matrix[$r][$c - 1]) {
                    $run++;
                } else {
                    if ($run >= 5) {
                        $penalty += 3 + ($run - 5);
                    }
                    $run = 1;
                }
            }
            if ($run >= 5) {
                $penalty += 3 + ($run - 5);
            }
        }

        for ($c = 0; $c < $size; $c++) {
            $run = 1;
            for ($r = 1; $r < $size; $r++) {
                if ($matrix[$r][$c] === $matrix[$r - 1][$c]) {
                    $run++;
                } else {
                    if ($run >= 5) {
                        $penalty += 3 + ($run - 5);
                    }
                    $run = 1;
                }
            }
            if ($run >= 5) {
                $penalty += 3 + ($run - 5);
            }
        }

        // Penalty 2: 2x2 blocks of same color
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $val = $matrix[$r][$c];
                if ($matrix[$r + 1][$c] === $val && $matrix[$r][$c + 1] === $val && $matrix[$r + 1][$c + 1] === $val) {
                    $penalty += 3;
                }
            }
        }

        return $penalty;
    }
}
