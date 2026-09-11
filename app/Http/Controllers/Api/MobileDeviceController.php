<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDeviceController extends Controller
{
    /**
     * POST /api/mobile/device-token
     * Registra o actualiza el FCM token del dispositivo del usuario.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required|string|max:512',
            'platform' => 'sometimes|string|in:android,ios',
        ]);

        $user = $request->user();

        DeviceToken::updateOrCreate(
            ['token' => $request->input('token')],
            [
                'user_id'      => $user->id,
                'platform'     => $request->input('platform', 'android'),
                'last_used_at' => now(),
            ]
        );

        return response()->json(['message' => 'ok']);
    }

    /**
     * DELETE /api/mobile/device-token
     */
    public function remove(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $request->input('token'))
            ->delete();

        return response()->json(['message' => 'ok']);
    }
}
