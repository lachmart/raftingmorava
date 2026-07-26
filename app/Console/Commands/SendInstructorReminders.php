<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\InstructorTomorrowReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

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

        foreach ($instructors as $instructor) {
            Notification::send($instructor, new InstructorTomorrowReminderNotification());
        }

        $this->info("Reminders sent: {$instructors->count()}");

        return self::SUCCESS;
    }
}
