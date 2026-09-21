<?php

namespace App\Filament\Resources\Locations\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Název')->required()->maxLength(255),
                Textarea::make('email_note')->label('Text do e-mailu (poznámka)')
                    ->helperText('Zobrazí se klientovi v připomínkovém e-mailu o výletu.')
                    ->rows(3),
                TextInput::make('map_url')->label('Odkaz na mapy.com')->url()->maxLength(255),
                Toggle::make('active')->label('Aktivní')->default(true)->inline(false)
                    ->helperText('Neaktivní lokace se nenabízí při plánování.'),
            ]);
    }
}
