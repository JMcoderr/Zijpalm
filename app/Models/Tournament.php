<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    protected $fillable = [
        'name',
        'description',
        'starts_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
    ];
}
