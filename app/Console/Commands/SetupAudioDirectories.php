<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SetupAudioDirectories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audio:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea las carpetas necesarias para el sistema de audios (static y dynamic)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("=== Configuración de Directorios de Audio ===\n");

        $directories = [
            'audio' => 'Carpeta principal de audio',
            'audio/static' => 'Audios reutilizables (bienvenida, frases)',
            'audio/dynamic' => 'Audios temporales (respuestas del asistente)',
        ];

        $created = 0;
        $existing = 0;

        foreach ($directories as $directory => $description) {
            $fullPath = storage_path('app/public/'.$directory);

            if (Storage::disk('public')->exists($directory)) {
                $this->line("✓ {$description}: <fg=green>Ya existe</>");
                $existing++;
            } else {
                if (Storage::disk('public')->makeDirectory($directory)) {
                    $this->info("✓ {$description}: <fg=cyan>Creada</>");
                    $created++;
                } else {
                    $this->error("✗ {$description}: <fg=red>Error al crear</>");

                    return 1;
                }
            }

            // Verificar permisos
            if (is_writable($fullPath)) {
                $this->line('  Permisos: <fg=green>Escritura OK</>');
            } else {
                $this->warn('  Permisos: <fg=yellow>Sin permisos de escritura</>');
                $this->line("  Ejecuta: chmod -R 775 {$fullPath}");
            }
        }

        $this->newLine();
        $this->info('=== Resumen ===');
        $this->line("Carpetas creadas: <fg=cyan>{$created}</>");
        $this->line("Carpetas existentes: <fg=green>{$existing}</>");

        // Verificar symlink storage
        $publicStoragePath = public_path('storage');
        if (! file_exists($publicStoragePath)) {
            $this->newLine();
            $this->warn('⚠ El symlink de storage no existe');
            $this->line('Ejecuta: php artisan storage:link');
        } else {
            $this->line('Symlink storage: <fg=green>OK</>');
        }

        // Mostrar rutas finales
        $this->newLine();
        $this->info('=== Rutas Configuradas ===');
        $this->line('Audios estáticos: storage/app/public/audio/static/');
        $this->line('Audios dinámicos: storage/app/public/audio/dynamic/');
        $this->line('URL pública: /storage/audio/');

        $this->newLine();
        $this->info('✓ Configuración completada exitosamente');

        return 0;
    }
}
