<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\Pages\ListTrips;
use App\Models\Location;
use App\Models\Trip;
use App\Models\TripDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TripsListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->userWithRole('Admin'));
    }

    public function test_a_multi_day_trip_has_a_row_per_day_with_its_section(): void
    {
        $trip = $this->trip(1, 3);
        [$day1, $day2, $day3] = $trip->days()->get()->all();
        $day2->update([
            'location_id' => Location::create(['name' => 'Postřelmov'])->id,
            'end_location_id' => Location::create(['name' => 'Vila Háj'])->id,
            'start_time' => '09:30',
        ]);

        Livewire::test(ListTrips::class)
            ->assertCanSeeTableRecords([$day1, $day2, $day3], inOrder: true)
            ->assertTableColumnStateSet('section', 'Postřelmov → Vila Háj', $day2)
            ->assertTableColumnStateSet('time', 'start 09:30', $day2)
            ->assertSee('den 2/3');
    }

    public function test_default_shows_days_from_today_on_soonest_first(): void
    {
        $inProgress = $this->trip(-1, 1)->days()->get();
        $soon = $this->trip(3, 3)->days()->first();

        Livewire::test(ListTrips::class)
            ->assertCanSeeTableRecords([$inProgress[1], $inProgress[2], $soon], inOrder: true)
            ->assertCanNotSeeTableRecords([$inProgress[0]]);
    }

    public function test_date_filter_can_reach_into_history(): void
    {
        $past = $this->trip(-10, -10)->days()->first();
        $future = $this->trip(5, 5)->days()->first();

        Livewire::test(ListTrips::class)
            ->filterTable('date_range', [
                'from' => Carbon::today()->subDays(30)->toDateString(),
                'until' => Carbon::today()->toDateString(),
            ])
            ->assertCanSeeTableRecords([$past])
            ->assertCanNotSeeTableRecords([$future]);
    }

    public function test_deleting_just_one_day_shortens_the_trip(): void
    {
        $trip = $this->trip(1, 3);
        $day3 = $trip->days()->where('order', 3)->first();

        Livewire::test(ListTrips::class)
            ->callTableAction('delete', $day3, data: ['scope' => 'day']);

        $trip->refresh();
        $this->assertSame(2, $trip->days()->count());
        $this->assertSame(Carbon::today()->addDays(2)->toDateString(), $trip->date_to->toDateString());
    }

    public function test_deleting_the_whole_trip_removes_all_its_days(): void
    {
        $trip = $this->trip(1, 3);
        $day2 = $trip->days()->where('order', 2)->first();

        Livewire::test(ListTrips::class)
            ->callTableAction('delete', $day2, data: ['scope' => 'trip']);

        $this->assertModelMissing($trip);
        $this->assertSame(0, TripDay::count());
    }

    public function test_removing_a_middle_day_renumbers_the_rest(): void
    {
        $trip = $this->trip(1, 3);
        $trip->removeDay($trip->days()->where('order', 2)->first());

        $this->assertSame([1, 2], $trip->days()->pluck('order')->all());
        $this->assertSame(Carbon::today()->addDay()->toDateString(), $trip->fresh()->date_from->toDateString());
    }

    private function trip(int $fromOffset, int $toOffset): Trip
    {
        $trip = Trip::create([
            'type' => 'guided', 'name' => 'Výlet '.$fromOffset, 'status' => 'planned',
            'date_from' => Carbon::today()->addDays($fromOffset)->toDateString(),
            'date_to' => Carbon::today()->addDays($toOffset)->toDateString(),
        ]);
        $trip->generateDays();

        return $trip;
    }
}
