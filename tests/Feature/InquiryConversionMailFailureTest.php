<?php

namespace Tests\Feature;

use App\Actions\ConvertInquiryToTrip;
use App\Models\Inquiry;
use App\Models\Trip;
use App\Models\TripProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * A dead SMTP server must not undo a completed inquiry -> trip conversion.
 */
class InquiryConversionMailFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_survives_a_failing_smtp_server(): void
    {
        // Mimic production: QUEUE_CONNECTION=sync + unreachable SMTP host.
        Mail::shouldReceive('mailer')->andThrow(
            new TransportException('Connection could not be established with host "wes1-smtp.wedos.net:587"')
        );

        $program = TripProgram::create([
            'name' => 'Jednodenní trasa', 'category' => 'domestic',
            'number_of_days' => 1, 'duration_minutes' => 240, 'meeting_offset_minutes' => 30,
            'instructor_reward' => 900,
        ]);

        $inquiry = Inquiry::create([
            'client_name' => 'ZŠ Test', 'client_email' => 'zs@test.cz',
            'program_id' => $program->id, 'status' => 'new', 'source' => 'manual',
        ]);

        try {
            app(ConvertInquiryToTrip::class)->handle($inquiry, [
                'date_from' => '2026-09-10', 'start_time' => '09:00',
                'trip_program_id' => $program->id, 'type' => 'guided',
            ]);
        } catch (\Throwable $e) {
            $this->fail('Conversion threw because the e-mail failed: ' . $e->getMessage());
        }

        $this->assertSame(1, Trip::count(), 'Trip was rolled back by the mail failure.');
        $this->assertSame('confirmed', $inquiry->fresh()->status);
    }
}
