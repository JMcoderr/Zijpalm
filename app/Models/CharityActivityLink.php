<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharityActivityLink extends Model
{
    protected $fillable = [
        'source_event_id',
        'activity_id',
    ];

    protected $casts = [
        'source_event_id' => 'integer',
        'activity_id' => 'integer',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
