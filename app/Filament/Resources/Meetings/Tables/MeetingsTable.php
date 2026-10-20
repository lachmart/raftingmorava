<?php

namespace App\Filament\Resources\Meetings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class MeetingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')->label('Datum')->date('d.m.Y')->sortable()
                    ->description(fn ($record) => $record->time ? substr((string) $record->time, 0, 5) : null),
                TextColumn::make('name')->label('Název')->searchable()->wrap(),
                TextColumn::make('place')->label('Místo')->placeholder('—'),
                TextColumn::make('note')->label('Poznámka')->limit(60)->placeholder('—')->toggleable(),
            ])
            ->defaultSort('date')
            ->filters([
                Filter::make('upcoming')->label('Jen nadcházející')->default()
                    ->query(fn ($query) => $query->whereDate('date', '>=', Carbon::today()->toDateString())),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
