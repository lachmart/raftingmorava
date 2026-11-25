<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One remembered table setting of a user (see PersistsTableLayoutPerUser). */
class TablePreference extends Model
{
    protected $fillable = ['user_id', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
