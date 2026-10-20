<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// A staff meeting (schůze) – shown to everyone in the calendar.
class Meeting extends Model
{
    protected $fillable = ['name', 'date', 'time', 'place', 'note'];

    protected $casts = ['date' => 'date'];
}
