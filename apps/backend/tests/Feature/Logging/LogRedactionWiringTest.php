<?php

namespace Tests\Feature\Logging;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Proves the config/logging.php `tap` wiring actually reaches a real log
 * write — App\Logging\RedactSensitiveDataProcessorTest already covers the
 * processor's own logic in isolation, but a config array pointing at the
 * right class is a real, separate way for this protection to silently do
 * nothing (a typo'd class name, a channel missing its `tap` entry, ...).
 * This writes through the actual 'single' channel to a real file and reads
 * it back, the same "verify the rendered output, don't trust the
 * config shape" standard this project applies to frontend response
 * envelopes too (see docs/CUSTOMER_WEB_APP.md and docs/PROVIDER_WEB_APP.md).
 */
class LogRedactionWiringTest extends TestCase
{
    public function test_a_real_log_write_through_the_single_channel_is_redacted_on_disk(): void
    {
        $path = storage_path('logs/test-redaction.log');
        File::ensureDirectoryExists(dirname($path));
        if (File::exists($path)) {
            File::delete($path);
        }

        config(['logging.channels.single.path' => $path]);
        // Force a fresh Logger instance so the path override above is
        // actually used, and so this test's `tap` runs against it.
        Log::forgetChannel('single');

        Log::channel('single')->info('test.sensitive_write', [
            'password' => 'super-secret-value',
            'order_id' => '01ABC123',
        ]);

        $contents = File::get($path);

        $this->assertStringNotContainsString('super-secret-value', $contents);
        $this->assertStringContainsString('[redacted]', $contents);
        $this->assertStringContainsString('01ABC123', $contents);

        File::delete($path);
    }
}
