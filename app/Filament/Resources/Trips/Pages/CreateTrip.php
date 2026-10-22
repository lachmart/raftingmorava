<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Resources\Trips\Schemas\TripForm;
use App\Filament\Resources\Trips\TripResource;
use App\Models\Location;
use App\Models\TripProgram;
use App\Support\TripDayDrafts;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Carbon;

// Create = 3-step wizard; the last step shows the pre-filled days (places from the program
// template, sraz/konec derived from the start) so the trip is complete in one go.
class CreateTrip extends CreateRecord
{
    use HasWizard;

    protected static string $resource = TripResource::class;

    /** Day rows taken out of the form data before the Trip is created. */
    protected array $dayDrafts = [];

    public function getSteps(): array
    {
        [$type, $program, $reward, $dateFrom, $dateTo] = TripForm::tripFields();
        [$status, $childrenAges, $paying, $escort] = TripForm::countFields();

        return [
            Step::make('Program a termín')->columns(2)->schema([
                $type, $program,
                $dateFrom, $dateTo,
                TimePicker::make('first_day_start')->label('Čas startu (1. den)')->seconds(false)
                    ->helperText('Sraz a konec se dopočítají podle programu.'),
                $reward,
                Section::make('Vybavení (půjčovna)')->columnSpanFull()
                    ->visible(fn (Get $get) => $get('type') === 'rental')
                    ->schema([TripForm::equipmentRepeater()]),
            ]),
            Step::make('Klient a počty')->columns(2)
                ->schema([
                    ...TripForm::clientFields(),
                    $status,
                    $paying, $escort,
                    $childrenAges,
                    TripForm::nameField()->columnSpanFull(),
                ])
                ->afterValidation(fn (Get $get, Set $set) => $this->prepareDays($get, $set)),
            Step::make('Dny')->schema([
                Hidden::make('days_key'),
                Repeater::make('days_draft')->hiddenLabel()
                    ->addable(false)->deletable(false)->reorderable(false)->defaultItems(0)
                    ->itemLabel(fn (array $state) => filled($state['date'] ?? null)
                        ? 'Den '.$state['order'].' – '.Carbon::parse($state['date'])->locale('cs')->isoFormat('dd D. M. YYYY')
                        : null)
                    ->columns(6)
                    ->schema([
                        Hidden::make('date'),
                        Hidden::make('order'),
                        Select::make('location_id')->label('Nástupní místo')
                            ->options(fn () => self::locationOptions())->searchable()->columnSpan(3),
                        Select::make('end_location_id')->label('Výstupní místo')
                            ->options(fn () => self::locationOptions())->searchable()->columnSpan(3),
                        TimePicker::make('meeting_time')->label('Čas srazu')->seconds(false)->columnSpan(2),
                        TimePicker::make('start_time')->label('Čas začátku')->seconds(false)->columnSpan(2),
                        TimePicker::make('end_time')->label('Čas konce')->seconds(false)->columnSpan(2),
                    ]),
            ]),
        ];
    }

    private static function locationOptions(): array
    {
        return Location::where('active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Entering the Dny step: build the day rows the first time, and rebuild them when the program,
     * dates or start changed since – manual edits are kept otherwise.
     */
    private function prepareDays(Get $get, Set $set): void
    {
        $programId = $get('type') === 'guided' ? $get('trip_program_id') : null;
        $key = json_encode([$programId, $get('date_from'), $get('date_to'), $get('first_day_start')]);

        $previousKey = $get('days_key');
        if (filled($get('days_draft')) && $previousKey === $key) {
            return;
        }

        $set('days_draft', TripDayDrafts::build(
            $programId ? TripProgram::find($programId) : null,
            $get('date_from'),
            $get('date_to') ?: $get('date_from'),
            $get('first_day_start'),
        ));
        $set('days_key', $key);

        if (filled($previousKey)) {
            Notification::make()->warning()
                ->title('Dny se sestavily znovu podle nového programu nebo termínu')
                ->body('Ruční úpravy dnů se zahodily.')
                ->send();
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->dayDrafts = array_values($data['days_draft'] ?? []);
        unset($data['days_draft'], $data['days_key'], $data['first_day_start']);

        if (($data['type'] ?? null) !== 'guided') {
            $data['trip_program_id'] = null;
        }

        return $data;
    }

    // Days from the wizard's last step (generateDays() only fills in if there were none), then the rental price.
    protected function afterCreate(): void
    {
        foreach ($this->dayDrafts as $draft) {
            $this->record->days()->create(collect($draft)->only([
                'date', 'order', 'location_id', 'end_location_id', 'meeting_time', 'start_time', 'end_time',
            ])->all());
        }

        $this->record->generateDays();
        $this->record->refresh()->recalculateRentalPrice();
    }
}
