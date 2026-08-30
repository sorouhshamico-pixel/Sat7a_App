<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    /**
     * Internal dependency health check (database, redis, the Reverb
     * realtime server). Intentionally avoids leaking stack traces or
     * connection details — see docs/SECURITY.md §Error handling.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'redis' => $this->check(fn () => Redis::connection()->ping()),
            'reverb' => $this->check(fn () => Http::timeout(2)->get($this->reverbHealthUrl())->throw()),
        ];

        $healthy = ! in_array(false, $checks, true);

        return ApiResponse::success(
            data: ['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks],
            status: $healthy ? 200 : 503,
        );
    }

    private function check(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Reverb ships its own Pusher-protocol-compatible `/up` endpoint on
     * its own HTTP port (`Laravel\Reverb\Servers\Reverb\Factory`) —
     * unrelated to the `broadcasting.default` connection tests force to
     * `null`, so this stays checkable independent of that.
     */
    private function reverbHealthUrl(): string
    {
        $options = config('reverb.apps.apps.0.options', []);

        return sprintf(
            '%s://%s:%s/up',
            $options['scheme'] ?? 'https',
            $options['host'] ?? 'localhost',
            $options['port'] ?? 443,
        );
    }
}
