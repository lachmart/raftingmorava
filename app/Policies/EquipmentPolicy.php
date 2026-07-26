<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

// Equipment catalog – Superadmin only (Gate::before). Admin picks equipment in the trip form.
class EquipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return false;
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
