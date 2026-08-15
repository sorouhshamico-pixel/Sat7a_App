<?php

namespace Tests\Unit\Domain\Ledger;

use App\Domain\Ledger\Enums\SettlementStatus;
use Tests\TestCase;

class SettlementStatusTest extends TestCase
{
    public function test_draft_can_transition_to_pending_approval_or_cancelled(): void
    {
        $this->assertTrue(SettlementStatus::Draft->canTransitionTo(SettlementStatus::PendingApproval));
        $this->assertTrue(SettlementStatus::Draft->canTransitionTo(SettlementStatus::Cancelled));
        $this->assertFalse(SettlementStatus::Draft->canTransitionTo(SettlementStatus::Paid));
    }

    public function test_processing_can_only_reach_paid_or_failed(): void
    {
        $this->assertTrue(SettlementStatus::Processing->canTransitionTo(SettlementStatus::Paid));
        $this->assertTrue(SettlementStatus::Processing->canTransitionTo(SettlementStatus::Failed));
        $this->assertFalse(SettlementStatus::Processing->canTransitionTo(SettlementStatus::Cancelled));
    }

    public function test_terminal_statuses_have_no_further_transitions(): void
    {
        $this->assertSame([], SettlementStatus::Paid->allowedTransitions());
        $this->assertSame([], SettlementStatus::Failed->allowedTransitions());
        $this->assertSame([], SettlementStatus::Cancelled->allowedTransitions());
    }

    public function test_only_failed_and_cancelled_release_claimed_entries(): void
    {
        $this->assertTrue(SettlementStatus::Failed->releasesClaimedEntries());
        $this->assertTrue(SettlementStatus::Cancelled->releasesClaimedEntries());
        $this->assertFalse(SettlementStatus::Paid->releasesClaimedEntries());
        $this->assertFalse(SettlementStatus::Draft->releasesClaimedEntries());
    }

    public function test_every_status_transition_target_is_reachable_via_can_transition_to(): void
    {
        foreach (SettlementStatus::cases() as $status) {
            foreach ($status->allowedTransitions() as $target) {
                $this->assertTrue($status->canTransitionTo($target));
            }
        }
    }
}
