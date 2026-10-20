<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Trip extends Model
{
    use LogsActivity;

    protected $fillable = [
        'trip_program_id', 'type', 'name',
        'client_name', 'customer_category', 'client_email', 'client_phone', 'onsite_phone',
        'date_from', 'date_to',
        'planned_paying_count', 'planned_escort_count', 'children_ages',
        'status', 'instructor_reward', 'total_price', 'price_manually_set', 'inquiry_id', 'reminder_sent_at',
        // settlement
        'actual_paying_count', 'actual_escort_count', 'amount_paid',
        'payment_method', 'document_number', 'payment_date',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'payment_date' => 'date',
        'instructor_reward' => 'decimal:2',
        'total_price' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'price_manually_set' => 'boolean',
        'reminder_sent_at' => 'datetime',
    ];

    // User-facing labels stay Czech; the DB values are English.
    public const TYPES = [
        'guided' => 'Akce s instruktorem',
        'rental' => 'Půjčovna',
    ];

    public const STATUSES = [
        'planned' => 'Naplánováno',
        'completed' => 'Proběhlo',
        'cancelled' => 'Zrušeno',
    ];

    public const PAYMENT_METHODS = [
        'invoice' => 'Faktura',
        'receipt' => 'Doklad',
        'cash' => 'Hotově',
    ];

    // Who the trip is for (not to be confused with the program's own category).
    public const CUSTOMER_CATEGORIES = [
        'school_nonprofit' => 'Školy a neziskovky',
        'company' => 'Firmy',
        'public' => 'Veřejnost',
    ];

    protected static function booted(): void
    {
        // Deliverable #5: for a guided trip pre-fill the reward from the program unless set manually.
        static::creating(function (Trip $trip) {
            if ($trip->instructor_reward === null && $trip->trip_program_id) {
                $trip->instructor_reward = $trip->program?->instructor_reward;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'type', 'status', 'trip_program_id',
                'date_from', 'date_to', 'planned_paying_count', 'planned_escort_count', 'children_ages',
                'instructor_reward', 'total_price',
                'client_name', 'customer_category', 'client_email', 'client_phone', 'onsite_phone',
                'actual_paying_count', 'actual_escort_count', 'amount_paid', 'payment_method', 'document_number', 'payment_date',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Recompute the price of a "rental" trip: SUM(price_per_day × qty) × number of calendar days.
     * Skipped when the admin overrode the price manually (price_manually_set).
     */
    public function recalculateRentalPrice(): void
    {
        if ($this->type !== 'rental' || $this->price_manually_set) {
            return;
        }

        $days = $this->date_from && $this->date_to
            ? $this->date_from->diffInDays($this->date_to) + 1
            : 1;

        // Per-item price override (price_per_unit) wins; empty falls back to the current catalog price.
        $sum = $this->equipmentItems()->with('equipment')->get()
            ->sum(fn (TripEquipment $item) => (float) ($item->price_per_unit ?? $item->equipment?->price_per_day ?? 0) * $item->quantity);

        $this->total_price = $sum * $days;
        $this->saveQuietly();
    }

    /** Longest allowed trip, in days – guards against typos like 16.7.2026–16.7.2027 (366 days). */
    public const MAX_DAYS = 31;

    /**
     * Creates one TripDay per calendar day between date_from and date_to (inclusive), using the
     * program's day-location template where available. No-op if the trip already has days, so it's
     * safe to call after every create without disturbing days added/edited manually afterwards.
     */
    public function generateDays(): void
    {
        if (! $this->date_from || $this->days()->exists()) {
            return;
        }

        $this->syncDays();
    }

    /**
     * Brings the trip days in line with date_from/date_to after the dates were changed: day N
     * moves to date_from + (N-1) and keeps its times, places, people and vehicles; missing days
     * are created (with the program's day template), days beyond the new length are deleted.
     *
     * @return int how many of the deleted days had someone assigned (so the caller can warn)
     */
    public function syncDays(): int
    {
        if (! $this->date_from) {
            return 0;
        }

        $from = $this->date_from->copy();
        $to = $this->date_to && $this->date_to->gte($from) ? $this->date_to : $from;
        $length = (int) $from->diffInDays($to) + 1;

        $template = $this->program
            ? $this->program->programDays()->get()->keyBy('order')
            : collect();

        $days = $this->days()->withCount('assignments')->get()->values();

        // Excess days go first, so re-dating the rest can never collide with them.
        $excess = $days->slice($length);
        $removedWithPeople = $excess->where('assignments_count', '>', 0)->count();
        $excess->each(fn (TripDay $day) => $day->delete());

        for ($order = 1; $order <= $length; $order++) {
            $date = $from->copy()->addDays($order - 1)->toDateString();
            $day = $days->get($order - 1);

            if ($day) {
                // Renumber too – manually added days may have left gaps in the order.
                $day->update(['date' => $date, 'order' => $order]);
            } else {
                $this->days()->create([
                    'date' => $date,
                    'order' => $order,
                    'location_id' => $template->get($order)?->location_id,
                    'end_location_id' => $template->get($order)?->end_location_id,
                ]);
            }
        }

        return $removedWithPeople;
    }

    /**
     * Deletes a single day and keeps the trip consistent: the remaining days are renumbered 1..N and
     * date_from/date_to shrink to the remaining days (e.g. the last day called off by a flood).
     * The trip itself is never deleted here, even when its last day goes.
     */
    public function removeDay(TripDay $day): void
    {
        $day->delete();

        $remaining = $this->days()->get();
        if ($remaining->isEmpty()) {
            return;
        }

        foreach ($remaining->values() as $i => $d) {
            if ($d->order !== $i + 1) {
                $d->update(['order' => $i + 1]);
            }
        }

        $this->update([
            'date_from' => $remaining->min('date')->toDateString(),
            'date_to' => $remaining->max('date')->toDateString(),
        ]);
        $this->recalculateRentalPrice();
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TripProgram::class, 'trip_program_id');
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(TripDay::class)->orderBy('order');
    }

    /** The first day of the trip (order 1) – used to show the trip's start time in listings. */
    public function firstDay(): HasOne
    {
        return $this->hasOne(TripDay::class)->orderBy('order');
    }

    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'trip_equipment')
            ->withPivot(['quantity', 'price_per_unit'])
            ->withTimestamps();
    }

    public function equipmentItems(): HasMany
    {
        return $this->hasMany(TripEquipment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TripNote::class)->latest();
    }

    /** All assignments across the trip's days. */
    public function assignments(): HasManyThrough
    {
        return $this->hasManyThrough(TripDayAssignment::class, TripDay::class);
    }

    public function isMultiDay(): bool
    {
        return $this->days()->count() > 1;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed' || $this->date_to?->isPast();
    }
}
