<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use App\Support\InstructorSchedule;

// Day-before reminder – the scheduled job sends it only to instructors who have a trip tomorrow.
class InstructorTomorrowReminderNotification extends TemplatedNotification
{
    protected function templateKey(): string
    {
        return EmailTemplate::KEY_REMINDER;
    }

    protected function macroData(object $notifiable): array
    {
        return [
            'instructor_name' => $notifiable->name ?? '',
            'tomorrow_trip_list' => InstructorSchedule::tomorrowList($notifiable),
        ];
    }
}
