<?php

namespace App\Domain\Pricing\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Exactly one version is active at a time. Orders always snapshot the
 * rules used at quote time (see docs/DATABASE_SCHEMA.md §Immutability), so
 * activating a new version never changes any historical order's price.
 */
class ActivatePricingRuleVersionAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(PricingRuleVersion $version, User $actor): PricingRuleVersion
    {
        DB::transaction(function () use ($version) {
            PricingRuleVersion::query()
                ->where('is_active', true)
                ->where('id', '!=', $version->id)
                ->update(['is_active' => false]);

            $version->update([
                'is_active' => true,
                'effective_from' => $version->effective_from ?? now(),
            ]);
        });

        $this->auditLogger->log(
            actor: $actor,
            action: 'pricing.version_activated',
            entityType: 'pricing_rule_version',
            entityId: $version->public_id,
            newValues: ['version_label' => $version->version_label],
        );

        return $version->fresh();
    }
}
