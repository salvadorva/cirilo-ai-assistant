<?php

namespace Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\Evaluation\BudgetGuard;
use Tests\TestCase;

/** El tope duro de F1-06b bloquea antes de enviar y acota cada petición. Sin red. */
class F1BudgetGuardTest extends TestCase
{
    private function client(BudgetGuard $guard, MockHandler $mock): Client
    {
        $stack = HandlerStack::create($mock);
        $stack->push($guard->middleware());

        return new Client(['handler' => $stack, 'http_errors' => false]);
    }

    private function ok(): Response
    {
        return new Response(200, [], json_encode(['model' => 'gpt-4.1-2025-04-14', 'output' => [['type' => 'web_search_call']],
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 100]]));
    }

    public function test_caps_output_and_tools_and_settles_conservatively(): void
    {
        $guard = new BudgetGuard(1.0);
        $mock = new MockHandler([$this->ok()]);
        $this->client($guard, $mock)->post('https://api.openai.com/v1/responses', ['json' => ['model' => 'gpt-4.1', 'input' => [],
            'tools' => [['type' => 'web_search_preview']]]]);

        $sent = json_decode((string) $mock->getLastRequest()->getBody(), true);
        $this->assertSame(BudgetGuard::OUTPUT_CAP, $sent['max_output_tokens']);
        $this->assertSame(1, $sent['max_tool_calls']);
        // 1000×2/1e6 + 100×8/1e6 + 1 búsqueda × 0.025
        $this->assertEqualsWithDelta(0.0278, $guard->committed(), 1e-9);
    }

    public function test_blocks_before_sending_when_budget_would_be_exceeded(): void
    {
        $guard = new BudgetGuard(0.10);
        $mock = new MockHandler([$this->ok()]);
        try {
            $this->client($guard, $mock)->post('https://api.openai.com/v1/responses', ['json' => ['model' => 'gpt-4.1', 'input' => [],
                'tools' => [['type' => 'web_search_preview']]]]);
            $this->fail('Debió bloquearse');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('bloqueado antes de enviar', $e->getMessage());
        }
        $this->assertNull($mock->getLastRequest());
        $this->assertSame(0.0, $guard->committed());
    }

    public function test_blocks_other_hosts_unknown_models_and_unbounded_tools(): void
    {
        $guard = new BudgetGuard(1.0);
        $mock = new MockHandler;
        foreach ([['https://fcm.googleapis.com/v1/projects/x/messages:send', ['model' => 'gpt-4.1']],
            ['https://api.openai.com/v1/responses', ['model' => 'modelo-sin-tarifa']],
            ['https://api.openai.com/v1/chat/completions', ['model' => 'gpt-4.1', 'tools' => [['type' => 'function']]]]] as [$url, $json]) {
            try {
                $this->client($guard, $mock)->post($url, ['json' => $json]);
                $this->fail('Debió bloquearse: '.$url);
            } catch (\RuntimeException $e) {
                $this->assertStringStartsWith('BudgetGuard:', $e->getMessage());
            }
        }
        $this->assertNull($mock->getLastRequest());
    }

    public function test_failed_or_usage_less_response_keeps_full_reservation(): void
    {
        $guard = new BudgetGuard(1.0);
        $this->client($guard, new MockHandler([new Response(500, [], '{}')]))
            ->post('https://api.openai.com/v1/chat/completions', ['json' => ['model' => 'gpt-4.1-mini', 'max_tokens' => 5000]]);

        $this->assertSame(BudgetGuard::COMPLETION_CAP, $guard->ledger[0]['output_cap']);
        $this->assertSame($guard->ledger[0]['reserved_usd'], $guard->committed());
        $this->assertGreaterThan(0, $guard->committed());
    }
}
