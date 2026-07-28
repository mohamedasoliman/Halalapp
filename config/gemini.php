<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
    'endpoint' => env(
        'GEMINI_ENDPOINT',
        'https://generativelanguage.googleapis.com/v1beta/interactions'
    ),
    'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 5),
    'timeout' => (int) env('GEMINI_TIMEOUT', 12),
];
