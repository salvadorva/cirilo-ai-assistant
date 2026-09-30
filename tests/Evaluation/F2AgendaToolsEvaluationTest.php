<?php

namespace Tests\Evaluation;

use App\Models\AgendaDraft;
use App\Models\AgendaOperation;
use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Tests\SecurityTestCase;

/**
 * F2 — evaluación de la agenda por herramientas (F2-02/F2-07) con el proveedor real.
 *
 * No forma parte de la suite normal. Entorno aislado (SQLite en memoria, usuario ficticio, reloj
 * fijo el lunes 5 de octubre de 2026 a las 08:00), AGENDA_TOOLS_ENABLED activo solo aquí.
 *   F2_TOOLS_MODE=dry   ensayo sin red (respuestas simuladas; las verificaciones fallan a propósito)
 *   F2_TOOLS_MODE=live  GPT-4.1 real con la clave local existente
 * Todo tráfico pasa por BudgetGuard (tope duro, por defecto 1 USD: F2_TOOLS_BUDGET_USD).
 */
class F2AgendaToolsEvaluationTest extends SecurityTestCase
{
    private BudgetGuard $guard;

    private User $user;

    private array $report = [];

    protected function setUp(): void
    {
        $mode = getenv('F2_TOOLS_MODE');
        if (! in_array($mode, ['dry', 'live'], true)) {
            $this->markTestSkipped('Evaluación manual: definir F2_TOOLS_MODE=dry|live.');
        }
        parent::setUp();
        $this->assertSame('testing', app()->environment());

        $this->guard = new BudgetGuard((float) (getenv('F2_TOOLS_BUDGET_USD') ?: 1.0));
        Http::preventStrayRequests(false);
        Http::globalMiddleware($this->guard->middleware());
        if ($mode === 'live') {
            config(['services.openai.api_key' => env('OPENAI_API_KEY')]);
            $this->assertTrue(filled(config('services.openai.api_key')), 'Falta la configuración local de OpenAI.');
            $handler = null;
        } else {
            config(['services.openai.api_key' => 'dry-run-key']);
            $handler = fn (RequestInterface $request) => Create::promiseFor($this->dryResponse($request));
        }
        $this->app->bind(Client::class, function ($app, $parameters) use ($handler) {
            $stack = HandlerStack::create($handler);
            $stack->push($this->guard->middleware(), 'f2-budget-guard');

            return new Client(array_merge($parameters['config'] ?? [], ['handler' => $stack]));
        });

        // BudgetGuard envuelve por fuera al reintento de AiTransport: sin reintentos, cada envío queda reservado.
        config(['ai.retry.max' => 0]);
        config(['ai.agenda_tools.enabled' => true]);
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:00:00', 'America/Guatemala'));
        $this->user = $this->user();
        $this->user->forceFill(['name' => 'Usuario de prueba F2'])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_agenda_tools_scenarios(): void
    {
        $this->report = ['mode' => getenv('F2_TOOLS_MODE'), 'started_at' => now()->toIso8601String(), 'clock' => '2026-10-05 08:00 America/Guatemala (lunes)',
            'budget_usd' => (float) (getenv('F2_TOOLS_BUDGET_USD') ?: 1.0), 'conditions' => [
                'db' => 'sqlite :memory:, usuario ficticio; la agenda se reinicia en cada escenario',
                'caps' => 'max_output_tokens='.BudgetGuard::OUTPUT_CAP.', max_tool_calls=1 (herramientas integradas), iteraciones de agenda <= '.config('ai.agenda_tools.max_iterations'),
                'audio' => 'generateAudio=false',
            ], 'scenarios' => []];

        $at = fn (string $title, string $start, array $extra = []) => CalendarEvent::create($extra + ['user_id' => $this->user->id, 'title' => $title,
            'start_date' => $start, 'end_date' => Carbon::parse($start)->addHour(), 'status' => 'pending', 'notified' => false, 'reminder_minutes_before' => 30]);
        $events = fn () => CalendarEvent::where('user_id', $this->user->id)->orderBy('start_date')->get();
        $startOf = fn ($e) => Carbon::parse($e->start_date)->format('Y-m-d H:i');

        $this->scenario('S1', 'Crear con fecha y hora', ['Recuérdame llamar a Ana mañana a las 10'], null, function () use ($events, $startOf) {
            $all = $events();

            return ['exactly_one_event' => $all->count() === 1, 'at_2026_10_06_10_00' => $all->count() === 1 && $startOf($all[0]) === '2026-10-06 10:00',
                'title_mentions_ana' => (bool) $all->first(fn ($e) => stripos($e->title, 'ana') !== false)];
        });

        $this->scenario('S2', 'Negación', ['No quiero agendar una reunión mañana'], null, fn () => ['no_events' => $events()->isEmpty()]);

        $this->scenario('S3', 'Petición de código', ['Puedes crear una función en PHP que sume dos números'], null, fn () => ['no_events' => $events()->isEmpty()]);

        $this->scenario('S4', 'Falta la hora y se completa en el siguiente turno', ['Agéndame una llamada con el banco mañana', 'a las 4 de la tarde'], null,
            function (array $turns) use ($events, $startOf) {
                $all = $events();

                return ['first_turn_created_nothing' => ($turns[0]['agenda']['status'] ?? null) !== 'created',
                    'first_turn_asks' => str_contains((string) $turns[0]['text'], '?'),
                    'first_turn_contract_needs_input' => ($turns[0]['agenda']['status'] ?? null) === 'needs_input',
                    'second_turn_one_event_at_16_00' => $all->count() === 1 && $startOf($all[0]) === '2026-10-06 16:00'];
            });

        $this->scenario('S5', 'Objetivo ambiguo', ['Muévela a las 11'], function () use ($at) {
            $at('Cita con Ana', '2026-10-05 09:00');
            $at('Cita con Luis', '2026-10-05 10:00');
        }, function (array $turns) use ($events, $startOf) {
            return ['nothing_moved' => $events()->map($startOf)->all() === ['2026-10-05 09:00', '2026-10-05 10:00'],
                'asks_which' => str_contains((string) $turns[0]['text'], '?'),
                'contract_needs_clarification_with_2_candidates' => ($turns[0]['agenda']['status'] ?? null) === 'needs_clarification'
                    && count($turns[0]['agenda']['candidates'] ?? []) === 2];
        });

        $this->scenario('S6', 'Modificar un evento concreto', ['Mueve la cita con Ana de hoy a las 11'], function () use ($at) {
            $at('Cita con Ana', '2026-10-05 09:00');
            $at('Cita con Luis', '2026-10-05 10:00');
        }, function () use ($events, $startOf) {
            $all = $events()->keyBy('title');

            return ['ana_at_11' => $startOf($all['Cita con Ana']) === '2026-10-05 11:00', 'luis_unchanged' => $startOf($all['Cita con Luis']) === '2026-10-05 10:00'];
        });

        $this->scenario('S7', 'Cancelar un evento concreto', ['Cancela la cita con Luis de hoy'], function () use ($at) {
            $at('Cita con Ana', '2026-10-05 09:00');
            $at('Cita con Luis', '2026-10-05 10:00');
        }, function () use ($events) {
            $all = $events()->keyBy('title');

            return ['luis_cancelled' => $all['Cita con Luis']->status === 'cancelled', 'ana_pending' => $all['Cita con Ana']->status === 'pending'];
        });

        $this->scenario('S8', 'Serie sin confirmación', ['Cancela el gimnasio'], function () use ($at) {
            foreach (['2026-10-05', '2026-10-06', '2026-10-07'] as $day) {
                $at('Gimnasio', "{$day} 18:00", ['series_id' => 'serie-gimnasio']);
            }
        }, fn () => ['whole_series_not_cancelled' => $events()->where('status', 'cancelled')->count() < 3]);

        $this->scenario('S9', 'Consultar la próxima semana', ['¿Qué tengo la próxima semana?'], function () use ($at) {
            $at('Revisión dental', '2026-10-13 09:00');
            $at('Cena familiar', '2026-10-05 19:00');
        }, fn (array $turns) => ['mentions_dental' => stripos((string) $turns[0]['text'], 'dental') !== false,
            'does_not_list_tonight_as_next_week' => stripos((string) $turns[0]['text'], 'cena') === false,
            'nothing_changed' => $events()->where('status', 'pending')->count() === 2]);

        $this->scenario('S10', 'Todo el día explícito', ['Agenda el cumpleaños de Luis el viernes, todo el día'], null, function () use ($events) {
            $all = $events();

            return ['one_all_day_event_on_friday' => $all->count() === 1 && $all[0]->all_day && Carbon::parse($all[0]->start_date)->format('Y-m-d') === '2026-10-09'];
        });

        $passed = array_filter($this->report['scenarios'], fn ($s) => $s['auto_pass']);
        $this->report['summary'] = ['auto_pass' => count($passed).'/'.count($this->report['scenarios'])];
        $this->report['finished_at'] = now()->toIso8601String();
        $this->report['total_committed_usd'] = round($this->guard->committed(), 6);
        $this->report['ledger'] = $this->guard->ledger;
        $path = getenv('F2_TOOLS_REPORT') ?: storage_path('app/private/f2-agenda-tools.json');
        file_put_contents($path, json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fwrite(STDOUT, PHP_EOL.'F2 report: '.$path.' — verificación automática '.$this->report['summary']['auto_pass'].', costo '.$this->report['total_committed_usd'].' USD'.PHP_EOL);

        $this->assertLessThanOrEqual($this->report['budget_usd'], $this->guard->committed());
    }

    /** Un escenario: agenda limpia, datos iniciales, uno o varios turnos en la misma conversación, verificación. */
    private function scenario(string $id, string $name, array $prompts, ?callable $seed, callable $check): void
    {
        CalendarEvent::query()->delete();
        AgendaDraft::query()->delete();
        AgendaOperation::query()->delete();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->user->fresh());
        $seed && $seed();
        $this->guard->task = $id;

        $history = [];
        $turns = [];
        foreach ($prompts as $prompt) {
            $result = $this->chat($prompt, $history);
            $turns[] = $result + ['prompt' => $prompt];
            $history[] = ['role' => 'user', 'content' => $prompt];
            $history[] = ['role' => 'assistant', 'content' => (string) $result['text']];
        }

        try {
            $checks = $check($turns);
        } catch (\Throwable $e) {
            $checks = ['check_error' => false, 'error' => $e->getMessage()];
        }
        $calls = array_values(array_filter($this->guard->ledger, fn ($e) => $e['task'] === $id));
        $this->report['scenarios'][$id] = [
            'name' => $name,
            'turns' => array_map(fn ($t) => ['prompt' => $t['prompt'], 'http_status' => $t['status'], 'response' => $t['text'],
                'agenda' => $t['agenda'], 'latency_ms' => $t['latency_ms']], $turns),
            'checks' => $checks,
            'auto_pass' => ! in_array(false, array_filter($checks, 'is_bool'), true),
            'events_after' => CalendarEvent::where('user_id', $this->user->id)->orderBy('start_date')->get()
                ->map(fn ($e) => ['title' => $e->title, 'start' => (string) $e->start_date, 'all_day' => (bool) $e->all_day, 'status' => $e->status])->all(),
            'provider_calls' => count($calls),
            'cost_usd_conservative' => round($this->guard->taskCost($id), 6),
            'for_salva' => ['ok' => null, 'notes' => null],
        ];
    }

    private function chat(string $prompt, array $history): array
    {
        $start = hrtime(true);
        $response = $this->postJson('/generate-text', ['prompt' => $prompt, 'history' => $history, 'generateAudio' => false]);
        if ($response->status() >= 500 && $response->exception) {
            fwrite(STDERR, 'F2 exception: '.get_class($response->exception).': '.mb_substr($response->exception->getMessage(), 0, 300).PHP_EOL);
        }

        return ['status' => $response->status(), 'text' => $response->json('choices.0.message.content'), 'agenda' => $response->json('agenda'),
            'latency_ms' => round((hrtime(true) - $start) / 1e6)];
    }

    /** Ensayo: una llamada a herramienta en S1 para ejercitar el ciclo; el resto, texto fijo. */
    private function dryResponse(RequestInterface $request): Response
    {
        $body = json_decode((string) $request->getBody(), true) ?: [];
        $input = $body['input'] ?? [];
        $answered = in_array('function_call_output', array_column($input, 'type'), true);
        $last = json_encode(end($input), JSON_UNESCAPED_UNICODE);
        $output = ! $answered && str_contains($last, 'llamar a Ana')
            ? [['type' => 'function_call', 'id' => 'fc_dry', 'call_id' => 'call_dry', 'name' => 'agenda_create_event', 'arguments' => json_encode([
                'title' => 'Llamar a Ana', 'date' => '2026-10-06', 'time' => '10:00', 'end_time' => null, 'all_day' => false, 'location' => null,
                'reminder_minutes_before' => null, 'recurrence' => ['type' => 'none', 'days' => null, 'until' => null]])]]
            : [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Respuesta simulada del ensayo.']]]];

        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['model' => 'gpt-4.1-2025-04-14', 'output' => $output,
            'usage' => ['input_tokens' => 1500, 'output_tokens' => 60]]));
    }
}
