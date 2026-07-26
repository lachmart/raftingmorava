<?php

namespace App\Filament\Resources\Inquiries\Schemas;

use App\Models\Inquiry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class InquiryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('program_id')->label('Program')
                    ->relationship('program', 'name')->searchable()->preload(),
                TextInput::make('proposed_program_name')->label('Navržený program (název)'),
                TextInput::make('approximate_dates')->label('Termíny orientačně'),
                TextInput::make('client_name')->label('Jméno klienta')
                    ->required(),
                TextInput::make('client_email')->label('E-mail klienta')
                    ->email()
                    ->required(),
                TextInput::make('client_phone')->label('Telefon klienta')
                    ->tel(),
                TextInput::make('people_count')->label('Počet osob')
                    ->numeric(),
                Textarea::make('note')->label('Poznámka')
                    ->columnSpanFull(),
                Select::make('status')->label('Stav')
                    ->options(Inquiry::STATUSES)
                    ->default('new')
                    ->required(),
                Select::make('source')->label('Zdroj')
                    ->options(Inquiry::SOURCES)
                    ->default('manual')
                    ->required(),
            ]);
    }
}
