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
        'phone' => '038-967-409, 086-368-1132',
        'fax' => '038-967-409',
        'email' => 'coop2530@gmail.com',
        'address' => '142 หมู่ 2 ตำบลเนินพระ อำเภอเมือง จังหวัดระยอง 21000',
        'office_hours' => 'กรุณาสอบถามเวลาทำการกับสหกรณ์',
        'legacy_url' => 'https://rayongcoop.com/',
    ]
];
