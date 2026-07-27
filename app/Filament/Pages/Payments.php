<?php

namespace App\Filament\Pages;

use App\Models\TripDayAssignment;
use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

// Payments – lists users who are still owed money for past trip days (unpaid rewards). Admin/Superadmin
// can pay everything for a person at once, or a single trip in the detail. Marks paid=true, paid_date=today.
class Payments extends Page
{
    protected string $view = 'filament.pages.payments';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Provoz';

    protected static ?int $navigationSort = 4;

    // Currently expanded user (detail with per-trip breakdown), null = none.
    public ?int $selectedUserId = null;

    // Which view: 'unpaid' (owed) or 'paid' (history of paid rewards).
    public string $tab = 'unpaid';

    // Period filter for the paid-rewards history (by paid_date).
    public ?string $paidFrom = null;

    public ?string $paidTo = null;

    public static function getNavigationLabel(): string
    {
        return 'Platby';
    }

    public function getTitle(): string
    {
        return 'Platby';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin']) ?? false;
    }

    /** Constrains an assignments query to unpaid, reward-bearing, past-dated rows. */
    private static function unpaidPast(Builder $query): Builder
    {
        return $query
            ->where('trip_day_assignments.paid', false)
            ->where('trip_day_assignments.reward', '>', 0)
            ->whereHas('tripDay', fn ($t) => $t->whereDate('date', '<', Carbon::today()->toDateString()));
    }

    /** Users still owed money for past trip days, with owed total and unpaid-day count. */
    public function owedUsers()
    {
        $owedSubquery = DB::table('trip_day_assignments')
            ->join('trip_days', 'trip_days.id', '=', 'trip_day_assignments.trip_day_id')
            ->selectRaw('COALESCE(SUM(trip_day_assignments.reward),0)')
            ->whereColumn('trip_day_assignments.user_id', 'users.id')
            ->where('trip_day_assignments.paid', false)
            ->where('trip_day_assignments.reward', '>', 0)
            ->whereDate('trip_days.date', '<', Carbon::today()->toDateString());

        return User::query()
            ->whereHas('assignments', fn (Builder $q) => self::unpaidPast($q))
            ->withCount(['assignments as unpaid_days_count' => fn (Builder $q) => self::unpaidPast($q)])
            ->addSelect(['owed_total' => $owedSubquery])
            ->orderByDesc('owed_total')
            ->orderBy('name')
            ->get();
    }

    /** Per-trip breakdown of what a user is owed (for the expanded detail). */
    public function userTrips(int $userId)
    {
        return TripDayAssignment::query()
            ->where('user_id', $userId)
            ->tap(fn ($q) => self::unpaidPast($q))
            ->with('tripDay.trip')
            ->get()
            ->groupBy(fn (TripDayAssignment $a) => $a->tripDay?->trip_id)
            ->map(function ($assignments) {
                $trip = $assignments->first()->tripDay?->trip;

                return (object) [
                    'trip_id' => $trip?->id,
                    'name' => $trip?->name ?? '—',
                    'days' => $assignments->count(),
                    'reward' => $assignments->sum(fn (TripDayAssignment $a) => (float) $a->reward),
                    'date_from' => $assignments->min(fn (TripDayAssignment $a) => $a->tripDay?->date?->toDateString()),
                ];
            })
            ->sortBy('date_from')
            ->values();
    }

    /** History of paid rewards (paid=true, reward>0), newest first, optionally filtered by paid date. */
    public function paidRewards()
    {
        return TripDayAssignment::query()
            ->where('paid', true)
            ->where('reward', '>', 0)
            ->when($this->paidFrom, fn ($q, $v) => $q->whereDate('paid_date', '>=', $v))
            ->when($this->paidTo, fn ($q, $v) => $q->whereDate('paid_date', '<=', $v))
            ->with(['user', 'tripDay.trip'])
            ->orderByDesc('paid_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get();
    }

    public function toggleDetail(int $userId): void
    {
        $this->selectedUserId = $this->selectedUserId === $userId ? null : $userId;
    }

    /** Marks all of a user's past unpaid trip days as paid. */
    public function payAll(int $userId): void
    {
        $this->guard();

        $count = $this->settle(
            TripDayAssignment::where('user_id', $userId)
                ->where('paid', false)
                ->whereHas('tripDay', fn ($t) => $t->whereDate('date', '<', Carbon::today()->toDateString()))
        );

        $this->selectedUserId = null;
        Notification::make()->success()->title('Zaplaceno')->body("Vyplaceno dnů: {$count}.")->send();
    }

    /** Marks a single trip's past unpaid days (for one user) as paid. */
    public function payTrip(int $userId, int $tripId): void
    {
        $this->guard();

        $count = $this->settle(
            TripDayAssignment::where('user_id', $userId)
                ->where('paid', false)
                ->whereHas('tripDay', fn ($t) => $t->where('trip_id', $tripId)
                    ->whereDate('date', '<', Carbon::today()->toDateString()))
        );

        Notification::make()->success()->title('Zaplaceno')->body("Vyplaceno dnů: {$count}.")->send();
    }

    /** Marks the matched assignments paid (saved one by one so the audit log records each). */
    private function settle(Builder $query): int
    {
        $assignments = $query->get();
        foreach ($assignments as $assignment) {
            $assignment->paid = true;
            $assignment->paid_date = Carbon::today();
            $assignment->save();
        }

        return $assignments->count();
    }

    private function guard(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Superadmin', 'Admin']) ?? false, 403);
    }
}
