<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Trip;
use App\Support\TripIcs;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
