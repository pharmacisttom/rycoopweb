<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;

$imgDir = dirname(__DIR__) . '/public/assets/img';
if (!is_dir($imgDir)) {
    @mkdir($imgDir, 0775, true);
}

// 1. Generate news_placeholder.jpg (600x400)
function createNewsPlaceholder(string $path): void {
    $width = 600;
    $height = 400;
    $im = imagecreatetruecolor($width, $height);

    // Gradient background (Deep Navy to Royal Blue)
    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        $r = (int)(7 + (11 - 7) * $ratio);
        $g = (int)(59 + (94 - 59) * $ratio);
        $b = (int)(116 + (215 - 116) * $ratio);
        $color = imagecolorallocate($im, $r, $g, $b);
        imageline($im, 0, $y, $width, $y, $color);
    }

    // Decorative circle
    $accentColor = imagecolorallocatealpha($im, 255, 255, 255, 115);
    imagefilledellipse($im, (int)($width * 0.8), (int)($height * 0.2), 220, 220, $accentColor);
    imagefilledellipse($im, (int)($width * 0.15), (int)($height * 0.85), 180, 180, $accentColor);

    // Central Icon Box
    $boxColor = imagecolorallocatealpha($im, 255, 255, 255, 100);
    imagefilledrectangle($im, (int)($width / 2 - 80), (int)($height / 2 - 60), (int)($width / 2 + 80), (int)($height / 2 + 20), $boxColor);

    $textColor = imagecolorallocate($im, 255, 255, 255);
    $goldColor = imagecolorallocate($im, 255, 215, 0);

    // Render text with built-in font
    $text1 = "RAYONGCOOP NEWS & PR";
    $text2 = "Rayong Public Health Savings Cooperative";
    imagestring($im, 5, (int)($width / 2 - strlen($text1) * 4.5), (int)($height / 2 - 30), $text1, $goldColor);
    imagestring($im, 3, (int)($width / 2 - strlen($text2) * 3.5), (int)($height / 2 + 45), $text2, $textColor);

    imagejpeg($im, $path, 92);
    imagedestroy($im);
    echo "✓ Generated: {$path}\n";
}

// 2. Generate hero_bg_default.jpg (1600x800)
function createHeroBg(string $path): void {
    $width = 1600;
    $height = 800;
    $im = imagecreatetruecolor($width, $height);

    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        $r = (int)(5 + (11 - 5) * $ratio);
        $g = (int)(38 + (76 - 38) * $ratio);
        $b = (int)(76 + (140 - 76) * $ratio);
        $color = imagecolorallocate($im, $r, $g, $b);
        imageline($im, 0, $y, $width, $y, $color);
    }

    $glowColor = imagecolorallocatealpha($im, 33, 118, 210, 100);
    imagefilledellipse($im, (int)($width * 0.75), (int)($height * 0.3), 600, 600, $glowColor);

    $goldGlow = imagecolorallocatealpha($im, 201, 154, 46, 115);
    imagefilledellipse($im, (int)($width * 0.2), (int)($height * 0.8), 400, 400, $goldGlow);

    imagejpeg($im, $path, 90);
    imagedestroy($im);
    echo "✓ Generated: {$path}\n";
}

// 3. Generate board_placeholder.jpg (400x500)
function createBoardPlaceholder(string $path): void {
    $width = 400;
    $height = 500;
    $im = imagecreatetruecolor($width, $height);

    $bg = imagecolorallocate($im, 234, 244, 255);
    imagefilledrectangle($im, 0, 0, $width, $height, $bg);

    $headColor = imagecolorallocate($im, 7, 59, 116);
    imagefilledellipse($im, (int)($width / 2), (int)($height * 0.38), 120, 130, $headColor);
    imagefilledarc($im, (int)($width / 2), (int)($height * 0.85), 260, 240, 180, 360, $headColor, IMG_ARC_PIE);

    imagejpeg($im, $path, 90);
    imagedestroy($im);
    echo "✓ Generated: {$path}\n";
}

// 4. Generate popup_dividend.jpg (800x400)
function createPopupDividend(string $path): void {
    $width = 800;
    $height = 400;
    $im = imagecreatetruecolor($width, $height);

    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        $r = (int)(7 + (11 - 7) * $ratio);
        $g = (int)(59 + (94 - 59) * $ratio);
        $b = (int)(116 + (215 - 116) * $ratio);
        $color = imagecolorallocate($im, $r, $g, $b);
        imageline($im, 0, $y, $width, $y, $color);
    }

    $goldColor = imagecolorallocate($im, 255, 215, 0);
    $textColor = imagecolorallocate($im, 255, 255, 255);

    imagefilledellipse($im, (int)($width / 2), 100, 90, 90, $goldColor);
    $navy = imagecolorallocate($im, 7, 59, 116);
    imagestring($im, 5, (int)($width / 2 - 5), 90, "$", $navy);

    $t1 = "ANNUAL DIVIDEND & PATRONAGE REFUND";
    $t2 = "Rayong Public Health Savings Cooperative";
    imagestring($im, 5, (int)($width / 2 - strlen($t1) * 4.5), 200, $t1, $textColor);
    imagestring($im, 4, (int)($width / 2 - strlen($t2) * 4), 240, $t2, $goldColor);

    imagejpeg($im, $path, 92);
    imagedestroy($im);
    echo "✓ Generated: {$path}\n";
}

createNewsPlaceholder("{$imgDir}/news_placeholder.jpg");
createHeroBg("{$imgDir}/hero_bg_default.jpg");
createBoardPlaceholder("{$imgDir}/board_placeholder.jpg");
createPopupDividend("{$imgDir}/popup_dividend.jpg");

// Seeded sample images are immutable public assets, not runtime uploads.
$newsUploadDir = dirname(__DIR__) . '/public/assets/news';
if (!is_dir($newsUploadDir)) {
    @mkdir($newsUploadDir, 0775, true);
}
createNewsPlaceholder("{$newsUploadDir}/sample_news_1.jpg");
createNewsPlaceholder("{$newsUploadDir}/sample_news_2.jpg");
createNewsPlaceholder("{$newsUploadDir}/sample_news_3.jpg");

echo "All placeholder images created successfully.\n";
