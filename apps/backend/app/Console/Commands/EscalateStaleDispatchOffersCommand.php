<?php

namespace App\Console\Commands;

use App\Domain\Dispatch\Services\DispatchEscalationService;
use Illuminate\Console\Command;

/**
 * See docs/DISPATCH_ENGINE.md §Dispatch waves.
 */
class EscalateStaleDispatchOffersCommand extends Command
{
    protected $signature = 'dispatch:escalate-stale-offers';

    protected $description = 'Expire dispatch offers a driver never responded to, and escalate exhausted waves';

    public function handle(DispatchEscalationService $service): int
    {
        $expired = $service->run();

        $this->info(sprintf('Expired %d stale dispatch offer(s).', $expired));

        return self::SUCCESS;
    }
}
