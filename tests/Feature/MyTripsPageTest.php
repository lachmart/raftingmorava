<?php

namespace Tests\Feature;

use App\Filament\Pages\MyTrips;
use App\Models\Trip;
use App\Models\TripNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

    public function test_add_note_action_creates_a_note_scoped_to_the_given_day(): void
    {
        $instructor = $this->userWithRole('Instructor');
        $assignment = $this->assignDay($instructor, Carbon::today());
        $day = $assignment->tripDay;

        $this->actingAs($instructor);

        Livewire::test(MyTrips::class)
            ->callAction('addNote', data: ['text' => 'Vezmi náhradní pumpu'], arguments: ['dayId' => $day->id]);

        $this->assertDatabaseHas('trip_notes', [
            'trip_id' => $day->trip_id,
            'trip_day_id' => $day->id,
            'text' => 'Vezmi náhradní pumpu',
            'author_id' => $instructor->id,
        ]);
    }

    public function test_add_note_action_rejects_a_day_the_user_is_not_assigned_to(): void
    {
        $instructor = $this->userWithRole('Instructor');
        $otherInstructor = $this->userWithRole('Instructor');
        $othersDay = $this->assignDay($otherInstructor, Carbon::today())->tripDay;

        $this->actingAs($instructor);

        $this->expectException(HttpException::class);
        $this->withoutExceptionHandling();

        Livewire::test(MyTrips::class)
            ->callAction('addNote', data: ['text' => 'Cizí den'], arguments: ['dayId' => $othersDay->id]);
    }

    public function test_card_shows_notes_from_the_whole_trip_and_an_add_note_button(): void
    {
        $instructor = $this->userWithRole('Instructor');

        $trip = Trip::create([
            'type' => 'guided', 'name' => 'Vícedenní výlet',
            'date_from' => Carbon::today()->toDateString(), 'date_to' => Carbon::today()->addDay()->toDateString(),
            'status' => 'planned',
        ]);
        $day1 = $trip->days()->create(['date' => Carbon::today()->toDateString(), 'order' => 1]);
        $day2 = $trip->days()->create(['date' => Carbon::today()->addDay()->toDateString(), 'order' => 2]);
        $day1->assignments()->create(['user_id' => $instructor->id, 'type' => 'instructor']);
        $day2->assignments()->create(['user_id' => $instructor->id, 'type' => 'instructor']);

        TripNote::create(['trip_id' => $trip->id, 'trip_day_id' => $day1->id, 'text' => 'Poznámka k prvnímu dni']);

        $this->actingAs($instructor);

        $this->get('/my-trips')
            ->assertOk()
            ->assertSee('Poznámka k prvnímu dni')
            ->assertSeeInOrder(["mountAction('addNote', { dayId: {$day1->id} }", "mountAction('addNote', { dayId: {$day2->id} }"], false);
    }

    public function test_card_shows_client_details_and_who_else_goes(): void
    {
        $instructor = $this->userWithRole('Instructor');
        $colleague = $this->userWithRole('Instructor');
        $colleague->update(['name' => 'Kolega Novák']);

        $assignment = $this->assignDay($instructor, Carbon::today());
        $assignment->tripDay->trip->update([
            'client_name' => 'ZŠ Olomouc', 'planned_paying_count' => 25, 'planned_escort_count' => 2,
            'children_ages' => '12–14 let', 'onsite_phone' => '777 123 456',
        ]);
        $assignment->tripDay->update(['meeting_time' => '08:10', 'start_time' => '09:00']);
        $assignment->tripDay->assignments()->create(['user_id' => $colleague->id, 'type' => 'driver']);

        $this->actingAs($instructor);

        $this->get('/my-trips')
            ->assertOk()
            ->assertSee('ZŠ Olomouc')
            ->assertSee('25 platících')
            ->assertSee('2 doprovod')
            ->assertSee('12–14 let')
            ->assertSee('777 123 456')
            ->assertSee('sraz 08:10')
            ->assertSee('start 09:00')
            ->assertSee('Kolega Novák (řidič)');
    }

    public function test_superadmin_sees_their_own_trips_on_the_page(): void
    {
        $superadmin = $this->userWithRole('Superadmin');
        $this->assignDay($superadmin, Carbon::today());

        $this->actingAs($superadmin);

        $this->get('/my-trips')
            ->assertOk()
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
