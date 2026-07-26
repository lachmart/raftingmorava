<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\Location;
use App\Models\TripProgram;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * Catalogs from real data: programs (price lists) and rental equipment are loaded from CSV files
 * in database/seeders/data/. Locations and vehicles are samples (not supplied – see README).
 *
 * NOTE: the CSV files keep Czech headers/values (user-supplied data), so $row['...'] keys and the
 * CSV category/certainty values below are Czech on purpose. They are mapped to English enum values.
 */
class CatalogSeeder extends Seeder
{
    // CSV "kategorie" text → TripProgram.category enum.
    private const CATEGORY_MAP = [
        'Skolni vylety' => 'school',
        'Vylety v CR' => 'domestic',
        'Zahranicni vylety' => 'foreign',
    ];

    public function run(): void
    {
        $this->seedPrograms();
        $this->seedEquipment();
        $this->seedLocationsAndVehicles();
    }

    private function seedPrograms(): void
    {
        foreach (CsvReader::read(database_path('seeders/data/seed_programy.csv')) as $row) {
            $name = trim($row['nazev'] ?? '');
            if ($name === '') {
                continue;
            }

            TripProgram::updateOrCreate(
                ['name' => $name], // exact WP name = key for exact-match inquiry pairing
                [
                    'category' => self::CATEGORY_MAP[trim($row['kategorie'] ?? '')] ?? 'domestic',
                    'price_per_person' => self::number($row['cena_za_osobu'] ?? null),
                    // Empty reward = "by agreement" (null), not 0.
                    'instructor_reward' => self::number($row['odmena_instruktora'] ?? null),
                    'active' => true,
                ],
            );
        }

        // Generic flat rates for multi-day trips without a specific route (see spec 2b.1 / 2e).
        TripProgram::updateOrCreate(['name' => 'Dvoudenní — obecně'],
            ['category' => 'domestic', 'price_per_person' => null, 'instructor_reward' => 1800, 'active' => true]);
        TripProgram::updateOrCreate(['name' => 'Třídenní — obecně'],
            ['category' => 'domestic', 'price_per_person' => null, 'instructor_reward' => 2700, 'active' => true]);
    }

    private function seedEquipment(): void
    {
        foreach (CsvReader::read(database_path('seeders/data/seed_equipment.csv')) as $row) {
            $name = trim($row['nazev'] ?? '');
            if ($name === '') {
                continue;
            }

            Equipment::updateOrCreate(
                ['name' => $name],
                [
                    'price_per_day' => self::number($row['cena_za_den'] ?? null),
                    'note' => trim($row['poznamka'] ?? '') ?: null,
                    'active' => true,
                ],
            );
        }
    }

    private function seedLocationsAndVehicles(): void
    {
        // Real locations from CSV (nazev, jistota, poznamka).
        foreach (CsvReader::read(database_path('seeders/data/seed_lokace.csv')) as $row) {
            $name = trim($row['nazev'] ?? '');
            if ($name === '') {
                continue;
            }

            Location::updateOrCreate(
                ['name' => $name],
                [
                    'certainty' => trim($row['jistota'] ?? '') === 'doplnkove' ? 'optional' : 'confirmed',
                    'note' => trim($row['poznamka'] ?? '') ?: null,
                    'active' => true,
                ],
            );
        }

        // Sample vehicles (not supplied – adjust to reality).
        $vehicles = [
            ['name' => 'Dodávka Ford Transit', 'license_plate' => '1AB 1234', 'capacity' => 9],
            ['name' => 'Mikrobus Mercedes', 'license_plate' => '2CD 5678', 'capacity' => 17],
            ['name' => 'Přívěs na rafty', 'license_plate' => '3EF 9012', 'capacity' => 0],
        ];
        foreach ($vehicles as $v) {
            Vehicle::updateOrCreate(['name' => $v['name']], $v + ['active' => true]);
        }
    }

    /** Converts a textual price from CSV to a number, empty → null. */
    private static function number(?string $val): ?float
    {
        $val = trim((string) $val);

        return $val === '' ? null : (float) str_replace([' ', ','], ['', '.'], $val);
    }
}
