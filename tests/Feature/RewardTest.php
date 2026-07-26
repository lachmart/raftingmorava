<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_reward_is_prefilled_from_program(): void
    {
        $program = TripProgram::create(['name' => 'Test', 'category' => 'domestic', 'instructor_reward' => 500]);

        $trip = Trip::create([
            'trip_program_id' => $program->id,
            'type' => 'guided',
            'name' => 'Test výlet',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-01',
            'status' => 'planned',
        ]);

        $this->assertEquals(500, (float) $trip->instructor_reward);
    }

    public function test_instructor_gets_full_reward_and_driver_zero(): void
    {
        $program = TripProgram::create(['name' => 'Test', 'category' => 'domestic', 'instructor_reward' => 500]);
        $trip = Trip::create([
            'trip_program_id' => $program->id, 'type' => 'guided', 'name' => 'T',
            'date_from' => '2026-08-01', 'date_to' => '2026-08-01', 'status' => 'planned',
        ]);
        $day = $trip->days()->create(['date' => '2026-08-01', 'order' => 1]);

        $instructor = User::factory()->create();
        $driver = User::factory()->create();

        $instrAssignment = $day->assignments()->create(['user_id' => $instructor->id, 'type' => 'instructor']);
        $driverAssignment = $day->assignments()->create(['user_id' => $driver->id, 'type' => 'driver']);

        $this->assertEquals(500, (float) $instrAssignment->reward, 'Instruktor dostane plnou odměnu výletu');
        $this->assertEquals(0, (float) $driverAssignment->reward, 'Řidič má odměnu 0');
    }
}
