<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_cannot_list_sessions(): void
    {
        $this->getJson('/api/v1/auth/sessions')->assertStatus(401);
    }

    public function test_user_can_list_their_own_sessions_with_current_flagged(): void
    {
        $user = User::factory()->customer()->create();
        $token = $user->createToken('device-a', ['*'])->plainTextToken;
        $user->createToken('device-b', ['*']);

        $response = $this->withToken($token)->getJson('/api/v1/auth/sessions');

        $response->assertOk();
        $sessions = $response->json('data.sessions');
        $this->assertCount(2, $sessions);
        $current = collect($sessions)->firstWhere('name', 'device-a');
        $this->assertTrue($current['is_current']);
    }

    public function test_user_can_revoke_a_specific_session(): void
    {
        $user = User::factory()->customer()->create();
        $token = $user->createToken('device-a', ['*'])->plainTextToken;
        $other = $user->createToken('device-b', ['*']);

        $this->withToken($token)
            ->deleteJson('/api/v1/auth/sessions/'.$other->accessToken->id)
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $user = User::factory()->customer()->create();
        $otherUser = User::factory()->customer()->create();
        $token = $user->createToken('device-a', ['*'])->plainTextToken;
        $otherToken = $otherUser->createToken('device-a', ['*']);

        $this->withToken($token)
            ->deleteJson('/api/v1/auth/sessions/'.$otherToken->accessToken->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
    }

    public function test_user_can_revoke_all_sessions_except_current(): void
    {
        $user = User::factory()->customer()->create();
        $token = $user->createToken('device-a', ['*'])->plainTextToken;
        $user->createToken('device-b', ['*']);
        $user->createToken('device-c', ['*']);

        $this->withToken($token)
            ->postJson('/api/v1/auth/sessions/revoke-all')
            ->assertOk();

        $this->assertSame(1, $user->tokens()->count());
    }
}
