<?php

namespace App\Console\Commands;

use App\Services\Reminders\DeviceRegistry;
use Illuminate\Console\Command;

/**
 * Liberación manual de una instalación cuando el reclamo no es posible (el
 * token FCM rotó con la sesión cerrada). Queda auditada con el motivo.
 */
class ReleaseDeviceInstallation extends Command
{
    protected $signature = 'reminders:installation-release {installation : installation_id} {--reason= : Motivo obligatorio para la auditoría}';

    protected $description = 'Libera una instalación para que otra cuenta pueda registrarla (auditado)';

    public function handle(DeviceRegistry $registry): int
    {
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('Indica --reason para la auditoría.');

            return self::FAILURE;
        }
        $device = $registry->release((string) $this->argument('installation'), mb_substr($reason, 0, 200));
        if (! $device) {
            $this->error('Instalación no registrada.');

            return self::FAILURE;
        }
        $this->info("Instalación liberada (dispositivo {$device->id}, usuario anterior {$device->user_id}).");

        return self::SUCCESS;
    }
}
