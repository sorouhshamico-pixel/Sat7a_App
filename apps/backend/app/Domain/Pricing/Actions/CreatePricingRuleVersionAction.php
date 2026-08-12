<?php

namespace App\Domain\Pricing\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Models\User;

/**
 * Creating a version never activates it — see ActivatePricingRuleVersionAction.
 * Keeping the two steps separate lets a reviewer create a draft rate card,
 * inspect it, and only then flip it live.
 */
class CreatePricingRuleVersionAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): PricingRuleVersion
    {
        $version = new PricingRuleVersion($data);
        $version->created_by = $actor->id;
        $version->is_active = false;
        $version->save();

        $this->auditLogger->log(
            actor: $actor,
            action: 'pricing.version_created',
            entityType: 'pricing_rule_version',
            entityId: $version->public_id,
            newValues: ['version_label' => $version->version_label],
        );

        return $version;
    }
}
