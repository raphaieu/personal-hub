<?php

namespace App\Enums\Events;

enum MercadoPagoEnvironment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';
}
