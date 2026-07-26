<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Trips\TripResource;
use App\Models\Location;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayAssignment;
use App\Models\TripNote;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\InstructorTripsSummaryNotification;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification as MailNotification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

// Interactive Planning (Monday meeting). Clicking a row opens a large grid for assigning
// instructors/drivers/vehicles – everything is saved CONTINUOUSLY (async) on every change. Notes below.
class Planning extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.planning';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Provoz';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Plánování';
    }

    public function getTitle(): string
    {
        return 'Plánování';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TripDay::query()
                    ->with(['trip' => fn ($q) => $q->withCount('days'), 'location', 'vehicles', 'assignments.user'])
                    ->whereHas('trip', fn ($q) => $q->where('status', '!=', 'cancelled'))
                    ->whereBetween('date', [Carbon::today()->toDateString(), Carbon::today()->addWeeks(6)->toDateString()])
            )
            ->defaultSort('date')
            ->heading('Nadcházející dny výletů (6 týdnů)')
            ->description('Klikni na řádek → otevře se mřížka pro přiřazení. Vše se ukládá průběžně (není potřeba nic potvrzovat).')
            ->recordAction('assignment') // clicking anywhere on a row opens the modal
            ->columns([
                // Date on 3 lines: weekday / date / start time.
                TextColumn::make('date')->label('Datum')->html()
                    ->state(function (TripDay $record) {
                        $weekday = Str::ucfirst($record->date?->translatedFormat('l') ?? '');
                        $date = $record->date?->format('d.m.Y');
                        $time = $record->start_time ?: $record->meeting_time;
                        $timeTxt = $time ? 'start ' . substr((string) $time, 0, 5) : null;

                        return new HtmlString(
                            '<div class="font-medium">' . e($weekday) . '</div>'
                            . '<div class="text-sm text-gray-500">' . e($date) . '</div>'
                            . ($timeTxt ? '<div class="text-sm text-gray-500">' . e($timeTxt) . '</div>' : '')
                        );
                    })
                    ->sortable(),
                TextColumn::make('trip.name')->label('Výlet')->wrap()->searchable()
                    // Multi-day trip: "day X/Y" + meeting time; single-day: just the meeting time.
                    ->description(function (TripDay $record) {
                        $parts = [];
                        $total = $record->trip?->days_count ?? 1;
                        if ($total > 1) {
                            $parts[] = 'den ' . $record->order . '/' . $total;
                        }
                        if ($record->meeting_time) {
                            $parts[] = 'sraz ' . substr((string) $record->meeting_time, 0, 5);
                        }

                        return $parts ? implode(' · ', $parts) : null;
                    }),
                TextColumn::make('trip.client_name')->label('Jméno / organizace')->wrap()->placeholder('—')->searchable(),
                // Shortened type label: "Výlet" instead of "Akce s instruktorem".
                TextColumn::make('trip.type')->label('Typ')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'rental' ? 'Půjčovna' : 'Výlet')
                    ->color(fn (string $state) => $state === 'rental' ? 'warning' : 'info'),
                TextColumn::make('location.name')->label('Nástupní místo')->wrap()->placeholder('—'),
                TextColumn::make('instructors')->label('Instruktoři')
                    ->state(fn (TripDay $record) => $record->assignments->where('type', 'instructor')->map(fn ($a) => $a->user?->name)->filter()->values()->all())
                    ->listWithLineBreaks()->placeholder('—'),
                TextColumn::make('drivers')->label('Řidiči')
                    ->state(fn (TripDay $record) => $record->assignments->where('type', 'driver')->map(fn ($a) => $a->user?->name)->filter()->values()->all())
                    ->listWithLineBreaks()->placeholder('—'),
                // Vehicles after instructors/drivers, stacked.
                TextColumn::make('vehicles.name')->label('Vozidla')->placeholder('—')
                    ->badge()->color('gray')->listWithLineBreaks(),
            ])
            ->filters([
                Filter::make('unassigned')->label('Jen nepřiřazené (bez instruktora)')
                    ->query(fn ($query) => $query
                        ->whereHas('trip', fn ($q) => $q->where('type', 'guided'))
                        ->whereDoesntHave('assignments', fn ($q) => $q->where('type', 'instructor'))),
                SelectFilter::make('trip_type')->label('Typ výletu')
                    ->options(Trip::TYPES)
                    ->query(fn ($query, array $data) => filled($data['value'])
                        ? $query->whereHas('trip', fn ($q) => $q->where('type', $data['value']))
                        : $query),
            ])
            ->recordActions([
                $this->assignmentAction(),
                Action::make('editTrip')
                    ->label('Upravit výlet')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->url(fn (TripDay $record) => TripResource::getUrl('edit', ['record' => $record->trip_id])),
            ])
            ->paginated([25, 50, 100]);
    }

    /**
     * Large assignment grid. Checkbox changes are saved CONTINUOUSLY (afterStateUpdated),
     * there is no Save button. Below is the note thread + adding a new one.
     */
    private function assignmentAction(): Action
    {
        $people = User::where('active', true)->orderBy('name')->pluck('name', 'id')->all();
        $driverOpts = User::where('active', true)->orderByDesc('is_driver')->orderBy('name')
            ->get()->mapWithKeys(fn ($u) => [$u->id => $u->name . ($u->is_driver ? ' 🚗' : '')])->all();
        $vehicleOpts = Vehicle::where('active', true)->orderBy('name')->pluck('name', 'id')->all();

        return Action::make('assignment')
            ->label('Přiřadit')
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->modalHeading(fn (TripDay $record) => 'Přiřazení – ' . $record->trip?->name)
            ->modalDescription(fn (TripDay $record) => $record->date?->translatedFormat('l d.m.Y')
                . ($record->meeting_time ? ' · sraz ' . substr((string) $record->meeting_time, 0, 5) : '')
                . '  •  změny se ukládají automaticky')
            ->modalWidth('7xl')
            ->modalSubmitAction(false)            // no "Save" – it saves continuously
            ->modalCancelActionLabel('Zavřít')
            ->fillForm(fn (TripDay $record) => [
                'trip_day_id' => $record->id,
                'meeting_time' => $record->meeting_time,
                'start_time' => $record->start_time,
                'end_time' => $record->end_time,
                'location_id' => $record->location_id,
                'instructors' => $record->assignments->where('type', 'instructor')->pluck('user_id')->all(),
                'drivers' => $record->assignments->where('type', 'driver')->pluck('user_id')->all(),
                'vehicles' => $record->vehicles->pluck('id')->all(),
            ])
            ->schema([
                Hidden::make('trip_day_id'),

                Section::make('Čas a místo')
                    ->columns(4)
                    ->schema([
                        TimePicker::make('meeting_time')->label('Čas srazu')->seconds(false)
                            ->live()->afterStateUpdated(fn (Get $get) => $this->saveDetails($get)),
                        TimePicker::make('start_time')->label('Čas začátku')->seconds(false)
                            ->live()->afterStateUpdated(fn (Get $get) => $this->saveDetails($get)),
                        TimePicker::make('end_time')->label('Čas konce')->seconds(false)
                            ->live()->afterStateUpdated(fn (Get $get) => $this->saveDetails($get)),
                        Select::make('location_id')->label('Lokace (místo srazu)')
                            ->options(fn () => Location::where('active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()->preload()
                            ->createOptionForm([TextInput::make('name')->label('Název lokace')->required()])
                            ->createOptionUsing(fn (array $data) => Location::create($data)->id)
                            ->live()->afterStateUpdated(fn (Get $get) => $this->saveDetails($get)),
                    ]),

                CheckboxList::make('instructors')->label('Instruktoři')
                    ->options($people)->columns(4)->gridDirection('row')
                    ->searchable()->bulkToggleable()
                    ->live()->afterStateUpdated(fn (Get $get) => $this->saveAssignments($get)),

                CheckboxList::make('drivers')->label('Řidiči (🚗 = má příznak „může řídit")')
                    ->options($driverOpts)->columns(4)->gridDirection('row')
                    ->searchable()->bulkToggleable()
                    ->live()->afterStateUpdated(fn (Get $get) => $this->saveAssignments($get)),

                CheckboxList::make('vehicles')->label('Vozidla')
                    ->options($vehicleOpts)->columns(4)->gridDirection('row')
                    ->bulkToggleable()
                    ->live()->afterStateUpdated(fn (Get $get) => $this->saveAssignments($get)),

                Section::make('Poznámky')
                    ->schema([
                        Placeholder::make('notes_list')->hiddenLabel()
                            ->content(fn (Get $get) => $this->notesHtml($get('trip_day_id'))),
                        Textarea::make('new_note')->hiddenLabel()->placeholder('Napiš poznámku…')->rows(2),
                        SchemaActions::make([
                            Action::make('addNote')
                                ->label('Přidat poznámku')
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->action(function (Get $get, Set $set) {
                                    $text = trim((string) $get('new_note'));
                                    if ($text === '') {
                                        return;
                                    }
                                    $day = TripDay::find($get('trip_day_id'));
                                    if (! $day) {
                                        return;
                                    }
                                    TripNote::create([
                                        'trip_id' => $day->trip_id,
                                        'trip_day_id' => $day->id,
                                        'author_id' => auth()->id(),
                                        'text' => $text,
                                    ]);
                                    $set('new_note', ''); // clear the field; the list re-renders from DB
                                }),
                        ]),
                    ]),
            ]);
    }

    /** Continuous save of the day's times and location. */
    public function saveDetails(Get $get): void
    {
        $day = TripDay::find($get('trip_day_id'));
        if (! $day) {
            return;
        }

        $day->update([
            'meeting_time' => $get('meeting_time') ?: null,
            'start_time' => $get('start_time') ?: null,
            'end_time' => $get('end_time') ?: null,
            'location_id' => $get('location_id') ?: null,
        ]);
    }

    /** Continuous save of assignments (instructors/drivers/vehicles) for the given day. */
    public function saveAssignments(Get $get): void
    {
        $day = TripDay::find($get('trip_day_id'));
        if (! $day) {
            return;
        }

        $this->syncType($day, 'instructor', $get('instructors') ?? []);
        $this->syncType($day, 'driver', $get('drivers') ?? []);
        $day->vehicles()->sync($get('vehicles') ?? []);
    }

    /** Reconciles assignments of the given type with the selected users (reward filled by the model). */
    private function syncType(TripDay $day, string $type, array $userIds): void
    {
        $existing = $day->assignments()->where('type', $type)->get();

        $existing->whereNotIn('user_id', $userIds)->each(fn (TripDayAssignment $a) => $a->delete());

        $alreadyHas = $existing->pluck('user_id')->all();
        foreach (array_diff($userIds, $alreadyHas) as $userId) {
            $day->assignments()->create(['user_id' => $userId, 'type' => $type]);
        }
    }

    /** The day's note thread (author + time). */
    private function notesHtml($dayId): HtmlString
    {
        $notes = TripNote::with('author')->where('trip_day_id', $dayId)->latest()->get();
        if ($notes->isEmpty()) {
            return new HtmlString('<span class="text-gray-500">Zatím žádné poznámky.</span>');
        }

        $html = $notes->map(function (TripNote $n) {
            $who = e($n->author?->name ?? 'systém');
            $when = $n->created_at?->format('d.m.Y H:i');
            $text = nl2br(e($n->text));

            return '<div class="py-1 border-b border-gray-100 dark:border-gray-700">'
                . '<span class="text-xs text-gray-500">' . $who . ' · ' . $when . '</span><br>' . $text . '</div>';
        })->implode('');

        return new HtmlString('<div class="space-y-1 text-sm">' . $html . '</div>');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendSummaries')
                ->label('Odeslat souhrnné e-maily')
                ->icon('heroicon-o-envelope')
                ->requiresConfirmation()
                ->modalDescription('Každému instruktorovi s nadcházejícím výletem se odešle JEDEN souhrnný e-mail.')
                ->action(function () {
                    $instructors = User::role('Instructor')
                        ->where('active', true)
                        ->whereHas('assignments.tripDay', fn ($q) => $q->where('date', '>=', Carbon::today()->toDateString()))
                        ->get();

                    foreach ($instructors as $instructor) {
                        MailNotification::send($instructor, new InstructorTripsSummaryNotification());
                    }

                    FilamentNotification::make()->success()
                        ->title('Souhrnné e-maily odeslány')
                        ->body('Počet instruktorů: ' . $instructors->count())
                        ->send();
                }),
        ];
    }
}
