<?php

namespace App\Filament\Resources\Locations\Tables;

use App\Models\Location;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Název')->searchable()->sortable(),
                TextColumn::make('certainty')->label('Jistota')->badge()
                    ->formatStateUsing(fn (string $state) => Location::CERTAINTIES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'confirmed' ? 'success' : 'gray'),
                TextColumn::make('note')->label('Poznámka')->limit(60)->wrap()->toggleable(),
                IconColumn::make('active')->label('Aktivní')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('certainty')->label('Jistota')->options(Location::CERTAINTIES),
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
