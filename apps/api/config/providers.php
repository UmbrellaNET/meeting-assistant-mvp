<?php
return [
    'teams' => ['tenant_id' => env('TEAMS_TENANT_ID'), 'client_id' => env('TEAMS_CLIENT_ID'), 'client_secret' => env('TEAMS_CLIENT_SECRET')],
    'zoom' => ['account_id' => env('ZOOM_ACCOUNT_ID'), 'client_id' => env('ZOOM_CLIENT_ID'), 'client_secret' => env('ZOOM_CLIENT_SECRET')],
    'google-meet' => ['client_id' => env('GOOGLE_CLIENT_ID'), 'client_secret' => env('GOOGLE_CLIENT_SECRET')],
];
