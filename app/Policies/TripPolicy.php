<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

// Trips are managed by Admin (and Superadmin via Gate::before). Instructors have their own "My trips" portal.
class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function view(User $user, Trip $trip): bool
    {
        return $user->hasRole('Admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, Trip $trip): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }
}
