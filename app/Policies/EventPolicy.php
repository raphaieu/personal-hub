<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

final class EventPolicy
{
    /**
     * Super admin opera qualquer evento (suporte e moderação da plataforma).
     */
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function view(User $user, Event $event): bool
    {
        return $event->owner_id === $user->id;
    }

    public function update(User $user, Event $event): bool
    {
        return $event->owner_id === $user->id;
    }

    public function delete(User $user, Event $event): bool
    {
        return $event->owner_id === $user->id;
    }

    /**
     * Portaria: só o dono (ou super admin) faz check-in dos convidados.
     */
    public function checkIn(User $user, Event $event): bool
    {
        return $event->owner_id === $user->id;
    }
}
