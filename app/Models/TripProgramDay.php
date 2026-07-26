<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One day of a program's location template (order → location). See TripProgram::programDays().
class TripProgramDay extends Model
{
    protected $fillable = ['trip_program_id', 'order', 'location_id'];

    protected $casts = ['order' => 'integer'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TripProgram::class, 'trip_program_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
