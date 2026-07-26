<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalPriceTest extends TestCase
{
    use RefreshDatabase;

    private function rentalTrip(): Trip
    {
        return Trip::create([
            'type' => 'rental', 'name' => 'Půjčovna',
            'date_from' => '2026-08-01', 'date_to' => '2026-08-02', 'status' => 'planned',
        ]);
    }

    public function test_price_is_quantity_times_catalog_price_times_days(): void
    {
        $equipment = Equipment::create(['name' => 'Raft', 'price_per_day' => 100]);
        $trip = $this->rentalTrip();
        $trip->equipmentItems()->create(['equipment_id' => $equipment->id, 'quantity' => 2]);

        $trip->recalculateRentalPrice();

        // 100 × 2 ks × 2 dny
        $this->assertEquals(400, (float) $trip->fresh()->total_price);
    }

    public function test_per_item_price_override_is_used(): void
    {
        $equipment = Equipment::create(['name' => 'Raft', 'price_per_day' => 100]);
        $trip = $this->rentalTrip();
        $trip->equipmentItems()->create(['equipment_id' => $equipment->id, 'quantity' => 2, 'price_per_unit' => 150]);

        $trip->recalculateRentalPrice();

        // Override 150 wins over the 100 catalog price: 150 × 2 × 2
        $this->assertEquals(600, (float) $trip->fresh()->total_price);
    }

    public function test_manual_price_is_not_recalculated(): void
    {
        $equipment = Equipment::create(['name' => 'Raft', 'price_per_day' => 100]);
        $trip = $this->rentalTrip();
        $trip->equipmentItems()->create(['equipment_id' => $equipment->id, 'quantity' => 2]);
        $trip->update(['price_manually_set' => true, 'total_price' => 999]);

        $trip->recalculateRentalPrice();

        $this->assertEquals(999, (float) $trip->fresh()->total_price);
    }
}
