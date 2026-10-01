<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MeetingSummary extends Model
{
    use HasUuids;

    protected $fillable = [
        'meeting_id', 'transcript_version_id', 'executive_summary', 'quick_summary',
        'decisions', 'topics', 'action_items', 'risks', 'dependencies', 'unknowns',
        'status', 'error_message',
    ];

    protected $casts = [
        'quick_summary' => 'array',
        'decisions' => 'array',
        'topics' => 'array',
        'action_items' => 'array',
        'risks' => 'array',
        'dependencies' => 'array',
        'unknowns' => 'array',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }
}
