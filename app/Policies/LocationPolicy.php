<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

// Location catalog – Superadmin only (Gate::before).
class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Location $location): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        // Admin may quickly add a new location on the fly (e.g. from the trip-day form),
        // but full catalog management (list/edit/delete) is Superadmin only.
        return $user->hasRole('Admin');
    }

    public function update(User $user, Location $location): bool
    {
        return false;
    }

    public function delete(User $user, Location $location): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
