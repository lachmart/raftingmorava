<?php

namespace App\Filament\Pages;

use App\Models\Meeting;
use App\Models\TripDay;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use UnitEnum;

// Month calendar of trip days and staff meetings. Admins see every trip (or just their own),
// instructors only the days they're assigned to. Meetings are shown to everyone.
class Calendar extends Page
{
    protected string $view = 'filament.pages.calendar';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Provoz';

    protected static ?int $navigationSort = 5;

    // First day of the shown month (Y-m-01).
    public string $month;

    // Admins can narrow the calendar down to their own trips; instructors always see only theirs.
    public bool $onlyMine = false;

    public function mount(): void
    {
        $this->month = Carbon::today()->startOfMonth()->toDateString();
    }

    public static function getNavigationLabel(): string
    {
        return 'Kalendář';
    }

    public function getTitle(): string
    {
        return 'Kalendář';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin', 'Instructor']) ?? false;
    }

    /** Admins see (and can open) all trips; everyone else only their own, read-only. */
    public function isManager(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin']) ?? false;
    }

    public function showsOnlyMine(): bool
    {
        return $this->onlyMine || ! $this->isManager();
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::parse($this->month)->subMonth()->toDateString();
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::parse($this->month)->addMonth()->toDateString();
    }

    public function currentMonth(): void
    {
        $this->month = Carbon::today()->startOfMonth()->toDateString();
    }

    public function monthLabel(): string
    {
        return Carbon::parse($this->month)->translatedFormat('F Y');
    }

    /**
     * Weeks (rows) of days for the calendar grid. Each day: date, whether it belongs to the
     * shown month, whether it is today, and the trip days that fall on it.
     */
    public function weeks(): array
    {
        $monthStart = Carbon::parse($this->month)->startOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthStart->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $tripDays = TripDay::query()
            ->with(['trip', 'location', 'endLocation'])
            ->whereHas('trip', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->when($this->showsOnlyMine(), fn ($q) => $q->whereHas('assignments', fn ($a) => $a->where('user_id', auth()->id())))
            ->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->get()
            ->groupBy(fn (TripDay $d) => $d->date->toDateString());

        $meetings = Meeting::query()
            ->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->orderBy('time')
            ->get()
            ->groupBy(fn (Meeting $m) => $m->date->toDateString());

        $today = Carbon::today()->toDateString();
        $weeks = [];
        $day = $gridStart->copy();

        while ($day->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $day->toDateString();
                $week[] = (object) [
                    'date' => $day->copy(),
                    'inMonth' => $day->month === $monthStart->month,
                    'isToday' => $key === $today,
                    'tripDays' => $tripDays->get($key, collect())->sortBy(fn (TripDay $d) => $d->start_time ?: $d->meeting_time),
                    'meetings' => $meetings->get($key, collect()),
                ];
                $day->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }
}
