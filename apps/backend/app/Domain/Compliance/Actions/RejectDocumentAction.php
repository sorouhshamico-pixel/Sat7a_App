<?php

namespace App\Domain\Compliance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Compliance\Enums\DocumentVerificationStatus;
use App\Domain\Compliance\Models\Document;
use App\Models\User;

class RejectDocumentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Document $document, User $actor, string $reason): Document
    {
        $document->update([
            'verification_status' => DocumentVerificationStatus::Rejected->value,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->auditLogger->log(
            actor: $actor,
            action: 'document.rejected',
            entityType: 'document',
            entityId: $document->public_id,
            newValues: ['verification_status' => DocumentVerificationStatus::Rejected->value],
            reason: $reason,
        );

        return $document->fresh();
    }
}
