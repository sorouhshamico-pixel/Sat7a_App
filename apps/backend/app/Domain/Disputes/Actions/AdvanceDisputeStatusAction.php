<?php

namespace App\Domain\Disputes\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Disputes\Enums\DisputeStatus;
use App\Domain\Disputes\Exceptions\DisputeException;
use App\Domain\Disputes\Models\Dispute;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single place a dispute's status ever changes — mirrors
 * App\Domain\Ledger\Actions\AdvanceSettlementStatusAction. Moving to
 * `under_review` records who picked it up (`assigned_to`); moving to a
 * terminal state (`resolved`/`rejected`) requires resolution notes and
 * records who closed it. Every transition is audit-logged — a customer's
 * dispute outcome is exactly the kind of sensitive staff decision
 * `docs/SECURITY.md` §Audited actions expects to be traceable.
 */
class AdvanceDisputeStatusAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @throws DisputeException
     */
    public function handle(Dispute $dispute, DisputeStatus $to, User $actor, ?string $resolutionNotes = null): Dispute
    {
        $from = $dispute->status;

        if (! $from->canTransitionTo($to)) {
            throw DisputeException::invalidTransition();
        }

        if (in_array($to, [DisputeStatus::Resolved, DisputeStatus::Rejected], true) && ($resolutionNotes === null || trim($resolutionNotes) === '')) {
            throw DisputeException::resolutionNotesRequired();
        }

        return DB::transaction(function () use ($dispute, $to, $actor, $resolutionNotes, $from): Dispute {
            $dispute->status = $to;

            if ($to === DisputeStatus::UnderReview) {
                $dispute->assigned_to = $actor->id;
            }

            if (in_array($to, [DisputeStatus::Resolved, DisputeStatus::Rejected], true)) {
                $dispute->resolution_notes = $resolutionNotes;
                $dispute->resolved_by = $actor->id;
                $dispute->resolved_at = now();
            }

            $dispute->save();

            $this->auditLogger->log(
                actor: $actor,
                action: 'disputes.status_changed',
                entityType: 'dispute',
                entityId: $dispute->public_id,
                oldValues: ['status' => $from->value],
                newValues: ['status' => $to->value],
            );

            return $dispute;
        });
    }
}
