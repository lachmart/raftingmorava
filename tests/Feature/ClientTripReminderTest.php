<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Location;
use App\Models\Trip;
use App\Notifications\ClientTripReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientTripReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reminder_only_for_eligible_trips_and_marks_them_sent(): void
    {
        Notification::fake();

        $dueGuided = $this->makeTrip('guided', Carbon::today()->addDays(14), 'guided@test.cz');
        $dueRental = $this->makeTrip('rental', Carbon::today()->addDays(5), 'rental@test.cz');
        $tooFar = $this->makeTrip('guided', Carbon::today()->addDays(15), 'far@test.cz');
        $noEmail = $this->makeTrip('guided', Carbon::today()->addDays(10), null);
        $cancelled = $this->makeTrip('guided', Carbon::today()->addDays(10), 'cancelled@test.cz', 'cancelled');
        $alreadySent = $this->makeTrip('guided', Carbon::today()->addDays(3), 'already@test.cz');
        $alreadySent->update(['reminder_sent_at' => now()]);

        $this->artisan('clients:trip-reminder')->assertSuccessful();

        Notification::assertSentOnDemandTimes(ClientTripReminderNotification::class, 2);
        Notification::assertSentOnDemand(
            ClientTripReminderNotification::class,
            fn ($notification, $channels, $notifiable) => ($notifiable->routes['mail'] ?? null) === 'guided@test.cz'
        );
        Notification::assertSentOnDemand(
            ClientTripReminderNotification::class,
            fn ($notification, $channels, $notifiable) => ($notifiable->routes['mail'] ?? null) === 'rental@test.cz'
        );

        $this->assertNotNull($dueGuided->fresh()->reminder_sent_at);
        $this->assertNotNull($dueRental->fresh()->reminder_sent_at);
        $this->assertNull($tooFar->fresh()->reminder_sent_at, 'Mimo 14denní okno se nemá posílat.');
        $this->assertNull($noEmail->fresh()->reminder_sent_at, 'Bez e-mailu klienta se nemá posílat.');
        $this->assertNull($cancelled->fresh()->reminder_sent_at, 'Zrušený výlet se nemá připomínat.');
    }

    public function test_a_second_run_does_not_resend_already_notified_trips(): void
    {
        Notification::fake();

        $this->makeTrip('guided', Carbon::today()->addDays(7), 'once@test.cz');

        $this->artisan('clients:trip-reminder');
        $this->artisan('clients:trip-reminder');

        Notification::assertSentOnDemandTimes(ClientTripReminderNotification::class, 1);
    }

    public function test_rental_trip_uses_the_rental_template_with_cc_and_substituted_macros(): void
    {
        EmailTemplate::create([
            'key' => EmailTemplate::KEY_CLIENT_REMINDER_RENTAL,
            'name' => 'x', 'subject' => 'Připomínka {{trip_name}}',
            'body' => '{{client_name}} / {{date_from}} / {{start_time}} / {{location_name}} / {{location_note}} / {{location_map_url}}',
        ]);
        EmailTemplate::create([
            'key' => EmailTemplate::KEY_CLIENT_REMINDER_GUIDED,
            'name' => 'x', 'subject' => 'Nepoužitá šablona', 'body' => 'Nepoužitá šablona',
        ]);

        $location = Location::create([
            'name' => 'Chomoutov', 'email_note' => 'Parkujte u mostu.', 'map_url' => 'https://mapy.com/xyz',
        ]);

        $trip = Trip::create([
            'type' => 'rental', 'name' => 'Půjčovna víkend',
            'client_name' => 'Jan Novák', 'client_email' => 'jan@test.cz',
            'date_from' => '2026-09-10', 'date_to' => '2026-09-11', 'status' => 'planned',
        ]);
        $trip->days()->create([
            'date' => '2026-09-10', 'order' => 1, 'location_id' => $location->id, 'start_time' => '09:00',
        ]);

        $mail = (new ClientTripReminderNotification($trip))->toMail(new AnonymousNotifiable());

        $this->assertEquals('Připomínka Půjčovna víkend', $mail->subject);
        $this->assertEquals(
            'Jan Novák / 10.09.2026 / 09:00 / Chomoutov / Parkujte u mostu. / https://mapy.com/xyz',
            $mail->viewData['body']
        );
        $this->assertEquals(config('company.email'), $mail->cc[0][0] ?? null);
    }

    private function makeTrip(string $type, Carbon $dateFrom, ?string $email, string $status = 'planned'): Trip
    {
        return Trip::create([
            'type' => $type, 'name' => 'Test ' . $dateFrom->toDateString(),
            'client_email' => $email,
            'date_from' => $dateFrom, 'date_to' => $dateFrom, 'status' => $status,
        ]);
    }
}
