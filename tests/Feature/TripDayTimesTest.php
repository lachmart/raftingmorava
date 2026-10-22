<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\TripProgram;
use App\Support\TripDayDrafts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripDayTimesTest extends TestCase
{
    use RefreshDatabase;

    private function program(array $attrs = []): TripProgram
    {
        return TripProgram::create(array_merge([
            'name' => 'Rafting '.uniqid(), 'category' => 'domestic', 'number_of_days' => 1,
            'meeting_offset_minutes' => 30, 'duration_minutes' => 180,
        ], $attrs));
    }

    public function test_first_day_derives_meeting_and_end_from_start(): void
    {
        $this->assertSame(
            ['meeting_time' => '09:30', 'start_time' => '10:00', 'end_time' => '13:00'],
            TripProgram::dayTimes($this->program(), 1, '10:00'),
        );
    }

    public function test_first_day_without_start_or_duration(): void
    {
        $this->assertSame(['meeting_time' => null, 'start_time' => null, 'end_time' => null],
            TripProgram::dayTimes($this->program(), 1, null));
        $this->assertSame(['meeting_time' => '09:30', 'start_time' => '10:00', 'end_time' => null],
            TripProgram::dayTimes($this->program(['duration_minutes' => null]), 1, '10:00'));
    }

    public function test_without_program_the_default_offset_applies(): void
    {
        $this->assertSame(['meeting_time' => '09:10', 'start_time' => '10:00', 'end_time' => null],
            TripProgram::dayTimes(null, 1, '10:00:00'));
    }

    public function test_next_days_default_to_nine_to_fifteen(): void
    {
        $this->assertSame(['meeting_time' => '08:30', 'start_time' => '09:00', 'end_time' => '15:00'],
            TripProgram::dayTimes($this->program(), 2, '10:00'));
        $this->assertSame(['meeting_time' => '09:30', 'end_time' => '16:00'],
            TripProgram::meetingAndEndFor($this->program(), 3, '10:00'));
    }

    public function test_drafts_follow_the_program_template_and_dates(): void
    {
        $a = Location::create(['name' => 'Postřelmov']);
        $b = Location::create(['name' => 'Vila Háj']);
        $program = $this->program(['number_of_days' => 2]);
        $program->programDays()->create(['order' => 1, 'location_id' => $a->id, 'end_location_id' => $b->id]);
        $program->programDays()->create(['order' => 2, 'location_id' => $b->id]);

        $drafts = TripDayDrafts::build($program, '2026-08-01', '2026-08-02', '10:00');

        $this->assertSame(['day-1', 'day-2'], array_keys($drafts));
        $this->assertSame(['date' => '2026-08-01', 'order' => 1, 'location_id' => $a->id, 'end_location_id' => $b->id,
            'meeting_time' => '09:30', 'start_time' => '10:00', 'end_time' => '13:00'], $drafts['day-1']);
        $this->assertSame('2026-08-02', $drafts['day-2']['date']);
        $this->assertSame($b->id, $drafts['day-2']['location_id']);
        $this->assertNull($drafts['day-2']['end_location_id']);
        $this->assertSame('09:00', $drafts['day-2']['start_time']);
    }

    public function test_drafts_cap_the_length_and_tolerate_reversed_dates(): void
    {
        $this->assertCount(1, TripDayDrafts::build(null, '2026-08-05', '2026-08-01', null));
        $this->assertCount(31, TripDayDrafts::build(null, '2026-08-01', '2027-08-01', null));
    }
}
