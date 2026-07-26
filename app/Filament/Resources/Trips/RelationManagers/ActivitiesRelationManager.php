<?php

namespace App\Filament\Resources\Trips\RelationManagers;

use App\Models\TripDay;
use App\Models\TripDayAssignment;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Audit log (activitylog) – read-only. Shows changes to the trip and its days and assignments.
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Historie změn (audit)';

    protected static string|BackedEnum|null $icon = 'heroicon-o-clock';

    // Only Superadmin can see the audit log.
    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->hasRole('Superadmin') ?? false;
    }

    private const SUBJECT_LABELS = [
        \App\Models\Trip::class => 'Výlet',
        TripDay::class => 'Den výletu',
        TripDayAssignment::class => 'Přiřazení',
    ];

    public function table(Table $table): Table
    {
        $trip = $this->getOwnerRecord();
        $dayIds = TripDay::where('trip_id', $trip->getKey())->pluck('id');
        $assignmentIds = TripDayAssignment::whereIn('trip_day_id', $dayIds)->pluck('id');

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($dayIds, $assignmentIds) {
                // Alongside the trip's own records, include records of its days and assignments.
                $query
                    ->orWhere(fn (Builder $q) => $q->where('subject_type', TripDay::class)->whereIn('subject_id', $dayIds))
                    ->orWhere(fn (Builder $q) => $q->where('subject_type', TripDayAssignment::class)->whereIn('subject_id', $assignmentIds));
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Kdy')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('subject_type')->label('Objekt')->badge()
                    ->formatStateUsing(fn (?string $state) => self::SUBJECT_LABELS[$state] ?? class_basename((string) $state)),
                TextColumn::make('event')->label('Akce')->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'created' => 'vytvořeno',
                        'updated' => 'změněno',
                        'deleted' => 'smazáno',
                        default => (string) $state,
                    }),
                TextColumn::make('causer.name')->label('Kdo')->placeholder('systém'),
                TextColumn::make('properties')->label('Změny')->limit(60)->wrap()
                    ->formatStateUsing(fn ($state) => is_iterable($state) ? collect($state->get('attributes', []))->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ') : ''),
            ])
            ->paginated([10, 25, 50]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
