<?php

namespace App\Domain\Payments\Events;

use App\Domain\Payments\Models\Refund;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched only for a *succeeded* refund — see
 * App\Domain\Payments\Actions\RefundPaymentAction. Not a broadcast event
 * (no realtime need identified for refunds yet); today's only listener
 * is App\Domain\Ledger\Listeners\RecordCommissionListener. Implements
 * ShouldDispatchAfterCommit for the same reason as every other event
 * dispatched from inside a DB transaction in this codebase — see
 * docs/REALTIME.md.
 */
class RefundProcessed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Refund $refund) {}
}
