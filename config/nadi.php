<?php
return [
    'recovery_mail_enabled' => (bool) env('NADI_RECOVERY_MAIL_ENABLED', false),
    'frontend_url' => env('NADI_FRONTEND_URL', 'http://127.0.0.1:5173'),
    'public_release_enabled' => (bool) env('NADI_PUBLIC_RELEASE_ENABLED', false),
    'production_ready' => (bool) env('NADI_PRODUCTION_READY', false),
    'totp_key' => env('NADI_TOTP_KEY'),
    'absolute_session_hours' => 8,
    'import_retention_days' => (int) env('NADI_IMPORT_RETENTION_DAYS', 14),
];
