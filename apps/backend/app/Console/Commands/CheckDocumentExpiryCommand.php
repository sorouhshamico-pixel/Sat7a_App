<?php

namespace App\Console\Commands;

use App\Domain\Compliance\Models\Document;
use App\Domain\Drivers\Models\Driver;
use App\Domain\Notifications\Actions\SendNotificationAction;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Daily expiry scan (see docs/COMPLIANCE.md §Expiry). Logs a structured
 * warning for every affected document (ops monitoring) and, since Phase 16,
 * also notifies whoever owns the expiring document — the provider's owner,
 * or the driver themselves for a driver-owned document — via
 * App\Domain\Notifications\Actions\SendNotificationAction. Suspending a
 * provider on expiry is still a deliberate business decision left for a
 * human compliance reviewer (Phase 3) — this command only alerts.
 */
class CheckDocumentExpiryCommand extends Command
{
    protected $signature = 'compliance:check-document-expiry';

    protected $description = 'Alert on documents expiring soon or already expired';

    private const ALERT_THRESHOLDS_DAYS = [30, 15, 7, 1];

    public function __construct(private readonly SendNotificationAction $sendNotification)
    {
        parent::__construct();
    }

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

                $this->notifyDocumentOwner($document, NotificationType::DocumentExpiring, [
                    'document_type' => $document->document_type->value,
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

            $this->notifyDocumentOwner($document, NotificationType::DocumentExpired, [
                'document_type' => $document->document_type->value,
            ]);
        }

        $this->info(sprintf('Checked document expiry: %d expired.', $expired->count()));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function notifyDocumentOwner(Document $document, NotificationType $type, array $params): void
    {
        $recipient = $this->resolveOwner($document->documentable);

        if ($recipient === null) {
            return;
        }

        $translationKey = $type === NotificationType::DocumentExpiring ? 'document_expiring' : 'document_expired';
        $locale = $recipient->locale ?? config('app.locale');

        $this->sendNotification->handle(
            recipient: $recipient,
            type: $type,
            title: __("notifications.{$translationKey}_title", $params, $locale),
            body: __("notifications.{$translationKey}_body", $params, $locale),
            data: array_merge($params, ['document_id' => $document->public_id]),
        );
    }

    private function resolveOwner(?Model $documentable): ?User
    {
        return match (true) {
            $documentable instanceof Provider => $documentable->owner,
            $documentable instanceof Driver => $documentable->user,
            default => null,
        };
    }
}
