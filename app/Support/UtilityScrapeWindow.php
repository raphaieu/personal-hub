<?php

namespace App\Support;

use App\Models\UtilityAccount;
use Carbon\CarbonInterface;

final class UtilityScrapeWindow
{
    /**
     * Conta está na janela: desde (próximo vencimento − reminder_lead_days) até (próximo vencimento + 30 dias), inclusive.
     */
    public static function isWithinWindow(UtilityAccount $account, ?CarbonInterface $today = null): bool
    {
        $today = ($today ?? now())->startOfDay();
        $nextDue = self::resolveNextDueDate($today, (int) $account->due_day);
        $start = $nextDue->copy()->subDays((int) $account->reminder_lead_days)->startOfDay();
        $end = $nextDue->copy()->addDays(30)->endOfDay();

        return $today->betweenIncluded($start, $end);
    }

    /**
     * Próxima data de vencimento (calendário) a partir do dia de vencimento habitual da conta.
     */
    public static function resolveNextDueDate(CarbonInterface $today, int $dueDay): CarbonInterface
    {
        $monthStart = $today->copy()->startOfMonth();
        $dayThisMonth = min(max(1, $dueDay), (int) $monthStart->daysInMonth);
        $candidate = $monthStart->copy()->day($dayThisMonth)->startOfDay();

        if ($candidate->lt($today->copy()->startOfDay())) {
            $nextMonth = $monthStart->copy()->addMonthNoOverflow()->startOfDay();
            $dayNext = min(max(1, $dueDay), (int) $nextMonth->daysInMonth);

            return $nextMonth->copy()->day($dayNext)->startOfDay();
        }

        return $candidate;
    }
}
