<?php

namespace App\Data;

use App\Enums\WhatsAppInboundRoute;

final readonly class WebhookRouteResolution
{
    public function __construct(
        public WhatsAppInboundRoute $route,
        public ?int $monitoredSourceId = null,
    ) {}
}
