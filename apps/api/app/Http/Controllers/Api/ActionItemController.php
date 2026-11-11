<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActionItem;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Put in: apps/api/app/Http/Controllers/Api/ActionItemController.php
class ActionItemController extends Controller
{
    /** Action items for one meeting. */
    public function index(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeView($request, $meeting);

        $items = ActionItem::where('meeting_id', $meeting->id)
            ->orderByRaw("case status when 'open' then 0 when 'in_progress' then 1 when 'done' then 2 else 3 end")
            ->orderBy('due_date')
            ->orderBy('created_at')
            ->get();

        return response()->json($items);
    }

    /** The signed-in user's own open items across meetings. */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = ActionItem::with('meeting:id,title,scheduled_start_at')
            ->where('tenant_id', $user->tenant_id)
            ->where('owner_user_id', $user->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->orderBy('due_date')
            ->orderBy('created_at')
            ->limit(200)
            ->get();

        return response()->json($items);
    }

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeView($request, $meeting);
        abort_unless($this->canEdit($request, $meeting), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:1000'],
            'owner_user_id' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'priority' => ['nullable', Rule::in(ActionItem::PRIORITIES)],
            'context' => ['nullable', 'string', 'max:2000'],
        ]);

        $ownerId = $data['owner_user_id'] ?? null;
        $ownerName = null;
        if ($ownerId) {
            $participant = $meeting->participants()->with('user')->where('user_id', $ownerId)->first();
            abort_unless($participant, 422, 'Owner must be a participant of this meeting.');
            $ownerName = $participant->user?->name;
        }

        $item = ActionItem::create([
            'tenant_id' => $meeting->tenant_id,
            'meeting_id' => $meeting->id,
            'owner_user_id' => $ownerId,
            'owner_name' => $ownerName,
            'title' => trim($data['title']),
            'context' => $data['context'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
            'source' => 'manual',
            'edited_at' => now(),
        ]);

        return response()->json($item, 201);
    }

    public function update(Request $request, ActionItem $actionItem): JsonResponse
    {
        $meeting = Meeting::findOrFail($actionItem->meeting_id);
        $this->authorizeView($request, $meeting);
        abort_unless($this->canEdit($request, $meeting, $actionItem), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:1000'],
            'owner_user_id' => ['sometimes', 'nullable', 'string'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::in(ActionItem::STATUSES)],
            'priority' => ['sometimes', Rule::in(ActionItem::PRIORITIES)],
            'context' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('owner_user_id', $data)) {
            if ($data['owner_user_id']) {
                $participant = $meeting->participants()->with('user')->where('user_id', $data['owner_user_id'])->first();
                abort_unless($participant, 422, 'Owner must be a participant of this meeting.');
                $data['owner_name'] = $participant->user?->name;
            } else {
                $data['owner_name'] = null;
            }
        }

        if (array_key_exists('status', $data)) {
            $data['completed_at'] = $data['status'] === 'done' ? now() : null;
        }

        $actionItem->fill($data);
        $actionItem->edited_at = now();
        $actionItem->save();

        return response()->json($actionItem->fresh());
    }

    public function destroy(Request $request, ActionItem $actionItem): JsonResponse
    {
        $meeting = Meeting::findOrFail($actionItem->meeting_id);
        $this->authorizeView($request, $meeting);
        // Deleting is limited to convenors and super admins; owners can edit but not delete.
        abort_unless($this->canEdit($request, $meeting), 403);

        $actionItem->delete();

        return response()->json(['deleted' => true]);
    }

    private function authorizeView(Request $request, Meeting $meeting): void
    {
        abort_unless($meeting->tenant_id === $request->user()->tenant_id, 404);
        abort_unless($request->user()->can('view', $meeting), 403);
    }

    /** Convenors and super admins can edit any item; an item's owner can edit their own. */
    private function canEdit(Request $request, Meeting $meeting, ?ActionItem $item = null): bool
    {
        $user = $request->user();

        if ($user->hasRole('super-admin')) {
            return true;
        }
        if ($item && $item->owner_user_id !== null && $item->owner_user_id === $user->id) {
            return true;
        }

        return $meeting->participants()
            ->where('user_id', $user->id)
            ->where('participation_role', 'convenor')
            ->exists();
    }
}
