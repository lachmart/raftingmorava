<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Equipment extends Model
{
    // Irregular plural – the table is named "equipment".
    protected $table = 'equipment';

    protected $fillable = ['name', 'price_per_day', 'note', 'active'];

    protected $casts = ['price_per_day' => 'decimal:2', 'active' => 'boolean'];

    public function trips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'trip_equipment')
            ->withPivot(['quantity', 'price_per_unit'])
            ->withTimestamps();
    }
}
