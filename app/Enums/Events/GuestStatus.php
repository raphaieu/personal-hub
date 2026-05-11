<?php


namespace App\Enums\Events;

enum GuestStatus: string
{
    case PendingEmail = 'pending_email';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Blocked = 'blocked';
}
