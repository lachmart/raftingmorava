<?php

namespace App\Policies;

use App\Models\TripDayAssignment;
use App\Models\User;

class TripDayAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function view(User $user, TripDayAssignment $assignment): bool
    {
        return $user->hasRole('Admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, TripDayAssignment $assignment): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, TripDayAssignment $assignment): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }
}
