<?php

namespace App;

enum CharityRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Exported = 'exported';
    case Paid = 'paid';
}
