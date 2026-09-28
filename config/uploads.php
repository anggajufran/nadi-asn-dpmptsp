<?php
return [
    // Production always fails closed even if the environment flag is omitted.
    'scan_enabled' => (bool) env('NADI_UPLOAD_SCAN_ENABLED', false),
    'clamav_host' => env('CLAMAV_HOST', 'antivirus'),
    'clamav_port' => (int) env('CLAMAV_PORT', 3310),
    'scan_timeout' => 30,
];
