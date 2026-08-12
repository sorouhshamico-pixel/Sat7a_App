<?php

namespace App\Console\Commands;

use App\Domain\Compliance\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily expiry scan (see docs/COMPLIANCE.md §Expiry). Alerts are logged for
 * now — real notification delivery (email/SMS/in-app) is wired up in
 * Phase 16 once the Notifications domain exists; this command is the
 * foundation those channels plug into, not a placeholder to be rewritten.
 * Suspending a provider on expiry is a deliberate business decision left
 * for a human compliance reviewer in Phase 3 — this command only alerts.
 */
class CheckDocumentExpiryCommand extends Command
{
    protected $signature = 'compliance:check-document-expiry';

    protected $description = 'Alert on documents expiring soon or already expired';

    private const ALERT_THRESHOLDS_DAYS = [30, 15, 7, 1];

    public function handle(): int
    {
        foreach (self::ALERT_THRESHOLDS_DAYS as $days) {
            $expiringSoon = Document::query()
                ->whereDate('expires_at', now()->addDays($days)->toDateString())
                ->get();

            foreach ($expiringSoon as $document) {
                Log::warning('compliance.document_expiring_soon', [
                    'document_id' => $document->public_id,
                    'document_type' => $document->document_type->value,
                    'expires_at' => $document->expires_at?->toDateString(),
                    'days_remaining' => $days,
                ]);
            }
        }

        $expired = Document::query()
            ->whereDate('expires_at', '<', now()->toDateString())
            ->get();

        foreach ($expired as $document) {
            Log::warning('compliance.document_expired', [
                'document_id' => $document->public_id,
                'document_type' => $document->document_type->value,
                'expires_at' => $document->expires_at?->toDateString(),
            ]);
        }

        $this->info(sprintf('Checked document expiry: %d expired.', $expired->count()));

        return self::SUCCESS;
    }
}
