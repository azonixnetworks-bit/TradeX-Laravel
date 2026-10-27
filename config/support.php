<?php

return [
    'timezone' => env('TRADEX_SUPPORT_TIMEZONE', 'Asia/Colombo'),
    'private_disk' => env('TRADEX_SUPPORT_DISK', 'private'),
    'max_attachment_kb' => (int) env('TRADEX_SUPPORT_MAX_ATTACHMENT_KB', 10240),
    'max_attachments_per_request' => (int) env('TRADEX_SUPPORT_MAX_ATTACHMENTS', 5),
    'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    'agent_presence_ttl_seconds' => (int) env('TRADEX_SUPPORT_AGENT_PRESENCE_TTL_SECONDS', 90),
    'reopen_days' => (int) env('TRADEX_SUPPORT_REOPEN_DAYS', 7),
    'mail_enabled' => filter_var(env('TRADEX_SUPPORT_MAIL_ENABLED', false), FILTER_VALIDATE_BOOL),
    'notification_queue' => env('TRADEX_SUPPORT_NOTIFICATION_QUEUE', 'support-notifications'),
    'sla_sweep_enabled' => filter_var(env('TRADEX_SUPPORT_SLA_SWEEP_ENABLED', true), FILTER_VALIDATE_BOOL),
    'daily_summary_enabled' => filter_var(env('TRADEX_SUPPORT_DAILY_SUMMARY_ENABLED', false), FILTER_VALIDATE_BOOL),
    'daily_summary_time' => env('TRADEX_SUPPORT_DAILY_SUMMARY_TIME', '08:30'),
];
