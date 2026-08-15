<?php

namespace App\Domain\Notifications\Enums;

/**
 * External delivery channels, gated by the recipient's preferences (see
 * App\Domain\Customers\Models\Customer::defaultNotificationPreferences()
 * and App\Domain\Notifications\Actions\SendNotificationAction). The in-app
 * inbox record itself (App\Domain\Notifications\Models\Notification) is
 * not a channel — it's always created, never opted out of.
 */
enum NotificationChannel: string
{
    case Sms = 'sms';
    case Push = 'push';
    case Email = 'email';
    case Whatsapp = 'whatsapp';
}
