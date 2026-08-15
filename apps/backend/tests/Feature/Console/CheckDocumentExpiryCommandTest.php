<?php

namespace Tests\Feature\Console;

use App\Domain\Compliance\Enums\DocumentType;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Providers\Models\Provider;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckDocumentExpiryCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('documents');
    }

    private function registerProviderWithDocument(string $phone, string $expiresAt): Provider
    {
        $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة النقل السريع '.$phone,
            'owner_name' => 'محمد أحمد',
            'contact_phone' => $phone,
        ])->assertCreated();

        $provider = Provider::query()->where('contact_phone', $phone)->firstOrFail();
        $token = $this->tokenFor($provider->owner);

        $this->actingAsToken('POST', $token, '/api/v1/providers/me/documents', [
            'document_type' => DocumentType::Insurance->value,
            'expires_at' => $expiresAt,
            'file' => UploadedFile::fake()->create('insurance.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        return $provider;
    }

    public function test_a_document_expiring_in_thirty_days_notifies_the_provider_owner(): void
    {
        $provider = $this->registerProviderWithDocument('+966501110190', now()->addDays(30)->toDateString());

        Artisan::call('compliance:check-document-expiry');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $provider->owner_id,
            'type' => 'document_expiring',
        ]);
    }

    public function test_an_already_expired_document_notifies_the_provider_owner(): void
    {
        // expires_at must be a future date at upload time (validation), so
        // upload it expiring tomorrow, then backdate it directly — the
        // command only cares about the stored value, not how it got there.
        $provider = $this->registerProviderWithDocument('+966501110191', now()->addDay()->toDateString());
        $provider->documents()->firstOrFail()->update(['expires_at' => now()->subDay()->toDateString()]);

        Artisan::call('compliance:check-document-expiry');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $provider->owner_id,
            'type' => 'document_expired',
        ]);

        $notification = Notification::query()->where('user_id', $provider->owner_id)->where('type', 'document_expired')->firstOrFail();
        $this->assertSame('insurance', $notification->data['document_type']);
    }

    public function test_a_document_not_near_expiry_does_not_notify(): void
    {
        $provider = $this->registerProviderWithDocument('+966501110192', now()->addDays(90)->toDateString());

        Artisan::call('compliance:check-document-expiry');

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $provider->owner_id,
        ]);
    }
}
