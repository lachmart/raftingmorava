<?php

namespace Tests\Feature;

use App\Filament\Pages\Payments;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function pastTripWithUnpaidReward(User $instructor, float $reward = 500): Trip
    {
        $trip = Trip::create([
            'type' => 'guided', 'name' => 'Proběhlý', 'instructor_reward' => $reward,
            'date_from' => Carbon::today()->subDays(7), 'date_to' => Carbon::today()->subDays(7),
            'status' => 'completed',
        ]);
        $day = $trip->days()->create(['date' => Carbon::today()->subDays(7), 'order' => 1]);
        $day->assignments()->create(['user_id' => $instructor->id, 'type' => 'instructor']);

        return $trip;
    }

    public function test_owed_lists_only_unpaid_past_rewards(): void
    {
        $admin = $this->userWithRole('Admin');
        $instructor = User::factory()->create();
        $this->pastTripWithUnpaidReward($instructor);

        $this->actingAs($admin);
        $owed = (new Payments())->owedUsers();

        $this->assertCount(1, $owed);
        $this->assertEquals($instructor->id, $owed->first()->id);
        $this->assertEquals(500, (float) $owed->first()->owed_total);
        $this->assertEquals(1, (int) $owed->first()->unpaid_days_count);
    }

    public function test_future_and_paid_rewards_are_not_owed(): void
    {
        $admin = $this->userWithRole('Admin');
        $instructor = User::factory()->create();

        // Future trip (not past) → not owed.
        $future = Trip::create([
            'type' => 'guided', 'name' => 'Budoucí', 'instructor_reward' => 500,
            'date_from' => Carbon::today()->addDays(7), 'date_to' => Carbon::today()->addDays(7), 'status' => 'planned',
        ]);
        $future->days()->create(['date' => Carbon::today()->addDays(7), 'order' => 1])
            ->assignments()->create(['user_id' => $instructor->id, 'type' => 'instructor']);

        $this->actingAs($admin);
        $this->assertCount(0, (new Payments())->owedUsers());
    }

    public function test_pay_all_marks_rewards_paid(): void
    {
        $admin = $this->userWithRole('Admin');
        $instructor = User::factory()->create();
        $this->pastTripWithUnpaidReward($instructor);

        $this->actingAs($admin);
        $page = new Payments();
        $page->payAll($instructor->id);

        $this->assertCount(0, (new Payments())->owedUsers());
        $this->assertDatabaseHas('trip_day_assignments', [
            'user_id' => $instructor->id, 'paid' => true,
        ]);
    }
}
