<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MessageLog;
use App\Models\User;

final class MessageLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function view(User $user, MessageLog $messageLog): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function reprocessAnalysis(User $user, MessageLog $messageLog): bool
    {
        return $user->hasVerifiedEmail();
    }
}
