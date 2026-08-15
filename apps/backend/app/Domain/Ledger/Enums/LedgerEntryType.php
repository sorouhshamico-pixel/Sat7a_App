<?php

namespace App\Domain\Ledger\Enums;

/**
 * See docs/SETTLEMENT_ARCHITECTURE.md §Ledger. `customer_payment`,
 * `platform_commission`, and `gateway_fee` are reporting lines — they
 * explain how `provider_payable` was derived but are never summed into a
 * provider's balance themselves (see
 * App\Domain\Ledger\Actions\GetProviderBalanceAction). `settlement` is
 * reserved for Phase 14 — nothing creates one yet.
 */
enum LedgerEntryType: string
{
    case CustomerPayment = 'customer_payment';
    case PlatformCommission = 'platform_commission';
    case GatewayFee = 'gateway_fee';
    case ProviderPayable = 'provider_payable';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case Settlement = 'settlement';

    public function affectsProviderBalance(): bool
    {
        return in_array($this, [self::ProviderPayable, self::Refund, self::Adjustment, self::Settlement], true);
    }
}
