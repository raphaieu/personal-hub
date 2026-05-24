<?php

namespace App\Enums\Events;

enum GuestTicketIssuanceResult: string
{
    case Confirmed = 'confirmed';
    case AlreadyConfirmed = 'already_confirmed';
    case CapacityReached = 'capacity_reached';
    case GuestNotFound = 'guest_not_found';
}
