<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

// Put in: apps/api/app/Models/TranscriptChunk.php
// The embedding column is read and written with raw SQL (see EmbedMeetingTranscript and ChunkSearch).
class TranscriptChunk extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'meeting_id', 'transcript_version_id', 'sequence', 'start_ms', 'end_ms', 'content', 'segment_ids',
    ];

    protected $casts = ['segment_ids' => 'array'];
}
