<?php

namespace App\Services;

use App\Models\ApiUsageLog;
use App\Support\AiLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Records attempts at the HTTP boundary, never request/response content. */
class AiTelemetry
{
    public ?int $imageReservationId = null;

    public ?int $userId = null;

    private array $pending = [];

    public function forUser(int $userId, \Closure $callback): mixed
    {
        $previous = $this->userId;
        $this->userId = $userId;
        $tracker = app(InteractionTracker::class);
        $previousId = $tracker->id;
        $previousStages = $tracker->stages;
        $tracker->id ??= (string) Str::uuid();
        try {
            return $callback();
        } finally {
            $this->userId = $previous;
            $tracker->id = $previousId;
            if ($previousId === null) {
                $tracker->stages = $previousStages;
            }
        }
    }

    public function begin(string $url, array $data): ?array
    {
        // Peticiones multipart (p. ej. /images/edits): Laravel entrega una lista de partes.
        if (array_is_list($data) && isset($data[0]['name']) && array_key_exists('contents', $data[0])) {
            $data = collect($data)->filter(fn ($part) => is_scalar($part['contents'] ?? null) && ! isset($part['filename']))
                ->mapWithKeys(fn ($part) => [$part['name'] => $part['contents']])->all();
        }
        $provider = match (parse_url($url, PHP_URL_HOST)) {
            'api.openai.com' => 'openai', 'api.x.ai' => 'grok', default => null,
        };
        if (! $provider) {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH);
        $type = match (true) {
            str_contains($path, '/images/') => 'image_generation',
            str_contains($path, '/audio/speech') => 'tts',
            str_contains($path, '/audio/transcriptions') => 'stt',
            default => 'text_generation',
        };
        if ($type === 'text_generation') {
            foreach (array_merge($data['messages'] ?? [], is_array($data['input'] ?? null) ? $data['input'] : []) as $message) {
                foreach (is_array($message['content'] ?? null) ? $message['content'] : [] as $part) {
                    if (in_array($part['type'] ?? '', ['image_url', 'input_image'], true)) {
                        $type = 'image_analysis';
                    }
                }
            }
        }
        $tracker = app(InteractionTracker::class);
        $stage = $tracker->stage ?? $type;

        return ['provider' => $provider, 'model' => $data['model'] ?? ($type === 'stt' ? config('ai.models.stt') : 'unknown'),
            'type' => $type, 'stage' => $stage, 'start' => hrtime(true),
            'interaction_id' => $tracker->id ?? (string) Str::uuid(), 'user_id' => $this->userId ?? Auth::id(),
            'characters' => $type === 'tts' && is_string($data['input'] ?? null) ? mb_strlen($data['input']) : null,
            'service_tier' => $data['service_tier'] ?? 'default',
            'search_tool' => collect($data['tools'] ?? [])->pluck('type')->first(fn ($type) => str_starts_with($type, 'web_search')),
            'image_count' => $type === 'image_generation' ? (int) ($data['n'] ?? 1) : 0];
    }

    public function finish(?array $context, ?int $status, array $response = []): void
    {
        if (! $context) {
            return;
        }
        try {
            $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
            $model = is_string($response['model'] ?? null) ? $response['model'] : $context['model'];
            $searches = 0;
            $tools = 0;
            $unsupported = false;
            foreach ($response['output'] ?? [] as $output) {
                $type = $output['type'] ?? '';
                if ($type === 'web_search_call') {
                    $searches++;
                }
                if (str_ends_with($type, '_call')) {
                    $tools++;
                    $unsupported = $unsupported || ! in_array($type, ['web_search_call', 'function_call'], true);
                }
            }
            foreach ($response['choices'] ?? [] as $choice) {
                $tools += count($choice['message']['tool_calls'] ?? []);
            }
            $ok = $status !== null && $status >= 200 && $status < 300;
            $pricing = $ok ? app(AiPricing::class)->estimate($context['provider'], $model, $usage, [
                'characters' => $context['characters'], 'duration_seconds' => $response['duration'] ?? null,
                'web_search_calls' => $searches, 'search_tool' => $context['search_tool'], 'unsupported_tools' => $unsupported,
                'service_tier' => $response['service_tier'] ?? $context['service_tier'],
            ]) : ['estimated_cost' => null, 'cost_status' => 'unknown_failure', 'pricing_date' => null];
            $reserved = $context['type'] === 'image_generation' ? $this->imageReservationId : null;
            $data = array_merge($pricing, [
                'user_id' => $context['user_id'], 'api_provider' => $context['provider'], 'api_type' => $context['type'],
                'model' => $model, 'interaction_id' => $context['interaction_id'], 'stage' => $context['stage'],
                'prompt_tokens' => (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0),
                'total_tokens' => (int) ($usage['total_tokens'] ?? (($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0) + ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0))),
                'status' => $ok ? 'success' : ($reserved && ($status === null || $status >= 500) ? 'pending' : 'error'),
                'response_time_ms' => (int) ((hrtime(true) - $context['start']) / 1e6),
                'error_message' => $ok ? null : 'provider_request_failed',
                'metadata' => ['status_code' => $status, 'cached_tokens' => $usage['input_tokens_details']['cached_tokens'] ?? $usage['prompt_tokens_details']['cached_tokens'] ?? 0,
                    'tool_calls' => $tools, 'web_search_calls' => $searches,
                    'image_count' => $context['type'] === 'image_generation' && $ok ? count($response['data'] ?? []) : 0,
                    'characters' => $context['characters'], 'duration_seconds' => $response['duration'] ?? null],
            ]);
            if ($reserved) {
                ApiUsageLog::findOrFail($reserved)->update($data);
                $this->imageReservationId = null;
            } else {
                ApiUsageLog::create($data);
            }
            app(InteractionTracker::class)->stages[] = ['stage' => $context['stage'], 'duration_ms' => $data['response_time_ms'], 'status' => $ok ? 'success' : 'error'];
        } catch (\Throwable $e) {
            AiLog::error('usage_storage_failed');
        }
    }

    public function sending($event): void
    {
        $request = $event->request;
        // Keyed by underlying PSR request; Laravel creates another wrapper on failure.
        $this->pending[spl_object_id($request->toPsrRequest())] = $this->begin($request->url(), $request->data());
    }

    public function received($event): void
    {
        $key = spl_object_id($event->request->toPsrRequest());
        $context = $this->pending[$key] ?? null;
        unset($this->pending[$key]);
        if (! $context) {
            return;
        }
        $data = $event->response->json();
        $this->finish($context, $event->response->status(), is_array($data) ? $data : []);
    }

    public function failed($event): void
    {
        $key = spl_object_id($event->request->toPsrRequest());
        $context = $this->pending[$key] ?? $this->begin($event->request->url(), $event->request->data());
        unset($this->pending[$key]);
        $this->finish($context, null);
    }
}
