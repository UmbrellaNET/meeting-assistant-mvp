<?php

namespace App\Models;

use App\Models\Concerns\LogsDomainActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MeetingArtifact extends Model
{
    use HasUuids, LogsDomainActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'is_original' => 'boolean'];
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function transcriptVersions()
    {
        return $this->hasMany(TranscriptVersion::class, 'source_artifact_id');
    }

    protected function activityLogName(): string
    {
        return 'meetings';
    }

    protected function activityEventPrefix(): string
    {
        return 'artifact';
    }

    protected function activityLogAttributes(): array
    {
        return ['artifact_type', 'original_filename', 'status', 'file_size'];
    }

    protected function activitySubjectLabel(): string
    {
        return $this->original_filename ?: 'Meeting artifact';
    }
}
