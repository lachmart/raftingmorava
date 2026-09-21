<?php

namespace App\Support;

use App\Models\TripDayAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

// Builds a textual list of an instructor's trips/days for the {{trip_list}} / {{tomorrow_trip_list}} macros.
class InstructorSchedule
{
    /** Instructor's assignments in the given date range (with relations for the description). */
    public static function assignmentsBetween(User $instructor, Carbon $from, Carbon $to)
    {
        return $instructor->assignments()
            ->with(['tripDay.trip.notes.author', 'tripDay.location', 'tripDay.vehicles'])
            ->whereHas('tripDay', fn ($q) => $q->whereBetween('date', [$from->toDateString(), $to->toDateString()]))
            ->get()
            ->sortBy(fn ($a) => $a->tripDay->date);
    }

    /** Instructor's past assignments (before today), newest first. No lower bound – whole history. */
    public static function pastAssignmentsFor(User $instructor)
    {
        return $instructor->assignments()
            ->with(['tripDay.trip.notes.author', 'tripDay.location', 'tripDay.vehicles'])
            ->whereHas('tripDay', fn ($q) => $q->whereDate('date', '<', Carbon::today()->toDateString()))
            ->get()
            ->sortByDesc(fn ($a) => $a->tripDay->date);
    }

    /** Upcoming trips from today onward (for the summary email). */
    public static function upcomingList(User $instructor): string
    {
        $from = Carbon::today();
        $to = Carbon::today()->addMonths(3);

        return self::format(self::assignmentsBetween($instructor, $from, $to));
    }

    /** Tomorrow's trips (for the day-before reminder). */
    public static function tomorrowList(User $instructor): string
    {
        $tomorrow = Carbon::tomorrow();

        return self::format(self::assignmentsBetween($instructor, $tomorrow, $tomorrow));
    }

    /** Formats a collection of assignments into a bulleted text list. */
    public static function format($assignments): string
    {
        if ($assignments->isEmpty()) {
            return '(žádné naplánované výlety)';
        }

        return $assignments->map(function ($a) {
            $day = $a->tripDay;
            $date = $day->date?->format('d.m.Y');
            $name = $day->trip?->name;
            $type = TripDayAssignment::TYPES[$a->type] ?? $a->type;
            // Instructors get both sraz (when to meet) and start (when the trip begins).
            $times = collect([
                $day->meeting_time ? 'sraz '.substr((string) $day->meeting_time, 0, 5) : null,
                $day->start_time ? 'start '.substr((string) $day->start_time, 0, 5) : null,
            ])->filter()->implode(', ');
            $times = $times !== '' ? ' '.$times : '';
            $location = $day->location?->name ? ' – '.$day->location->name : '';
            $vehicle = $day->vehicles->isNotEmpty() ? ' [vozidla: '.$day->vehicles->pluck('name')->join(', ').']' : '';

            return "• {$date}{$times} – {$name} ({$type}){$location}{$vehicle}";
        })->implode("\n");
    }
}
