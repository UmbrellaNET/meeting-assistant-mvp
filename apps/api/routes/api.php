<?php

use App\Http\Controllers\Api\ArtifactController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EvidenceController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\ProviderWebhookController;
use App\Http\Controllers\Api\SpeakerController;
use App\Http\Controllers\Api\TranscriptController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::post('webhooks/{provider}', ProviderWebhookController::class)
    ->whereIn('provider', ['teams', 'zoom', 'google-meet']);

Route::middleware('api.token')->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::apiResource('meetings', MeetingController::class)->only(['index', 'store', 'show']);
    Route::post('meetings/{meeting}/artifacts', [ArtifactController::class, 'store']);
    Route::get('meetings/{meeting}/artifacts/{artifact}/playback', [ArtifactController::class, 'playback']);
    Route::get('meetings/{meeting}/transcript', [TranscriptController::class, 'show']);
    Route::patch('meetings/{meeting}/speakers/{speaker}', [SpeakerController::class, 'update']);
    Route::get('evidence/{evidence}', [EvidenceController::class, 'show']);
});
