<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegisterUser;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser->handle(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'data' => [
                'token' => $user->createToken('api')->plainTextToken,
                'user' => UserResource::make($user),
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw new ApiException('invalid_credentials', 401);
        }

        return response()->json([
            'data' => [
                'token' => $user->createToken($request->string('device_name', 'api')->toString())->plainTextToken,
                'user' => UserResource::make($user),
            ],
        ]);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
