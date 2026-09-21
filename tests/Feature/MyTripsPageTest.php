<?php

namespace Tests\Feature;

use App\Filament\Pages\MyTrips;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class MyTripsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_past_assignments_returns_only_the_instructors_own_past_trips_newest_first(): void
    {
        $instructor = $this->userWithRole('Instructor');
        $otherInstructor = $this->userWithRole('Instructor');

        $older = $this->assignDay($instructor, Carbon::today()->subDays(10));
        $newer = $this->assignDay($instructor, Carbon::today()->subDay());
        $futureOwn = $this->assignDay($instructor, Carbon::today()->addDay());
        $othersPast = $this->assignDay($otherInstructor, Carbon::today()->subDays(5));

        $this->actingAs($instructor);

        $result = (new MyTrips)->pastAssignments();

        $this->assertSame([$newer->id, $older->id], $result->pluck('id')->all());
    }

    public function test_upcoming_assignments_excludes_past_but_includes_today(): void
    {
        $instructor = $this->userWithRole('Instructor');
        $otherInstructor = $this->userWithRole('Instructor');

        $yesterday = $this->assignDay($instructor, Carbon::today()->subDay());
        $today = $this->assignDay($instructor, Carbon::today());
        $tomorrow = $this->assignDay($instructor, Carbon::today()->addDay());
        $othersFuture = $this->assignDay($otherInstructor, Carbon::today()->addDay());

        $this->actingAs($instructor);

        $result = (new MyTrips)->upcomingAssignments();

        $this->assertSame([$today->id, $tomorrow->id], $result->pluck('id')->all());
    }

    public function test_page_renders_both_tabs_and_shows_the_correct_empty_state(): void
    {
        $instructor = $this->userWithRole('Instructor');
        $this->actingAs($instructor);

        $this->get('/my-trips')
            ->assertOk()
            ->assertSee('Nadcházející')
            ->assertSee('Proběhlé')
            ->assertSee('Nemáš naplánované žádné nadcházející výlety.');

        $this->assignDay($instructor, Carbon::today()->subDay());

        Livewire::test(MyTrips::class)
            ->set('tab', 'past')
            ->assertDontSee('Zatím žádné proběhlé výlety.')
            ->assertSee('Test výlet');
    }

    private function assignDay(User $instructor, Carbon $date)
    {
        $trip = Trip::create([
            'type' => 'guided',
            'name' => 'Test výlet '.$date->toDateString(),
            'date_from' => $date->toDateString(),
            'date_to' => $date->toDateString(),
            'status' => 'planned',
        ]);

        $day = $trip->days()->create(['date' => $date->toDateString(), 'order' => 1]);

        return $day->assignments()->create(['user_id' => $instructor->id, 'type' => 'instructor']);
    }
}
