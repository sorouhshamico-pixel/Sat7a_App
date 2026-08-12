<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Domain\Compliance\Enums\DocumentType;
use App\Domain\Providers\Enums\ProviderStatus;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProviderComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('documents');
    }

    private function staffWithRole(RoleName $role): User
    {
        $user = User::factory()->admin()->create();
        $user->roles()->attach(Role::where('name', $role->value)->firstOrFail());

        return $user;
    }

    private function registerProvider(): Provider
    {
        $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة النقل السريع',
            'owner_name' => 'محمد أحمد',
            'contact_phone' => '+966501112233',
        ])->assertCreated();

        return Provider::query()->firstOrFail();
    }

    public function test_staff_without_providers_view_permission_cannot_list_providers(): void
    {
        $this->registerProvider();
        $dispatcher = $this->staffWithRole(RoleName::Dispatcher);
        $token = $dispatcher->createToken('test', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/providers')->assertStatus(403);
    }

    public function test_compliance_officer_can_list_and_filter_providers(): void
    {
        $this->registerProvider();
        $officer = $this->staffWithRole(RoleName::ComplianceOfficer);
        $token = $officer->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/admin/providers?status=pending');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.providers'));
    }

    public function test_compliance_officer_can_approve_a_provider_and_it_is_audited(): void
    {
        $provider = $this->registerProvider();
        $officer = $this->staffWithRole(RoleName::ComplianceOfficer);
        $token = $officer->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($token)->postJson("/api/v1/admin/providers/{$provider->public_id}/approve");

        $response->assertOk();
        $response->assertJsonPath('data.provider.status', ProviderStatus::Approved->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'provider.approved',
            'entity_type' => 'provider',
            'entity_id' => $provider->public_id,
            'actor_id' => $officer->id,
        ]);
    }

    public function test_compliance_officer_can_reject_a_provider_with_a_reason(): void
    {
        $provider = $this->registerProvider();
        $officer = $this->staffWithRole(RoleName::ComplianceOfficer);
        $token = $officer->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($token)->postJson("/api/v1/admin/providers/{$provider->public_id}/reject", [
            'reason' => 'Missing commercial registration document.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.provider.status', ProviderStatus::Rejected->value);
        $response->assertJsonPath('data.provider.rejection_reason', 'Missing commercial registration document.');
    }

    public function test_reject_requires_a_reason(): void
    {
        $provider = $this->registerProvider();
        $officer = $this->staffWithRole(RoleName::ComplianceOfficer);
        $token = $officer->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($token)->postJson("/api/v1/admin/providers/{$provider->public_id}/reject", []);

        $response->assertStatus(422);
    }

    public function test_compliance_officer_can_suspend_an_approved_provider(): void
    {
        $provider = $this->registerProvider();
        $officer = $this->staffWithRole(RoleName::ComplianceOfficer);
        $token = $officer->createToken('test', ['*'])->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/admin/providers/{$provider->public_id}/approve")->assertOk();

        $response = $this->withToken($token)->postJson("/api/v1/admin/providers/{$provider->public_id}/suspend", [
            'reason' => 'Insurance document expired.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.provider.status', ProviderStatus::Suspended->value);

        $this->assertDatabaseHas('audit_logs', ['action' => 'provider.suspended', 'entity_type' => 'provider']);
    }

    public function test_compliance_officer_can_verify_a_document_and_it_is_audited(): void
    {
        $provider = $this->registerProvider();
        $ownerToken = $provider->owner->createToken('test', ['*'])->plainTextToken;

        $uploadResponse = $this->withToken($ownerToken)->postJson('/api/v1/providers/me/documents', [
            'document_type' => DocumentType::CommercialRegistration->value,
            'file' => UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf'),
        ]);
        $documentId = $uploadResponse->json('data.document.id');

        $officer = $this->staffWithRole(RoleName::ComplianceOfficer);
        $token = $officer->createToken('test', ['*'])->plainTextToken;

        $response = $this->actingAsToken('POST', $token, "/api/v1/admin/documents/{$documentId}/verify");

        $response->assertOk();
        $response->assertJsonPath('data.document.verification_status', 'verified');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.verified',
            'entity_type' => 'document',
            'entity_id' => $documentId,
        ]);
    }

    public function test_compliance_officer_can_reject_a_document_with_a_reason(): void
    {
        $provider = $this->registerProvider();
        $ownerToken = $provider->owner->createToken('test', ['*'])->plainTextToken;

        $uploadResponse = $this->withToken($ownerToken)->postJson('/api/v1/providers/me/documents', [
            'document_type' => DocumentType::CommercialRegistration->value,
            'file' => UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf'),
        ]);
        $documentId = $uploadResponse->json('data.document.id');

        $officer = $this->staffWithRole(RoleName::ComplianceOfficer);
        $token = $officer->createToken('test', ['*'])->plainTextToken;

        $response = $this->actingAsToken('POST', $token, "/api/v1/admin/documents/{$documentId}/reject", [
            'reason' => 'Document is blurry and unreadable.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.document.verification_status', 'rejected');
    }

    public function test_finance_officer_cannot_approve_providers(): void
    {
        $provider = $this->registerProvider();
        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $token = $financeOfficer->createToken('test', ['*'])->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/admin/providers/{$provider->public_id}/approve")
            ->assertStatus(403);
    }

    public function test_super_admin_audit_trail_is_never_bypassed(): void
    {
        $provider = $this->registerProvider();
        $superAdmin = $this->staffWithRole(RoleName::SuperAdmin);
        $token = $superAdmin->createToken('test', ['*'])->plainTextToken;

        $before = AuditLog::count();

        $this->withToken($token)->postJson("/api/v1/admin/providers/{$provider->public_id}/approve")->assertOk();

        $this->assertGreaterThan($before, AuditLog::count());
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $superAdmin->id, 'action' => 'provider.approved']);
    }
}
