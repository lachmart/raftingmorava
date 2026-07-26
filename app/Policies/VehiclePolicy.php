<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

// Vehicle catalog – Superadmin only (Gate::before).
class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return false;
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
