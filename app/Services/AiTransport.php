<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class AiTransport
{
    public function client(array $config = []): Client
    {
        $client = app(Client::class, ['config' => $config]);
        $client->getConfig('handler')->push(Middleware::retry($this->retryDecider(), fn () => (int) config('ai.retry.delay_ms', 500)), 'cirilo-retry');
        $client->getConfig('handler')->push(function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $telemetry = app(AiTelemetry::class);
                $json = str_contains($request->getHeaderLine('Content-Type'), 'json') ? $this->json($request->getBody()) : [];
                $context = $telemetry->begin((string) $request->getUri(), is_array($json) ? $json : []);

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($telemetry, $context, $json) {
                        // F5-03: con streaming el uso llega al final; lo registra finishStream().
                        if (($json['stream'] ?? false) === true && $context && $response->getStatusCode() < 400) {
                            return $response->withHeader(self::STREAM_CONTEXT_HEADER, base64_encode(json_encode($context)));
                        }
                        $json = $this->json($response->getBody());
                        $telemetry->finish($context, $response->getStatusCode(), is_array($json) ? $json : []);

                        return $response;
                    },
                    function ($error) use ($telemetry, $context) {
                        $telemetry->finish($context, null);
                        throw $error;
                    }
                );
            };
        }, 'cirilo-usage');

        return $client;
    }

    public const STREAM_CONTEXT_HEADER = 'X-Cirilo-Telemetry-Context';

    /** Registra el uso de una respuesta en streaming con el evento final (response.completed). */
    public function finishStream(ResponseInterface $response, array $completed): void
    {
        $context = json_decode(base64_decode($response->getHeaderLine(self::STREAM_CONTEXT_HEADER)), true);
        if (is_array($context)) {
            app(AiTelemetry::class)->finish($context, $response->getStatusCode(), $completed);
        }
    }

    /** F5-04: reintentar solo lo recuperable (429, 5xx o fallo de conexión), una vez. */
    private function retryDecider(): callable
    {
        return function (int $retries, RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $error = null): bool {
            if ($retries >= (int) config('ai.retry.max', 1)) {
                return false;
            }
            if ($error instanceof ConnectException) {
                return true;
            }

            return $response !== null && ($response->getStatusCode() === 429 || $response->getStatusCode() >= 500);
        };
    }

    private function json(StreamInterface $stream): array
    {
        if (! $stream->isSeekable()) {
            return []; // Do not consume a streaming response for telemetry.
        }
        $position = $stream->tell();
        try {
            $stream->rewind();
            $data = json_decode($stream->getContents(), true);

            return is_array($data) ? $data : [];
        } finally {
            $stream->seek($position);
        }
    }
}
