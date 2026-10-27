<?php

declare(strict_types=1);

return [
    'client_id' => env('GOOGLE_OAUTH_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET', ''),
    'redirect_uri' => env('GOOGLE_OAUTH_REDIRECT_URI', ''),
    'enabled' => filter_var(env('GOOGLE_AUTH_ENABLED', false), FILTER_VALIDATE_BOOL),
    'staff_google_login_required' => filter_var(env('STAFF_GOOGLE_LOGIN_REQUIRED', true), FILTER_VALIDATE_BOOL),
    'staff_mfa_required' => filter_var(env('STAFF_MFA_REQUIRED', true), FILTER_VALIDATE_BOOL),
    'primary_superadmin_email' => env('PRIMARY_SUPERADMIN_EMAIL', ''),
    'endpoints' => [
        'issuer' => 'https://accounts.google.com',
        'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'jwks_url' => 'https://www.googleapis.com/oauth2/v3/certs',
        'tokeninfo_url' => 'https://oauth2.googleapis.com/tokeninfo',
    ],
    'scopes' => ['openid', 'email', 'profile'],
];
