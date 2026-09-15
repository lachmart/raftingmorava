<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Trip;
use App\Models\TripProgram;
use App\Notifications\CustomerInquiryConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A one-day trip must not print the same date twice ("11.09.2026 – 11.09.2026").
 */
class ConfirmationDateRangeTest extends TestCase
{
    use RefreshDatabase;

    private function bodyFor(string $from, string $to): string
    {
        $program = TripProgram::create([
            'name' => '1 denní Nebe a Voda', 'category' => 'domestic',
            'number_of_days' => 1, 'duration_minutes' => 240, 'meeting_offset_minutes' => 30,
            'instructor_reward' => 900,
        ]);

        $trip = Trip::create([
            'name' => '1 denní Nebe a Voda',
            'client_name' => 'ZŠ Test',
            'trip_program_id' => $program->id,
            'date_from' => $from,
            'date_to' => $to,
            'type' => 'guided',
            'status' => 'planned',
        ]);

        $notification = new CustomerInquiryConfirmedNotification($trip);

        return $notification->toMail(new \App\Models\User)->viewData['body'];
    }

    public function test_one_day_trip_prints_the_date_once(): void
    {
        $this->seed(\Database\Seeders\EmailTemplateSeeder::class);

        $body = $this->bodyFor('2026-09-11', '2026-09-11');

        $this->assertStringContainsString('11.09.2026', $body);
        $this->assertSame(1, substr_count($body, '11.09.2026'),
            'One-day trip printed the date twice: ' . $body);
        $this->assertStringNotContainsString('11.09.2026 – 11.09.2026', $body);
    }

    public function test_multi_day_trip_still_prints_a_range(): void
    {
        $this->seed(\Database\Seeders\EmailTemplateSeeder::class);

        $body = $this->bodyFor('2026-09-11', '2026-09-13');

        $this->assertStringContainsString('11.09.2026 – 13.09.2026', $body);
    }
}
