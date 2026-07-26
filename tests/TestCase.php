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

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
