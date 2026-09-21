<?php

namespace App\Filament\Pages;

use App\Models\TripDay;
use App\Models\TripNote;
use App\Support\InstructorSchedule;
use BackedEnum;
use Filament\Actions\Action;
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
        // Any of the app's roles can have their own trip assignments and want this portal.
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin', 'Instructor']) ?? false;
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

    /**
     * Adds a note to a specific trip day, given as the `dayId` argument – no trip/day picker,
     * the calling card already knows which day it is. Called from the card as
     * `mountAction('addNote', { dayId: ... })`.
     */
    public function addNoteAction(): Action
    {
        return Action::make('addNote')
            ->label('Přidat poznámku')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->schema([
                Textarea::make('text')->label('Text poznámky')->required()->rows(3),
            ])
            ->action(function (array $data, array $arguments) {
                $day = TripDay::findOrFail($arguments['dayId']);

                // The Select in the old header action only ever offered the instructor's own
                // trips – this is the equivalent server-side check now that there is no picker.
                abort_unless($day->assignments()->where('user_id', auth()->id())->exists(), 403);

                TripNote::create([
                    'trip_id' => $day->trip_id,
                    'trip_day_id' => $day->id,
                    'text' => $data['text'],
                ]);

                Notification::make()->success()->title('Poznámka přidána')->send();
            });
    }
}
