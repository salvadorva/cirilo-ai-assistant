<?php

namespace Tests\Evaluation;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Tope de gasto duro para evaluaciones con proveedor real (F1-06b).
 *
 * Antes de cada petición: solo permite api.openai.com (/v1/responses y
 * /v1/chat/completions) con modelo tarifado; acota salida y herramientas;
 * reserva el peor caso y bloquea sin enviar si lo comprometido + la reserva
 * supera el límite. Tras la respuesta sustituye la reserva por un costo
 * conservador según el uso devuelto; si falla o no hay uso, conserva la
 * reserva completa. Nunca registra cabeceras ni credenciales.
 */
class BudgetGuard
{
    public const OUTPUT_CAP = 1500;

    public const COMPLETION_CAP = 1000;

    /** Margen por búsqueda web para contenido recuperado (oficialmente sin cargo en modelos no razonadores). */
    public const SEARCH_MARGIN_TOKENS = 40000;

    public string $task = 'setup';

    /** @var list<array<string, mixed>> */
    public array $ledger = [];

    public function __construct(private float $limitUsd) {}

    public function committed(): float
    {
        return array_sum(array_map(fn ($e) => $e['actual_usd'] ?? $e['reserved_usd'], $this->ledger));
    }

    public function taskCost(string $task): float
    {
        return array_sum(array_map(fn ($e) => $e['actual_usd'] ?? $e['reserved_usd'], array_filter($this->ledger, fn ($e) => $e['task'] === $task)));
    }

    public function middleware(): callable
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                [$request, $index] = $this->reserve($request);
                $start = hrtime(true);

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($index, $start) {
                        $this->settle($index, $response, (hrtime(true) - $start) / 1e6);

                        return $response;
                    },
                    function ($error) use ($index, $start) {
                        $this->ledger[$index]['status'] = 'transport_error';
                        $this->ledger[$index]['latency_ms'] = round((hrtime(true) - $start) / 1e6);
                        throw $error;
                    }
                );
            };
        };
    }

    private function reserve(RequestInterface $request): array
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        if ($uri->getHost() !== 'api.openai.com' || ! in_array($path, ['/v1/responses', '/v1/chat/completions'], true)) {
            throw new RuntimeException('BudgetGuard: destino no permitido en la evaluación ('.$uri->getHost().$path.')');
        }
        $body = json_decode((string) $request->getBody(), true);
        $model = is_array($body) ? ($body['model'] ?? null) : null;
        $price = $model ? self::price($model) : null;
        if (! $price || ! isset($price['input'], $price['output'])) {
            throw new RuntimeException('BudgetGuard: modelo sin tarifa, costo no acotable ('.($model ?? 'desconocido').')');
        }

        $toolCalls = 0;
        if ($path === '/v1/responses') {
            $body['max_output_tokens'] = min((int) ($body['max_output_tokens'] ?? self::OUTPUT_CAP), self::OUTPUT_CAP);
            if (! empty($body['tools'])) {
                $body['max_tool_calls'] = 1;
                $toolCalls = 1;
            }
            $outputCap = $body['max_output_tokens'];
        } else {
            if (! empty($body['tools']) || ! empty($body['functions'])) {
                throw new RuntimeException('BudgetGuard: herramientas en chat/completions no acotadas');
            }
            $outputCap = min((int) ($body['max_tokens'] ?? $body['max_completion_tokens'] ?? self::COMPLETION_CAP), self::COMPLETION_CAP);
            unset($body['max_completion_tokens']);
            $body['max_tokens'] = $outputCap;
            $body['n'] = 1;
        }

        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // Cota superior: cada token consume al menos un byte del cuerpo.
        $inputUpper = strlen($encoded) + $toolCalls * self::SEARCH_MARGIN_TOKENS;
        $worst = $inputUpper * $price['input'] / 1e6 + $outputCap * $price['output'] / 1e6 + $toolCalls * config('ai.web_search_preview_per_call');
        if ($this->committed() + $worst > $this->limitUsd) {
            throw new RuntimeException(sprintf('BudgetGuard: bloqueado antes de enviar; comprometido %.4f + peor caso %.4f > %.2f USD', $this->committed(), $worst, $this->limitUsd));
        }

        $this->ledger[] = ['task' => $this->task, 'endpoint' => $path, 'model_requested' => $model, 'output_cap' => $outputCap,
            'max_tool_calls' => $toolCalls ?: null, 'reserved_usd' => round($worst, 6), 'actual_usd' => null, 'status' => 'sent'];

        return [$request->withBody(Utils::streamFor($encoded))->withHeader('Content-Length', (string) strlen($encoded)), array_key_last($this->ledger)];
    }

    /** Los nombres de modelo contienen puntos: no usar notación de puntos de config(). */
    private static function price(string $model): ?array
    {
        $prices = config('ai.prices')['openai'] ?? [];

        return $prices[config('ai.aliases')[$model] ?? $model] ?? null;
    }

    private function settle(int $index, ResponseInterface $response, float $latencyMs): void
    {
        $stream = $response->getBody();
        $json = [];
        if ($stream->isSeekable()) {
            $position = $stream->tell();
            $stream->rewind();
            $json = json_decode($stream->getContents(), true) ?: [];
            $stream->seek($position);
        }
        $entry = &$this->ledger[$index];
        $entry['http_status'] = $response->getStatusCode();
        $entry['latency_ms'] = round($latencyMs);
        $entry['model_returned'] = $json['model'] ?? null;
        $usage = $json['usage'] ?? null;
        if ($response->getStatusCode() >= 400 || ! is_array($usage)) {
            $entry['status'] = 'no_usage_reservation_kept';

            return;
        }
        $model = $json['model'] ?? $entry['model_requested'];
        $price = self::price($model) ?? self::price($entry['model_requested']);
        $input = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);
        $searches = count(array_filter($json['output'] ?? [], fn ($item) => ($item['type'] ?? null) === 'web_search_call'));
        // Conservador: sin descuento de caché.
        $entry['usage'] = ['input_tokens' => $input, 'output_tokens' => $output, 'web_search_calls' => $searches];
        $entry['actual_usd'] = round($input * $price['input'] / 1e6 + $output * $price['output'] / 1e6 + $searches * config('ai.web_search_preview_per_call'), 6);
        $entry['status'] = 'settled';
    }
}
