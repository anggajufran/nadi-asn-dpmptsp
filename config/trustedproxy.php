<?php
return [
    // Explicit proxy IPs only; never trust client-supplied forwarding headers globally.
    'proxies' => array_values(
        array_filter(array_map('trim', explode(',', env('NADI_TRUSTED_PROXIES', '')))),
    ),
];
