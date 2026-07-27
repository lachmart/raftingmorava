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

    // Czech labels for the logged fields (see each model's getActivitylogOptions()).
    private const FIELD_LABELS = [
        // Trip
        'name' => 'Název', 'type' => 'Typ', 'status' => 'Stav', 'trip_program_id' => 'Program',
        'date_from' => 'Termín od', 'date_to' => 'Termín do',
        'planned_paying_count' => 'Platících', 'planned_escort_count' => 'Doprovod', 'children_ages' => 'Věk dětí',
        'instructor_reward' => 'Odměna', 'total_price' => 'Cena',
        'client_name' => 'Klient', 'client_email' => 'E-mail klienta', 'client_phone' => 'Telefon klienta', 'onsite_phone' => 'Kontakt na místě',
        'actual_paying_count' => 'Platících (skut.)', 'actual_escort_count' => 'Doprovod (skut.)',
        'amount_paid' => 'Zaplaceno', 'payment_method' => 'Způsob úhrady', 'document_number' => 'Číslo dokladu', 'payment_date' => 'Datum úhrady',
        // Trip day
        'date' => 'Datum', 'order' => 'Pořadí', 'meeting_time' => 'Sraz', 'start_time' => 'Start',
        'end_time' => 'Konec', 'location_id' => 'Lokace (ID)',
        // Assignment
        'user_id' => 'Osoba (ID)', 'reward' => 'Odměna', 'paid' => 'Vyplaceno', 'paid_date' => 'Datum výplaty',
    ];

    /** Human-readable summary of what changed: "Sraz: 08:00 → 08:30; Vyplaceno: ne → ano". */
    public static function changeSummary($record): string
    {
        $props = $record->properties;
        $new = collect(data_get($props, 'attributes', []));
        $old = collect(data_get($props, 'old', []));

        if ($new->isEmpty()) {
            return '—';
        }

        return $new->map(function ($value, $key) use ($old) {
            $label = self::FIELD_LABELS[$key] ?? $key;
            $newVal = self::formatValue($value);

            if ($old->has($key) && $old->get($key) !== $value) {
                return $label . ': ' . self::formatValue($old->get($key)) . ' → ' . $newVal;
            }

            return $label . ': ' . $newVal;
        })->implode('; ');
    }

    private static function formatValue($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'ano' : 'ne';
        }
        // ISO date/time strings → short Czech date.
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T/', $value)) {
            return \Illuminate\Support\Carbon::parse($value)->format('d.m.Y');
        }
        // Time "HH:MM:SS" → "HH:MM".
        if (is_string($value) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return substr($value, 0, 5);
        }

        return (string) $value;
    }

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
                // Read the whole record (Filament would otherwise iterate the properties collection
                // and call the formatter per element, losing the "attributes"/"old" structure).
                TextColumn::make('changes')->label('Změny')->wrap()
                    ->state(fn ($record) => self::changeSummary($record)),
            ])
            ->paginated([10, 25, 50]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
