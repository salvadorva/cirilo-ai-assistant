<?php

namespace App\Services;

use App\Models\ApiUsageLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class ImageQuotaService
{
    public function limit(User $user): ?int
    {
        $limit = (int) ($user->daily_image_limit ?? 4);

        return $user->role?->name === 'admin' || $limit === 0 ? null : max(0, $limit);
    }

    public function used(User $user): int
    {
        return ApiUsageLog::where('user_id', $user->id)
            ->where('api_type', 'image_generation')
            ->whereIn('status', ['pending', 'success'])
            ->whereDate('created_at', today())
            ->count();
    }

    public function assertAvailable(User $user): void
    {
        $limit = $this->limit($user);
        if ($limit !== null && $this->used($user) >= $limit) {
            throw new HttpResponseException(response()->json([
                'error' => 'Has alcanzado el límite diario de generación de imágenes.',
                'limit' => $limit, 'used' => $this->used($user),
            ], 429));
        }
    }

    public function generate(User $user, Closure $generate): JsonResponse
    {
        $key = "ai:image-generation:{$user->id}";
        if (RateLimiter::tooManyAttempts($key, config('ai_security.requests_per_minute.image'))) {
            throw new HttpResponseException(response()->json(['error' => 'Demasiadas solicitudes de imágenes.'], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key)));
        }
        RateLimiter::hit($key, 60);
        // Reserve atomically before contacting the provider, including chat calls.
        // Release the DB lock before the slow HTTP request.
        $reservation = DB::transaction(function () use ($user) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assertAvailable($lockedUser);

            return ApiUsageLog::create([
                'user_id' => $user->id, 'api_provider' => 'openai',
                'api_type' => 'image_generation', 'model' => config('ai.models.image'),
                'status' => 'pending', 'estimated_cost' => null, 'cost_status' => 'unknown_pending',
                'interaction_id' => app(InteractionTracker::class)->id,
            ]);
        });
        $started = microtime(true);
        $telemetry = app(AiTelemetry::class);
        $previousReservation = $telemetry->imageReservationId;
        $telemetry->imageReservationId = $reservation->id;

        try {
            $response = $generate();
            $success = $response->isSuccessful() && ! empty($response->getData(true)['image_url']);
            $reservation->refresh()->update([
                'status' => $success ? 'success' : ($response->getStatusCode() >= 500 ? 'pending' : 'error'),
                'response_time_ms' => (int) ((microtime(true) - $started) * 1000),
                'error_message' => $success ? null : 'image_generation_failed',
            ]);

            return $response;
        } catch (\Throwable $e) {
            // Unknown outcomes remain reserved: automatically releasing them could
            // allow more billed generations after a process/network failure.
            throw $e;
        } finally {
            $telemetry->imageReservationId = $previousReservation;
        }
    }
}
