<?php

namespace App\Policies;

use App\Models\TripProgram;
use App\Models\User;

// Program catalog (price list) – managed by Superadmin only (via Gate::before). Admin has no access
// here, and only picks programs in the trip form (a Select does not check the policy).
class TripProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, TripProgram $program): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, TripProgram $program): bool
    {
        return false;
    }

    public function delete(User $user, TripProgram $program): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
