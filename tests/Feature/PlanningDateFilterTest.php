<?php

namespace Tests\Feature;

use App\Filament\Pages\Planning;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PlanningDateFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->userWithRole('Superadmin'));
    }

    public function test_default_range_is_today_plus_14_days(): void
    {
        $yesterday = $this->makeDay(-1);
        $in10 = $this->makeDay(10);
        $in20 = $this->makeDay(20);

        Livewire::test(Planning::class)
            ->assertCanSeeTableRecords([$in10])
            ->assertCanNotSeeTableRecords([$yesterday, $in20]);
    }

    public function test_from_in_the_past_shows_past_days(): void
    {
        $yesterday = $this->makeDay(-1);

        Livewire::test(Planning::class)
            ->filterTable('date_range', [
                'from' => Carbon::today()->subDays(7)->toDateString(),
                'until' => Carbon::today()->addDays(14)->toDateString(),
            ])
            ->assertCanSeeTableRecords([$yesterday]);
    }

    public function test_until_further_out_shows_later_days(): void
    {
        $in20 = $this->makeDay(20);

        Livewire::test(Planning::class)
            ->filterTable('date_range', [
                'from' => Carbon::today()->toDateString(),
                'until' => Carbon::today()->addDays(30)->toDateString(),
            ])
            ->assertCanSeeTableRecords([$in20]);
    }

    public function test_cancelled_trips_are_hidden_in_any_range(): void
    {
        $cancelled = $this->makeDay(5, 'cancelled');

        Livewire::test(Planning::class)
            ->filterTable('date_range', [
                'from' => Carbon::today()->subYear()->toDateString(),
                'until' => Carbon::today()->addYear()->toDateString(),
            ])
            ->assertCanNotSeeTableRecords([$cancelled]);
    }

    public function test_unassigned_deep_link_shows_six_weeks_of_unassigned_days(): void
    {
        $unassignedIn20 = $this->makeDay(20);
        $assignedIn20 = $this->makeDay(20);
        $assignedIn20->assignments()->create([
            'user_id' => User::factory()->create()->id,
            'type' => 'instructor',
        ]);

        // Mirrors StatsOverview's "Nepřiřazené" link.
        $url = \App\Filament\Widgets\StatsOverview::unassignedPlanningUrl();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        Livewire::withQueryParams($query)
            ->test(Planning::class)
            ->assertCanSeeTableRecords([$unassignedIn20])
            ->assertCanNotSeeTableRecords([$assignedIn20]);
    }

    private function makeDay(int $offsetDays, string $status = 'planned'): TripDay
    {
        $date = Carbon::today()->addDays($offsetDays)->toDateString();

        $trip = Trip::create([
            'type' => 'guided', 'name' => 'Výlet '.$offsetDays,
            'date_from' => $date, 'date_to' => $date,
            'status' => $status,
        ]);

        return $trip->days()->create(['date' => $date, 'order' => 1]);
    }
}
