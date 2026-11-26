<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CharityMember extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'email',
        'full_name',
        'name',
        'dob',
        'years_of_service',
    ];

    // Keep personal member details out of default API and log serialization.
    protected $hidden = [
        'email',
        'full_name',
        'name',
        'dob',
        'years_of_service',
        'deleted_at',
    ];

    protected $casts = [
        'dob' => 'immutable_date',
        'years_of_service' => 'immutable_date',
    ];

    public function scopeAvailableForRequests(Builder $query, int $months = 3): Builder
    {
        $cutoff = now()->subMonths($months);

        // Recently departed members remain available for the source system's three-month request window.
        return $query->withTrashed()
            ->where(function (Builder $query) use ($cutoff) {
                $query->whereNull('deleted_at')
                    ->orWhere('deleted_at', '>=', $cutoff);
            });
    }

    public function requests(): HasMany
    {
        return $this->hasMany(CharityRequest::class, 'charity_member_id');
    }
}
