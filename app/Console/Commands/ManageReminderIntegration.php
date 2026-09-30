<?php

namespace App\Console\Commands;

use App\Models\ReminderIntegration;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Provisión de la credencial exclusiva de Hermes (RC5, con autorización).
 * La credencial nunca se imprime: se escribe una sola vez en un archivo 0600
 * como línea CIRILO_HERMES_REMINDERS_TOKEN=... para trasladarlo a ~/.secrets.
 */
class ManageReminderIntegration extends Command
{
    protected $signature = 'reminders:integration
        {action : issue | rotate | revoke}
        {--user= : ID del propietario (issue)}
        {--integration= : ID de la integración (rotate | revoke)}
        {--name=hermes : Nombre de la integración (issue)}
        {--expires-days= : Vigencia en días (issue)}
        {--output= : Archivo nuevo donde escribir la credencial (issue | rotate)}';

    protected $description = 'Emite, rota o revoca la credencial de integración de recordatorios sin mostrarla';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'issue' => $this->issue(),
            'rotate' => $this->rotate(),
            'revoke' => $this->revoke(),
            default => $this->failWith('Acción no válida: usa issue, rotate o revoke.'),
        };
    }

    private function issue(): int
    {
        $user = User::find($this->option('user'));
        if (! $user) {
            return $this->failWith('Propietario inexistente: indica --user con un ID explícito.');
        }
        if (! $this->writableOutput()) {
            return self::FAILURE;
        }
        $days = $this->option('expires-days');
        [$integration, $token] = ReminderIntegration::issue($user, (string) $this->option('name'), ReminderIntegration::SCOPES,
            $days ? now()->addDays((int) $days) : null);
        $this->writeToken($token);
        $this->info("Integración {$integration->id} emitida para el usuario {$user->id}; credencial escrita en {$this->option('output')} (0600).");

        return self::SUCCESS;
    }

    private function rotate(): int
    {
        $integration = ReminderIntegration::find($this->option('integration'));
        if (! $integration || $integration->revoked_at) {
            return $this->failWith('Integración inexistente o revocada.');
        }
        if (! $this->writableOutput()) {
            return self::FAILURE;
        }
        $this->writeToken($integration->rotate());
        $this->info("Credencial de la integración {$integration->id} rotada; la anterior deja de funcionar.");

        return self::SUCCESS;
    }

    private function revoke(): int
    {
        $integration = ReminderIntegration::find($this->option('integration'));
        if (! $integration) {
            return $this->failWith('Integración inexistente.');
        }
        $integration->forceFill(['revoked_at' => now()])->save();
        $this->info("Integración {$integration->id} revocada.");

        return self::SUCCESS;
    }

    /** El archivo debe ser nuevo: no se sobrescribe ni se escribe en la salida estándar. */
    private function writableOutput(): bool
    {
        $path = (string) $this->option('output');
        if ($path === '' || $path === '-' || file_exists($path) || ! is_dir(dirname($path))) {
            $this->error('Indica --output con la ruta de un archivo nuevo en un directorio existente.');

            return false;
        }

        return true;
    }

    private function writeToken(string $token): void
    {
        $path = (string) $this->option('output');
        $previous = umask(0077);
        try {
            file_put_contents($path, 'CIRILO_HERMES_REMINDERS_TOKEN='.$token.PHP_EOL, LOCK_EX);
            chmod($path, 0600);
        } finally {
            umask($previous);
        }
    }

    private function failWith(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
