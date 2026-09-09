<?php

return [
    'location_enabled' => env('ACTIVITY_LOCATION_ENABLED', true),
    'sensitive_keys' => [
        'password',
        'current_password',
        'password_confirmation',
        'token',
        'token_hash',
        'plain_token',
        'secret',
        'authorization',
        'remember_token',
        'webhook_signature',
    ],
];
