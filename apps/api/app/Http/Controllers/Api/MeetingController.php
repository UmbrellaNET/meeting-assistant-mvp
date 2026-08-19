<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 20) ?: 20));
        $meetings = Meeting::query()->where('tenant_id', $request->user()->tenant_id)->withCount(['artifacts','transcriptVersions'])->latest()->paginate($perPage);
        return response()->json($meetings);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'=>'required|string|max:200', 'provider'=>'nullable|in:manual,teams,zoom,google-meet',
            'provider_meeting_id'=>'nullable|string|max:255', 'scheduled_start_at'=>'nullable|date',
            'timezone'=>'nullable|string|max:80', 'meeting_url'=>'nullable|url|max:1000'
        ]);
        $meeting = Meeting::create(array_merge($data, ['tenant_id'=>$request->user()->tenant_id,'organiser_user_id'=>$request->user()->id,'provider'=>$data['provider'] ?? 'manual','status'=>'created','processing_status'=>'not_started','capture_mode'=>'post_meeting','timezone'=>$data['timezone'] ?? 'UTC']));
        return response()->json($meeting, 201);
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $this->assertTenant($request, $meeting);
        return response()->json($meeting->load(['artifacts'=>fn($q)=>$q->latest(),'currentTranscript.segments.speaker','speakers','processingJobs'=>fn($q)=>$q->latest()]));
    }

    private function assertTenant(Request $request, Meeting $meeting): void { abort_unless($meeting->tenant_id === $request->user()->tenant_id, 404); }
}
