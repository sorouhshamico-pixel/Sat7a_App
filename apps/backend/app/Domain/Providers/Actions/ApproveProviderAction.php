<?php

namespace App\Domain\Providers\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Providers\Enums\ProviderStatus;
use App\Domain\Providers\Models\Provider;
use App\Models\User;

class ApproveProviderAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Provider $provider, User $actor): Provider
    {
        $previousStatus = $provider->status;

        $provider->update([
            'status' => ProviderStatus::Approved->value,
            'approved_at' => now(),
            'approved_by' => $actor->id,
            'rejection_reason' => null,
        ]);

        $this->auditLogger->log(
            actor: $actor,
            action: 'provider.approved',
            entityType: 'provider',
            entityId: $provider->public_id,
            oldValues: ['status' => $previousStatus->value],
            newValues: ['status' => ProviderStatus::Approved->value],
        );

        return $provider->fresh();
    }
}
