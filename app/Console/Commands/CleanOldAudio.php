<?php

namespace App\Console\Commands;

use App\Services\AudioCacheService;
use Illuminate\Console\Command;

class CleanOldAudio extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audio:clean
                            {--days=7 : Número de días a conservar}
                            {--dry-run : Mostrar qué se eliminaría sin eliminar realmente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina archivos de audio dinámicos antiguos de la carpeta audio/dynamic';

    protected $audioCacheService;

    /**
     * Create a new command instance.
     */
    public function __construct(AudioCacheService $audioCacheService)
    {
        parent::__construct();
        $this->audioCacheService = $audioCacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info('=== Limpieza de Audios Dinámicos ===');
        $this->info("Días a conservar: {$days}");

        if ($dryRun) {
            $this->warn('MODO DRY-RUN: No se eliminarán archivos realmente');
        }

        $this->info('Escaneando carpeta audio/dynamic...');

        if ($dryRun) {
            // En modo dry-run, solo mostrar lo que se eliminaría
            $files = \Storage::disk('public')->files('audio/dynamic');
            $cutoffDate = now()->subDays((int) $days);
            $toDelete = [];
            $totalSize = 0;

            foreach ($files as $file) {
                $lastModified = \Storage::disk('public')->lastModified($file);
                $fileDate = \Carbon\Carbon::createFromTimestamp($lastModified);

                if ($fileDate->lt($cutoffDate)) {
                    $size = \Storage::disk('public')->size($file);
                    $toDelete[] = [
                        'file' => basename($file),
                        'date' => $fileDate->format('Y-m-d H:i:s'),
                        'size' => $this->formatBytes($size),
                    ];
                    $totalSize += $size;
                }
            }

            if (count($toDelete) > 0) {
                $this->table(['Archivo', 'Fecha', 'Tamaño'], $toDelete);
                $this->info("\nTotal a eliminar: ".count($toDelete).' archivos');
                $this->info('Espacio a liberar: '.$this->formatBytes($totalSize));
            } else {
                $this->info('No hay archivos para eliminar');
            }
        } else {
            // Ejecutar limpieza real
            $stats = $this->audioCacheService->cleanDynamicAudios($days);

            $this->info("\n=== Resultados ===");
            $this->info("Archivos revisados: {$stats['total_checked']}");
            $this->line("Archivos eliminados: {$stats['deleted']}");
            $this->error("Fallos: {$stats['failed']}");
            $this->info('Espacio liberado: '.$this->formatBytes($stats['freed_space']));

            if ($stats['deleted'] > 0) {
                $this->info("\n✓ Limpieza completada exitosamente");
            } else {
                $this->info("\n• No había archivos antiguos para eliminar");
            }
        }

        return 0;
    }

    /**
     * Formatea bytes a formato legible
     *
     * @param  int  $bytes
     * @return string
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }
}
