<?php

namespace App\Enums\Events;

enum GuestPaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Refunded = 'refunded';
    case Expired = 'expired';
}
