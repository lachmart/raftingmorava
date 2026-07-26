<?php

namespace App\Filament\Resources\TripPrograms\Schemas;

use App\Models\TripProgram;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TripProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Název')
                    ->required(),
                Select::make('category')->label('Kategorie')
                    ->options(TripProgram::CATEGORIES)
                    ->default('domestic')
                    ->required(),
                TextInput::make('price_per_person')->label('Cena za osobu (Kč)')
                    ->numeric(),
                TextInput::make('instructor_reward')->label('Odměna instruktora (Kč)')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Textarea::make('description')->label('Popis')
                    ->columnSpanFull(),
                Toggle::make('active')->label('Aktivní')
                    ->required(),
            ]);
    }
}
