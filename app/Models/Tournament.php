<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    protected $fillable = [
        'name',
        'team_count',
        'description',
        'starts_at',
    ];

    protected $casts = [
        'team_count' => 'integer',
        'starts_at' => 'datetime',
    ];
}
