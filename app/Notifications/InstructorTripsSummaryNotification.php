<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use App\Support\InstructorSchedule;

// Summary email to an instructor – a single email with an overview of their upcoming trips.
// Sent manually via the "Send summary emails" action on the Planning screen.
class InstructorTripsSummaryNotification extends TemplatedNotification
{
    protected function templateKey(): string
    {
        return EmailTemplate::KEY_SUMMARY;
    }

    protected function macroData(object $notifiable): array
    {
        return [
            'instructor_name' => $notifiable->name ?? '',
            'trip_list' => InstructorSchedule::upcomingList($notifiable),
        ];
    }
}
