<?php

namespace App\Domain\Providers\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Providers\Enums\ProviderStatus;
use App\Domain\Providers\Models\Provider;
use App\Models\User;

class RejectProviderAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Provider $provider, User $actor, string $reason): Provider
    {
        $previousStatus = $provider->status;

        $provider->update([
            'status' => ProviderStatus::Rejected->value,
            'rejection_reason' => $reason,
        ]);

        $this->auditLogger->log(
            actor: $actor,
            action: 'provider.rejected',
            entityType: 'provider',
            entityId: $provider->public_id,
            oldValues: ['status' => $previousStatus->value],
            newValues: ['status' => ProviderStatus::Rejected->value],
            reason: $reason,
        );

        return $provider->fresh();
    }
}
