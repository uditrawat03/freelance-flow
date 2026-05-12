<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Issue a new API token.
     * POST /api/v1/tokens/create
     */
    public function createToken(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'required|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Create a token with optional abilities
        $token = $user->createToken(
            $request->device_name,
            ['clients:read', 'clients:write', 'projects:read', 'projects:write']
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'type' => 'Bearer',
        ], 201);
    }

    /**
     * Revoke the current token.
     * POST /api/v1/tokens/revoke
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Token revoked successfully.',
        ]);
    }
}