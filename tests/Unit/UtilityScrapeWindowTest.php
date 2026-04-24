<?php

namespace Tests\Unit;

use App\Models\UtilityAccount;
use App\Support\UtilityScrapeWindow;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UtilityScrapeWindowTest extends TestCase
{
    #[Test]
    public function it_accepts_day_within_lead_window_before_due(): void
    {
        $account = new UtilityAccount([
            'due_day' => 10,
            'reminder_lead_days' => 5,
        ]);

        $today = Carbon::parse('2026-03-07')->startOfDay();
        $this->assertTrue(UtilityScrapeWindow::isWithinWindow($account, $today));
    }

    #[Test]
    public function it_rejects_day_before_lead_window(): void
    {
        $account = new UtilityAccount([
            'due_day' => 10,
            'reminder_lead_days' => 5,
        ]);

        $today = Carbon::parse('2026-03-03')->startOfDay();
        $this->assertFalse(UtilityScrapeWindow::isWithinWindow($account, $today));
    }

    #[Test]
    public function it_accepts_day_within_thirty_days_after_due(): void
    {
        $account = new UtilityAccount([
            'due_day' => 10,
            'reminder_lead_days' => 5,
        ]);

        $today = Carbon::parse('2026-04-05')->startOfDay();
        $this->assertTrue(UtilityScrapeWindow::isWithinWindow($account, $today));
    }

    #[Test]
    public function it_rejects_day_after_thirty_days_post_due(): void
    {
        $account = new UtilityAccount([
            'due_day' => 10,
            'reminder_lead_days' => 5,
        ]);

        $today = Carbon::parse('2026-04-15')->startOfDay();
        $this->assertFalse(UtilityScrapeWindow::isWithinWindow($account, $today));
    }
}
