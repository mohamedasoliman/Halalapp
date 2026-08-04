<?php

return [
    'base_url' => rtrim(
        (string) env('AWQAT_BASE_URL', 'http://www.awqatesalah.com'),
        '/'
    ),
    'read_path' => env(
        'AWQAT_READ_PATH',
        '/API_AwqatESalah__V2_2/GetMasjidsByAreaID'
    ),
    'update_path' => env(
        'AWQAT_UPDATE_PATH',
        '/API_AwqatESalah__V2_2/UpdateMasjidTimings'
    ),
    'connect_timeout' => (int) env('AWQAT_CONNECT_TIMEOUT', 4),
    'timeout' => (int) env('AWQAT_TIMEOUT', 10),
    'cache_store' => env('AWQAT_CACHE_STORE', 'file'),
    'read_cache_ttl' => (int) env('AWQAT_READ_CACHE_TTL', 300),
    'read_stale_ttl' => (int) env('AWQAT_READ_STALE_TTL', 86400),
    'correction_cache_max_age' => (int) env(
        'AWQAT_CORRECTION_CACHE_MAX_AGE',
        120
    ),
];
