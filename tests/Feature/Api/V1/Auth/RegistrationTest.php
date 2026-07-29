<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload());

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Registered successfully.',
                'data' => [
                    'token_type' => 'Bearer',
                    'user' => [
                        'name' => 'Jane Doe',
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
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'name' => 'Jane Doe']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_registration_hashes_the_password(): void
    {
        $this->postJson('/api/v1/register', $this->validPayload())->assertCreated();

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_registration_response_never_exposes_sensitive_fields(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload());

        $response->assertCreated();
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data.user'));
    }

    public function test_registration_requires_name_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_requires_a_valid_email_format(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload(['email' => 'not-an-email']));

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/v1/register', $this->validPayload());

        $response->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload([
            'password_confirmation' => 'does-not-match',
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_registration_rejects_a_too_short_password(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_registration_endpoint_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/v1/register', $this->validPayload(['email' => 'dup@example.com']));
        }

        $response = $this->postJson('/api/v1/register', $this->validPayload(['email' => 'another@example.com']));

        $response->assertStatus(429);
    }
}
