<?php
return [
    'ai_worker' => ['url' => env('AI_WORKER_URL', 'http://worker:8100'), 'secret' => env('INTERNAL_SHARED_SECRET')],
    'provider_webhooks' => ['secret' => env('WEBHOOK_SHARED_SECRET')],
];
