<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CharityEvent extends Model
{
    protected $fillable = [
        'source_event_id',
        'title',
    ];

    protected $casts = [
        'source_event_id' => 'integer',
    ];

    public function eventCosts(): HasMany
    {
        return $this->hasMany(CharityEventCost::class);
    }
}
