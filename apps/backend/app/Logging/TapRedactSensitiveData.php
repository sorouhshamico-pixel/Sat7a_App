<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\ProcessableHandlerInterface;

/**
 * Laravel's "tap" hook for customizing a log channel — see config/
 * logging.php, where this is attached to every channel that actually
 * persists log records (see App\Logging\RedactSensitiveDataProcessor).
 */
class TapRedactSensitiveData
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            // Not every Monolog handler supports processors (e.g. one
            // wrapping another handler without implementing this
            // interface itself) — skip rather than fatal on those.
            if ($handler instanceof ProcessableHandlerInterface) {
                $handler->pushProcessor(new RedactSensitiveDataProcessor);
            }
        }
    }
}
