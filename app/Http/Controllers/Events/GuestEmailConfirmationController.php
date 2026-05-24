<?php

namespace App\Http\Controllers\Events;

use App\Enums\Events\GuestTicketIssuanceResult;
use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Services\Events\GuestTicketIssuanceService;
use Illuminate\Contracts\View\View;

final class GuestEmailConfirmationController extends Controller
{
    public function __construct(
        private readonly GuestTicketIssuanceService $ticketIssuanceService,
    ) {}

    public function __invoke(string $token): View
    {
        $guest = Guest::query()->where('email_confirmation_token', $token)->first();

        if ($guest === null) {
            return view('events.email-confirm-invalid');
        }

        $guest->loadMissing('event');

        $result = $this->ticketIssuanceService->confirmAndSendTicket($guest);

        return match ($result) {
            GuestTicketIssuanceResult::AlreadyConfirmed => view('events.email-already-confirmed', ['guest' => $guest->refresh()]),
            GuestTicketIssuanceResult::CapacityReached => view('events.email-confirm-capacity'),
            GuestTicketIssuanceResult::Confirmed => view('events.email-confirmed-success', ['guest' => $guest->refresh()]),
            GuestTicketIssuanceResult::GuestNotFound => view('events.email-confirm-invalid'),
        };
    }
}
