<?php

namespace App\Support;

use App\Models\Trip;
use App\Models\TripProgram;
use Illuminate\Support\Carbon;

/**
 * Pre-filled days of a trip that doesn't exist yet (create wizard, inquiry conversion): one row per
 * date with the program's boarding/exit point for that order and the default times.
 */
class TripDayDrafts
{
    /** @return array<string, array{date: string, order: int, location_id: ?int, end_location_id: ?int, meeting_time: ?string, start_time: ?string, end_time: ?string}> */
    public static function build(?TripProgram $program, string $from, string $to, ?string $firstDayStart): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        $length = $to->gte($from) ? min((int) $from->diffInDays($to) + 1, Trip::MAX_DAYS) : 1;

        $template = $program ? $program->programDays()->get()->keyBy('order') : collect();

        $days = [];
        for ($order = 1; $order <= $length; $order++) {
            $days["day-{$order}"] = [
                'date' => $from->copy()->addDays($order - 1)->toDateString(),
                'order' => $order,
                'location_id' => $template->get($order)?->location_id,
                'end_location_id' => $template->get($order)?->end_location_id,
                ...TripProgram::dayTimes($program, $order, $firstDayStart),
            ];
        }

        return $days;
    }
}
