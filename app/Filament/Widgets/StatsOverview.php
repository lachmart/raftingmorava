<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Payments;
use App\Filament\Pages\Planning;
use App\Filament\Resources\Inquiries\InquiryResource;
use App\Models\Inquiry;
use App\Models\Trip;
use App\Models\TripDay;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Dashboard key numbers for Admin/Superadmin: upcoming trips, unassigned guided days,
// new inquiries and the total still owed to instructors.
class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin']) ?? false;
    }

    /** Planning filtered to unassigned days over the same 6-week window this stat counts. */
    public static function unassignedPlanningUrl(): string
    {
        return Planning::getUrl(['filters' => [
            'unassigned' => ['isActive' => true],
            'date_range' => [
                'from' => Carbon::today()->toDateString(),
                'until' => Carbon::today()->addWeeks(6)->toDateString(),
            ],
        ]]);
    }

    protected function getStats(): array
    {
        $today = Carbon::today()->toDateString();
        $in6weeks = Carbon::today()->addWeeks(6)->toDateString();

        $upcoming = Trip::where('status', '!=', 'cancelled')
            ->whereDate('date_to', '>=', $today)->count();

        // Guided trip days in the next 6 weeks with no instructor assigned.
        $unassigned = TripDay::query()
            ->whereHas('trip', fn ($q) => $q->where('type', 'guided')->where('status', '!=', 'cancelled'))
            ->whereBetween('date', [$today, $in6weeks])
            ->whereDoesntHave('assignments', fn ($q) => $q->where('type', 'instructor'))
            ->count();

        $newInquiries = Inquiry::where('status', 'new')->count();

        $owed = (float) DB::table('trip_day_assignments')
            ->join('trip_days', 'trip_days.id', '=', 'trip_day_assignments.trip_day_id')
            ->where('trip_day_assignments.paid', false)
            ->where('trip_day_assignments.reward', '>', 0)
            ->whereDate('trip_days.date', '<', $today)
            ->sum('trip_day_assignments.reward');

        return [
            Stat::make('Nadcházející výlety', $upcoming)
                ->description('Neproběhlé a nezrušené')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info')
                ->url(Planning::getUrl()),

            Stat::make('Nepřiřazené (6 týdnů)', $unassigned)
                ->description('Dny s instruktorem bez instruktora')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($unassigned > 0 ? 'warning' : 'success')
                ->url(self::unassignedPlanningUrl()),

            Stat::make('Nové poptávky', $newInquiries)
                ->description('Čekají na zpracování')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($newInquiries > 0 ? 'warning' : 'gray')
                ->url(InquiryResource::getUrl('index', ['filters' => ['status' => ['value' => 'new']]])),

            Stat::make('K vyplacení', number_format($owed, 0, ',', ' ') . ' Kč')
                ->description('Nevyplacené odměny za proběhlé dny')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($owed > 0 ? 'danger' : 'success')
                ->url(Payments::getUrl()),
        ];
    }
}
