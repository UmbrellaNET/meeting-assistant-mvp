<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\EmbedMeetingTranscript;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Put in: apps/api/app/Http/Controllers/Api/SearchIndexController.php
class SearchIndexController extends Controller
{
    /** Super admins: (re)index every meeting in the organisation that has a transcript. */
    public function rebuild(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('super-admin'), 403);

        $ids = Meeting::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereHas('currentTranscript')
            ->pluck('id');

        foreach ($ids as $id) {
            EmbedMeetingTranscript::dispatch($id);
        }

        return response()->json(['queued' => $ids->count()]);
    }
}
