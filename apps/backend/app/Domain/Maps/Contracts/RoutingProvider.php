<?php

namespace App\Domain\Maps\Contracts;

use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Maps\DataTransferObjects\RouteInfo;
use App\Domain\Maps\Exceptions\MapsProviderException;

/**
 * Only ever called for a short-listed set of candidates, never a bulk
 * "every provider" scan — see docs/DISPATCH_ENGINE.md §Cost control and
 * docs/ARCHITECTURE.md §6.
 */
interface RoutingProvider
{
    /**
     * @throws MapsProviderException
     */
    public function route(Coordinates $origin, Coordinates $destination): RouteInfo;
}
