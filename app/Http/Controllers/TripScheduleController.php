<?php

namespace App\Http\Controllers;

use App\Models\TripDay;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

// Print-friendly schedule of all trip days in a date range (from Planning) – one table per date,
// ordered by meeting time. Opened in a new tab and printed via the browser.
class TripScheduleController extends Controller
{
    public const MAX_DAYS = 62;

    /** Default range: next Monday through the Tuesday after (8 days later). */
    public static function defaultRange(): array
    {
        $from = Carbon::today()->next(Carbon::MONDAY);

        return [$from, $from->copy()->addDays(8)];
    }

    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->hasAnyRole(['Superadmin', 'Admin']) ?? false, 403);

        [$defaultFrom, $defaultUntil] = self::defaultRange();
        $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : $defaultFrom;
        $until = $request->filled('until') ? Carbon::parse($request->query('until'))->startOfDay() : $defaultUntil;
        if ($until->lt($from)) {
            [$from, $until] = [$until, $from];
        }

        abort_if($from->diffInDays($until) + 1 > self::MAX_DAYS, 422, 'Rozsah může mít nejvýš '.self::MAX_DAYS.' dní.');

        $days = TripDay::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $until->toDateString())
            ->whereHas('trip', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->with([
                'trip' => fn ($q) => $q->withCount('days'),
                'location', 'endLocation', 'vehicles', 'assignments.user',
            ])
            ->orderBy('date')
            ->orderByRaw('meeting_time is null')
            ->orderBy('meeting_time')
            ->orderBy('start_time')
            ->orderBy('trip_id')
            ->get()
            ->groupBy(fn (TripDay $day) => $day->date->toDateString());

        return view('trip-schedule', ['from' => $from, 'until' => $until, 'days' => $days]);
    }
}
