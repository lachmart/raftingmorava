<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Models\Location;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Název')->required()->maxLength(255),
                Select::make('certainty')->label('Jistota')->options(Location::CERTAINTIES)->default('confirmed')->required()
                    ->helperText('Doplňkové = orientační / k ověření přesného názvu.'),
                TextInput::make('address')->label('Adresa'),
                Toggle::make('active')->label('Aktivní')->default(true)->inline(false),
                Textarea::make('note')->label('Poznámka')->columnSpanFull()->rows(2),
            ]);
    }
}
