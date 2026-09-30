<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$cases = [
    [null, null],
    ['', null],
    ['   ', null],
    ['http://cdn.example.com/a.jpg', 'http://cdn.example.com/a.jpg'],
    ['https://cdn.example.com/a.jpg', 'https://cdn.example.com/a.jpg'],
    ['/assets/news/sample_news_1.jpg', '/assets/news/sample_news_1.jpg'],
    ['/storage/uploads/news/a.jpg', '/storage/uploads/news/a.jpg'],
    ['assets/news/sample_news_2.jpg', '/assets/news/sample_news_2.jpg'],
    ['storage/uploads/news/a.jpg', '/storage/uploads/news/a.jpg'],
    ['abc.jpg', '/storage/uploads/abc.jpg'],
    ['news\\abc.jpg', '/storage/uploads/news/abc.jpg'],
];

$failed = 0;
foreach ($cases as [$input, $expected]) {
    $actual = resolve_media_url($input);
    if ($actual !== $expected) {
        $failed++;
        echo 'FAIL: ' . var_export($input, true) . ' => ' . var_export($actual, true) . '; expected ' . var_export($expected, true) . PHP_EOL;
    }
}

foreach (['/storage/uploads/assets/x.jpg', '/storage/uploads//assets/x.jpg', '/storage/uploads/storage/uploads/x.jpg'] as $invalid) {
    foreach ($cases as [$input]) {
        if (resolve_media_url($input) === $invalid) {
            $failed++;
            echo "FAIL: generated forbidden URL {$invalid}" . PHP_EOL;
        }
    }
}

$storageAssetUrl = storage_url('/assets/news/sample_news_1.jpg');
if (!str_ends_with($storageAssetUrl, '/assets/news/sample_news_1.jpg') || str_contains($storageAssetUrl, '/storage/uploads/assets/')) {
    $failed++;
    echo "FAIL: storage_url duplicated an absolute asset path: {$storageAssetUrl}" . PHP_EOL;
}

if (media_url('news/a.jpg') !== '/storage/uploads/news/a.jpg') {
    $failed++;
    echo "FAIL: media_url alias did not resolve an upload-relative path." . PHP_EOL;
}

$uploadPath = str_replace('\\', '/', storage_upload_path('storage/uploads/news/a.jpg'));
if (!str_ends_with($uploadPath, '/storage/uploads/news/a.jpg')) {
    $failed++;
    echo "FAIL: storage_upload_path did not resolve the canonical path: {$uploadPath}" . PHP_EOL;
}

foreach (['../secret.php', 'news/../../secret.php', 'C:\\Windows\\file.txt', 'https://example.com/a.jpg'] as $unsafe) {
    try {
        storage_upload_path($unsafe);
        $failed++;
        echo "FAIL: unsafe filesystem path accepted: {$unsafe}" . PHP_EOL;
    } catch (InvalidArgumentException $e) {
        // Expected.
    }
}

if ($failed > 0) exit(1);
echo 'Media URL tests passed (' . count($cases) . ' cases).' . PHP_EOL;
