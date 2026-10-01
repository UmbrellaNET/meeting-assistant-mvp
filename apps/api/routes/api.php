<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AdminRoleController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\ArtifactController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EvidenceController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\ProviderWebhookController;
use App\Http\Controllers\Api\SpeakerController;
use App\Http\Controllers\Api\TenantUserController;
use App\Http\Controllers\Api\TranscriptController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CalendarExportController;
use App\Http\Controllers\Api\PublicCalendarExportController;
use App\Http\Controllers\Api\ShareLinkController;
use App\Http\Controllers\Api\MeetingSummaryController;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::post('webhooks/{provider}', ProviderWebhookController::class)
    ->whereIn('provider', ['teams', 'zoom', 'google-meet']);

Route::get('calendar/{token}/calendar.ics', PublicCalendarExportController::class)
    ->where('token', '[A-Za-z0-9]{40}');

Route::middleware('api.token')->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::patch('auth/profile', [AuthController::class, 'profile'])->middleware('permission:profile.update');
    Route::post('auth/profile/photo', [AuthController::class, 'uploadPhoto'])->middleware('permission:profile.update');
    Route::delete('auth/profile/photo', [AuthController::class, 'destroyPhoto'])->middleware('permission:profile.update');
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/stop-impersonation', [AuthController::class, 'stopImpersonation']);

    Route::get('meetings/{meeting}/calendar.ics', CalendarExportController::class);
    Route::post('meetings/{meeting}/share-link', [ShareLinkController::class, 'store']);
    Route::delete('meetings/{meeting}/share-link', [ShareLinkController::class, 'destroy']);
    Route::get('dashboard', [DashboardController::class, 'show']);
    Route::get('users', [TenantUserController::class, 'index']);
    Route::get('invitations', [InvitationController::class, 'inbox']);

    Route::get('meetings/{meeting}/summary', [MeetingSummaryController::class, 'show']);
    Route::post('meetings/{meeting}/summary/regenerate', [MeetingSummaryController::class, 'regenerate']);

    Route::apiResource('meetings', MeetingController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('meetings/{meeting}/invitations', [InvitationController::class, 'store']);
    Route::get('meetings/{meeting}/participants', [InvitationController::class, 'participants']);
    Route::post('meetings/{meeting}/invitations/respond', [InvitationController::class, 'respond']);

    Route::post('meetings/{meeting}/artifacts', [ArtifactController::class, 'store']);
    Route::get('meetings/{meeting}/artifacts/{artifact}/playback', [ArtifactController::class, 'playback']);
    Route::get('meetings/{meeting}/transcript', [TranscriptController::class, 'show']);
    Route::patch('meetings/{meeting}/speakers/{speaker}', [SpeakerController::class, 'update']);
    Route::get('evidence/{evidence}', [EvidenceController::class, 'show']);

    Route::get('settings/branding', [BrandingController::class, 'show']);
    Route::put('settings/branding', [BrandingController::class, 'update'])->middleware('permission:branding.manage');
    Route::post('settings/branding/{type}', [BrandingController::class, 'upload'])
        ->middleware('permission:branding.manage')
        ->whereIn('type', ['logo', 'icon', 'favicon']);
    Route::delete('settings/branding/{type}', [BrandingController::class, 'destroy'])
        ->middleware('permission:branding.manage')
        ->whereIn('type', ['logo', 'icon', 'favicon']);

    Route::post('admin/users/{user}/impersonate', [AdminUserController::class, 'impersonate']);

    Route::middleware('permission:users.manage')->prefix('admin')->group(function (): void {
        Route::get('users', [AdminUserController::class, 'index']);
        Route::post('users', [AdminUserController::class, 'store']);
        Route::patch('users/{user}', [AdminUserController::class, 'update']);
        Route::delete('users/{user}', [AdminUserController::class, 'destroy']);
    });

    Route::middleware('permission:roles.view')->prefix('admin')->group(function (): void {
        Route::get('roles', [AdminRoleController::class, 'index']);
    });

    Route::middleware('permission:activity.view')->prefix('admin')->group(function (): void {
        Route::get('activity', [ActivityController::class, 'index']);
        Route::get('activity/{activity}', [ActivityController::class, 'show']);
    });
});
