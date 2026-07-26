<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TripDay extends Model
{
    use LogsActivity;

    protected $fillable = [
        'trip_id', 'date', 'order',
        'meeting_time', 'start_time', 'end_time',
        'location_id',
    ];

    protected $casts = ['date' => 'date', 'order' => 'integer'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['date', 'order', 'meeting_time', 'start_time', 'end_time', 'location_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** A day may have multiple vehicles (M:N). */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'trip_day_vehicle')->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TripDayAssignment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TripNote::class);
    }
}
