<?php

namespace Tests\Feature\Api\V1\Providers;

use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Domain\Compliance\Enums\DocumentType;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('documents');
    }

    private function registerProvider(string $phone = '+966501112233'): Provider
    {
        $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة النقل السريع',
            'owner_name' => 'محمد أحمد',
            'contact_phone' => $phone,
        ])->assertCreated();

        return Provider::query()->where('contact_phone', $phone)->firstOrFail();
    }

    public function test_owner_can_upload_a_document(): void
    {
        $provider = $this->registerProvider();
        $token = $provider->owner->createToken('test', ['*'])->plainTextToken;

        $file = UploadedFile::fake()->create('license.pdf', 500, 'application/pdf');

        $response = $this->withToken($token)->postJson('/api/v1/providers/me/documents', [
            'document_type' => DocumentType::CommercialRegistration->value,
            'document_number' => 'CR-12345',
            'file' => $file,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.document.verification_status', 'pending');

        $this->assertDatabaseHas('documents', [
            'documentable_type' => Provider::class,
            'documentable_id' => $provider->id,
            'document_type' => DocumentType::CommercialRegistration->value,
        ]);

        $document = $provider->documents()->firstOrFail();
        Storage::disk('documents')->assertExists($document->storage_path);
    }

    public function test_upload_rejects_a_disallowed_file_type(): void
    {
        $provider = $this->registerProvider();
        $token = $provider->owner->createToken('test', ['*'])->plainTextToken;

        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload');

        $response = $this->withToken($token)->postJson('/api/v1/providers/me/documents', [
            'document_type' => DocumentType::CommercialRegistration->value,
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_upload_rejects_an_oversized_file(): void
    {
        $provider = $this->registerProvider();
        $token = $provider->owner->createToken('test', ['*'])->plainTextToken;

        $file = UploadedFile::fake()->create('license.pdf', 20000, 'application/pdf');

        $response = $this->withToken($token)->postJson('/api/v1/providers/me/documents', [
            'document_type' => DocumentType::CommercialRegistration->value,
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_upload_rejects_a_double_extension_filename(): void
    {
        $provider = $this->registerProvider();
        $token = $provider->owner->createToken('test', ['*'])->plainTextToken;

        $file = UploadedFile::fake()->create('license.pdf.php', 100, 'application/pdf');

        $response = $this->withToken($token)->postJson('/api/v1/providers/me/documents', [
            'document_type' => DocumentType::CommercialRegistration->value,
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_can_list_their_own_documents(): void
    {
        $provider = $this->registerProvider();
        $token = $provider->owner->createToken('test', ['*'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/providers/me/documents', [
            'document_type' => DocumentType::Insurance->value,
            'file' => UploadedFile::fake()->create('insurance.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $response = $this->withToken($token)->getJson('/api/v1/providers/me/documents');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.documents'));
    }

    public function test_owner_can_download_their_own_document(): void
    {
        $provider = $this->registerProvider();
        $token = $provider->owner->createToken('test', ['*'])->plainTextToken;

        $uploadResponse = $this->withToken($token)->postJson('/api/v1/providers/me/documents', [
            'document_type' => DocumentType::Insurance->value,
            'file' => UploadedFile::fake()->create('insurance.pdf', 100, 'application/pdf'),
        ]);

        $documentId = $uploadResponse->json('data.document.id');

        $response = $this->withToken($token)->get("/api/v1/documents/{$documentId}/download");

        $response->assertOk();
    }

    public function test_a_different_providers_owner_cannot_download_the_document(): void
    {
        $provider = $this->registerProvider('+966501112233');
        $token = $provider->owner->createToken('test', ['*'])->plainTextToken;

        $uploadResponse = $this->withToken($token)->postJson('/api/v1/providers/me/documents', [
            'document_type' => DocumentType::Insurance->value,
            'file' => UploadedFile::fake()->create('insurance.pdf', 100, 'application/pdf'),
        ]);
        $documentId = $uploadResponse->json('data.document.id');

        $otherProvider = $this->registerProvider('+966501112244');
        $otherToken = $otherProvider->owner->createToken('test', ['*'])->plainTextToken;

        $response = $this->actingAsToken('GET', $otherToken, "/api/v1/documents/{$documentId}/download");

        $response->assertStatus(403);
    }

    public function test_staff_with_documents_view_permission_can_download_any_document(): void
    {
        $provider = $this->registerProvider();
        $ownerToken = $provider->owner->createToken('test', ['*'])->plainTextToken;

        $uploadResponse = $this->withToken($ownerToken)->postJson('/api/v1/providers/me/documents', [
            'document_type' => DocumentType::Insurance->value,
            'file' => UploadedFile::fake()->create('insurance.pdf', 100, 'application/pdf'),
        ]);
        $documentId = $uploadResponse->json('data.document.id');

        $complianceOfficer = User::factory()->admin()->create();
        $complianceOfficer->roles()->attach(
            Role::where('name', RoleName::ComplianceOfficer->value)->firstOrFail(),
        );
        $staffToken = $complianceOfficer->createToken('test', ['*'])->plainTextToken;

        $response = $this->actingAsToken('GET', $staffToken, "/api/v1/documents/{$documentId}/download");

        $response->assertOk();
    }
}
