<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\InstructorTomorrowReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

// Day-before reminder: emails only instructors who have a trip TOMORROW.
class SendInstructorReminders extends Command
{
    protected $signature = 'instructors:reminder';

    protected $description = 'Sends instructors a reminder about tomorrow\'s trips';

    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        // Instructors who have at least one assignment tomorrow.
        $instructors = User::query()
            ->where('active', true)
            ->whereHas('assignments.tripDay', fn ($q) => $q->whereDate('date', $tomorrow))
            ->get();

        // Cron runs hourly via URL - one failing recipient must not skip everyone after it.
        $sent = 0;
        $failed = 0;

        foreach ($instructors as $instructor) {
            try {
                Notification::send($instructor, new InstructorTomorrowReminderNotification());
                $sent++;
            } catch (Throwable $e) {
                $failed++;
                Log::error('Reminder e-mail for instructor could not be sent.', [
                    'user_id' => $instructor->id,
                    'recipient' => $instructor->email,
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
