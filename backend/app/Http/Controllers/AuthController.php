<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        ['user' => $user, 'token' => $token] = $this->auth->register($request->validated());

        return response()->json([
            'user'  => new UserResource($user),
            'access_token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        ['user' => $user, 'token' => $token] = $this->auth->login(
            $request->email,
            $request->password
        );

        return response()->json([
            'user'  => new UserResource($user),
            'access_token' => $token,
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        ['user' => $user, 'token' => $token] = $this->auth->refresh($request->user());

        return response()->json([
            'user'         => new UserResource($user),
            'access_token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadCount(['posts', 'followers', 'following']);

        return response()->json(new UserResource($user));
    }
}
