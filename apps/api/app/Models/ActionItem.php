<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Put in: apps/api/app/Models/ActionItem.php
class ActionItem extends Model
{
    use HasUuids;

    public const STATUSES = ['open', 'in_progress', 'done', 'cancelled'];
    public const PRIORITIES = ['high', 'medium', 'low'];

    protected $fillable = [
        'tenant_id', 'meeting_id', 'owner_user_id', 'owner_name', 'title', 'context',
        'due_date', 'due_text', 'status', 'priority', 'source', 'fingerprint',
        'completed_at', 'edited_at', 'external_provider', 'external_id', 'external_url',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
