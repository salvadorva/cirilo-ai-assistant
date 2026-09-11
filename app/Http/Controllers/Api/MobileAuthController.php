<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    /**
     * POST /api/mobile/login
     * Login móvil con Sanctum. Sin reCAPTCHA (decisión del proyecto AsistenteIA).
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string|min:6',
            'device_name' => 'required|string|max:100',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }

        // Revocar tokens previos del mismo device_name para evitar acumulación
        $user->tokens()->where('name', $request->input('device_name'))->delete();

        $token = $user->createToken($request->input('device_name'))->plainTextToken;

        $user->update(['last_login_at' => now()]);

        return response()->json([
            'token' => $token,
            'user'  => $this->formatUser($user),
        ]);
    }

    /**
     * GET /api/mobile/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }

    /**
     * POST /api/mobile/logout
     * Revoca solo el token actual (el de la sesión móvil).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'ok']);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'role'         => $user->role?->name,
            'ai_provider'  => $user->ai_provider ?? 'openai',
            'avatar'       => $user->avatar,
            'bio'          => $user->bio,
            'has_nextcloud' => $user->hasNextcloud(),
        ];
    }
}
