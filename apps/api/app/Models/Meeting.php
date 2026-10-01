<?php

namespace App\Models;

use App\Models\Concerns\LogsDomainActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Meeting extends Model
{
    use HasUuids, LogsDomainActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected function activityLogName(): string
    {
        return 'meetings';
    }

    protected function activityEventPrefix(): string
    {
        return 'meeting';
    }

    protected function activityLogAttributes(): array
    {
        return [
            'title',
            'provider',
            'status',
            'processing_status',
            'scheduled_start_at',
            'timezone',
            'meeting_url',
            'organiser_user_id',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function organiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organiser_user_id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(MeetingArtifact::class);
    }

    public function transcriptVersions(): HasMany
    {
        return $this->hasMany(TranscriptVersion::class);
    }

    public function currentTranscript(): HasOne
    {
        return $this->hasOne(TranscriptVersion::class)->where('is_current', true);
    }

    public function speakers(): HasMany
    {
        return $this->hasMany(MeetingSpeaker::class);
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(ProcessingJob::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function acceptedParticipants(): HasMany
    {
        return $this->participants()->where('status', 'accepted');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('tenant_id', $user->tenant_id);

        if (! $user->isSuperAdmin() && ! $user->can('meetings.view-any')) {
            $query->whereHas('participants', fn (Builder $participants) => $participants
                ->where('user_id', $user->id)
                ->where('status', 'accepted'));
        }

        return $query;
    }

    public function scopeAcceptedFor(Builder $query, User $user): Builder
    {
        return $query->where('tenant_id', $user->tenant_id)
            ->whereHas('participants', fn (Builder $participants) => $participants
                ->where('user_id', $user->id)
                ->where('status', 'accepted'));
    }

    public function participationFor(User $user): ?MeetingParticipant
    {
        if ($this->relationLoaded('participants')) {
            return $this->participants->firstWhere('user_id', $user->id);
        }

        return $this->participants()->where('user_id', $user->id)->first();
    }

    public function myParticipationPayload(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $participation = $this->participationFor($user);
        if (! $participation) {
            return null;
        }

        return [
            'participation_role' => $participation->participation_role,
            'status' => $participation->status,
        ];
    }

    public function withMyParticipation(User $user): static
    {
        $this->setAttribute('my_participation', $this->myParticipationPayload($user));

        return $this;
    }

    public function summary()
    {
        return $this->hasOne(MeetingSummary::class);
    }
}
