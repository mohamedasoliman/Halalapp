<?php

$allowedAppIds = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env(
        'APP_CHECK_ALLOWED_APP_IDS',
        '1:952667093663:android:8623ef0d014e99e46140aa,1:952667093663:ios:6268a0b1a840c84b6140aa'
    ))
)));

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase App Check rollout
    |--------------------------------------------------------------------------
    |
    | off:     Do not inspect App Check tokens.
    | monitor: Verify tokens when present and record the result, but never
    |          reject an existing client that has not yet been upgraded.
    | enforce: Reject missing or invalid tokens.
    |
    | Keep production in monitor mode until the App Check-enabled mobile
    | release has reached users. Enable the existing minimum-version policy
    | before switching this setting to enforce.
    |
    */
    'mode' => env('APP_CHECK_MODE', 'off'),
    'project_number' => env('APP_CHECK_PROJECT_NUMBER', '952667093663'),
    'allowed_app_ids' => $allowedAppIds,
    'jwks_url' => env(
        'APP_CHECK_JWKS_URL',
        'https://firebaseappcheck.googleapis.com/v1/jwks'
    ),
    'jwks_cache_ttl' => (int) env('APP_CHECK_JWKS_CACHE_TTL', 21600),
    'jwks_stale_ttl' => (int) env('APP_CHECK_JWKS_STALE_TTL', 604800),
    'connect_timeout' => (int) env('APP_CHECK_CONNECT_TIMEOUT', 3),
    'timeout' => (int) env('APP_CHECK_TIMEOUT', 6),
];
