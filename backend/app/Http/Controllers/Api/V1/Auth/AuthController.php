<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());
        return $this->successResponse($result, 'Registration successful.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());
        return $this->successResponse($result, 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());
        return $this->successResponse(null, 'Logged out successfully.');
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $this->authService->profile($request->user());
        return $this->successResponse($user);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate(['name' => 'sometimes|string|max:255', 'phone' => 'sometimes|string|max:20']);
        $request->user()->update($request->only('name', 'phone'));
        return $this->successResponse($request->user()->fresh(), 'Profile updated.');
    }
}
