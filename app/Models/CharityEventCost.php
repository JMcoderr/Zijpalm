<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CharityEventCost extends Model
{
    protected $fillable = [
        'charity_event_id',
        'amount',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CharityEvent::class, 'charity_event_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(CharityRequest::class, 'charity_event_cost_id');
    }
}
