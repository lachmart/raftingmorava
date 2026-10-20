<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

// Meetings are managed by Admin (and Superadmin via Gate::before); everyone sees them in the calendar.
class MeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $user->hasRole('Admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }
}
