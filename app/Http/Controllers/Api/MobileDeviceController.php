<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\Reminders\DeviceRegistry;
use App\Services\Reminders\ReminderDestinations;
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
        $data = $request->validate([
            'token'           => 'required|string|max:512',
            'platform'        => 'sometimes|string|in:android,ios',
            // RC3: apps compatibles envían identidad estable y capacidades; las antiguas no.
            'installation_id' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9-]{8,100}$/'],
            'capabilities'    => 'sometimes|array|max:10',
            'capabilities.*'  => 'string|max:50',
            'app_version'     => 'sometimes|string|max:40',
        ]);

        $user = $request->user();

        if (isset($data['installation_id'])) {
            $device = app(DeviceRegistry::class)->register($user, $data);

            return response()->json([
                'message'                    => 'ok',
                'installation_id'            => $device->installation_id,
                'capabilities'               => $device->capabilities,
                'contextual_reminders_ready' => app(ReminderDestinations::class)->isContextualDestination($device, $user->id),
            ]);
        }

        DeviceToken::updateOrCreate(
            ['token' => $request->input('token')],
            [
                'user_id'      => $user->id,
                'platform'     => $request->input('platform', 'android'),
                'last_used_at' => now(),
                // Un registro válido rehabilita un token marcado como no registrado.
                'disabled_at'  => null,
            ]
        );

        return response()->json(['message' => 'ok']);
    }

    /**
     * POST /api/mobile/device-token/claim
     * Reclamo explícito de una instalación que quedó ligada a otra cuenta
     * (p. ej. cierre de sesión sin red). Exige el token FCM vigente del teléfono.
     */
    public function claim(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'           => 'required|string|max:512',
            'platform'        => 'sometimes|string|in:android,ios',
            'installation_id' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{8,100}$/'],
            'capabilities'    => 'sometimes|array|max:10',
            'capabilities.*'  => 'string|max:50',
            'app_version'     => 'sometimes|string|max:40',
        ]);
        $user = $request->user();
        [$device, $claimed] = app(DeviceRegistry::class)->claim($user, $data);

        return response()->json([
            'message'                    => 'ok',
            'claimed'                    => $claimed,
            'already_owned'              => ! $claimed,
            'installation_id'            => $device->installation_id,
            'capabilities'               => $device->capabilities,
            'contextual_reminders_ready' => app(ReminderDestinations::class)->isContextualDestination($device, $user->id),
        ]);
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
