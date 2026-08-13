<?php

return [
    // This subsystem is deliberately isolated from products@ and manufacturer outreach.
    'mailbox_address' => env('SUPPORT_MAILBOX_ADDRESS', 'appsupport@halalkiwi.com'),
    'mailbox_name' => env('SUPPORT_MAILBOX_NAME', 'Halal Kiwi App Support'),

    // Customer replies fail closed unless the dedicated support SMTP path is enabled.
    'mail_enabled' => (bool) env('SUPPORT_MAIL_ENABLED', false),
    'mailer' => env('SUPPORT_MAILER', 'support'),
    'attachment_max_bytes' => (int) env('SUPPORT_ATTACHMENT_MAX_BYTES', 5 * 1024 * 1024),
    'attachment_daily_per_email_count' => (int) env('SUPPORT_ATTACHMENT_DAILY_PER_EMAIL_COUNT', 5),
    'attachment_daily_per_email_bytes' => (int) env('SUPPORT_ATTACHMENT_DAILY_PER_EMAIL_BYTES', 15 * 1024 * 1024),
    'attachment_daily_global_count' => (int) env('SUPPORT_ATTACHMENT_DAILY_GLOBAL_COUNT', 250),
    'attachment_daily_global_bytes' => (int) env('SUPPORT_ATTACHMENT_DAILY_GLOBAL_BYTES', 500 * 1024 * 1024),
    'attachment_min_free_bytes' => (int) env('SUPPORT_ATTACHMENT_MIN_FREE_BYTES', 1024 * 1024 * 1024),
    'delivery_reconcile_after_seconds' => (int) env('SUPPORT_DELIVERY_RECONCILE_AFTER_SECONDS', 300),
    'mailbox_body_max_bytes' => (int) env('SUPPORT_MAILBOX_BODY_MAX_BYTES', 2 * 1024 * 1024),
    'mailbox_headers_max_bytes' => (int) env('SUPPORT_MAILBOX_HEADERS_MAX_BYTES', 64 * 1024),
];
