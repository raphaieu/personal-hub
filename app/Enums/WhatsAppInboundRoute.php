<?php

namespace App\Enums;

enum WhatsAppInboundRoute: string
{
    case Personal = 'personal';
    case Contact = 'contact';
    case Group = 'group';
    case Ignored = 'ignored';
}
