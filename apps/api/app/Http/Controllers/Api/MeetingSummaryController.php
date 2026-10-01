<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateMeetingSummary;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingSummaryController extends Controller
{
    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless($meeting->tenant_id === $request->user()->tenant_id, 404);
        abort_unless($request->user()->can('view', $meeting), 403);

        $summary = $meeting->summary;

        // No summary yet: return an explicit marker instead of null,
        // which Laravel would serialise as an empty object `{}`.
        if (! $summary) {
            return response()->json(['status' => 'pending']);
        }

        return response()->json($summary);
    }

    public function regenerate(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless($meeting->tenant_id === $request->user()->tenant_id, 404);
        abort_unless($request->user()->can('view', $meeting), 403);

        GenerateMeetingSummary::dispatch($meeting->id);

        return response()->json(['ok' => true]);
    }
}