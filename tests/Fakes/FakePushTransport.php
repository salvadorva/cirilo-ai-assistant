<?php

namespace Tests\Fakes;

use App\Models\DeviceToken;
use App\Services\Reminders\Push\PushResult;
use App\Services\Reminders\Push\PushTransport;
use Closure;

/** Transporte push en memoria: registra envíos y devuelve resultados programados. */
class FakePushTransport implements PushTransport
{
    /** @var list<array{device_id: int, data: array, ttl: int}> */
    public array $sent = [];

    /** @var list<PushResult|Closure> */
    private array $queue = [];

    public function push(PushResult|Closure ...$results): self
    {
        array_push($this->queue, ...$results);

        return $this;
    }

    public function send(DeviceToken $device, array $data, int $ttlSeconds): PushResult
    {
        $this->sent[] = ['device_id' => $device->id, 'data' => $data, 'ttl' => $ttlSeconds];
        $next = array_shift($this->queue) ?? PushResult::accepted('projects/test/messages/'.count($this->sent));

        return $next instanceof Closure ? $next($device, $data, $ttlSeconds) : $next;
    }
}
