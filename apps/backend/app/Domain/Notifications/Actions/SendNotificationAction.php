<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Contracts\EmailProvider;
use App\Domain\Notifications\Contracts\PushProvider;
use App\Domain\Notifications\Contracts\SmsProvider;
use App\Domain\Notifications\Contracts\WhatsappProvider;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single entry point for every notification in the system — see
 * docs/NOTIFICATIONS.md. Always creates the in-app inbox record
 * (Notification) regardless of preferences; then best-effort fans out to
 * whichever external channels (sms/push/email/whatsapp) the recipient has
 * enabled. A channel a recipient has no customer profile for (a provider
 * owner, driver, or platform staff member — preferences are customer-only
 * today, see docs/DATABASE_SCHEMA.md) defaults to every channel except
 * whatsapp, matching Customer::defaultNotificationPreferences().
 *
 * External delivery is deliberately fire-and-forget: a failed/unavailable
 * channel provider must never block the in-app record from being created
 * or bubble an exception up to the caller (an order status change, a
 * document expiry scan) — notifications are never allowed to break the
 * business action that triggered them.
 */
class SendNotificationAction
{
    public function __construct(
        private readonly SmsProvider $smsProvider,
        private readonly PushProvider $pushProvider,
        private readonly EmailProvider $emailProvider,
        private readonly WhatsappProvider $whatsappProvider,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $recipient, NotificationType $type, string $title, string $body, array $data = []): Notification
    {
        $enabledChannels = $this->enabledChannels($recipient);

        $notification = new Notification([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'channels' => array_map(fn (NotificationChannel $channel) => $channel->value, $enabledChannels),
        ]);
        $notification->save();

        foreach ($enabledChannels as $channel) {
            $this->dispatchToChannel($channel, $recipient, $title, $body, $data);
        }

        return $notification;
    }

    /**
     * @return list<NotificationChannel>
     */
    private function enabledChannels(User $recipient): array
    {
        $preferences = $recipient->customer->notification_preferences
            ?? Customer::defaultNotificationPreferences();

        return array_values(array_filter(
            NotificationChannel::cases(),
            fn (NotificationChannel $channel) => (bool) ($preferences[$channel->value] ?? false),
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dispatchToChannel(NotificationChannel $channel, User $recipient, string $title, string $body, array $data): void
    {
        try {
            match ($channel) {
                NotificationChannel::Sms => $recipient->phone !== null
                    ? $this->smsProvider->send($recipient->phone, $body)
                    : null,
                NotificationChannel::Push => $this->pushProvider->send($recipient, $title, $body, $data),
                NotificationChannel::Email => $recipient->email !== null
                    ? $this->emailProvider->send($recipient->email, $title, $body)
                    : null,
                NotificationChannel::Whatsapp => $recipient->phone !== null
                    ? $this->whatsappProvider->send($recipient->phone, $body)
                    : null,
            };
        } catch (Throwable $e) {
            Log::warning('notifications.delivery_failed', [
                'user_id' => $recipient->public_id,
                'channel' => $channel->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
