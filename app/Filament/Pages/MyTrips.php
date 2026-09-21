<?php

namespace App\Filament\Pages;

use App\Models\Trip;
use App\Models\TripNote;
use App\Support\InstructorSchedule;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

// Instructor portal (mobile-first): overview of own trips + own reward + adding a note.
class MyTrips extends Page
{
    protected string $view = 'filament.pages.my-trips';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 0;

    public static function getNavigationLabel(): string
    {
        return 'Moje výlety';
    }

    public function getTitle(): string
    {
        return 'Moje výlety';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('Instructor') ?? false;
    }

    // Which list is shown: 'upcoming' or 'past'.
    public string $tab = 'upcoming';

    /** Upcoming assignments of the logged-in instructor (today → +6 months, soonest first). */
    public function upcomingAssignments()
    {
        return InstructorSchedule::assignmentsBetween(
            auth()->user(),
            Carbon::today(),
            Carbon::today()->addMonths(6),
        );
    }

    /** Past assignments of the logged-in instructor (whole history, newest first). */
    public function pastAssignments()
    {
        return InstructorSchedule::pastAssignmentsFor(auth()->user());
    }

    /** Trips the instructor is assigned to (for selection when writing a note). */
    private function myTripsForNote(): array
    {
        return Trip::whereHas('days.assignments', fn ($q) => $q->where('user_id', auth()->id()))
            ->orderByDesc('date_from')
            ->pluck('name', 'id')
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addNote')
                ->label('Přidat poznámku')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    Select::make('trip_id')->label('K výletu')
                        ->options(fn () => $this->myTripsForNote())->required()->searchable(),
                    Textarea::make('text')->label('Text poznámky')->required()->rows(3),
                ])
                ->action(function (array $data) {
                    TripNote::create([
                        'trip_id' => $data['trip_id'],
                        'author_id' => auth()->id(),
                        'text' => $data['text'],
                    ]);
                    Notification::make()->success()->title('Poznámka přidána')->send();
                }),
        ];
    }
}
