<?php

namespace Tests\Evaluation;

use App\Models\CalendarEvent;
use App\Models\Conversation;
use App\Models\User;
use App\Models\UserProfileFact;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Tests\SecurityTestCase;

/**
 * F1-06b — línea base humana, una ejecución por tarea, en entorno local
 * aislado (SQLite en memoria, usuario ficticio, sin teléfono ni FCM).
 *
 * No forma parte de la suite normal. Requiere la configuración
 * tests/Evaluation/phpunit.xml y:
 *   F1_HUMAN_MODE=dry   ensayo sin red (respuestas simuladas)
 *   F1_HUMAN_MODE=live  GPT-4.1 real con la clave local existente
 * Todo tráfico pasa por BudgetGuard (tope duro, por defecto 1 USD).
 */
class F1HumanBaselineTest extends SecurityTestCase
{
    private BudgetGuard $guard;

    private User $user;

    private array $report = [];

    protected function setUp(): void
    {
        $mode = getenv('F1_HUMAN_MODE');
        if (! in_array($mode, ['dry', 'live'], true)) {
            $this->markTestSkipped('Evaluación manual: definir F1_HUMAN_MODE=dry|live.');
        }
        parent::setUp(); // SQLite :memory: comprobado, migraciones aisladas, Storage/Mail/Notification falsos.
        $this->assertSame('testing', app()->environment());

        $this->guard = new BudgetGuard((float) (getenv('F1_HUMAN_BUDGET_USD') ?: 1.0));
        Http::preventStrayRequests(false);
        Http::globalMiddleware($this->guard->middleware());
        if ($mode === 'live') {
            // Clave local existente, sin imprimirla. SecurityTestCase la vació para la suite normal.
            config(['services.openai.api_key' => env('OPENAI_API_KEY')]);
            $this->assertTrue(filled(config('services.openai.api_key')), 'Falta la configuración local de OpenAI.');
            $handler = null;
        } else {
            config(['services.openai.api_key' => 'dry-run-key']);
            $handler = fn (RequestInterface $request) => Create::promiseFor($this->dryResponse($request));
            Http::fake(fn ($request) => Http::response(json_decode((string) $this->dryResponse($request->toPsrRequest())->getBody(), true)));
        }
        $this->app->bind(Client::class, function ($app, $parameters) use ($handler) {
            $stack = HandlerStack::create($handler);
            $stack->push($this->guard->middleware(), 'f1-budget-guard');

            return new Client(array_merge($parameters['config'] ?? [], ['handler' => $stack]));
        });

        // BudgetGuard envuelve por fuera al reintento de AiTransport: sin reintentos, cada envío queda reservado.
        config(['ai.retry.max' => 0]);
        $this->user = $this->user();
        $this->user->forceFill(['name' => 'Usuario de prueba F1'])->save();
        $this->actingAs($this->user);
    }

    public function test_human_baseline_tasks(): void
    {
        $this->report = ['mode' => getenv('F1_HUMAN_MODE'), 'started_at' => now()->toIso8601String(), 'timezone' => config('app.timezone'),
            'budget_usd' => (float) (getenv('F1_HUMAN_BUDGET_USD') ?: 1.0), 'conditions' => [
                'db' => 'sqlite :memory:, usuario ficticio sin agenda ni memoria previa',
                'caps' => 'max_output_tokens='.BudgetGuard::OUTPUT_CAP.', max_tool_calls=1 (web search), chat/completions max_tokens<='.BudgetGuard::COMPLETION_CAP,
                'audio' => 'generateAudio=false (solo texto)',
            ], 'tasks' => []];

        $this->task('T1', 'Planifica el resto de mi miércoles 23 de septiembre de 2026 con mis prioridades reales.', function (array $r) {
            return ['check' => 'Sin verificación automática: requiere juicio de Salva sobre utilidad y realismo.'];
        });

        $this->task('T2', 'Agenda una revisión de Cirilo para el jueves 24 de septiembre de 2026 a las 11:00.', function (array $r) {
            $events = CalendarEvent::where('user_id', $this->user->id)->get();
            $match = $events->first(fn ($e) => Carbon::parse($e->start_date)->format('Y-m-d H:i') === '2026-09-24 11:00');

            return ['events_in_test_db' => $events->map(fn ($e) => ['title' => $e->title, 'start' => (string) $e->start_date, 'end' => (string) $e->end_date])->all(),
                'persisted_at_expected_time' => (bool) $match,
                'response_reports_event' => ! empty($r['json']['calendar_event_created'] ?? null)];
        });

        $this->task('T3', 'Llámame Salva.', function (array $r) {
            // Otra conversación y otra sesión de prueba, sin historial ni conversation_id.
            $this->app['auth']->forgetGuards();
            $this->flushSession();
            $this->actingAs($this->user->fresh());
            $verify = $this->chat('¿Cómo prefiero que me llames?');
            $facts = UserProfileFact::where('user_id', $this->user->id)->get(['category', 'key', 'value'])->toArray();

            return ['verification_prompt' => '¿Cómo prefiero que me llames?', 'verification_status' => $verify['status'],
                'verification_response' => $verify['text'], 'verification_latency_ms' => $verify['latency_ms'],
                'verification_mentions_salva' => (bool) preg_match('/\bSalva\b/iu', (string) $verify['text']),
                'profile_facts' => $facts,
                'saved_conversations' => Conversation::where('user_id', $this->user->id)->count()];
        });

        $this->report['finished_at'] = now()->toIso8601String();
        $this->report['total_committed_usd'] = round($this->guard->committed(), 6);
        $this->report['ledger'] = $this->guard->ledger;
        $path = getenv('F1_HUMAN_REPORT') ?: storage_path('app/private/f1-human-baseline.json');
        file_put_contents($path, json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fwrite(STDOUT, PHP_EOL.'F1-06b report: '.$path.PHP_EOL);

        $this->assertLessThanOrEqual($this->report['budget_usd'], $this->guard->committed());
    }

    private function task(string $id, string $prompt, callable $check): void
    {
        $this->guard->task = $id;
        $result = $this->chat($prompt);
        // Mismo guardado que hace /preguntas tras cada respuesta.
        $save = $this->postJson('/conversaciones', ['title' => mb_substr($prompt, 0, 50), 'type' => 'chat',
            'content' => json_encode(['messages' => [
                ['role' => 'user', 'content' => $prompt, 'timestamp' => now()->toIso8601String()],
                ['role' => 'assistant', 'content' => (string) $result['text'], 'timestamp' => now()->toIso8601String()],
            ]])]);
        $extra = $check($result);
        $calls = array_values(array_filter($this->guard->ledger, fn ($e) => $e['task'] === $id));
        $this->report['tasks'][$id] = [
            'input' => $prompt,
            'http_status' => $result['status'],
            'response' => $result['text'],
            'error' => $result['json']['error'] ?? null,
            'interaction_id' => $result['json']['interaction_id'] ?? null,
            'latency_ms_request' => $result['latency_ms'],
            'conversation_saved_status' => $save->status(),
            'provider_calls' => count($calls),
            'models' => array_values(array_unique(array_filter(array_map(fn ($e) => $e['model_returned'] ?? $e['model_requested'], $calls)))),
            'cost_usd_conservative' => round($this->guard->taskCost($id), 6),
            'for_salva' => ['useful' => null, 'task_achieved' => null, 'corrections' => null, 'notes' => null],
        ] + $extra;
    }

    private function chat(string $prompt): array
    {
        $start = hrtime(true);
        $response = $this->postJson('/generate-text', ['prompt' => $prompt, 'history' => [], 'generateAudio' => false]);

        if ($response->status() >= 500 && $response->exception) {
            fwrite(STDERR, 'F1 exception: '.get_class($response->exception).': '.mb_substr($response->exception->getMessage(), 0, 300).PHP_EOL);
        }

        return ['status' => $response->status(), 'json' => $response->json() ?? [], 'text' => $response->json('choices.0.message.content'),
            'latency_ms' => round((hrtime(true) - $start) / 1e6)];
    }

    /** Respuestas simuladas del ensayo, por endpoint. */
    private function dryResponse(RequestInterface $request): Response
    {
        $body = json_decode((string) $request->getBody(), true) ?: [];
        if (str_ends_with($request->getUri()->getPath(), '/responses')) {
            return new Response(200, ['Content-Type' => 'application/json'], json_encode(['model' => 'gpt-4.1-2025-04-14',
                'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Respuesta simulada; te llamaré Salva.']]]],
                'usage' => ['input_tokens' => 1200, 'output_tokens' => 80]]));
        }
        $system = json_encode($body['messages'] ?? []);
        $content = str_contains($system, 'extracted_facts')
            ? json_encode(['summary' => 'Resumen simulado', 'extracted_facts' => []])
            : json_encode(['title' => 'Revisión de Cirilo', 'start_date' => '2026-09-24', 'start_time' => '11:00', 'end_time' => '12:00']);

        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['model' => $body['model'] ?? 'gpt-4.1-mini',
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content]]], 'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 40]]));
    }
}
