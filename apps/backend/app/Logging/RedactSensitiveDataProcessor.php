<?php

namespace App\Logging;

use Monolog\LogRecord;

/**
 * A Monolog processor, not a convention every call site has to remember —
 * every log record's context array is scanned recursively before it's
 * written anywhere, regardless of which action logged it. Closes a real
 * Phase 23 gap: docs/SECURITY.md's "Logging" section always claimed
 * sensitive fields are "redacted at the logging layer, not by convention
 * alone," but the only redaction that existed before this was one
 * hand-rolled helper local to `ProcessPaymentWebhookAction` — every other
 * `Log::*()` call site anywhere in the codebase had no systemic guarantee
 * at all.
 */
class RedactSensitiveDataProcessor
{
    private const REDACTED = '[redacted]';

    /**
     * Matched case-insensitively against array keys, wherever they appear
     * in a (possibly nested) context array. Deliberately broad — this is
     * a safety net, not a precise allowlist, so it errs toward redacting
     * a field that turns out to be harmless rather than missing one that
     * isn't (see docs/SECURITY.md's Data classification "highly
     * sensitive" list: OTPs, auth tokens, bank account numbers, payment
     * metadata, compliance documents' contents).
     *
     * @var list<string>
     */
    // Deliberately excludes short/generic fragments like "code" or "pan"
    // (as in "Primary Account Number") — a bare 3-4 character substring
    // match on either would redact ordinary, debugging-critical fields
    // too (status_code, error_code, country_code, "company", "expand",
    // ...). "otp" already covers "otp_code" specifically; card numbers
    // are covered by "card_number"/"cvv"/"cvc" instead of the ambiguous
    // "pan".
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'otp',
        'token',
        'secret',
        'authorization',
        'iban',
        'account_number',
        'card_number',
        'cvv',
        'cvc',
        'api_key',
        'private_key',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(context: $this->redact($record->context));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redact($value);

                continue;
            }

            if (is_string($key) && $this->isSensitiveKey($key)) {
                $data[$key] = self::REDACTED;
            }
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        // Strip separators so "api_key", "api-key", "X-Api-Key", and
        // "apiKey" are all recognized as the same fragment regardless of
        // naming convention (snake_case config keys, kebab-case HTTP
        // header names, camelCase gateway payload fields, ...).
        $normalized = str_replace(['_', '-', ' '], '', strtolower($key));

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, str_replace('_', '', $fragment))) {
                return true;
            }
        }

        return false;
    }
}
