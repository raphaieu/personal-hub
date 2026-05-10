<?php

declare(strict_types=1);

namespace App\Enums\Events;

enum GuestStatus: string
{
    case PendingEmail = 'pending_email';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Blocked = 'blocked';
}
