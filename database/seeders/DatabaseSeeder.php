<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles must exist before assigning them to users.
        $this->call(RolesAndPermissionsSeeder::class);

        // Default accounts (test credentials – see README).
        $superadmin = User::updateOrCreate(
            ['email' => 'superadmin@raftingmorava.cz'],
            ['name' => 'Superadmin', 'phone' => '+420 000 000 000', 'active' => true, 'password' => Hash::make('heslo1234')],
        );
        $superadmin->syncRoles(['Superadmin']);

        $admin = User::updateOrCreate(
            ['email' => 'admin@raftingmorava.cz'],
            ['name' => 'Admin', 'phone' => '+420 000 000 001', 'active' => true, 'password' => Hash::make('heslo1234')],
        );
        $admin->syncRoles(['Admin']);

        // Default email templates, catalogs from CSV, real instructors and sample operational data.
        $this->call([
            EmailTemplateSeeder::class,
            CatalogSeeder::class,
            InstructorSeeder::class,
            DemoTripsSeeder::class,
        ]);
    }
}
