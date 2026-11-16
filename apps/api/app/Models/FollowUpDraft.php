<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Put in: apps/api/app/Models/FollowUpDraft.php
class FollowUpDraft extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'meeting_id', 'created_by_user_id', 'subject', 'body', 'recipients',
        'status', 'sent_at', 'sent_by_user_id', 'sent_to',
    ];

    protected $casts = [
        'recipients' => 'array',
        'sent_to' => 'array',
        'sent_at' => 'datetime',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
