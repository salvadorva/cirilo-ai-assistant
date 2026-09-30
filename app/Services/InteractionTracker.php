<?php

namespace App\Services;

use App\Models\AiInteraction;
use App\Support\AiLog;
use Closure;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class InteractionTracker
{
    public ?string $id = null;

    public ?string $stage = null;

    public array $stages = [];

    public function measure(string $stage, Closure $operation): mixed
    {
        $previous = $this->stage;
        $this->stage = $stage;
        $start = hrtime(true);
        $status = 'success';
        try {
            return $operation();
        } catch (\Throwable $e) {
            $status = 'error';
            throw $e;
        } finally {
            $this->stages[] = ['stage' => $stage, 'duration_ms' => (int) ((hrtime(true) - $start) / 1e6), 'status' => $status];
            $this->stage = $previous;
        }
    }

    public function run(Request $request, Closure $operation): mixed
    {
        $this->id = (string) Str::uuid(); // Never accept an identifier supplied by the client.
        $request->attributes->set('ai_interaction_id', $this->id);
        $this->stages = [];
        $start = hrtime(true);
        $status = 500;
        $result = 'error';
        try {
            $response = $operation();
            $status = $response->getStatusCode();
            $data = $response instanceof JsonResponse ? $response->getData(true) : [];
            $result = $status < 400 && empty($data['error']) && ($data['success'] ?? true) !== false ? 'responded' : 'error';
            if ($result === 'responded' && (! empty($data['calendar_event_created']) || ! empty($data['event_created']))) {
                $result = 'action_reported'; // Not a substitute for user-rated task achievement.
            }
            if ($response instanceof JsonResponse && is_array($data) && ! array_is_list($data)) {
                $response->setData(array_merge($data, ['interaction_id' => $this->id]));
            }
            $response->headers->set('X-Interaction-ID', $this->id);

            return $response;
        } catch (\Throwable $e) {
            $status = match (true) {
                $e instanceof HttpResponseException => $e->getResponse()->getStatusCode(),
                $e instanceof ValidationException => 422,
                $e instanceof ModelNotFoundException => 404,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                $e instanceof TransferException, $e instanceof RequestException => 502,
                default => 500,
            };
            throw $e;
        } finally {
            try {
                AiInteraction::create(['id' => $this->id, 'user_id' => $request->user()->id,
                    'operation' => class_basename($request->route()->getActionName()),
                    'http_status' => $status, 'result' => $result,
                    'duration_ms' => (int) ((hrtime(true) - $start) / 1e6), 'stages' => $this->stages]);
            } catch (\Throwable $e) {
                AiLog::error('interaction_storage_failed');
            }
            $this->id = null;
            $this->stage = null;
        }
    }
}
