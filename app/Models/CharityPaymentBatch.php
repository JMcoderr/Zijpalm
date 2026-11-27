<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CharityPaymentBatch extends Model
{
    protected $fillable = [
        'message_id',
        'debtor_name',
        'debtor_iban',
        'execution_date',
        'transaction_count',
        'total_amount_cents',
        'xml_content',
        'status',
        'exported_at',
        'paid_at',
    ];

    protected $hidden = [
        'debtor_name',
        'debtor_iban',
        'xml_content',
    ];

    protected $casts = [
        'debtor_name' => 'encrypted',
        'debtor_iban' => 'encrypted',
        'execution_date' => 'immutable_date',
        'transaction_count' => 'integer',
        'total_amount_cents' => 'integer',
        'xml_content' => 'encrypted',
        'exported_at' => 'immutable_datetime',
        'paid_at' => 'immutable_datetime',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(CharityRequest::class);
    }
}
