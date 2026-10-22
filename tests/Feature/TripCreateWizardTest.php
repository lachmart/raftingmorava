<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\Pages\CreateTrip;
use App\Models\Equipment;
use App\Models\Location;
use App\Models\Trip;
use App\Models\TripProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TripCreateWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->userWithRole('Admin'));
    }

    private function program(int $days = 1): TripProgram
    {
        $program = TripProgram::create([
            'name' => 'Rafting Morava', 'category' => 'domestic', 'number_of_days' => $days,
            'meeting_offset_minutes' => 30, 'duration_minutes' => 180, 'instructor_reward' => 900,
        ]);
        $program->programDays()->create([
            'order' => 1,
            'location_id' => Location::create(['name' => 'Postřelmov'])->id,
            'end_location_id' => Location::create(['name' => 'Vila Háj'])->id,
        ]);

        return $program;
    }

    private function hm($t): ?string
    {
        return $t === null ? null : substr((string) $t, 0, 5);
    }

    public function test_one_day_trip_is_created_with_prefilled_day(): void
    {
        $program = $this->program();

        Livewire::test(CreateTrip::class)
            ->fillForm(['type' => 'guided', 'trip_program_id' => $program->id, 'date_from' => '2026-08-01', 'first_day_start' => '10:00'])
            ->assertSchemaStateSet(['date_to' => '2026-08-01', 'instructor_reward' => 900])
            ->goToWizardStep(2)
            ->fillForm(['client_name' => 'ZŠ Test', 'planned_paying_count' => 25])
            ->goToWizardStep(3)
            ->assertNotNotified()
            ->assertHasNoFormErrors()
            ->call('create')
            ->assertHasNoFormErrors();

        $trip = Trip::sole();
        $this->assertSame('Rafting Morava, ZŠ Test', $trip->name);
        $this->assertSame(25, (int) $trip->planned_paying_count);
        $day = $trip->days()->sole();
        $this->assertSame('2026-08-01', $day->date->toDateString());
        $this->assertSame(['09:30', '10:00', '13:00'], [$this->hm($day->meeting_time), $this->hm($day->start_time), $this->hm($day->end_time)]);
        $this->assertSame('Postřelmov → Vila Háj', $day->sectionLabel());
    }

    public function test_multi_day_trip_gets_nine_to_fifteen_on_later_days_and_keeps_edits(): void
    {
        $program = $this->program(3);

        $page = Livewire::test(CreateTrip::class)
            ->fillForm(['type' => 'guided', 'trip_program_id' => $program->id, 'date_from' => '2026-08-01', 'first_day_start' => '10:00'])
            ->goToWizardStep(2)
            ->fillForm(['client_name' => 'Gymnázium'])
            ->goToWizardStep(3);

        $page->set('data.days_draft.day-3.start_time', '11:00')
            ->call('create')
            ->assertHasNoFormErrors();

        $days = Trip::sole()->days()->orderBy('order')->get();
        $this->assertCount(3, $days);
        $this->assertSame(['08:30', '09:00', '15:00'], [$this->hm($days[1]->meeting_time), $this->hm($days[1]->start_time), $this->hm($days[1]->end_time)]);
        $this->assertSame('11:00', $this->hm($days[2]->start_time));
    }

    public function test_changing_the_dates_after_reaching_the_days_step_rebuilds_them(): void
    {
        $program = $this->program(2);

        $page = Livewire::test(CreateTrip::class)
            ->fillForm(['type' => 'guided', 'trip_program_id' => $program->id, 'date_from' => '2026-08-01', 'first_day_start' => '10:00'])
            ->goToWizardStep(2)
            ->goToWizardStep(3);
        $this->assertCount(2, $page->get('data.days_draft'));

        $page->set('data.days_draft.day-1.start_time', '07:00')
            ->fillForm(['date_to' => '2026-08-03'])
            ->goToWizardStep(2)
            ->goToWizardStep(3)
            ->assertNotified();

        $this->assertCount(3, $page->get('data.days_draft'));
        $this->assertSame('10:00', $page->get('data.days_draft.day-1.start_time'));
    }

    public function test_rental_trip_saves_equipment_and_price(): void
    {
        $kayak = Equipment::create(['name' => 'Kajak', 'price_per_day' => 400]);

        Livewire::test(CreateTrip::class)
            ->fillForm([
                'type' => 'rental', 'date_from' => '2026-08-01', 'date_to' => '2026-08-02',
                'equipmentItems' => [['equipment_id' => $kayak->id, 'quantity' => 2, 'price_per_unit' => null]],
            ])
            ->goToWizardStep(2)
            ->fillForm(['client_name' => 'Jan Vodák'])
            ->goToWizardStep(3)
            ->call('create')
            ->assertHasNoFormErrors();

        $trip = Trip::sole();
        $this->assertSame(2, $trip->days()->count());
        $this->assertEquals(1600, $trip->total_price);
    }
}
