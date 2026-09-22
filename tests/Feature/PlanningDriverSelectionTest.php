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

class PlanningDriverSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_options_filters_by_is_driver_flag_unless_including_non_drivers(): void
    {
        $driver = User::factory()->create(['is_driver' => true, 'active' => true]);
        $nonDriver = User::factory()->create(['is_driver' => false, 'active' => true]);

        $page = new Planning;

        $driversOnly = $page->driverOptions(false);
        $all = $page->driverOptions(true);

        $this->assertArrayHasKey($driver->id, $driversOnly);
        $this->assertArrayNotHasKey($nonDriver->id, $driversOnly);

        $this->assertArrayHasKey($driver->id, $all);
        $this->assertArrayHasKey($nonDriver->id, $all);
    }

    public function test_assignment_modal_hides_non_drivers_by_default(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'));

        $driver = User::factory()->create(['is_driver' => true, 'active' => true, 'name' => 'Jana Řidičová']);
        $nonDriver = User::factory()->create(['is_driver' => false, 'active' => true, 'name' => 'Petr Neřidič']);

        $day = $this->makeDay();

        $html = Livewire::test(Planning::class)
            ->mountTableAction('assignment', $day)
            ->getMountedActionModalHtml();

        // Everyone active is offered as an instructor, so a name always appears once there.
        // A driver-flagged name should appear a 2nd time in "Řidiči"; a non-flagged one shouldn't.
        $this->assertSame(2, substr_count($html, $driver->name));
        $this->assertSame(1, substr_count($html, $nonDriver->name));
    }

    public function test_assignment_modal_auto_reveals_when_a_non_driver_is_already_assigned_as_driver(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'));

        $alreadyAssignedNonDriver = User::factory()->create([
            'is_driver' => false, 'active' => true, 'name' => 'Petr Neřidič',
        ]);
        $otherNonDriver = User::factory()->create([
            'is_driver' => false, 'active' => true, 'name' => 'Eva Bokem Stojící',
        ]);

        $day = $this->makeDay();
        $day->assignments()->create(['user_id' => $alreadyAssignedNonDriver->id, 'type' => 'driver']);

        $html = Livewire::test(Planning::class)
            ->mountTableAction('assignment', $day)
            ->getMountedActionModalHtml();

        // Both should now appear twice (Instruktoři + Řidiči) – the whole list is revealed,
        // not just the already-assigned person.
        $this->assertSame(2, substr_count($html, $alreadyAssignedNonDriver->name));
        $this->assertSame(2, substr_count($html, $otherNonDriver->name));
    }

    public function test_the_show_non_drivers_button_reveals_them_in_an_already_open_modal(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'));

        $nonDriver = User::factory()->create(['is_driver' => false, 'active' => true, 'name' => 'Petr Neřidič']);

        $day = $this->makeDay();

        $component = Livewire::test(Planning::class)->mountTableAction('assignment', $day);

        $this->assertSame(1, substr_count($component->getMountedActionModalHtml(), $nonDriver->name));
        $component->assertMountedActionModalSee('Zobrazit neřidiče');

        // This is exactly what the "Zobrazit neřidiče" button's action does.
        $component->set('showNonDriversForDay', true);

        $this->assertSame(2, substr_count($component->getMountedActionModalHtml(), $nonDriver->name));
        $component->assertMountedActionModalDontSee('Zobrazit neřidiče');
    }

    private function makeDay(): TripDay
    {
        $trip = Trip::create([
            'type' => 'guided', 'name' => 'Test výlet',
            'date_from' => Carbon::today()->addWeek()->toDateString(),
            'date_to' => Carbon::today()->addWeek()->toDateString(),
            'status' => 'planned',
        ]);

        return $trip->days()->create(['date' => Carbon::today()->addWeek()->toDateString(), 'order' => 1]);
    }
}
