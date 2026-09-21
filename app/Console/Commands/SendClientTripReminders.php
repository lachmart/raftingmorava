<?php

namespace App\Console\Commands;

use App\Models\Trip;
use App\Notifications\ClientTripReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

// 14-day-before reminder to clients about their upcoming trip's start time and place.
// `reminder_sent_at` makes this idempotent per trip and lets a missed cron day catch up later
// (unlike the daily instructor reminder, this is a one-time event per trip, so it must not be
// allowed to simply never fire).
class SendClientTripReminders extends Command
{
    protected $signature = 'clients:trip-reminder';

    protected $description = 'Sends clients a reminder about their trip starting within 14 days';

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();
        $in14Days = Carbon::today()->addDays(14)->toDateString();

        $trips = Trip::query()
            ->whereNull('reminder_sent_at')
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('client_email')
            ->whereDate('date_from', '>=', $today)
            ->whereDate('date_from', '<=', $in14Days)
            ->get();

        // Cron runs hourly via URL - one failing recipient must not skip everyone after it.
        $sent = 0;
        $failed = 0;

        foreach ($trips as $trip) {
            try {
                Notification::route('mail', $trip->client_email)
                    ->notify(new ClientTripReminderNotification($trip));
                $trip->update(['reminder_sent_at' => now()]);
                $sent++;
            } catch (Throwable $e) {
                $failed++;
                Log::error('Trip reminder e-mail for client could not be sent.', [
                    'trip_id' => $trip->id,
                    'recipient' => $trip->client_email,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Reminders sent: {$sent}");

        if ($failed > 0) {
            $this->warn("Reminders failed: {$failed} (see the log)");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
