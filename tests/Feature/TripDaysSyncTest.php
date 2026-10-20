<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\Pages\EditTrip;
use App\Models\Location;
use App\Models\Trip;
use App\Models\TripProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TripDaysSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixing_a_year_typo_removes_the_extra_days(): void
    {
        // The reported case: 16.7.2026 – 16.7.2027 generated 366 days.
        $trip = $this->trip('2026-07-16', '2027-07-16');
        $trip->generateDays();
        $this->assertSame(366, $trip->days()->count());

        $trip->update(['date_from' => '2027-07-16']);
        $trip->syncDays();

        $this->assertSame(['2027-07-16'], $trip->days()->get()->map(fn ($d) => $d->date->toDateString())->all());
    }

    public function test_moving_the_trip_shifts_days_and_keeps_their_details(): void
    {
        $user = $this->userWithRole('Instructor');
        $trip = $this->trip('2026-08-01', '2026-08-02');
        $trip->generateDays();
        $day2 = $trip->days()->where('order', 2)->first();
        $day2->update(['start_time' => '09:30']);
        $day2->assignments()->create(['user_id' => $user->id, 'type' => 'instructor']);

        $trip->update(['date_from' => '2026-08-10', 'date_to' => '2026-08-11']);
        $removedWithPeople = $trip->syncDays();

        $day2->refresh();
        $this->assertSame(0, $removedWithPeople);
        $this->assertSame('2026-08-11', $day2->date->toDateString());
        $this->assertSame('09:30', substr((string) $day2->start_time, 0, 5));
        $this->assertSame(1, $day2->assignments()->count());
        $this->assertSame(2, $trip->days()->count());
    }

    public function test_lengthening_adds_days_from_the_program_template_and_shortening_reports_staffed_days(): void
    {
        $start = Location::create(['name' => 'Postřelmov']);
        $end = Location::create(['name' => 'Vila Háj']);
        $program = TripProgram::create(['name' => 'Svišti', 'price_per_person' => 0, 'number_of_days' => 2]);
        $program->programDays()->create(['order' => 2, 'location_id' => $start->id, 'end_location_id' => $end->id]);

        $trip = $this->trip('2026-08-01', '2026-08-01', ['trip_program_id' => $program->id]);
        $trip->generateDays();

        $trip->update(['date_to' => '2026-08-02']);
        $trip->syncDays();
        $day2 = $trip->days()->where('order', 2)->first();
        $this->assertSame('Postřelmov → Vila Háj', $day2->sectionLabel());

        $day2->assignments()->create(['user_id' => $this->userWithRole('Instructor')->id, 'type' => 'instructor']);
        $trip->update(['date_to' => '2026-08-01']);

        $this->assertSame(1, $trip->syncDays());
        $this->assertSame(1, $trip->days()->count());
    }

    public function test_editing_dates_in_the_form_syncs_the_days(): void
    {
        $this->actingAs($this->userWithRole('Admin'));
        $trip = $this->trip('2026-07-16', '2026-07-18');
        $trip->generateDays();

        Livewire::test(EditTrip::class, ['record' => $trip->getRouteKey()])
            ->fillForm(['date_from' => '2026-07-20', 'date_to' => '2026-07-21'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['2026-07-20', '2026-07-21'], $trip->days()->get()->map(fn ($d) => $d->date->toDateString())->all());
    }

    public function test_form_rejects_a_range_longer_than_the_limit_or_reversed(): void
    {
        $this->actingAs($this->userWithRole('Admin'));
        $trip = $this->trip('2026-07-16', '2026-07-16');

        Livewire::test(EditTrip::class, ['record' => $trip->getRouteKey()])
            ->fillForm(['date_from' => '2026-07-16', 'date_to' => '2027-07-16'])
            ->call('save')
            ->assertHasFormErrors(['date_to']);

        Livewire::test(EditTrip::class, ['record' => $trip->getRouteKey()])
            ->fillForm(['date_from' => '2026-07-16', 'date_to' => '2026-07-15'])
            ->call('save')
            ->assertHasFormErrors(['date_to']);
    }

    private function trip(string $from, string $to, array $extra = []): Trip
    {
        return Trip::create(array_merge([
            'type' => 'guided', 'name' => 'Výlet', 'status' => 'planned',
            'date_from' => $from, 'date_to' => $to,
        ], $extra));
    }
}
