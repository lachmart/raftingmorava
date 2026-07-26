<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Trip extends Model
{
    use LogsActivity;

    protected $fillable = [
        'trip_program_id', 'type', 'name',
        'client_name', 'client_email', 'client_phone', 'onsite_phone',
        'date_from', 'date_to',
        'planned_paying_count', 'planned_escort_count', 'children_ages',
        'status', 'instructor_reward', 'total_price', 'price_manually_set', 'inquiry_id',
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
            ->logOnly(['name', 'type', 'status', 'date_from', 'date_to', 'planned_paying_count', 'instructor_reward', 'total_price', 'trip_program_id'])
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

        $sum = $this->equipmentItems()->with('equipment')->get()
            ->sum(fn (TripEquipment $item) => (float) ($item->equipment?->price_per_day ?? 0) * $item->quantity);

        $this->total_price = $sum * $days;
        $this->saveQuietly();
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
