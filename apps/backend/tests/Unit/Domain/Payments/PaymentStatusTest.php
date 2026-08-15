<?php

namespace Tests\Unit\Domain\Payments;

use App\Domain\Payments\Enums\PaymentStatus;
use Tests\TestCase;

class PaymentStatusTest extends TestCase
{
    public function test_pending_can_transition_to_captured(): void
    {
        $this->assertTrue(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Captured));
    }

    public function test_pending_cannot_transition_directly_to_refunded(): void
    {
        $this->assertFalse(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Refunded));
    }

    public function test_terminal_statuses_have_no_further_transitions(): void
    {
        $this->assertSame([], PaymentStatus::Failed->allowedTransitions());
        $this->assertSame([], PaymentStatus::Cancelled->allowedTransitions());
        $this->assertSame([], PaymentStatus::Refunded->allowedTransitions());
    }

    public function test_captured_can_be_partially_refunded_more_than_once(): void
    {
        $this->assertTrue(PaymentStatus::PartiallyRefunded->canTransitionTo(PaymentStatus::PartiallyRefunded));
        $this->assertTrue(PaymentStatus::PartiallyRefunded->canTransitionTo(PaymentStatus::Refunded));
    }

    public function test_is_refundable_matches_the_catalog(): void
    {
        $this->assertTrue(PaymentStatus::Captured->isRefundable());
        $this->assertTrue(PaymentStatus::PartiallyRefunded->isRefundable());
        $this->assertFalse(PaymentStatus::Pending->isRefundable());
        $this->assertFalse(PaymentStatus::Failed->isRefundable());
    }

    public function test_every_status_transition_target_is_reachable_via_can_transition_to(): void
    {
        foreach (PaymentStatus::cases() as $status) {
            foreach ($status->allowedTransitions() as $target) {
                $this->assertTrue($status->canTransitionTo($target));
            }
        }
    }
}
