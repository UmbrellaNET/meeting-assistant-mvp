<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function view(User $user, Meeting $meeting): bool
    {
        if ($user->tenant_id !== $meeting->tenant_id) {
            return false;
        }

        if ($this->canViewAny($user)) {
            return true;
        }

        return $meeting->participants()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('meetings.create');
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $this->isConvenorOrAdmin($user, $meeting);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->isConvenorOrAdmin($user, $meeting);
    }

    public function invite(User $user, Meeting $meeting): bool
    {
        return $this->isConvenorOrAdmin($user, $meeting);
    }

    public function upload(User $user, Meeting $meeting): bool
    {
        return $this->isConvenorOrAdmin($user, $meeting);
    }

    public function viewAny(User $user): bool
    {
        return $this->canViewAny($user) || $user->can('meetings.view-own');
    }

    private function canViewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->can('meetings.view-any');
    }

    private function isConvenorOrAdmin(User $user, Meeting $meeting): bool
    {
        if ($user->tenant_id !== $meeting->tenant_id) {
            return false;
        }

        if ($this->canViewAny($user)) {
            return true;
        }

        return $meeting->participants()
            ->where('user_id', $user->id)
            ->where('participation_role', 'convenor')
            ->where('status', 'accepted')
            ->exists();
    }
}
