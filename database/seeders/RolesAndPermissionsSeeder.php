<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

// Three roles per the spec. Concrete permissions are handled by Policy classes (see app/Policies) +
// Gate::before for Superadmin. Admin is intentionally limited: no catalogs, users or leaderboard.
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Superadmin', 'Admin', 'Instructor'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
