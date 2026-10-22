<?php

namespace Tests\Feature;

use App\Filament\Resources\Locations\LocationResource;
use App\Filament\Resources\Locations\Pages\CreateLocation;
use App\Filament\Resources\Locations\Pages\EditLocation;
use App\Filament\Resources\Trips\Pages\EditTrip;
use App\Filament\Resources\Trips\TripResource;
use App\Models\Location;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Saving a record (create or edit) returns to the resource's list, for every resource.
class SaveRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->userWithRole('Admin'));
    }

    public function test_editing_a_trip_returns_to_the_list(): void
    {
        $trip = Trip::create(['name' => 'Výlet', 'type' => 'guided', 'status' => 'planned',
            'date_from' => '2026-08-01', 'date_to' => '2026-08-01']);

        Livewire::test(EditTrip::class, ['record' => $trip->getRouteKey()])
            ->fillForm(['name' => 'Výlet upravený'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(TripResource::getUrl('index'));
    }

    public function test_creating_and_editing_a_location_returns_to_the_list(): void
    {
        $this->actingAs($this->userWithRole('Superadmin')); // the location catalog is Superadmin-only

        Livewire::test(CreateLocation::class)
            ->fillForm(['name' => 'Litovel'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(LocationResource::getUrl('index'));

        Livewire::test(EditLocation::class, ['record' => Location::sole()->getRouteKey()])
            ->fillForm(['name' => 'Litovel – loděnice'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(LocationResource::getUrl('index'));
    }
}
