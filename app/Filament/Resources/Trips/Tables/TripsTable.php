<?php

namespace App\Filament\Resources\Trips\Tables;

use App\Filament\Resources\Trips\TripResource;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripProgram;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripsTable
{
    /**
     * One row per trip DAY (a 3-day trip = 3 rows, like Planning), so a continuing program is visible
     * on every date it occupies. Everything trip-level (statistics, settlement export, rewards)
     * still counts the trip once – this only changes what the list shows.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->query(
                TripDay::query()
                    ->with(['trip' => fn ($q) => $q->withCount('days'), 'trip.program', 'location', 'endLocation'])
            )
            ->recordUrl(fn (TripDay $record) => TripResource::getUrl('edit', ['record' => $record->trip_id]))
            // Every column can be hidden and reordered (column manager), and the chosen sort/filters
            // survive navigating away and back within the session.
            ->reorderableColumns()
            ->persistSortInSession()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            // Days that are already over are muted so the upcoming ones stand out.
            ->recordClasses(fn (TripDay $record) => $record->date?->lt(Carbon::today()) ? 'opacity-50' : null)
            ->columns([
                TextColumn::make('trip.name')->label('Název')->sortable()->wrap()->toggleable()
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('trip', fn ($q) => $q->where('name', 'like', "%{$search}%")))
                    ->description(fn (TripDay $record) => ($record->trip?->days_count ?? 1) > 1
                        ? 'den ' . $record->order . '/' . $record->trip->days_count
                        : null),
                TextColumn::make('trip.type')->label('Typ')->badge()->toggleable()
                    ->formatStateUsing(fn (string $state) => Trip::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'rental' ? 'warning' : 'info'),
                TextColumn::make('trip.program.name')->label('Program')->placeholder('—')->toggleable(isToggledHiddenByDefault: true)->wrap(),
                TextColumn::make('trip.client_name')->label('Klient')->placeholder('—')->toggleable()->wrap()
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('trip', fn ($q) => $q->where('client_name', 'like', "%{$search}%"))),
                TextColumn::make('date')->label('Datum')->date('d.m.Y')->sortable()->toggleable()
                    ->description(fn (TripDay $record) => Str::ucfirst($record->date?->translatedFormat('l') ?? '')),
                // The day's start (or meeting time when the start isn't set yet).
                TextColumn::make('time')->label('Čas')->toggleable()->placeholder('—')
                    ->state(function (TripDay $record) {
                        $time = $record->start_time ?: $record->meeting_time;

                        return $time ? ($record->start_time ? 'start ' : 'sraz ') . substr((string) $time, 0, 5) : null;
                    }),
                TextColumn::make('section')->label('Úsek')->toggleable()->wrap()->placeholder('—')
                    ->state(fn (TripDay $record) => $record->sectionLabel()),
                TextColumn::make('trip.planned_paying_count')->label('Platících')->numeric()->toggleable()
                    ->formatStateUsing(fn ($state, TripDay $record) => $state . ($record->trip?->planned_escort_count ? ' + ' . $record->trip->planned_escort_count . ' dopr.' : '')),
                TextColumn::make('trip.status')->label('Stav')->badge()->toggleable()
                    ->formatStateUsing(fn (string $state) => Trip::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'planned' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                // Money belongs to the whole trip – shown on its first day only, so it isn't read as 3×.
                TextColumn::make('reward')->label('Odměna')->toggleable(isToggledHiddenByDefault: true)->placeholder('—')
                    ->state(fn (TripDay $record) => $record->order === 1 ? $record->trip?->instructor_reward : null)
                    ->money('CZK', locale: 'cs'),
                TextColumn::make('price')->label('Cena')->toggleable(isToggledHiddenByDefault: true)->placeholder('—')
                    ->state(fn (TripDay $record) => $record->order === 1 ? $record->trip?->total_price : null)
                    ->money('CZK', locale: 'cs'),
            ])
            // Soonest day first (together with the default "from today" filter below).
            ->defaultSort(fn (Builder $query) => $query->orderBy('date')->orderBy('start_time')->orderBy('trip_id'))
            ->filters([
                // By default only days from today on; any period (history too) can be picked.
                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')->label('Termín od')->native(false)->displayFormat('d.m.Y')
                            ->default(fn () => Carbon::today()->toDateString()),
                        DatePicker::make('until')->label('Termín do')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->columns(2)
                    ->query(fn ($query, array $data) => $query
                        // The picker state may carry a time part – compare plain dates.
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', Carbon::parse($v)->toDateString()))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', Carbon::parse($v)->toDateString())))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'Od ' . Carbon::parse($data['from'])->format('d.m.Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Do ' . Carbon::parse($data['until'])->format('d.m.Y');
                        }

                        return $indicators;
                    }),
                self::tripFilter('type', 'Typ', Trip::TYPES),
                self::tripFilter('status', 'Stav', Trip::STATUSES),
                self::tripFilter('trip_program_id', 'Program', fn () => TripProgram::orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
            ])
            ->headerActions([
                self::exportSettlementAction(),
            ])
            ->recordActions([
                Action::make('sheet')
                    ->label('Tisk rozpisu')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (TripDay $record) => route('trip.sheet', $record->trip_id))
                    ->openUrlInNewTab(),
                Action::make('edit')
                    ->label('Upravit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (TripDay $record) => TripResource::getUrl('edit', ['record' => $record->trip_id])),
                self::deleteAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Selected rows are days, but this removes their whole trips (with all days).
                    BulkAction::make('deleteTrips')
                        ->label('Smazat vybrané výlety')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Smažou se celé výlety (všechny jejich dny), ke kterým patří vybrané řádky.')
                        ->visible(fn () => Gate::allows('deleteAny', Trip::class))
                        ->action(fn (Collection $records) => Trip::whereIn('id', $records->pluck('trip_id')->unique())->get()->each->delete())
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /** A filter on a trip-level field (the rows are days, so it goes through the trip relation). */
    private static function tripFilter(string $field, string $label, array|\Closure $options): SelectFilter
    {
        return SelectFilter::make($field)->label($label)->options($options)
            ->query(fn (Builder $query, array $data) => filled($data['value'])
                ? $query->whereHas('trip', fn ($q) => $q->where($field, $data['value']))
                : $query);
    }

    /**
     * Deleting a row of a multi-day trip asks what to delete: usually the whole trip, but sometimes
     * only that day (e.g. the last day is called off because of a flood).
     */
    private static function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Smazat')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (TripDay $record) => 'Smazat – ' . $record->trip?->name)
            ->modalDescription(fn (TripDay $record) => ($record->trip?->days_count ?? 1) > 1
                ? null
                : 'Smaže se celý výlet.')
            ->schema(fn (TripDay $record) => ($record->trip?->days_count ?? 1) > 1 ? [
                Radio::make('scope')->hiddenLabel()->required()->default('trip')
                    ->options([
                        'trip' => 'Celý výlet (všech ' . $record->trip->days_count . ' dní)',
                        'day' => 'Jen tento den – den ' . $record->order . ', ' . $record->date?->format('d.m.Y'),
                    ]),
            ] : [])
            ->visible(fn (TripDay $record) => Gate::allows('delete', $record->trip))
            ->action(function (TripDay $record, array $data) {
                if (($data['scope'] ?? 'trip') === 'day') {
                    $record->trip->removeDay($record);
                    Notification::make()->success()->title('Den smazán, termín výletu upraven')->send();

                    return;
                }

                $record->trip->delete();
                Notification::make()->success()->title('Výlet smazán')->send();
            });
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
