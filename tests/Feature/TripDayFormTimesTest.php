<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\RelationManagers\DaysRelationManager;
use App\Models\TripProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripDayFormTimesTest extends TestCase
{
    use RefreshDatabase;

    private function program(): TripProgram
    {
        return TripProgram::create(['name' => 'R '.uniqid(), 'category' => 'domestic', 'number_of_days' => 1,
            'meeting_offset_minutes' => 30, 'duration_minutes' => 180]);
    }

    public function test_empty_fields_are_filled_from_the_new_start(): void
    {
        $this->assertSame(['meeting_time' => '09:30', 'end_time' => '13:00'],
            DaysRelationManager::recalculateTimes($this->program(), 1, null, '10:00', null, null));
    }

    public function test_computed_values_follow_the_start_but_manual_ones_stay(): void
    {
        // Sraz 09:30 was computed from 10:00 → moves; konec 14:15 was typed by hand → stays.
        $this->assertSame(['meeting_time' => '10:30', 'end_time' => '14:15'],
            DaysRelationManager::recalculateTimes($this->program(), 1, '10:00', '11:00', '09:30', '14:15'));
    }

    public function test_later_days_shift_the_nine_to_fifteen_end(): void
    {
        $this->assertSame(['meeting_time' => '09:30', 'end_time' => '16:00'],
            DaysRelationManager::recalculateTimes($this->program(), 2, '09:00', '10:00', '08:30:00', '15:00:00'));
    }
}
