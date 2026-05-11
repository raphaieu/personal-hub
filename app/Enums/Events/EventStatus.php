<?php


namespace App\Enums\Events;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Ended = 'ended';
    case Archived = 'archived';
}
