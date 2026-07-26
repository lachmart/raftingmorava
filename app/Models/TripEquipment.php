<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Trip ↔ Equipment pivot row as a model (to edit the quantity via a Repeater in the trip form).
class TripEquipment extends Model
{
    protected $table = 'trip_equipment';

    protected $fillable = ['trip_id', 'equipment_id', 'quantity', 'price_per_unit'];

    protected $casts = ['quantity' => 'integer', 'price_per_unit' => 'decimal:2'];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
