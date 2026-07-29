<?php

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = new AuthService;
    }

    public function test_login_returns_user_and_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password',
        ]);

        $result = $this->authService->login('jane@example.com', 'password', 'phpunit');

        $this->assertTrue($user->is($result['user']));
        $this->assertIsString($result['token']);
        $this->assertNotEmpty($result['token']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_throws_validation_exception_for_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password',
        ]);

        $this->expectException(ValidationException::class);

        $this->authService->login('jane@example.com', 'wrong', 'phpunit');
    }

    public function test_login_throws_validation_exception_for_unknown_user(): void
    {
        $this->expectException(ValidationException::class);

        $this->authService->login('ghost@example.com', 'password', 'phpunit');
    }

    public function test_failed_login_issues_no_token(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'password']);

        try {
            $this->authService->login('jane@example.com', 'wrong', 'phpunit');
        } catch (ValidationException) {
            // expected
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_register_creates_a_user_and_issues_a_token(): void
    {
        $result = $this->authService->register([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ], 'phpunit');

        $this->assertTrue($result['user']->exists);
        $this->assertSame('jane@example.com', $result['user']->email);
        $this->assertIsString($result['token']);
        $this->assertNotEmpty($result['token']);
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_register_stores_the_password_hashed(): void
    {
        $result = $this->authService->register([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ], 'phpunit');

        $this->assertNotSame('password123', $result['user']->password);
        $this->assertTrue(Hash::check('password123', $result['user']->password));
    }

    public function test_logout_revokes_the_current_access_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit');

        // Simulate the token being the one backing the current request.
        $user->withAccessToken($token->accessToken);

        $this->authService->logout($user);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
