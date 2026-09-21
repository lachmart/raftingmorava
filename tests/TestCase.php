<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /** Create an active user with the given app role (roles are created on demand). */
    protected function userWithRole(string $role): User
    {
        foreach (['Superadmin', 'Admin', 'Instructor'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        // `active` isn't passed to create(), so it only exists as a DB-level default – the
        // in-memory model won't see it until refreshed. Needed by canAccessPanel() checks.
        $user = User::factory()->create()->refresh();
        $user->assignRole($role);

        return $user;
    }
}
