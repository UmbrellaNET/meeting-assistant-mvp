<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeetingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = max(1, min(100, $request->integer('per_page', 20) ?: 20));
        $meetings = Meeting::query()
            ->visibleTo($user)
            ->withCount(['artifacts', 'transcriptVersions'])
            ->with(['participants' => fn ($query) => $query->where('user_id', $user->id)])
            ->latest()
            ->paginate($perPage);

        $meetings->getCollection()->transform(function (Meeting $meeting) use ($user) {
            $meeting->withMyParticipation($user);
            $meeting->unsetRelation('participants');

            return $meeting;
        });

        return response()->json($meetings);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('create', Meeting::class), 403, 'Forbidden.');

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'provider' => 'nullable|in:manual,teams,zoom,google-meet',
            'provider_meeting_id' => 'nullable|string|max:255',
            'scheduled_start_at' => 'nullable|date',
            'timezone' => 'nullable|string|max:80',
            'meeting_url' => 'nullable|url|max:1000',
        ]);

        $meeting = DB::transaction(function () use ($data, $user) {
            $meeting = Meeting::create(array_merge($data, [
                'tenant_id' => $user->tenant_id,
                'organiser_user_id' => $user->id,
                'provider' => $data['provider'] ?? 'manual',
                'status' => 'created',
                'processing_status' => 'not_started',
                'capture_mode' => 'post_meeting',
                'timezone' => $data['timezone'] ?? 'UTC',
            ]));

            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'participation_role' => 'convenor',
                'status' => 'accepted',
                'invited_by_user_id' => $user->id,
            ]);

            return $meeting;
        });

        $meeting->load(['participants' => fn ($query) => $query->where('user_id', $user->id)]);

        return response()->json($meeting->withMyParticipation($user), 201);
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();
        abort_unless($meeting->tenant_id === $user->tenant_id, 404);
        abort_unless($user->can('view', $meeting), 403);

        $meeting->load([
            'artifacts' => fn ($query) => $query->latest(),
            'currentTranscript.segments.speaker',
            'speakers',
            'processingJobs' => fn ($query) => $query->latest(),
            'participants.user',
        ]);
        $participants = $meeting->participants->map->payload()->values();
        $meeting->withMyParticipation($user);
        $meeting->unsetRelation('participants');
        $meeting->setAttribute('participants', $participants);

        return response()->json($meeting);
    }

    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();
        abort_unless($meeting->tenant_id === $user->tenant_id, 404);
        abort_unless($user->can('delete', $meeting), 403);

        $meeting->delete();

        return response()->json(['ok' => true]);
    }
}
