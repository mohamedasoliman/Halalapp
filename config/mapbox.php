<?php

return [
    'directions_enabled' => env('MAPBOX_DIRECTIONS_ENABLED', false),
    'access_token' => env('MAPBOX_ACCESS_TOKEN'),
    'directions_endpoint' => env(
        'MAPBOX_DIRECTIONS_ENDPOINT',
        'https://api.mapbox.com/directions/v5/mapbox/driving'
    ),
    'directions_monthly_limit' => (int) env(
        'MAPBOX_DIRECTIONS_MONTHLY_LIMIT',
        90000
    ),
    'billing_cycle_day' => (int) env('MAPBOX_BILLING_CYCLE_DAY', 1),
    'route_cache_ttl' => (int) env('MAPBOX_ROUTE_CACHE_TTL', 900),
    'connect_timeout' => (int) env('MAPBOX_CONNECT_TIMEOUT', 4),
    'timeout' => (int) env('MAPBOX_TIMEOUT', 10),
];
