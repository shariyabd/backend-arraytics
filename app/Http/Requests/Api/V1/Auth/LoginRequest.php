<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates credentials for the login endpoint.
 *
 * Login is a public endpoint, so authorization is always granted here;
 * credential verification is performed by the AuthService, not by validation.
 */
class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
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
