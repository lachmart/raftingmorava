<?php

namespace App\Filament\Resources\Equipment\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EquipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Název')->required()->maxLength(255),
                TextInput::make('price_per_day')->label('Cena za den / kus (Kč)')->numeric()->minValue(0)
                    ->helperText('Prázdné = vybavení se neúčtuje zvlášť (jen na akcích s instruktorem).'),
                Toggle::make('active')->label('Aktivní')->default(true)->inline(false),
                Textarea::make('note')->label('Poznámka')->columnSpanFull()->rows(2),
            ]);
    }
}
