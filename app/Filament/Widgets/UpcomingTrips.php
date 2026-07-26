<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Trips\TripResource;
use App\Models\Trip;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;

// Dashboard table of the nearest unfinished trips (planned, not yet past) for Admin/Superadmin.
class UpcomingTrips extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Nejbližší nedokončené výlety')
            ->query(
                Trip::query()
                    ->with('firstDay')
                    ->where('status', 'planned')
                    ->whereDate('date_to', '>=', Carbon::today()->toDateString())
                    ->orderBy('date_from')
            )
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('date_from')->label('Termín')->date('d.m.Y')->sortable()
                    ->description(function (Trip $record) {
                        $parts = [];
                        $time = $record->firstDay?->start_time ?: $record->firstDay?->meeting_time;
                        if ($time) {
                            $parts[] = ($record->firstDay?->start_time ? 'start ' : 'sraz ') . substr((string) $time, 0, 5);
                        }
                        if ($record->date_to && $record->date_to->ne($record->date_from)) {
                            $parts[] = 'do ' . $record->date_to->format('d.m.Y');
                        }

                        return $parts ? implode(' · ', $parts) : null;
                    }),
                TextColumn::make('name')->label('Výlet')->wrap()->searchable(),
                TextColumn::make('client_name')->label('Klient')->placeholder('—')->searchable(),
                TextColumn::make('type')->label('Typ')->badge()
                    ->formatStateUsing(fn (string $state) => Trip::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'rental' ? 'warning' : 'info'),
            ])
            ->recordUrl(fn (Trip $record) => TripResource::getUrl('edit', ['record' => $record]));
    }
}
