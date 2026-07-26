<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Program catalog (price lists). `name` = the exact WP trip page title (exact-match inquiry pairing).
class TripProgram extends Model
{
    protected $fillable = ['name', 'category', 'price_per_person', 'instructor_reward', 'meeting_offset_minutes', 'description', 'active'];

    protected $casts = [
        'price_per_person' => 'decimal:2',
        'instructor_reward' => 'decimal:2',
        'meeting_offset_minutes' => 'integer',
        'active' => 'boolean',
    ];

    // User-facing labels stay Czech; the DB values are English.
    public const CATEGORIES = [
        'school' => 'Školní výlety',
        'domestic' => 'Výlety v ČR',
        'foreign' => 'Zahraniční výlety',
    ];

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /** Day-by-day location template for multi-day routes (order → location). */
    public function programDays(): HasMany
    {
        return $this->hasMany(TripProgramDay::class)->orderBy('order');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
