<?php

namespace Tests\Unit\Domain\Disputes;

use App\Domain\Disputes\Enums\DisputeStatus;
use Tests\TestCase;

class DisputeStatusTest extends TestCase
{
    public function test_open_can_only_transition_to_under_review(): void
    {
        $this->assertTrue(DisputeStatus::Open->canTransitionTo(DisputeStatus::UnderReview));
        $this->assertFalse(DisputeStatus::Open->canTransitionTo(DisputeStatus::Resolved));
        $this->assertFalse(DisputeStatus::Open->canTransitionTo(DisputeStatus::Rejected));
    }

    public function test_under_review_can_reach_resolved_or_rejected(): void
    {
        $this->assertTrue(DisputeStatus::UnderReview->canTransitionTo(DisputeStatus::Resolved));
        $this->assertTrue(DisputeStatus::UnderReview->canTransitionTo(DisputeStatus::Rejected));
        $this->assertFalse(DisputeStatus::UnderReview->canTransitionTo(DisputeStatus::Open));
    }

    public function test_resolved_and_rejected_are_terminal(): void
    {
        $this->assertTrue(DisputeStatus::Resolved->isTerminal());
        $this->assertTrue(DisputeStatus::Rejected->isTerminal());
        $this->assertSame([], DisputeStatus::Resolved->allowedTransitions());
        $this->assertSame([], DisputeStatus::Rejected->allowedTransitions());
    }

    public function test_open_and_under_review_are_not_terminal(): void
    {
        $this->assertFalse(DisputeStatus::Open->isTerminal());
        $this->assertFalse(DisputeStatus::UnderReview->isTerminal());
    }

    public function test_every_status_transition_target_is_reachable_via_can_transition_to(): void
    {
        foreach (DisputeStatus::cases() as $status) {
            foreach ($status->allowedTransitions() as $target) {
                $this->assertTrue($status->canTransitionTo($target));
            }
        }
    }
}
