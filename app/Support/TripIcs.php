<?php

namespace App\Support;

use App\Models\Trip;
use Illuminate\Support\Carbon;

// Builds an iCalendar (.ics) event for a trip, attached to the customer confirmation email so mail
// apps offer an "Add to calendar" button. Times use day 1; missing end time defaults to +3 h.
class TripIcs
{
    public static function for(Trip $trip): ?string
    {
        if (! $trip->date_from || ! $trip->date_to) {
            return null;
        }

        $tz = 'Europe/Prague';
        $day1 = $trip->days()->orderBy('order')->first();

        $startTime = substr((string) ($day1?->start_time ?: '09:00'), 0, 5);
        $start = Carbon::parse($trip->date_from->toDateString() . ' ' . $startTime, $tz);

        $endTime = $day1?->end_time ? substr((string) $day1->end_time, 0, 5) : $start->copy()->addHours(3)->format('H:i');
        $end = Carbon::parse($trip->date_to->toDateString() . ' ' . $endTime, $tz);
        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->copy()->addHours(3);
        }

        $utc = fn (Carbon $c) => $c->clone()->utc()->format('Ymd\THis\Z');
        $esc = fn (string $s) => str_replace(['\\', ',', ';', "\r\n", "\n"], ['\\\\', '\\,', '\\;', '\\n', '\\n'], $s);

        $summary = 'Rafting Morava – ' . $trip->name;
        $location = $day1?->location?->name ?? '';
        $description = trim(
            ($trip->program?->name ? 'Program: ' . $trip->program->name . "\n" : '') .
            ($trip->client_name ? 'Klient: ' . $trip->client_name : '')
        );

        $lines = array_filter([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Rafting Morava//Trip//CS',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:trip-' . $trip->id . '@raftingmorava.cz',
            'DTSTAMP:' . $utc(Carbon::now()),
            'DTSTART:' . $utc($start),
            'DTEND:' . $utc($end),
            'SUMMARY:' . $esc($summary),
            $location !== '' ? 'LOCATION:' . $esc($location) : null,
            $description !== '' ? 'DESCRIPTION:' . $esc($description) : null,
            'END:VEVENT',
            'END:VCALENDAR',
        ], fn ($line) => $line !== null);

        return implode("\r\n", $lines) . "\r\n";
    }
}
