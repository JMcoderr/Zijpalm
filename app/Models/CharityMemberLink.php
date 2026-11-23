<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharityMemberLink extends Model
{
    protected $fillable = [
        'source_member_id',
        'user_id',
    ];

    protected $casts = [
        'source_member_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        // Historical links must still resolve after the account is soft-deleted.
        return $this->belongsTo(User::class)->withTrashed();
    }
}
