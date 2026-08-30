<?php

namespace Tests\Unit\Logging;

use App\Logging\RedactSensitiveDataProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

class RedactSensitiveDataProcessorTest extends TestCase
{
    private function record(array $context): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'test message',
            context: $context,
        );
    }

    public function test_redacts_top_level_sensitive_keys(): void
    {
        $processor = new RedactSensitiveDataProcessor;

        $result = $processor($this->record([
            'password' => 'super-secret',
            'otp_code' => '123456',
            'auth_token' => 'abc.def.ghi',
            'iban' => 'SA0380000000608010167519',
            'card_number' => '4111111111111111',
        ]));

        $this->assertSame('[redacted]', $result->context['password']);
        $this->assertSame('[redacted]', $result->context['otp_code']);
        $this->assertSame('[redacted]', $result->context['auth_token']);
        $this->assertSame('[redacted]', $result->context['iban']);
        $this->assertSame('[redacted]', $result->context['card_number']);
    }

    public function test_leaves_non_sensitive_keys_untouched(): void
    {
        $processor = new RedactSensitiveDataProcessor;

        $result = $processor($this->record([
            'order_id' => '01ABC123',
            'status' => 'completed',
            'amount' => 10120,
        ]));

        $this->assertSame('01ABC123', $result->context['order_id']);
        $this->assertSame('completed', $result->context['status']);
        $this->assertSame(10120, $result->context['amount']);
    }

    public function test_redacts_sensitive_keys_nested_inside_a_raw_payload(): void
    {
        // The exact shape ProcessPaymentWebhookAction logs a raw gateway
        // payload as — see docs/SECURITY.md §Logging.
        $processor = new RedactSensitiveDataProcessor;

        $result = $processor($this->record([
            'gateway' => 'fake',
            'payload' => [
                'event' => 'payment.captured',
                'card' => ['card_number' => '4111111111111111', 'cvv' => '123'],
            ],
        ]));

        $this->assertSame('fake', $result->context['gateway']);
        $this->assertSame('payment.captured', $result->context['payload']['event']);
        $this->assertSame('[redacted]', $result->context['payload']['card']['card_number']);
        $this->assertSame('[redacted]', $result->context['payload']['card']['cvv']);
    }

    public function test_does_not_redact_generic_short_fragments_that_would_over_match(): void
    {
        // "code"/"pan" are deliberately excluded from the sensitive
        // fragment list — see RedactSensitiveDataProcessor's docblock.
        $processor = new RedactSensitiveDataProcessor;

        $result = $processor($this->record([
            'status_code' => 404,
            'error_code' => 'NOT_FOUND',
            'country_code' => 'SA',
            'company' => 'Acme Towing',
        ]));

        $this->assertSame(404, $result->context['status_code']);
        $this->assertSame('NOT_FOUND', $result->context['error_code']);
        $this->assertSame('SA', $result->context['country_code']);
        $this->assertSame('Acme Towing', $result->context['company']);
    }

    public function test_key_matching_is_case_insensitive_and_matches_substrings(): void
    {
        $processor = new RedactSensitiveDataProcessor;

        $result = $processor($this->record([
            'Authorization' => 'Bearer abc123',
            'X-Api-Key' => 'secret-key',
        ]));

        $this->assertSame('[redacted]', $result->context['Authorization']);
        $this->assertSame('[redacted]', $result->context['X-Api-Key']);
    }
}
