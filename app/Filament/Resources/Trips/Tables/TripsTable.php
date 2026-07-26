<?php

namespace App\Filament\Resources\Trips\Tables;

use App\Models\Trip;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TripsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Název')->searchable()->sortable()->wrap(),
                TextColumn::make('type')->label('Typ')->badge()
                    ->formatStateUsing(fn (string $state) => Trip::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'rental' ? 'warning' : 'info'),
                TextColumn::make('program.name')->label('Program')->placeholder('—')->toggleable()->wrap(),
                TextColumn::make('client_name')->label('Klient')->searchable()->placeholder('—'),
                TextColumn::make('date_from')->label('Od')->date('d.m.Y')->sortable(),
                TextColumn::make('date_to')->label('Do')->date('d.m.Y')->sortable(),
                TextColumn::make('planned_paying_count')->label('Platících')->numeric()->sortable()
                    ->formatStateUsing(fn ($state, Trip $record) => $state . ($record->planned_escort_count ? ' + ' . $record->planned_escort_count . ' dopr.' : '')),
                TextColumn::make('status')->label('Stav')->badge()
                    ->formatStateUsing(fn (string $state) => Trip::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'planned' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('instructor_reward')->label('Odměna')->money('CZK', locale: 'cs')->sortable()->toggleable()->placeholder('—'),
                TextColumn::make('total_price')->label('Cena')->money('CZK', locale: 'cs')->sortable()->toggleable()->placeholder('—'),
            ])
            ->defaultSort('date_from', 'desc')
            ->filters([
                SelectFilter::make('type')->label('Typ')->options(Trip::TYPES),
                SelectFilter::make('status')->label('Stav')->options(Trip::STATUSES),
                SelectFilter::make('trip_program_id')->label('Program')->relationship('program', 'name')->searchable()->preload(),
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
