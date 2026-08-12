<?php

namespace Tests\Unit\Domain\Orders;

use App\Domain\Orders\Enums\OrderStatus;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_pending_can_transition_to_searching_provider(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::SearchingProvider));
    }

    public function test_pending_cannot_transition_directly_to_completed(): void
    {
        $this->assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Completed));
    }

    public function test_a_terminal_status_has_no_further_transitions(): void
    {
        $this->assertSame([], OrderStatus::Expired->allowedTransitions());
        $this->assertSame([], OrderStatus::Refunded->allowedTransitions());
    }

    public function test_trip_started_cannot_be_cancelled(): void
    {
        $this->assertFalse(OrderStatus::TripStarted->canTransitionTo(OrderStatus::CancelledByCustomer));
        $this->assertFalse(OrderStatus::InTransit->canTransitionTo(OrderStatus::CancelledByCustomer));
    }

    public function test_customer_cancellable_statuses_exclude_everything_from_trip_started_onward(): void
    {
        $cancellable = OrderStatus::customerCancellable();

        $this->assertContains(OrderStatus::VehicleLoading, $cancellable);
        $this->assertNotContains(OrderStatus::TripStarted, $cancellable);
        $this->assertNotContains(OrderStatus::InTransit, $cancellable);
        $this->assertNotContains(OrderStatus::Completed, $cancellable);
    }

    public function test_is_customer_cancellable_matches_the_catalog(): void
    {
        $this->assertTrue(OrderStatus::Pending->isCustomerCancellable());
        $this->assertFalse(OrderStatus::TripStarted->isCustomerCancellable());
    }

    public function test_every_status_transition_target_is_reachable_via_can_transition_to(): void
    {
        foreach (OrderStatus::cases() as $status) {
            foreach ($status->allowedTransitions() as $target) {
                $this->assertTrue($status->canTransitionTo($target));
            }
        }
    }
}
