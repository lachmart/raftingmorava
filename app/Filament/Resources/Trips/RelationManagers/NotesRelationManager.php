<?php

namespace App\Filament\Resources\Trips\RelationManagers;

use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

// Trip notes as a comment thread (author + time). Any logged-in user may add one.
// A note can optionally be tied to a specific trip day.
class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Poznámky';

    protected static string|BackedEnum|null $icon = 'heroicon-o-chat-bubble-left-right';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('trip_day_id')->label('Ke dni (volitelné)')
                    ->relationship('tripDay', 'date', fn ($query) => $query->where('trip_id', $this->getOwnerRecord()->getKey()))
                    ->getOptionLabelFromRecordUsing(fn ($record) => 'Den ' . $record->order . ' – ' . $record->date?->format('d.m.Y')),
                Textarea::make('text')->label('Text poznámky')->required()->rows(3)->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Kdy')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('author.name')->label('Autor')->placeholder('—'),
                TextColumn::make('tripDay.date')->label('Den')->date('d.m.Y')->placeholder('celý výlet'),
                TextColumn::make('text')->label('Poznámka')->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()->label('Přidat poznámku'),
            ]);
    }
}
