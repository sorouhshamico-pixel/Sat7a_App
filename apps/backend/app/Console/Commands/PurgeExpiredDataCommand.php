<?php

namespace App\Console\Commands;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Tracking\Models\OrderLocationPing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Baseline data-retention hygiene (see docs/SECURITY.md §Data retention
 * and config/retention.php) — a Phase 23 security-hardening addition.
 * Deliberately scoped to two bounded, uncontroversial purges rather than
 * the full account-deletion/anonymization workflow that document also
 * describes (a distinct, larger compliance feature, not attempted here):
 *
 * - OTP codes, functionally dead the moment they expire or are consumed —
 *   kept only briefly afterward for fraud investigation.
 * - Raw GPS location-ping history, a real privacy exposure if retained
 *   indefinitely, unbounded by anything else in the codebase before this.
 *
 * Both deletes are plain bulk queries, not per-row Eloquent deletes —
 * these tables can grow large (a location ping is written on every
 * driver location update, see App\Domain\Tracking\Actions\
 * RecordLocationPingAction) and neither model has any `deleting` event
 * listener whose side effects a bulk delete would skip.
 */
class PurgeExpiredDataCommand extends Command
{
    protected $signature = 'data:purge-expired';

    protected $description = 'Purge OTP codes and location pings past their retention window';

    public function handle(): int
    {
        $otpCutoff = now()->subHours((int) config('retention.otp_codes_hours'));

        $purgedOtpCodes = OtpCode::query()
            ->where(function ($query) use ($otpCutoff) {
                $query->where('expires_at', '<', $otpCutoff)
                    ->orWhere('consumed_at', '<', $otpCutoff);
            })
            ->delete();

        $pingCutoff = now()->subDays((int) config('retention.location_pings_days'));

        $purgedLocationPings = OrderLocationPing::query()
            ->where('recorded_at', '<', $pingCutoff)
            ->delete();

        Log::info('retention.purge_expired_data', [
            'otp_codes_purged' => $purgedOtpCodes,
            'otp_cutoff' => $otpCutoff->toIso8601String(),
            'location_pings_purged' => $purgedLocationPings,
            'location_ping_cutoff' => $pingCutoff->toIso8601String(),
        ]);

        $this->info(sprintf(
            'Purged %d expired OTP codes and %d old location pings.',
            $purgedOtpCodes,
            $purgedLocationPings,
        ));

        return self::SUCCESS;
    }
}
