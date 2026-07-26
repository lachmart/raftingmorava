<?php

namespace App\Filament\Resources\TripPrograms\Tables;

use App\Models\TripProgram;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TripProgramsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Název')
                    ->searchable(),
                TextColumn::make('category')->label('Kategorie')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => TripProgram::CATEGORIES[$state] ?? $state),
                TextColumn::make('price_per_person')->label('Cena za osobu (Kč)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('instructor_reward')->label('Odměna instruktora (Kč)')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('active')->label('Aktivní')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
