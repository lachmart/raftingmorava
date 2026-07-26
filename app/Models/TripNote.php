<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A note (comment) on a trip, optionally on a specific day. Chronological thread – author + time.
class TripNote extends Model
{
    protected $fillable = ['trip_id', 'trip_day_id', 'author_id', 'text'];

    protected static function booted(): void
    {
        // Author = the logged-in user unless set manually.
        static::creating(function (TripNote $note) {
            $note->author_id ??= auth()->id();
        });
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function tripDay(): BelongsTo
    {
        return $this->belongsTo(TripDay::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
