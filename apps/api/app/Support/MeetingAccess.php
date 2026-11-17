<?php

namespace App\Support;

use App\Models\Meeting;

// Put in: apps/api/app/Support/MeetingAccess.php
class MeetingAccess
{
    /**
     * The meetings a user may search and ask about: those in their organisation that they attended and accepted.
     * Search and Ask always filter by this list BEFORE looking at any transcript text.
     *
     * @return array<int, string>
     */
    public static function idsFor($user): array
    {
        return Meeting::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereHas('participants', fn ($participants) => $participants
                ->where('user_id', $user->id)
                ->where('status', 'accepted'))
            ->pluck('id')
            ->all();
    }
}
