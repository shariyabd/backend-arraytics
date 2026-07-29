<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'jane@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Logged in successfully.',
                'data' => [
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $user->id,
                        'email' => 'jane@example.com',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['user' => ['id', 'name', 'email'], 'token', 'token_type'],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_response_never_exposes_sensitive_fields(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'jane@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data.user'));
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'The given data was invalid.',
            ])
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_requires_a_valid_email_format(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_me_endpoint_rejects_unauthenticated_request(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/logout');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_rejects_unauthenticated_request(): void
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }

    public function test_revoked_token_can_no_longer_access_protected_routes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/logout')->assertOk();

        // The test app is reused across requests, so the guard caches the user
        // resolved during logout. Flush it to simulate a fresh request process
        // (as happens in production) before re-checking the revoked token.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'password']);

        // Route allows 6 attempts per minute; the 7th must be throttled.
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/v1/login', [
                'email' => 'jane@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/v1/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }
}
