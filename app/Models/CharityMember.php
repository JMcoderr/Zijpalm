<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharityMember extends Model
{
    protected $fillable = [
        'email',
        'full_name',
    ];
}
