<?php

namespace App\Models;

use App\Models\Concerns\LogsDomainActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipant extends Model
{
    use HasUuids, LogsDomainActivity;

    protected $guarded = [];

    protected function activityLogName(): string
    {
        return 'meetings';
    }

    protected function activityEventPrefix(): string
    {
        return 'participant';
    }

    protected function activityLogAttributes(): array
    {
        return ['meeting_id', 'user_id', 'participation_role', 'status', 'invited_by_user_id'];
    }

    protected function activitySubjectLabel(): string
    {
        return $this->user?->name
            ?? $this->user?->email
            ?? 'Meeting participant';
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function payload(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'participation_role' => $this->participation_role,
            'status' => $this->status,
            'invited_by_user_id' => $this->invited_by_user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
        ];
    }
}
