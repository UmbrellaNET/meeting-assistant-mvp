<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class TranscriptController extends Controller { public function show(Request $request, Meeting $meeting): JsonResponse { abort_unless($meeting->tenant_id === $request->user()->tenant_id, 404); $version=$meeting->currentTranscript()->with(['segments.speaker','sourceArtifact'])->first(); abort_unless($version,404,'Transcript is not ready.'); return response()->json($version); } }
