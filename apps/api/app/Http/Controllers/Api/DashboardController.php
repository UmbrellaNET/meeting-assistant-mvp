<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $weekStart = now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addWeek();

        $pending = MeetingParticipant::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('meeting', fn ($query) => $query->where('tenant_id', $user->tenant_id))
            ->with('meeting')
            ->latest()
            ->get()
            ->map(function (MeetingParticipant $participant) use ($user) {
                $meeting = $participant->meeting;
                if ($meeting) {
                    $meeting->setRelation('participants', collect([$participant]));
                    $meeting->withMyParticipation($user);
                    $meeting->unsetRelation('participants');
                }

                return [
                    'id' => $participant->id,
                    'meeting_id' => $participant->meeting_id,
                    'meeting' => $meeting,
                    'title' => $meeting?->title,
                    'scheduled_start_at' => $meeting?->scheduled_start_at,
                    'created_at' => $participant->created_at,
                    'participation_role' => $participant->participation_role,
                    'status' => $participant->status,
                    'my_participation' => [
                        'participation_role' => $participant->participation_role,
                        'status' => $participant->status,
                    ],
                ];
            });

        $accepted = fn () => Meeting::query()
            ->acceptedFor($user)
            ->with(['participants' => fn ($query) => $query->where('user_id', $user->id)]);

        $upcoming = $accepted()
            ->whereNotNull('scheduled_start_at')
            ->where('scheduled_start_at', '>=', $weekStart)
            ->where('scheduled_start_at', '<', $weekEnd)
            ->orderBy('scheduled_start_at')
            ->get()
            ->map(fn (Meeting $meeting) => $meeting->withMyParticipation($user)->unsetRelation('participants'));

        $notesReady = $accepted()
            ->where(function ($query) {
                $query->where('status', 'ready')
                    ->orWhereHas('currentTranscript')
                    ->orWhereHas('artifacts');
            })
            ->latest()
            ->get()
            ->map(fn (Meeting $meeting) => $meeting->withMyParticipation($user)->unsetRelation('participants'));

        $payload = [
            'pending_invitations' => $pending->values(),
            'upcoming_meetings' => $upcoming->values(),
            'notes_ready' => $notesReady->values(),
        ];

        if ($user->isSuperAdmin() || $user->can('meetings.view-any')) {
            $tenantMeetings = Meeting::query()->where('tenant_id', $user->tenant_id);
            $payload['total_meetings'] = (clone $tenantMeetings)->count();
            $payload['ready_count'] = (clone $tenantMeetings)->where('status', 'ready')->count();
            $payload['processing_count'] = (clone $tenantMeetings)
                ->whereIn('processing_status', ['queued', 'transcribing', 'processing'])
                ->count();
        }

        return response()->json($payload);
    }
}
