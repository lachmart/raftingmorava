<?php

namespace App\Support;

use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

// Builds iCalendar (.ics) content. A single VCALENDAR may hold several VEVENTs, so it works both for
// one trip (customer confirmation) and for all of an instructor's upcoming days (summary email).
class TripIcs
{
    private const TZ = 'Europe/Prague';

    /** One event for the whole trip (day 1's start time … end time). */
    public static function for(Trip $trip): ?string
    {
        if (! $trip->date_from || ! $trip->date_to) {
            return null;
        }

        $day1 = $trip->days()->orderBy('order')->first();
        [$start, $end] = self::window($trip->date_from, $trip->date_to, $day1?->start_time, $day1?->end_time);

        $description = trim(
            ($trip->program?->name ? 'Program: ' . $trip->program->name . "\n" : '') .
            ($trip->client_name ? 'Klient: ' . $trip->client_name : '')
        );

        $event = self::event('trip-' . $trip->id, $start, $end, 'Rafting Morava – ' . $trip->name, $day1?->location?->name, $description);

        return self::wrap([$event]);
    }

    /** One event per day the instructor is assigned to, in the given range. */
    public static function forInstructor(User $instructor, Carbon $from, Carbon $to): ?string
    {
        $assignments = $instructor->assignments()
            ->with(['tripDay.trip', 'tripDay.location'])
            ->whereHas('tripDay', fn ($q) => $q->whereBetween('date', [$from->toDateString(), $to->toDateString()]))
            ->get()
            ->sortBy(fn (TripDayAssignment $a) => $a->tripDay?->date);

        $events = [];
        foreach ($assignments as $a) {
            $day = $a->tripDay;
            if (! $day || ! $day->date) {
                continue;
            }

            [$start, $end] = self::window($day->date, $day->date, $day->start_time, $day->end_time);
            $role = TripDayAssignment::TYPES[$a->type] ?? $a->type;
            $description = $role . ($day->meeting_time ? "\nSraz " . substr((string) $day->meeting_time, 0, 5) : '');

            $events[] = self::event(
                'tripday-' . $day->id,
                $start,
                $end,
                'Rafting Morava – ' . $day->trip?->name,
                $day->location?->name,
                $description,
            );
        }

        return $events ? self::wrap($events) : null;
    }

    /** Start/end Carbon for a date range + optional times; missing end defaults to +3 h. */
    private static function window(Carbon $dateFrom, Carbon $dateTo, $startTime, $endTime): array
    {
        $start = Carbon::parse($dateFrom->toDateString() . ' ' . substr((string) ($startTime ?: '09:00'), 0, 5), self::TZ);
        $endStr = $endTime ? substr((string) $endTime, 0, 5) : $start->copy()->addHours(3)->format('H:i');
        $end = Carbon::parse($dateTo->toDateString() . ' ' . $endStr, self::TZ);
        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->copy()->addHours(3);
        }

        return [$start, $end];
    }

    private static function event(string $uid, Carbon $start, Carbon $end, string $summary, ?string $location, ?string $description): string
    {
        $utc = fn (Carbon $c) => $c->clone()->utc()->format('Ymd\THis\Z');

        $lines = array_filter([
            'BEGIN:VEVENT',
            'UID:' . $uid . '@raftingmorava.cz',
            'DTSTAMP:' . $utc(Carbon::now()),
            'DTSTART:' . $utc($start),
            'DTEND:' . $utc($end),
            'SUMMARY:' . self::escape($summary),
            $location ? 'LOCATION:' . self::escape($location) : null,
            $description !== '' && $description !== null ? 'DESCRIPTION:' . self::escape($description) : null,
            'END:VEVENT',
        ], fn ($line) => $line !== null);

        return implode("\r\n", $lines);
    }

    private static function wrap(array $events): string
    {
        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Rafting Morava//Trip//CS',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            implode("\r\n", $events),
            'END:VCALENDAR',
        ]) . "\r\n";
    }

    private static function escape(string $s): string
    {
        return str_replace(['\\', ',', ';', "\r\n", "\n"], ['\\\\', '\\,', '\\;', '\\n', '\\n'], $s);
    }
}
