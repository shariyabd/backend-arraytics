<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

class AuthService
{
    public function login(string $email, string $password, string $deviceName): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            Log::warning('Failed login attempt.', ['email' => $email]);

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

    public function register(array $attributes, string $deviceName): array
    {
        $user = User::create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => $attributes['password'],
        ]);

        $token = $this->issueToken($user, $deviceName);

        Log::info('User registered.', ['user_id' => $user->id]);

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }

        Log::info('User logged out.', ['user_id' => $user->id]);
    }

    private function issueToken(User $user, string $deviceName): NewAccessToken
    {
        return $user->createToken($deviceName);
    }
}
