<?php

namespace App\Filament\Resources\Trips\Tables;

use App\Models\Trip;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('firstDay'))
            ->columns([
                TextColumn::make('name')->label('Název')->searchable()->sortable()->wrap(),
                TextColumn::make('type')->label('Typ')->badge()
                    ->formatStateUsing(fn (string $state) => Trip::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'rental' ? 'warning' : 'info'),
                TextColumn::make('program.name')->label('Program')->placeholder('—')->toggleable()->wrap(),
                TextColumn::make('client_name')->label('Klient')->searchable()->placeholder('—'),
                TextColumn::make('date_from')->label('Od')->date('d.m.Y')->sortable()
                    ->description(function (Trip $record) {
                        $time = $record->firstDay?->start_time ?: $record->firstDay?->meeting_time;

                        return $time ? ($record->firstDay?->start_time ? 'start ' : 'sraz ') . substr((string) $time, 0, 5) : null;
                    }),
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
            ->headerActions([
                self::exportSettlementAction(),
            ])
            ->recordActions([
                Action::make('sheet')
                    ->label('Tisk rozpisu')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Trip $record) => route('trip.sheet', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Header action: download an accounting CSV of trip settlements, optionally filtered by date. */
    private static function exportSettlementAction(): Action
    {
        return Action::make('exportSettlement')
            ->label('Export vyúčtování (CSV)')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->schema([
                DatePicker::make('from')->label('Termín od')->native(false)->displayFormat('d.m.Y'),
                DatePicker::make('to')->label('Termín do')->native(false)->displayFormat('d.m.Y'),
            ])
            ->action(function (array $data): StreamedResponse {
                return response()->streamDownload(function () use ($data) {
                    $out = fopen('php://output', 'w');
                    fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Czech characters
                    fputcsv($out, ['Výlet', 'Termín od', 'Termín do', 'Typ', 'Stav', 'Platících (skut.)', 'Doprovod (skut.)', 'Zaplaceno (Kč)', 'Způsob úhrady', 'Číslo dokladu', 'Datum úhrady']);

                    Trip::query()
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('date_from', '>=', $v))
                        ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('date_from', '<=', $v))
                        ->orderBy('date_from')
                        ->get()
                        ->each(function (Trip $trip) use ($out) {
                            fputcsv($out, [
                                $trip->name,
                                $trip->date_from?->format('d.m.Y'),
                                $trip->date_to?->format('d.m.Y'),
                                Trip::TYPES[$trip->type] ?? $trip->type,
                                Trip::STATUSES[$trip->status] ?? $trip->status,
                                $trip->actual_paying_count,
                                $trip->actual_escort_count,
                                $trip->amount_paid,
                                Trip::PAYMENT_METHODS[$trip->payment_method] ?? $trip->payment_method,
                                $trip->document_number,
                                $trip->payment_date?->format('d.m.Y'),
                            ]);
                        });

                    fclose($out);
                }, 'vyuctovani-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }
}
