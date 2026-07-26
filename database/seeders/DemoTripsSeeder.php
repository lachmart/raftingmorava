<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\Location;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayAssignment;
use App\Models\TripProgram;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sample trips of both types (guided + rental, single- and multi-day) for testing.
 * Instructors are real (see InstructorSeeder); a vehicle is assigned per day, the driver as a separate row.
 */
class DemoTripsSeeder extends Seeder
{
    public function run(): void
    {
        $instructors = User::role('Instructor')->orderBy('id')->get();
        if ($instructors->isEmpty()) {
            return;
        }
        $drivers = User::where('is_driver', true)->orderBy('id')->get();
        $vehicle = Vehicle::first();
        $location = Location::first();

        // --- Single-day "guided" trip (tomorrow) ---
        $program1 = TripProgram::where('category', 'domestic')->first();
        $trip1 = Trip::updateOrCreate(
            ['name' => 'UKÁZKA – Jednodenní rafting (demo)'],
            [
                'trip_program_id' => $program1?->id,
                'type' => 'guided',
                'client_name' => 'ZŠ Ukázková',
                'client_email' => 'skola@example.cz',
                'client_phone' => '+420 123 456 789',
                'date_from' => Carbon::tomorrow(),
                'date_to' => Carbon::tomorrow(),
                'planned_paying_count' => 24,
                'planned_escort_count' => 2,
                'children_ages' => '12–14 let',
                'status' => 'planned',
            ],
        );
        $day1 = TripDay::updateOrCreate(
            ['trip_id' => $trip1->id, 'order' => 1],
            ['date' => Carbon::tomorrow(), 'location_id' => $location?->id,
                'meeting_time' => '08:00', 'start_time' => '09:00', 'end_time' => '16:00'],
        );
        $day1->vehicles()->sync($vehicle ? [$vehicle->id] : []);
        $this->assign($day1, $instructors->take(2), $drivers->first());

        // --- Multi-day "guided" trip (3 days) ---
        $program2 = TripProgram::where('category', 'school')->skip(1)->first() ?? TripProgram::where('category', 'school')->first();
        $from = Carbon::today()->addDays(7);
        $trip2 = Trip::updateOrCreate(
            ['name' => 'UKÁZKA – Vícedenní školní výlet (demo)'],
            [
                'trip_program_id' => $program2?->id,
                'type' => 'guided',
                'client_name' => 'Gymnázium Ukázkové',
                'client_email' => 'gymnazium@example.cz',
                'client_phone' => '+420 987 654 321',
                'date_from' => $from,
                'date_to' => (clone $from)->addDays(2),
                'planned_paying_count' => 38,
                'planned_escort_count' => 2,
                'status' => 'planned',
            ],
        );
        for ($d = 0; $d < 3; $d++) {
            $day = TripDay::updateOrCreate(
                ['trip_id' => $trip2->id, 'order' => $d + 1],
                ['date' => (clone $from)->addDays($d), 'location_id' => $location?->id,
                    'meeting_time' => '08:30', 'start_time' => '09:30', 'end_time' => '17:00'],
            );
            $day->vehicles()->sync($vehicle ? [$vehicle->id] : []);
            $this->assign($day, $instructors->skip($d)->take(2), $drivers->skip($d)->first());
        }

        // --- Rental (price computed from equipment × days) ---
        $trip3 = Trip::updateOrCreate(
            ['name' => 'UKÁZKA – Půjčovna vybavení (demo)'],
            [
                'type' => 'rental',
                'client_name' => 'Jan Vodák',
                'client_email' => 'vodak@example.cz',
                'client_phone' => '+420 555 111 222',
                'date_from' => Carbon::today()->addDays(3),
                'date_to' => Carbon::today()->addDays(4),
                'status' => 'planned',
            ],
        );
        TripDay::updateOrCreate(['trip_id' => $trip3->id, 'order' => 1],
            ['date' => Carbon::today()->addDays(3), 'location_id' => $location?->id]);
        // Equipment + price recalculation (2 days).
        if ($raft = Equipment::where('name', 'like', '%COLORADO%')->first()) {
            $trip3->equipmentItems()->updateOrCreate(['equipment_id' => $raft->id], ['quantity' => 1]);
        }
        if ($vest = Equipment::where('name', 'Vesta')->first()) {
            $trip3->equipmentItems()->updateOrCreate(['equipment_id' => $vest->id], ['quantity' => 6]);
        }
        $trip3->refresh()->recalculateRentalPrice();
    }

    private function assign(TripDay $day, $instructors, ?User $driver): void
    {
        foreach ($instructors as $ins) {
            TripDayAssignment::updateOrCreate(
                ['trip_day_id' => $day->id, 'user_id' => $ins->id, 'type' => 'instructor'],
                [],
            );
        }
        // Driver as a separate row (reward 0).
        if ($driver) {
            TripDayAssignment::updateOrCreate(
                ['trip_day_id' => $day->id, 'user_id' => $driver->id, 'type' => 'driver'],
                [],
            );
        }
    }
}
