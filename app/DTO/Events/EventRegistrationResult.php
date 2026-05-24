<?php

namespace App\DTO\Events;

use App\Models\Guest;

final readonly class EventRegistrationResult
{
    public function __construct(
        public Guest $guest,
        public string $flow,
        public ?string $checkoutUrl = null,
    ) {}

    public function isCheckout(): bool
    {
        return $this->flow === 'checkout';
    }
}
