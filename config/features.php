<?php

declare(strict_types=1);

return [
    // Member functionality is retained in source but disabled by default.
    'member_portal' => filter_var(env('FEATURE_MEMBER_PORTAL', false), FILTER_VALIDATE_BOOL),
    'member_login' => filter_var(env('FEATURE_MEMBER_LOGIN', true), FILTER_VALIDATE_BOOL),
    'member_eservice' => filter_var(env('FEATURE_MEMBER_ESERVICE', false), FILTER_VALIDATE_BOOL),
    'member_financial_data' => filter_var(env('FEATURE_MEMBER_FINANCIAL_DATA', false), FILTER_VALIDATE_BOOL),
];
