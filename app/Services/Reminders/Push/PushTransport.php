<?php

namespace App\Services\Reminders\Push;

use App\Models\DeviceToken;

/** Envío data-only a un destino concreto con resultado estructurado. */
interface PushTransport
{
    /** @param  array<string, string>  $data */
    public function send(DeviceToken $device, array $data, int $ttlSeconds): PushResult;
}
