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

                // Day-by-day boarding-point template. Order = trip day order; when an inquiry is converted
                // to a trip, each day gets the boarding point for its order and the number of rows sets the
                // trip length. Even a single-day program should have one row = the start boarding point.
                Section::make('Nástupní místa po dnech')
                    ->description('Ke každému dnu výletu přiřaď nástupní místo, odkud se ten den vyráží. Jeden řádek = jeden den; i jednodenní výlet má jeden řádek (nástupní místo startu). Počet řádků zároveň určuje délku výletu a při převodu poptávky se místa přiřadí dnům podle pořadí.')
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
                                Select::make('location_id')->label('Nástupní místo')
                                    ->relationship('location', 'name', fn ($query) => $query->where('active', true))
                                    ->searchable()->preload()->required()
                                    ->createOptionForm([TextInput::make('name')->label('Název místa')->required()]),
                            ])
                            ->addActionLabel('Přidat den')
                            ->defaultItems(0),
                    ]),
            ]);
    }
}
