<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\InstructorSchedule;
use App\Support\TripIcs;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;

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

    public function toMail(object $notifiable): MailMessage
    {
        $mail = parent::toMail($notifiable);

        // One .ics with an event for every upcoming assigned day – the instructor adds them all at once.
        if ($notifiable instanceof User) {
            $ics = TripIcs::forInstructor($notifiable, Carbon::today(), Carbon::today()->addMonths(3));
            if ($ics) {
                $mail->attachData($ics, 'moje-vylety.ics', ['mime' => 'text/calendar; charset=UTF-8']);
            }
        }

        return $mail;
    }
}
