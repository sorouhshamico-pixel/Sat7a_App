<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dispatch wave tuning
    |--------------------------------------------------------------------------
    |
    | See docs/DISPATCH_ENGINE.md §Dispatch waves. Weights/sizes/timeouts
    | live here rather than inline in the dispatch actions, so they can be
    | tuned without a code change. Wave 3 also widens the radius per spec —
    | the last wave configured here is the final one; after it's exhausted
    | with no acceptance, `orders.manual_dispatch_required` is flipped and
    | an operations dispatcher takes over (see
    | App\Domain\Dispatch\Services\DispatchEscalationService).
    |
    */

    'waves' => [
        1 => ['radius_meters' => 5000, 'candidates' => 5],
        2 => ['radius_meters' => 15000, 'candidates' => 5],
        3 => ['radius_meters' => 30000, 'candidates' => 10],
    ],

    // How long a driver has to respond to an offer before it's expired and
    // (if it was the last pending offer in its wave) the next wave starts.
    'offer_ttl_seconds' => env('DISPATCH_OFFER_TTL_SECONDS', 60),

];
