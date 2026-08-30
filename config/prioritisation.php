<?php

return [
    'mailbox_address' => env('PRIORITISATION_MAILBOX_ADDRESS', 'products@halalkiwi.com'),

    'attachment_max_bytes' => (int) env('PRIORITISATION_ATTACHMENT_MAX_BYTES', 5 * 1024 * 1024),
    'attachment_max_count' => (int) env('PRIORITISATION_ATTACHMENT_MAX_COUNT', 12),
    'attachment_total_max_bytes' => (int) env(
        'PRIORITISATION_ATTACHMENT_TOTAL_MAX_BYTES',
        60 * 1024 * 1024,
    ),
    'attachment_max_dimension' => (int) env('PRIORITISATION_ATTACHMENT_MAX_DIMENSION', 4096),
    'attachment_max_pixels' => (int) env('PRIORITISATION_ATTACHMENT_MAX_PIXELS', 13_000_000),
    'attachment_jpeg_quality' => (int) env('PRIORITISATION_ATTACHMENT_JPEG_QUALITY', 88),
    'attachment_daily_per_email_count' => (int) env('PRIORITISATION_ATTACHMENT_DAILY_PER_EMAIL_COUNT', 12),
    'attachment_daily_per_email_bytes' => (int) env(
        'PRIORITISATION_ATTACHMENT_DAILY_PER_EMAIL_BYTES',
        60 * 1024 * 1024,
    ),
    'attachment_daily_global_count' => (int) env('PRIORITISATION_ATTACHMENT_DAILY_GLOBAL_COUNT', 500),
    'attachment_daily_global_bytes' => (int) env(
        'PRIORITISATION_ATTACHMENT_DAILY_GLOBAL_BYTES',
        1024 * 1024 * 1024,
    ),
    'attachment_min_free_bytes' => (int) env(
        'PRIORITISATION_ATTACHMENT_MIN_FREE_BYTES',
        1024 * 1024 * 1024,
    ),
    'attachment_intake_lock_seconds' => (int) env('PRIORITISATION_ATTACHMENT_INTAKE_LOCK_SECONDS', 600),
    'message_id_lock_seconds' => (int) env('PRIORITISATION_MESSAGE_ID_LOCK_SECONDS', 600),
    'mailbox_body_max_bytes' => (int) env('PRIORITISATION_MAILBOX_BODY_MAX_BYTES', 2 * 1024 * 1024),
    'mailbox_headers_max_bytes' => (int) env('PRIORITISATION_MAILBOX_HEADERS_MAX_BYTES', 64 * 1024),
];
