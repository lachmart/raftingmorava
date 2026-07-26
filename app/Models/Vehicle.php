<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Vehicle extends Model
{
    protected $fillable = ['name', 'license_plate', 'capacity', 'note', 'active'];

    protected $casts = ['active' => 'boolean', 'capacity' => 'integer'];

    /** A vehicle is assigned at the trip-day level (M:N). */
    public function tripDays(): BelongsToMany
    {
        return $this->belongsToMany(TripDay::class, 'trip_day_vehicle')->withTimestamps();
    }
}
