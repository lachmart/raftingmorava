<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use App\Models\Trip;
use App\Support\TripIcs;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Number;

// Confirmation email to the customer – sent automatically when an inquiry is converted to a trip.
class CustomerInquiryConfirmedNotification extends TemplatedNotification
{
    public function __construct(public Trip $trip)
    {
    }

    protected function templateKey(): string
    {
        return EmailTemplate::KEY_CONFIRMATION;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = parent::toMail($notifiable);

        // Attach the trip as a calendar event so the customer can add it to their calendar.
        if ($ics = TripIcs::for($this->trip)) {
            $mail->attachData($ics, 'rafting-morava.ics', ['mime' => 'text/calendar; charset=UTF-8']);
        }

        return $mail;
    }

    protected function macroData(object $notifiable): array
    {
        $trip = $this->trip->loadMissing('program');

        // Total price: primarily from Trip.total_price, otherwise program price × paying count.
        $price = $trip->total_price;
        if ($price === null && $trip->program?->price_per_person !== null && $trip->planned_paying_count) {
            $price = (float) $trip->program->price_per_person * $trip->planned_paying_count;
        }
        $totalPrice = $price !== null ? Number::currency((float) $price, 'CZK', 'cs') : '';

        // Start time = start_time of the first trip day (set at conversion).
        $startTime = $trip->days()->orderBy('order')->value('start_time');
        $startTime = $startTime ? substr((string) $startTime, 0, 5) : '';

        // A one-day trip must read "11.09.2026", not "11.09.2026 - 11.09.2026". The dash used to
        // live in the template text, so the range is now built here and exposed as one macro.
        $dateFrom = $trip->date_from?->format('d.m.Y') ?? '';
        $dateTo = $trip->date_to?->format('d.m.Y') ?? '';
        $dateRange = ($dateTo === '' || $dateTo === $dateFrom)
            ? $dateFrom
            : $dateFrom . ' – ' . $dateTo;

        return [
            'client_name' => $trip->client_name ?? '',
            'program_name' => $trip->program?->name ?? $trip->name,
            'date_range' => $dateRange,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'start_time' => $startTime,
            'total_price' => $totalPrice,
            'company_phone' => config('company.phone'),
            'company_email' => config('company.email'),
        ];
    }
}
