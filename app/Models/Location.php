<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = ['name', 'certainty', 'address', 'note', 'active'];

    protected $casts = ['active' => 'boolean'];

    // User-facing labels stay Czech; the DB values are English.
    public const CERTAINTIES = [
        'confirmed' => 'Jisté',
        'optional' => 'Doplňkové',
    ];

    public function tripDays(): HasMany
    {
        return $this->hasMany(TripDay::class);
    }
}
