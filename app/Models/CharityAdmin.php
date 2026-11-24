<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class CharityAdmin extends Model
{
    // Super access must only be changed through an authorized workflow.
    protected $fillable = [
        'employee',
    ];

    protected $hidden = [
        'employee',
        'super',
    ];

    protected $casts = [
        'super' => 'immutable_datetime',
    ];

    protected function employee(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => strtolower(trim($value)),
        );
    }

    public function hasSuperAccess(): bool
    {
        return $this->super !== null;
    }
}
