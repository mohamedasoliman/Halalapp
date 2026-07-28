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
    'intent_cache_ttl' => (int) env('GEMINI_INTENT_CACHE_TTL', 604800),
    'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 120),
    'thinking_level' => env('GEMINI_THINKING_LEVEL', 'minimal'),
];
