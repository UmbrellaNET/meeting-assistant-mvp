<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\User;
use App\Services\RecordsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function inbox(Request $request): JsonResponse
    {
        $user = $request->user();
        $invitations = MeetingParticipant::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('meeting', fn ($query) => $query->where('tenant_id', $user->tenant_id))
            ->with(['meeting.participants' => fn ($query) => $query->where('user_id', $user->id)])
            ->latest()
            ->get()
            ->map(fn (MeetingParticipant $participant) => $this->invitationPayload($participant, $user));

        return response()->json($invitations);
    }

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $actor = $request->user();
        abort_unless($meeting->tenant_id === $actor->tenant_id, 404);
        abort_unless($actor->can('invite', $meeting), 403);

        $data = $request->validate([
            'user_id' => 'required|uuid',
        ]);

        $invitee = User::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('id', $data['user_id'])
            ->first();
        abort_unless($invitee, 422, 'User is not in this organisation.');
        abort_if($invitee->id === $actor->id, 422, 'You cannot invite yourself.');

        $existing = MeetingParticipant::query()
            ->where('meeting_id', $meeting->id)
            ->where('user_id', $invitee->id)
            ->first();

        if ($existing) {
            abort_if(in_array($existing->status, ['pending', 'accepted'], true), 422, 'User is already invited.');
            $existing->update([
                'participation_role' => 'attendee',
                'status' => 'pending',
                'invited_by_user_id' => $actor->id,
            ]);
            $existing->load('user');
            $this->recordInvitationSent($actor, $meeting, $invitee);

            return response()->json($existing->payload());
        }

        $participant = MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'user_id' => $invitee->id,
            'participation_role' => 'attendee',
            'status' => 'pending',
            'invited_by_user_id' => $actor->id,
        ]);
        $participant->load('user');
        $this->recordInvitationSent($actor, $meeting, $invitee);

        return response()->json($participant->payload(), 201);
    }

    public function participants(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();
        abort_unless($meeting->tenant_id === $user->tenant_id, 404);
        abort_unless($user->can('view', $meeting), 403);

        $participants = $meeting->participants()->with('user')->orderBy('created_at')->get();

        return response()->json($participants->map->payload()->values());
    }

    public function respond(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();
        abort_unless($meeting->tenant_id === $user->tenant_id, 404);

        $data = $request->validate([
            'status' => 'required|in:accepted,declined',
        ]);

        $participant = MeetingParticipant::query()
            ->where('meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->first();
        abort_unless($participant, 404, 'Invitation not found.');

        $participant->update(['status' => $data['status']]);
        $participant->load(['user', 'meeting']);

        RecordsActivity::make()
            ->event($data['status'] === 'accepted' ? ActivityEvent::InvitationAccepted : ActivityEvent::InvitationDeclined)
            ->causedBy($user)
            ->performedOn($meeting)
            ->log();

        return response()->json($this->invitationPayload($participant->fresh(['user', 'meeting']), $user));
    }

    private function recordInvitationSent(User $actor, Meeting $meeting, User $invitee): void
    {
        RecordsActivity::make()
            ->event(ActivityEvent::InvitationSent)
            ->causedBy($actor)
            ->performedOn($meeting)
            ->withProperties([
                'invitee_id' => $invitee->id,
                'invitee_email' => $invitee->email,
            ])
            ->log();
    }

    private function invitationPayload(MeetingParticipant $participant, User $user): array
    {
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
    }
}
