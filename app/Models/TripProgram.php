<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

// Program catalog (price lists). `name` = the exact WP trip page title (exact-match inquiry pairing).
class TripProgram extends Model
{
    protected $fillable = ['name', 'category', 'price_per_person', 'instructor_reward', 'number_of_days', 'meeting_offset_minutes', 'duration_minutes', 'description', 'active'];

    protected $casts = [
        'price_per_person' => 'decimal:2',
        'instructor_reward' => 'decimal:2',
        'number_of_days' => 'integer',
        'meeting_offset_minutes' => 'integer',
        'duration_minutes' => 'integer',
        'active' => 'boolean',
    ];

    // User-facing labels stay Czech; the DB values are English.
    public const CATEGORIES = [
        'school' => 'Školní výlety',
        'domestic' => 'Výlety v ČR',
        'foreign' => 'Zahraniční výlety',
    ];

    public const DEFAULT_MEETING_OFFSET = 50;

    public const NEXT_DAY_START = '09:00';

    public const NEXT_DAY_END = '15:00';

    /**
     * Sraz and konec for a day starting at $start: sraz = start − the program's offset; konec =
     * start + the program's duration on day 1, start + the default 9–15 length on later days.
     *
     * @return array{meeting_time: ?string, end_time: ?string}
     */
    public static function meetingAndEndFor(?self $program, int $order, ?string $start): array
    {
        if (blank($start)) {
            return ['meeting_time' => null, 'end_time' => null];
        }

        $start = Carbon::createFromFormat('H:i', substr($start, 0, 5));
        $offset = $program?->meeting_offset_minutes ?? self::DEFAULT_MEETING_OFFSET;
        $length = $order === 1
            ? $program?->duration_minutes
            : Carbon::parse(self::NEXT_DAY_START)->diffInMinutes(Carbon::parse(self::NEXT_DAY_END));

        return [
            'meeting_time' => $start->copy()->subMinutes($offset)->format('H:i'),
            'end_time' => $length ? $start->copy()->addMinutes((int) $length)->format('H:i') : null,
        ];
    }

    /**
     * Default times of the trip's day $order: day 1 starts at the entered time, later days 9–15.
     *
     * @return array{meeting_time: ?string, start_time: ?string, end_time: ?string}
     */
    public static function dayTimes(?self $program, int $order, ?string $firstDayStart): array
    {
        $start = $order === 1
            ? (filled($firstDayStart) ? substr($firstDayStart, 0, 5) : null)
            : self::NEXT_DAY_START;

        $derived = self::meetingAndEndFor($program, $order, $start);

        return ['meeting_time' => $derived['meeting_time'], 'start_time' => $start, 'end_time' => $derived['end_time']];
    }

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
