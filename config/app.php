<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'RayongCoop Digital Portal'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => rtrim(env('APP_URL', ''), '/'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Bangkok'),
    'locale' => 'th',
    'key' => env('APP_KEY', ''),
    'version' => '1.0.0',
    'coop' => [
        'full_name_th' => 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด',
        'full_name_en' => 'Rayong Public Health Savings and Credit Cooperative Limited',
        'short_name' => 'สอ.สธ.ระยอง',
        'phone' => '038-611178, 038-617478',
        'fax' => '038-611179',
        'email' => 'contact@rayongcoop.com',
        'address' => 'เลขที่ 444 หมู่ 2 ถนนสุขุมวิท ตำบลเนินพระ อำเภอเมืองระยอง จังหวัดระยอง 21150',
        'office_hours' => 'จันทร์ - ศุกร์ 08.30 - 16.30 น. (เว้นวันหยุดราชการและวันหยุดนักขัตฤกษ์)',
        'legacy_url' => 'https://rayongcoop.com/',
    ]
];
