<?php

namespace App\Models;

use App\Models\Concerns\LogsDomainActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MeetingSpeaker extends Model
{
    use HasUuids, LogsDomainActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'identity_confidence' => 'float'];
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function segments()
    {
        return $this->hasMany(TranscriptSegment::class, 'speaker_id');
    }

    protected function activityLogName(): string
    {
        return 'meetings';
    }

    protected function activityEventPrefix(): string
    {
        return 'speaker';
    }

    protected function activityLogAttributes(): array
    {
        return ['display_name', 'identity_status'];
    }

    protected function activitySubjectLabel(): string
    {
        return $this->display_name ?: 'Meeting speaker';
    }
}
