<?php

namespace App\Domain\Ledger\Enums;

/**
 * See docs/SETTLEMENT_ARCHITECTURE.md §Settlement batches. `failed` and
 * `cancelled` are both terminal — a failed transfer or a cancelled batch
 * is never retried in place; its claimed ledger entries are released
 * (see App\Domain\Ledger\Actions\AdvanceSettlementStatusAction) and a
 * fresh batch picks them up next time.
 */
enum SettlementStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingApproval, self::Cancelled],
            self::PendingApproval => [self::Approved, self::Cancelled],
            self::Approved => [self::Processing, self::Cancelled],
            self::Processing => [self::Paid, self::Failed],
            self::Paid, self::Failed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Once a batch reaches one of these, its claimed ledger entries must
     * be released back to the unclaimed pool.
     */
    public function releasesClaimedEntries(): bool
    {
        return in_array($this, [self::Failed, self::Cancelled], true);
    }
}
