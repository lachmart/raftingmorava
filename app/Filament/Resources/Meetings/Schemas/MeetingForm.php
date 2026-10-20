<?php

namespace App\Filament\Resources\Meetings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class MeetingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Název')->required()->maxLength(255)
                    ->placeholder('např. Porada instruktorů'),
                TextInput::make('place')->label('Místo')->maxLength(255),
                DatePicker::make('date')->label('Datum')->required()->native(false)->displayFormat('d.m.Y'),
                TimePicker::make('time')->label('Čas')->seconds(false),
                Textarea::make('note')->label('Poznámka')->rows(3)->columnSpanFull(),
            ]);
    }
}
