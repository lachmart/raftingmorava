<?php

namespace Tests\Feature;

use App\Filament\Pages\Planning;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TripScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function trip(string $name, string $from, string $to, string $status = 'planned'): Trip
    {
        $trip = Trip::create(['name' => $name, 'type' => 'guided', 'status' => $status,
            'date_from' => $from, 'date_to' => $to]);
        $trip->generateDays();

        return $trip;
    }

    public function test_only_operational_users_may_open_it(): void
    {
        $this->actingAs($this->userWithRole('Instructor'))->get('/trip-schedule')->assertForbidden();
        $this->actingAs($this->userWithRole('Admin'))->get('/trip-schedule')->assertOk();
    }

    public function test_default_range_is_next_monday_to_the_tuesday_after(): void
    {
        Carbon::setTestNow('2026-10-22 10:00'); // Thursday

        $this->actingAs($this->userWithRole('Admin'))->get('/trip-schedule')
            ->assertOk()
            ->assertSee('Rozpis výletů 26. 10. – 3. 11. 2026');
    }

    public function test_lists_days_in_range_grouped_by_date_without_cancelled_trips(): void
    {
        $this->trip('Třídenní Soča', '2026-10-26', '2026-10-28');
        $this->trip('Zrušený výlet', '2026-10-27', '2026-10-27', 'cancelled');
        $this->trip('Mimo rozsah', '2026-11-10', '2026-11-10');

        $this->actingAs($this->userWithRole('Admin'))->get('/trip-schedule?from=2026-10-26&until=2026-11-03')
            ->assertOk()
            ->assertSeeInOrder(['Pondělí 26. 10. 2026', 'den 1/3', 'Úterý 27. 10. 2026', 'den 2/3', 'Středa 28. 10. 2026', 'den 3/3'])
            ->assertDontSee('Zrušený výlet')
            ->assertDontSee('Mimo rozsah')
            ->assertDontSee('Čtvrtek 29. 10. 2026');
    }

    public function test_days_are_ordered_by_meeting_time_with_missing_ones_last(): void
    {
        $late = $this->trip('Pozdní sraz', '2026-10-26', '2026-10-26');
        $late->days()->update(['meeting_time' => '10:30']);
        $early = $this->trip('Brzký sraz', '2026-10-26', '2026-10-26');
        $early->days()->update(['meeting_time' => '08:15']);
        $this->trip('Bez srazu', '2026-10-26', '2026-10-26');

        $this->actingAs($this->userWithRole('Admin'))->get('/trip-schedule?from=2026-10-26&until=2026-10-26')
            ->assertSeeInOrder(['Brzký sraz', 'Pozdní sraz', 'Bez srazu']);
    }

    public function test_too_long_range_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('Admin'))->get('/trip-schedule?from=2026-01-01&until=2026-12-31')
            ->assertStatus(422);
    }

    public function test_planning_action_prefills_the_default_range_and_opens_the_print(): void
    {
        Carbon::setTestNow('2026-10-22 10:00');
        $this->actingAs($this->userWithRole('Admin'));

        Livewire::test(Planning::class)
            ->mountAction('printSchedule')
            ->assertSchemaStateSet(['from' => '2026-10-26', 'until' => '2026-11-03'])
            ->callMountedAction()
            ->assertHasNoFormErrors();
    }
}
