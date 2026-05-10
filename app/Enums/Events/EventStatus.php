<?php

declare(strict_types=1);

namespace App\Enums\Events;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Ended = 'ended';
    case Archived = 'archived';
}
