<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles authentication endpoints.
 *
 * Controllers stay thin: they receive validated input, delegate to the
 * AuthService for all business logic, and return a uniform response.
 */
class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    /**
     * Register a new user and return a bearer token. (Public)
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register([
            'name' => $request->string('name')->value(),
            'email' => $request->string('email')->value(),
            'password' => $request->string('password')->value(),
        ], $request->deviceName());

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'Registered successfully.', 201);
    }

    /**
     * Authenticate a user and return a bearer token. (Public)
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->string('email')->value(),
            $request->string('password')->value(),
            $request->deviceName(),
        );

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'Logged in successfully.');
    }

    /**
     * Return the currently authenticated user. (Protected)
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($request->user()),
            'Authenticated user retrieved.',
        );
    }

    /**
     * Revoke the current access token. (Protected)
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(null, 'Logged out successfully.');
    }
}
