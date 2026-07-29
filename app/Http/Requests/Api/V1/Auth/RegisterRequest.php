<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates input for the public registration endpoint.
 *
 * Registration is public, so authorization is always granted here; account
 * creation and token issuance are performed by the AuthService.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * A label for the issued token, defaulting to the client user agent.
     */
    public function deviceName(): string
    {
        return $this->input('device_name')
            ?? (string) $this->userAgent()
            ?: 'api-token';
    }
}
