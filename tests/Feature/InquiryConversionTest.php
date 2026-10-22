<?php

namespace Tests\Feature;

use App\Actions\ConvertInquiryToTrip;
use App\Models\Inquiry;
use App\Models\Location;
use App\Models\TripProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InquiryConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversion_derives_dates_times_and_boarding_points(): void
    {
        Notification::fake();

        $loc1 = Location::create(['name' => 'Mohelnice']);
        $loc2 = Location::create(['name' => 'Litovel']);

        $program = TripProgram::create([
            'name' => 'Dvoudenní trasa', 'category' => 'domestic',
            'number_of_days' => 2, 'duration_minutes' => 240, 'meeting_offset_minutes' => 50,
            'instructor_reward' => 1800,
        ]);
        $program->programDays()->create(['order' => 1, 'location_id' => $loc1->id]);
        $program->programDays()->create(['order' => 2, 'location_id' => $loc2->id]);

        $inquiry = Inquiry::create([
            'client_name' => 'ZŠ Test', 'client_email' => 'zs@test.cz',
            'program_id' => $program->id, 'status' => 'new', 'source' => 'manual',
        ]);

        $trip = app(ConvertInquiryToTrip::class)->handle($inquiry, [
            'date_from' => '2026-09-10', 'start_time' => '09:00',
            'trip_program_id' => $program->id, 'type' => 'guided',
        ]);

        // End date from number_of_days (2 days → +1).
        $this->assertEquals('2026-09-11', $trip->date_to->toDateString());
        $this->assertEquals(2, $trip->days()->count());

        $day1 = $trip->days()->orderBy('order')->first();
        $day2 = $trip->days()->orderBy('order')->skip(1)->first();

        // Time column format differs across drivers (SQLite HH:MM, MySQL HH:MM:SS) – compare HH:MM.
        $hm = fn ($t) => substr((string) $t, 0, 5);
        $this->assertEquals('09:00', $hm($day1->start_time));
        $this->assertEquals('08:10', $hm($day1->meeting_time), 'Sraz = start − offset (50 min)');
        $this->assertEquals('13:00', $hm($day1->end_time), 'Konec = start + trvání (240 min)');
        $this->assertEquals('09:00', $hm($day2->start_time), 'Další dny 9–15');
        $this->assertEquals('15:00', $hm($day2->end_time));
        $this->assertEquals('08:10', $hm($day2->meeting_time));
        $this->assertEquals($loc1->id, $day1->location_id, 'Den 1 → nástupní místo 1');
        $this->assertEquals($loc2->id, $day2->location_id, 'Den 2 → nástupní místo 2');

        $this->assertEquals('confirmed', $inquiry->fresh()->status);
        $this->assertEquals($trip->id, $inquiry->fresh()->trip_id);
    }
}
