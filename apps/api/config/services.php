<?php
return [
    'ai_worker' => ['url' => env('AI_WORKER_URL', 'http://worker:8100'), 'secret' => env('INTERNAL_SHARED_SECRET')],
    'provider_webhooks' => ['secret' => env('WEBHOOK_SHARED_SECRET')],
    'microsoft_teams' => [
        'tenant_id' => env('TEAMS_TENANT_ID'),
        'client_id' => env('TEAMS_CLIENT_ID'),
        'client_secret' => env('TEAMS_CLIENT_SECRET'),
        'webhook_client_state' => env('TEAMS_WEBHOOK_CLIENT_STATE'),
    ],
    'zoom' => [
        'account_id' => env('ZOOM_ACCOUNT_ID'),
        'client_id' => env('ZOOM_CLIENT_ID'),
        'client_secret' => env('ZOOM_CLIENT_SECRET'),
        'webhook_secret_token' => env('ZOOM_WEBHOOK_SECRET_TOKEN'),
    ],
];