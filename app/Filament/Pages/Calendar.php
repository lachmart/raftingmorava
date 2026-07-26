<?php

namespace App\Filament\Pages;

use App\Models\TripDay;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use UnitEnum;

// Month calendar of planned trip days (Admin/Superadmin) – a quick visual overview for planning.
class Calendar extends Page
{
    protected string $view = 'filament.pages.calendar';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Provoz';

    protected static ?int $navigationSort = 5;

    // First day of the shown month (Y-m-01).
    public string $month;

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
        return 'Kalendář výletů';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin']) ?? false;
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
            ->with(['trip', 'location'])
            ->whereHas('trip', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->get()
            ->groupBy(fn (TripDay $d) => $d->date->toDateString());

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
                ];
                $day->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }
}
