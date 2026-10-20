<?php

namespace App\Filament\Resources\Trips\RelationManagers;

use App\Filament\Resources\Trips\TripResource;
use App\Models\TripDay;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

// Trip days relation manager (like Planning, without trip info). Assignments via two repeaters:
// instructors and drivers – both with only person/reward/paid/date (type is set by the group).
class DaysRelationManager extends RelationManager
{
    protected static string $relationship = 'days';

    protected static ?string $title = 'Dny výletu';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1) // single column – sections stacked
            ->components([
                // Rows: date + order + vehicles / boarding → exit point / sraz, start, konec.
                Section::make('Den')
                    ->columns(6)
                    ->schema([
                        DatePicker::make('date')->label('Datum')->required()->native(false)->displayFormat('d.m.Y')->columnSpan(2),
                        TextInput::make('order')->label('Pořadí dne')->numeric()->default(1)->minValue(1)->required()->columnSpan(1),
                        Select::make('vehicles')->label('Vozidla')
                            ->multiple()->relationship('vehicles', 'name')->searchable()->preload()->columnSpan(3),

                        Select::make('location_id')->label('Nástupní místo')
                            ->relationship('location', 'name', fn ($query) => $query->where('active', true))->searchable()->preload()
                            ->createOptionForm([TextInput::make('name')->label('Název lokace')->required()])
                            ->columnSpan(3),
                        Select::make('end_location_id')->label('Výstupní místo')
                            ->relationship('endLocation', 'name', fn ($query) => $query->where('active', true))->searchable()->preload()
                            ->createOptionForm([TextInput::make('name')->label('Název lokace')->required()])
                            ->columnSpan(3),

                        TimePicker::make('meeting_time')->label('Čas srazu')->seconds(false)->columnSpan(2),
                        TimePicker::make('start_time')->label('Čas začátku')->seconds(false)->columnSpan(2),
                        TimePicker::make('end_time')->label('Čas konce')->seconds(false)->columnSpan(2),
                    ]),

                Section::make('Instruktoři')
                    ->schema([
                        Repeater::make('instructors')
                            ->hiddenLabel()
                            ->relationship('assignments', fn ($query) => $query->where('type', 'instructor'))
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => array_merge($data, ['type' => 'instructor']))
                            ->itemLabel(fn (array $state): ?string => User::find($state['user_id'] ?? null)?->name)
                            ->columns(4)
                            ->schema(self::assignmentFields())
                            ->addActionLabel('Přidat instruktora')
                            ->defaultItems(0),
                    ]),

                Section::make('Řidiči')
                    ->schema([
                        Repeater::make('drivers')
                            ->hiddenLabel()
                            ->relationship('assignments', fn ($query) => $query->where('type', 'driver'))
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => array_merge($data, ['type' => 'driver']))
                            ->itemLabel(fn (array $state): ?string => User::find($state['user_id'] ?? null)?->name)
                            ->columns(4)
                            ->schema(self::assignmentFields())
                            ->addActionLabel('Přidat řidiče')
                            ->defaultItems(0),
                    ]),
            ]);
    }

    /** Fields of a single assignment: person, reward, paid, date (no type or role). */
    private static function assignmentFields(): array
    {
        return [
            Select::make('user_id')->label('Osoba')
                ->relationship('user', 'name')->searchable()->preload()->required(),
            TextInput::make('reward')->label('Odměna (Kč)')->numeric()->minValue(0),
            Toggle::make('paid')->label('Vyplaceno')->inline(false),
            DatePicker::make('paid_date')->label('Datum výplaty')->native(false)->displayFormat('d.m.Y'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['location', 'endLocation', 'vehicles', 'assignments.user']))
            ->recordTitleAttribute('date')
            ->recordTitle(fn ($record) => 'Den ' . $record->order . ' – ' . $record->date?->format('d.m.Y'))
            ->recordAction('edit') // clicking a row opens the day editor
            ->columns([
                TextColumn::make('order')->label('Den')->sortable(),
                TextColumn::make('date')->label('Datum')->html()
                    ->state(function ($record) {
                        $weekday = Str::ucfirst($record->date?->translatedFormat('l') ?? '');
                        $date = $record->date?->format('d.m.Y');

                        $times = '';
                        if ($record->meeting_time) {
                            $times .= '<div class="text-sm text-gray-500">sraz ' . e(substr((string) $record->meeting_time, 0, 5)) . '</div>';
                        }
                        if ($record->start_time) {
                            $times .= '<div class="text-sm text-gray-500">start ' . e(substr((string) $record->start_time, 0, 5)) . '</div>';
                        }
                        if ($record->end_time) {
                            $times .= '<div class="text-sm text-gray-500">konec ' . e(substr((string) $record->end_time, 0, 5)) . '</div>';
                        }

                        return new HtmlString(
                            '<div class="font-medium">' . e($weekday) . '</div>'
                            . '<div class="text-sm text-gray-500">' . e($date) . '</div>'
                            . $times
                        );
                    })
                    ->sortable(),
                TextColumn::make('section')->label('Úsek')->wrap()
                    ->state(fn ($record) => $record->sectionLabel())->placeholder('—'),
                TextColumn::make('instructors')->label('Instruktoři')
                    ->state(fn ($record) => $record->assignments->where('type', 'instructor')->map(fn ($a) => $a->user?->name)->filter()->values()->all())
                    ->listWithLineBreaks()->placeholder('—'),
                TextColumn::make('drivers')->label('Řidiči')
                    ->state(fn ($record) => $record->assignments->where('type', 'driver')->map(fn ($a) => $a->user?->name)->filter()->values()->all())
                    ->listWithLineBreaks()->placeholder('—'),
                TextColumn::make('vehicles.name')->label('Vozidla')->placeholder('—')
                    ->badge()->color('gray')->listWithLineBreaks(),
            ])
            ->defaultSort('order')
            ->headerActions([
                CreateAction::make()->label('Přidat den')->modalWidth('4xl'),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('4xl'),
                // Through the trip, so its dates shrink to the remaining days. Then reload the page –
                // otherwise the trip form above still holds the old dates and saving it would re-add the day.
                DeleteAction::make()
                    ->using(fn (TripDay $record) => $record->trip->removeDay($record))
                    ->after(fn () => $this->redirect(TripResource::getUrl('edit', ['record' => $this->getOwnerRecord()]))),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
