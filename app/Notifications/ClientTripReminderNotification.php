<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use App\Models\Trip;
use Illuminate\Notifications\Messages\MailMessage;

// 14-day-before reminder to the client about an upcoming trip's start time and place.
// Wording differs by trip type (guided vs. rental), always CC'd to the company mailbox.
class ClientTripReminderNotification extends TemplatedNotification
{
    public function __construct(public Trip $trip)
    {
    }

    protected function templateKey(): string
    {
        return $this->trip->type === 'rental'
            ? EmailTemplate::KEY_CLIENT_REMINDER_RENTAL
            : EmailTemplate::KEY_CLIENT_REMINDER_GUIDED;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return parent::toMail($notifiable)->cc(config('company.email'));
    }

    protected function macroData(object $notifiable): array
    {
        $trip = $this->trip->loadMissing(['program', 'firstDay.location']);
        $firstDay = $trip->firstDay;

        return [
            'client_name' => $trip->client_name ?? '',
            'trip_name' => $trip->program?->name ?? $trip->name,
            'date_from' => $trip->date_from?->format('d.m.Y') ?? '',
            'start_time' => $firstDay?->start_time ? substr((string) $firstDay->start_time, 0, 5) : '',
            'location_name' => $firstDay?->location?->name ?? '',
            'location_note' => $firstDay?->location?->email_note ?? '',
            'location_map_url' => $firstDay?->location?->map_url ?? '',
            'company_phone' => config('company.phone'),
            'company_email' => config('company.email'),
        ];
    }
}
