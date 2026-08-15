<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pending balance hold period
    |--------------------------------------------------------------------------
    |
    | A newly-earned provider payable sits in `pending_balance` for this
    | many hours before it moves to `available_balance` — a short
    | fraud/dispute-protection window before money is considered clear
    | for settlement (see docs/SETTLEMENT_ARCHITECTURE.md and
    | App\Domain\Ledger\Actions\GetProviderBalanceAction). Tunable without
    | a code change.
    |
    */

    'pending_hold_hours' => (int) env('LEDGER_PENDING_HOLD_HOURS', 24),

];
