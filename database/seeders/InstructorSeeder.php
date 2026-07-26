<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Real instructors from database/seeders/data/seed_instruktori.csv (name, phone, email).
 * Everyone gets a default password (see README) – after deployment they must be prompted to change/reset it.
 * NOTE: the CSV keeps Czech headers (user-supplied data), so $row['...'] keys are Czech on purpose.
 */
class InstructorSeeder extends Seeder
{
    public function run(): void
    {
        $i = 0;
        foreach (CsvReader::read(database_path('seeders/data/seed_instruktori.csv')) as $row) {
            $name = trim($row['jmeno'] ?? '');
            $email = trim($row['email'] ?? '');

            // Without a name or email we do not create the instructor (no orphan mailto records).
            if ($name === '' || $email === '') {
                continue;
            }

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => trim($row['telefon'] ?? '') ?: null,
                    'active' => true,
                    // Demo: every 4th instructor can also drive (the flag is edited by Superadmin).
                    'is_driver' => (++$i % 4 === 0),
                    'password' => Hash::make('heslo1234'),
                ],
            );

            $user->syncRoles(['Instructor']);
        }
    }
}
