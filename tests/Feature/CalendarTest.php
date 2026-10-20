<?php

namespace Tests\Feature;

use App\Filament\Pages\Calendar;
use App\Models\Meeting;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_sees_only_their_own_trips_and_all_meetings(): void
    {
        $instructor = $this->userWithRole('Instructor');
        $this->assignedTrip('Můj výlet', $instructor);
        $this->assignedTrip('Cizí výlet', $this->userWithRole('Instructor'));
        Meeting::create(['name' => 'Porada instruktorů', 'date' => Carbon::today()->toDateString(), 'time' => '18:00']);

        $this->actingAs($instructor);

        $this->assertTrue(Calendar::canAccess());
        Livewire::test(Calendar::class)
            ->assertSee('Můj výlet')
            ->assertDontSee('Cizí výlet')
            ->assertSee('Porada instruktorů')
            ->assertDontSee('Jen moje výlety');
    }

    public function test_admin_sees_all_trips_and_can_narrow_to_their_own(): void
    {
        $admin = $this->userWithRole('Admin');
        $this->assignedTrip('Můj výlet', $admin);
        $this->assignedTrip('Cizí výlet', $this->userWithRole('Instructor'));

        $this->actingAs($admin);

        Livewire::test(Calendar::class)
            ->assertSee('Můj výlet')
            ->assertSee('Cizí výlet')
            ->set('onlyMine', true)
            ->assertSee('Můj výlet')
            ->assertDontSee('Cizí výlet');
    }

    private function assignedTrip(string $name, User $user): void
    {
        $trip = Trip::create([
            'type' => 'guided', 'name' => $name, 'status' => 'planned',
            'date_from' => Carbon::today()->toDateString(), 'date_to' => Carbon::today()->toDateString(),
        ]);
        $trip->days()->create(['date' => Carbon::today()->toDateString(), 'order' => 1])
            ->assignments()->create(['user_id' => $user->id, 'type' => 'instructor']);
    }
}
