<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_fetch_profile_and_logout(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonMissingPath('data.user.password');

        $token = $loginResponse->json('data.access_token');

        $this->assertNotEmpty($token);
        $this->assertNotSame($token, User::query()->where('email', 'admin@example.com')->value('remember_token'));

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.com')
            ->assertJsonPath('data.role.code', 'admin');

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        \Illuminate\Support\Facades\Auth::forgetUser();
        \Illuminate\Support\Facades\Auth::guard('sanctum')->forgetUser();

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_login_rejects_invalid_credentials_and_inactive_users(): void
    {
        $this->seed();

        $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        User::query()
            ->where('email', 'admin@example.com')
            ->update(['status' => 'inactive']);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_protected_api_routes_require_bearer_token(): void
    {
        $this->withHeaders(['Authorization' => ''])
            ->getJson('/api/master-data/customers')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_protected_api_routes_require_permission(): void
    {
        $role = Role::query()->create([
            'code' => 'no-access',
            'name' => 'No Access',
        ]);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/master-data/customers')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }
}
