<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Název')
                    ->required(),
                TextInput::make('license_plate')->label('SPZ'),
                TextInput::make('capacity')->label('Kapacita')
                    ->numeric(),
                Textarea::make('note')->label('Poznámka')
                    ->columnSpanFull(),
                Toggle::make('active')->label('Aktivní')
                    ->required(),
            ]);
    }
}
