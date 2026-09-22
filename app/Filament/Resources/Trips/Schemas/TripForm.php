<?php

namespace App\Filament\Resources\Trips\Schemas;

use App\Models\Trip;
use App\Models\TripProgram;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class TripForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Trip on the left, Client and Settlement stacked on the right.
                Section::make('Výlet')
                    ->columnSpan(1)
                    ->columns(2)
                    ->schema([
                        Select::make('type')->label('Typ výletu')->options(Trip::TYPES)
                            ->default('guided')->required()->live()
                            ->helperText('Akce s instruktorem = program + odměny. Půjčovna = cena z vybavení, bez instruktora.'),
                        TextInput::make('name')->label('Název výletu')->required()->maxLength(255)
                            ->helperText('Předvyplní se z programu a klienta; lze upravit ručně.'),

                        Select::make('trip_program_id')
                            ->label('Program (ceník)')
                            ->relationship('program', 'name')
                            ->searchable()->preload()
                            ->visible(fn (Get $get) => $get('type') === 'guided')
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                if ($state && blank($get('instructor_reward'))) {
                                    $set('instructor_reward', TripProgram::find($state)?->instructor_reward);
                                }
                                self::syncDateTo($get, $set);
                                self::syncName($get, $set);
                            }),
                        TextInput::make('instructor_reward')->label('Odměna instruktora za výlet (Kč)')
                            ->numeric()->minValue(0)
                            ->visible(fn (Get $get) => $get('type') === 'guided')
                            ->helperText('Předvyplní se z programu; přenáší se v plné výši na každého instruktora.'),

                        Select::make('status')->label('Stav')->options(Trip::STATUSES)->default('planned')->required(),
                        TextInput::make('children_ages')->label('Věkové složení dětí')->placeholder('např. 12–14 let'),

                        DatePicker::make('date_from')->label('Termín od')->required()->native(false)->displayFormat('d.m.Y')
                            ->live()->afterStateUpdated(fn (Get $get, Set $set) => self::syncDateTo($get, $set)),
                        DatePicker::make('date_to')->label('Termín do')->required()->native(false)->displayFormat('d.m.Y')->live()
                            ->helperText('Předvyplní se podle délky programu; lze upravit ručně.'),

                        TextInput::make('planned_paying_count')->label('Platících (plán)')->numeric()->minValue(0),
                        TextInput::make('planned_escort_count')->label('Doprovod – neplatící (plán)')->numeric()->minValue(0),
                    ]),

                Group::make()
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Klient')
                            ->columns(2)
                            ->schema([
                                TextInput::make('client_name')->label('Jméno / organizace')
                                    ->live()->afterStateUpdated(fn (Get $get, Set $set) => self::syncName($get, $set)),
                                Select::make('customer_category')->label('Kategorie zákazníka')
                                    ->options(Trip::CUSTOMER_CATEGORIES),
                                TextInput::make('client_email')->label('E-mail')->email(),
                                TextInput::make('client_phone')->label('Telefon klienta')->tel(),
                                TextInput::make('onsite_phone')->label('Kontakt na místě akce')->tel(),
                            ]),

                        // Settlement right under Client, always open.
                        Section::make('Vyúčtování (po výletu)')
                            ->description('Podklad pro účetní – skutečné počty a platba.')
                            ->columns(2)
                            ->schema([
                                TextInput::make('actual_paying_count')->label('Platících (skutečně)')->numeric()->minValue(0),
                                TextInput::make('actual_escort_count')->label('Doprovod (skutečně)')->numeric()->minValue(0),
                                TextInput::make('amount_paid')->label('Zaplacená částka (Kč)')->numeric()->minValue(0),
                                Select::make('payment_method')->label('Způsob úhrady')->options(Trip::PAYMENT_METHODS),
                                TextInput::make('document_number')->label('Číslo dokladu (volitelné)'),
                                DatePicker::make('payment_date')->label('Datum úhrady')->native(false)->displayFormat('d.m.Y'),
                            ]),
                    ]),

                Section::make('Vybavení (půjčovna)')
                    ->description('U typu „půjčovna" se z počtu kusů × cena/den × počet dní automaticky spočte cena.')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        Repeater::make('equipmentItems')
                            ->label('Položky vybavení')
                            ->relationship()
                            ->columns(3)
                            ->schema([
                                Select::make('equipment_id')->label('Vybavení')
                                    ->relationship('equipment', 'name')->searchable()->preload()->required(),
                                TextInput::make('quantity')->label('Počet')->numeric()->default(1)->minValue(1)->required(),
                                TextInput::make('price_per_unit')->label('Cena/kus (Kč)')->numeric()
                                    ->helperText('Prázdné = aktuální cena z ceníku.'),
                            ])
                            ->addActionLabel('Přidat vybavení')
                            ->defaultItems(0),

                        TextInput::make('total_price')->label('Cena celkem pro zákazníka (Kč)')
                            ->numeric()->minValue(0)
                            ->helperText('U půjčovny se dopočítává automaticky. Ruční přepis vypne automatický přepočet.'),
                        Toggle::make('price_manually_set')->label('Cena upravena ručně (nepřepočítávat)')->inline(false),
                    ]),
            ]);
    }

    /**
     * Composes the "Program, Klient" trip-name suggestion out of whichever parts are present
     * (either may be blank – e.g. a rental trip has no program). Null = nothing to fill in.
     */
    public static function composeName(?string $programName, ?string $clientName): ?string
    {
        $parts = array_filter([$programName, $clientName], fn (?string $v) => filled($v));

        return $parts === [] ? null : implode(', ', $parts);
    }

    /** Re-composes "Název výletu" whenever the program or the client name changes. */
    private static function syncName(Get $get, Set $set): void
    {
        $programId = $get('trip_program_id');
        $programName = $programId ? TripProgram::find($programId)?->name : null;

        $composed = self::composeName($programName, $get('client_name'));

        if ($composed !== null) {
            $set('name', $composed);
        }
    }

    /** Derives "termín do" from "termín od" + the program's number of days (guided trips only). */
    private static function syncDateTo(Get $get, Set $set): void
    {
        $programId = $get('trip_program_id');
        $from = $get('date_from');

        if (! $programId || ! $from) {
            return;
        }

        $days = TripProgram::find($programId)?->number_of_days;
        if (! $days) {
            return;
        }

        $set('date_to', Carbon::parse($from)->addDays($days - 1)->toDateString());
    }
}
