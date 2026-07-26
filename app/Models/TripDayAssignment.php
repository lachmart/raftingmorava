<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

// Assignment of a user (instructor/driver) to a trip day + reward bookkeeping.
class TripDayAssignment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'trip_day_id', 'user_id', 'type',
        'reward', 'paid', 'paid_date',
    ];

    protected $casts = [
        'reward' => 'decimal:2',
        'paid' => 'boolean',
        'paid_date' => 'date',
    ];

    // User-facing labels stay Czech; the DB values are English.
    public const TYPES = [
        'instructor' => 'Instruktor',
        'driver' => 'Řidič',
    ];

    protected static function booted(): void
    {
        // Deliverable #5: reward is pre-filled unless set manually:
        //  - instructor → FULL trip reward (no splitting between several people),
        //  - driver → 0 CZK (the price list has no driving rate).
        static::creating(function (TripDayAssignment $a) {
            if ($a->reward === null) {
                $a->reward = $a->type === 'driver'
                    ? 0
                    : $a->tripDay?->trip?->instructor_reward;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'type', 'reward', 'paid', 'paid_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tripDay(): BelongsTo
    {
        return $this->belongsTo(TripDay::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
