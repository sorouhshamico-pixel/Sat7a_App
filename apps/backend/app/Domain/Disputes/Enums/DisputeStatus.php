<?php

namespace App\Domain\Disputes\Enums;

/**
 * `open` only ever advances to `under_review` — a member of staff must
 * explicitly "pick up" a dispute (which is what records `assigned_to`,
 * see App\Domain\Disputes\Actions\AdvanceDisputeStatusAction) before it
 * can be resolved or rejected. There is no shortcut straight from `open`
 * to a terminal state.
 */
enum DisputeStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::UnderReview],
            self::UnderReview => [self::Resolved, self::Rejected],
            self::Resolved, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
