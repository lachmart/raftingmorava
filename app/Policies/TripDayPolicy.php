<?php

namespace App\Policies;

use App\Models\TripDay;
use App\Models\User;

class TripDayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function view(User $user, TripDay $tripDay): bool
    {
        return $user->hasRole('Admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, TripDay $tripDay): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, TripDay $tripDay): bool
    {
        return $user->hasRole('Admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }
}
