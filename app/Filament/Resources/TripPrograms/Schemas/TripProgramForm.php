<?php

namespace App\Filament\Resources\TripPrograms\Schemas;

use App\Models\Location;
use App\Models\TripProgram;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TripProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Název')
                            ->required()->columnSpanFull(),
                        Select::make('category')->label('Kategorie')
                            ->options(TripProgram::CATEGORIES)
                            ->default('domestic')
                            ->required(),
                        Toggle::make('active')->label('Aktivní')->default(true)->inline(false),
                        TextInput::make('price_per_person')->label('Cena za osobu (Kč)')
                            ->numeric(),
                        TextInput::make('instructor_reward')->label('Odměna instruktora (Kč)')
                            ->required()
                            ->numeric()
                            ->default(0.0),
                        TextInput::make('meeting_offset_minutes')->label('Sraz před startem (min)')
                            ->numeric()->minValue(0)->default(50)->required()
                            ->helperText('Sraz výletu se předvyplní jako start minus tento počet minut.'),
                        Textarea::make('description')->label('Popis')
                            ->columnSpanFull(),
                    ]),

                // Day-by-day location template for multi-day routes. Order = trip day order; when an
                // inquiry is converted to a trip, each generated day gets the location for its order.
                Section::make('Lokace po dnech (vícedenní trasy)')
                    ->description('Pořadí = pořadí dne výletu. Při vytvoření výletu z poptávky se lokace přiřadí na jednotlivé dny podle pořadí. Nech prázdné u jednodenních programů.')
                    ->schema([
                        Repeater::make('programDays')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('order')
                            ->reorderable()
                            ->itemLabel(fn (array $state): ?string => filled($state['location_id'] ?? null)
                                ? Location::find($state['location_id'])?->name
                                : null)
                            ->schema([
                                Select::make('location_id')->label('Lokace')
                                    ->relationship('location', 'name', fn ($query) => $query->where('active', true))
                                    ->searchable()->preload()->required()
                                    ->createOptionForm([TextInput::make('name')->label('Název lokace')->required()]),
                            ])
                            ->addActionLabel('Přidat den')
                            ->defaultItems(0),
                    ]),
            ]);
    }
}
