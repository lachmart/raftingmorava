<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Inquiry from WordPress (CF7). Program pairing is exact-match on proposed_program_name.
class Inquiry extends Model
{
    protected $fillable = [
        'program_id', 'proposed_program_name', 'approximate_dates',
        'client_name', 'client_email', 'client_phone', 'people_count', 'note',
        'status', 'source', 'trip_id',
    ];

    // User-facing labels stay Czech; the DB values are English.
    public const STATUSES = [
        'new' => 'Nová',
        'in_progress' => 'V jednání',
        'confirmed' => 'Potvrzená',
        'rejected' => 'Zamítnutá',
    ];

    public const SOURCES = [
        'web' => 'Web (formulář)',
        'manual' => 'Ruční zadání',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TripProgram::class, 'program_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
