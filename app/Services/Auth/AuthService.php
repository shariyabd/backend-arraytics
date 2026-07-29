<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * Business logic for authentication.
 *
 * This service is the single owner of credential verification and token
 * lifecycle. It is HTTP-independent (it receives plain values, not requests)
 * so it can be reused and unit-tested in isolation.
 */
class AuthService
{
    /**
     * Verify credentials and issue a personal access token.
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException when the credentials are invalid.
     */
    public function login(string $email, string $password, string $deviceName): array
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            Log::warning('Failed login attempt.', ['email' => $email]);

            // Framework convention: surface as a 422 validation error without
            // revealing whether the email or the password was the problem.
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $this->issueToken($user, $deviceName);

        Log::info('User authenticated.', ['user_id' => $user->id]);

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }

    /**
     * Revoke the access token backing the current request.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }

        Log::info('User logged out.', ['user_id' => $user->id]);
    }

    /**
     * Issue a fresh personal access token for the user.
     */
    private function issueToken(User $user, string $deviceName): NewAccessToken
    {
        return $user->createToken($deviceName);
    }
}
