<?php

namespace App\Models;

use App\CharityRequestStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharityRequest extends Model
{
    protected $fillable = [
        'requester_email',
        'charity_member_id',
        'charity_event_cost_id',
        'account_name',
        'iban',
    ];

    protected $hidden = [
        'requester_email',
        'account_name',
        'iban',
        'reason',
    ];

    protected $casts = [
        'status' => CharityRequestStatus::class,
        'iban' => 'encrypted',
        'paid_at' => 'immutable_datetime',
    ];

    protected function requesterEmail(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => strtolower(trim($value)),
        );
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(CharityMember::class, 'charity_member_id');
    }

    public function eventCost(): BelongsTo
    {
        return $this->belongsTo(CharityEventCost::class, 'charity_event_cost_id');
    }

    public function review(CharityRequestStatus $status, ?string $reason = null): bool
    {
        if ($this->status !== CharityRequestStatus::Pending
            || ! in_array($status, [CharityRequestStatus::Accepted, CharityRequestStatus::Rejected], true)
        ) {
            return false;
        }

        $this->status = $status;
        $this->reason = $status === CharityRequestStatus::Rejected ? $reason : null;

        if ($status === CharityRequestStatus::Rejected) {
            // Rejected requests will not be paid, so their bank details can be cleared.
            $this->account_name = null;
            $this->iban = null;
        }

        return $this->save();
    }
}
