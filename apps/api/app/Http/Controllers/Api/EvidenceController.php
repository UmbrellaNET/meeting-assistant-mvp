<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvidenceLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EvidenceController extends Controller
{
    public function show(Request $request, EvidenceLink $evidence): JsonResponse
    {
        $meeting = $evidence->meeting;
        abort_unless($meeting && $meeting->tenant_id === $request->user()->tenant_id, 404);
        abort_unless($request->user()->can('view', $meeting), 403);
        $evidence->load(['artifact', 'transcriptVersion']);
        $url = $evidence->artifact
            ? Storage::disk($evidence->artifact->storage_disk)->temporaryUrl($evidence->artifact->storage_key, now()->addMinutes(10))
            : null;

        return response()->json(['evidence' => $evidence, 'playback_url' => $url]);
    }
}
