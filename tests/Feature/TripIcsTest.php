<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Trip;
use App\Models\User;
use App\Support\TripIcs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TripIcsTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_calendar_event_from_trip(): void
    {
        $location = Location::create(['name' => 'Chomoutov']);
        $trip = Trip::create([
            'type' => 'guided', 'name' => 'Testovací výlet',
            'date_from' => '2026-09-10', 'date_to' => '2026-09-10', 'status' => 'planned',
        ]);
        $trip->days()->create([
            'date' => '2026-09-10', 'order' => 1, 'location_id' => $location->id,
            'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $ics = TripIcs::for($trip->fresh());

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        // 09:00 Prague (CEST, +2) → 07:00 UTC.
        $this->assertStringContainsString('DTSTART:20260910T070000Z', $ics);
        $this->assertStringContainsString('DTEND:20260910T100000Z', $ics);
        $this->assertStringContainsString('SUMMARY:Rafting Morava – Testovací výlet', $ics);
        $this->assertStringContainsString('LOCATION:Chomoutov', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
    }

    public function test_instructor_summary_has_one_event_per_assigned_day(): void
    {
        $instructor = User::factory()->create();

        // Two future trips, one day each, both assigned to the instructor.
        foreach ([2, 9] as $offset) {
            $date = Carbon::today()->addDays($offset);
            $trip = Trip::create([
                'type' => 'guided', 'name' => 'Výlet ' . $offset,
                'date_from' => $date, 'date_to' => $date, 'status' => 'planned',
            ]);
            $trip->days()->create(['date' => $date, 'order' => 1, 'start_time' => '09:00'])
                ->assignments()->create(['user_id' => $instructor->id, 'type' => 'instructor']);
        }

        $ics = TripIcs::forInstructor($instructor, Carbon::today(), Carbon::today()->addMonths(3));

        $this->assertEquals(1, substr_count($ics, 'BEGIN:VCALENDAR'));
        $this->assertEquals(2, substr_count($ics, 'BEGIN:VEVENT'), 'Jeden .ics se dvěma událostmi');
    }
}
