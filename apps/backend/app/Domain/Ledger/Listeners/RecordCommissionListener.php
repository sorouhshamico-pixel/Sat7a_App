<?php

namespace App\Domain\Ledger\Listeners;

use App\Domain\Ledger\Actions\RecordPaymentLedgerEntriesAction;
use App\Domain\Ledger\Actions\RecordRefundLedgerEntryAction;
use App\Domain\Payments\Events\PaymentCaptured;
use App\Domain\Payments\Events\RefundProcessed;

class RecordCommissionListener
{
    public function __construct(
        private readonly RecordPaymentLedgerEntriesAction $recordPaymentLedgerEntries,
        private readonly RecordRefundLedgerEntryAction $recordRefundLedgerEntry,
    ) {}

    public function handlePaymentCaptured(PaymentCaptured $event): void
    {
        $this->recordPaymentLedgerEntries->handle($event->payment);
    }

    public function handleRefundProcessed(RefundProcessed $event): void
    {
        $this->recordRefundLedgerEntry->handle($event->refund);
    }
}
