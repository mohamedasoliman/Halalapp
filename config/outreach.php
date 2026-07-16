<?php

return [
    'enabled' => (bool) env('OUTREACH_ENABLED', false),
    'queue_connection' => env('OUTREACH_QUEUE_CONNECTION', 'database'),
    'queue' => env('OUTREACH_QUEUE', 'outreach'),
    'daily_limit' => (int) env('OUTREACH_DAILY_LIMIT', 20),
    'spacing_minutes' => (int) env('OUTREACH_SPACING_MINUTES', 3),
    'products_per_email' => (int) env('OUTREACH_PRODUCTS_PER_EMAIL', 10),
    'timezone' => env('OUTREACH_TIMEZONE', 'Pacific/Auckland'),
    'follow_up_days' => [7, 14],
    'from_address' => env('OUTREACH_FROM_ADDRESS', 'products@halalkiwi.com'),
    'from_name' => env('OUTREACH_FROM_NAME', 'Halal Kiwi Products'),
    'reply_to' => env('OUTREACH_REPLY_TO', 'products@halalkiwi.com'),
];
