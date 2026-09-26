<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Generates (or returns the existing) public share token for a meeting,
 * used to build an unauthenticated calendar-export link, and allows
 * revoking it.
 */
class ShareLinkController extends Controller
{
    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();
        abort_unless($meeting->tenant_id === $user->tenant_id, 404);
        abort_unless($user->can('view', $meeting), 403);

        if (! $meeting->share_token) {
            $meeting->share_token = Str::random(40);
            $meeting->save();
        }

        return response()->json([
            'share_token' => $meeting->share_token,
            'url' => rtrim(config('app.url'), '/') . "/api/calendar/{$meeting->share_token}/calendar.ics",
        ]);
    }

    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();
        abort_unless($meeting->tenant_id === $user->tenant_id, 404);
        abort_unless($user->can('view', $meeting), 403);

        $meeting->update(['share_token' => null]);

        return response()->json(['ok' => true]);
    }
}