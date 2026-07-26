<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

// Instructor leaderboard (Superadmin only). Ranked by number of days worked, with a period filter.
class Leaderboard extends Page
{
    protected string $view = 'filament.pages.leaderboard';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|UnitEnum|null $navigationGroup = 'Přehledy';

    // Period filter (optional) – empty = whole history.
    public ?string $from = null;

    public ?string $to = null;

    public static function getNavigationLabel(): string
    {
        return 'Žebříček';
    }

    public function getTitle(): string
    {
        return 'Žebříček instruktorů';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('Superadmin') ?? false;
    }

    /** Instructors ranked by days worked (in the period), with total and paid reward. */
    public function leaderboard()
    {
        $from = $this->from ?: null;
        $to = $this->to ?: null;

        $dateFilter = function ($q) use ($from, $to) {
            $q->whereHas('tripDay', function ($t) use ($from, $to) {
                $t->when($from, fn ($x) => $x->where('date', '>=', $from))
                    ->when($to, fn ($x) => $x->where('date', '<=', $to));
            });
        };

        $sumReward = fn (string $expr = 'reward') => DB::table('trip_day_assignments')
            ->join('trip_days', 'trip_days.id', '=', 'trip_day_assignments.trip_day_id')
            ->selectRaw('COALESCE(SUM(' . $expr . '),0)')
            ->whereColumn('trip_day_assignments.user_id', 'users.id')
            ->when($from, fn ($q) => $q->where('trip_days.date', '>=', $from))
            ->when($to, fn ($q) => $q->where('trip_days.date', '<=', $to));

        return User::role('Instructor')
            ->withCount(['assignments as assignments_count' => $dateFilter])
            ->addSelect([
                'total_reward' => $sumReward(),
                'paid_reward' => $sumReward('CASE WHEN paid = 1 THEN reward ELSE 0 END'),
            ])
            ->orderByDesc('assignments_count')
            ->orderBy('name')
            ->get();
    }
}
